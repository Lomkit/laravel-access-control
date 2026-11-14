<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Database\Eloquent\Model;

/** @internal */
final readonly class VerdictBatch
{
    public const int CHUNK = 1000;

    private const string KEY_ALIAS = '__access_key';

    private const string VERDICT_ALIAS = '__access_verdict';

    public function __construct(private VerdictRecorder $recorder) {}

    /**
     * @param  list<int|string>  $keys
     * @return array<string, Verdict>
     */
    public function run(Model $model, array $keys): array
    {
        $case = $this->recorder->verdictCase($model);

        if ($case === null) {
            return [];
        }

        $verdicts = [];

        foreach (array_chunk($keys, self::CHUNK) as $chunk) {
            $query = $model
                ->newQueryWithoutScopes()
                ->whereKey($chunk)
                ->select("{$model->getQualifiedKeyName()} as ".self::KEY_ALIAS);

            $case->select($query, self::VERDICT_ALIAS);

            foreach ($query->toBase()->get() as $record) {
                $key = $record->{self::KEY_ALIAS} ?? null;

                if (is_int($key) || is_string($key)) {
                    $verdict = $case->verdictFrom($record->{self::VERDICT_ALIAS} ?? null);
                    $verdicts[(string) $key] = $verdict;
                    $this->recorder->record($model::class, $key, $verdict);
                }
            }
        }

        return $verdicts;
    }
}
