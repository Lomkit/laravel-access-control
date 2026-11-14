<?php

namespace Lomkit\Access\Testing;

use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Access;
use Lomkit\Access\Controls\Control;

/** @internal */
final readonly class ControlAgreement
{
    private const string GRANTED = 'granted';

    private const string VIEW = 'view';

    /**
     * @param  list<Model>  $rows
     * @param  list<string>  $abilities
     */
    public function __construct(
        private Control $control,
        private array $rows,
        private array $abilities,
    ) {}

    /** @return list<string> */
    public function disagreementsFor(Model $reader): array
    {
        $paths = [
            'row by row' => $this->answersAfter($reader, function (): void {}),
            'preload' => $this->answersAfter($reader, function () use ($reader): void {
                foreach ($this->abilities as $ability) {
                    Access::preload($reader, $ability, $this->rows);
                }
            }),
            'withAbilities' => $this->answersAfter($reader, function () use ($reader): void {
                $query = $this->controlled($reader);
                $query->scopes(['withAbilities' => [$this->abilities]]);
                $query->get();
            }),
        ];
        $listed = array_flip(array_map(self::key(...), $this->controlled($reader)->get()->all()));
        $disagreements = [];

        foreach ($this->rows as $row) {
            foreach ($this->abilities as $ability) {
                $answers = array_map(fn (array $answers): string => $answers[self::key($row)][$ability], $paths);

                if ($ability === self::VIEW) {
                    $answers['controlled()'] = array_key_exists(self::key($row), $listed) ? 'listed' : 'not listed';
                }

                if (! $this->agree($answers)) {
                    $disagreements[] = $this->describe($row, $ability, $answers);
                }
            }
        }

        return $disagreements;
    }

    /**
     * @param  Closure(): void  $prepare
     * @return array<string, array<string, string>>
     */
    private function answersAfter(Model $reader, Closure $prepare): array
    {
        Access::forgetDecisions();
        $prepare();
        $answers = [];

        foreach ($this->rows as $row) {
            foreach ($this->abilities as $ability) {
                $response = Gate::forUser($reader)->inspect($ability, $row);
                $answers[self::key($row)][$ability] = $response->allowed() ? self::GRANTED : self::refusal($response);
            }
        }

        Access::forgetDecisions();

        return $answers;
    }

    /** @return Builder<Model> */
    private function controlled(Model $reader): Builder
    {
        $model = $this->control->getModel();
        $query = (new $model)->newQuery();

        $query->scopes(['controlledFor' => [$reader]]);

        return $query->whereKey(array_map(fn (Model $row): mixed => $row->getKey(), $this->rows));
    }

    private static function refusal(Response $response): string
    {
        $code = $response->code();

        if (is_string($code) || is_int($code)) {
            return (string) $code;
        }

        $message = $response->message();

        return $message === null || $message === '' ? 'denied without a code' : "denied without a code: {$message}";
    }

    /** @param  array<string, string>  $answers */
    private function agree(array $answers): bool
    {
        $listed = $answers['controlled()'] ?? null;
        unset($answers['controlled()']);

        if (count(array_unique($answers)) !== 1) {
            return false;
        }

        return $listed === null || ($listed === 'listed') === (reset($answers) === self::GRANTED);
    }

    /** @param  array<string, string>  $answers */
    private function describe(Model $row, string $ability, array $answers): string
    {
        $paths = [];

        foreach ($answers as $path => $answer) {
            $paths[] = "{$path} [{$answer}]";
        }

        return sprintf('row [%s], ability [%s]: %s', self::key($row), $ability, implode(', ', $paths));
    }

    private static function key(Model $row): string
    {
        $key = $row->getKey();

        return is_int($key) || is_string($key) ? (string) $key : get_debug_type($key);
    }
}
