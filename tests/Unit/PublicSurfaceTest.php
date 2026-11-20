<?php

namespace Lomkit\Access\Tests\Unit;

use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Policies\ControlledPolicy;
use Lomkit\Access\Tests\PackageTestCase;
use PhpToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;
use Throwable;

class PublicSurfaceTest extends PackageTestCase
{
    private const array PUBLIC_CLASSES = [
        'Lomkit\Access\Ability',
        'Lomkit\Access\Access',
        'Lomkit\Access\AccessContext',
        'Lomkit\Access\AccessServiceProvider',
        'Lomkit\Access\Attributes\ControlledBy',
        'Lomkit\Access\Boundaries\Boundary',
        'Lomkit\Access\Boundaries\ColumnBoundary',
        'Lomkit\Access\Controls\Control',
        'Lomkit\Access\Controls\DecidesRows',
        'Lomkit\Access\Controls\HasControl',
        'Lomkit\Access\Controls\ReachesKeys',
        'Lomkit\Access\Decisions\Draft',
        'Lomkit\Access\Integrations\RestApi\AccessControlExtension',
        'Lomkit\Access\Perimeters\Levels',
        'Lomkit\Access\Perimeters\Perimeter',
        'Lomkit\Access\Perimeters\ThroughParent',
        'Lomkit\Access\Policies\ControlledPolicy',
        'Lomkit\Access\Search\ControlledSearchBuilder',
        'Lomkit\Access\Search\ControlledSearchable',
        'Lomkit\Access\Search\ReaderSearchKeys',
        'Lomkit\Access\Search\ReindexesSearch',
        'Lomkit\Access\Search\SearchKeys',
        'Lomkit\Access\Search\Tokens\SearchToken',
        'Lomkit\Access\Search\Tokens\SearchTokenKey',
        'Lomkit\Access\Testing\InteractsWithAccess',
    ];

    private const string EXCEPTIONS = 'Lomkit\Access\Exceptions\\';

    #[Test]
    public function it_marks_internal_every_class_outside_the_documented_surface(): void
    {
        $public = array_values(array_filter(
            array_keys(array_filter($this->declarations(), fn (bool $internal): bool => ! $internal)),
            fn (string $class): bool => ! str_starts_with($class, self::EXCEPTIONS),
        ));
        sort($public);

        $this->assertSame(self::PUBLIC_CLASSES, $public);
    }

    #[Test]
    public function it_keeps_every_exception_public(): void
    {
        $internalExceptions = array_filter(
            array_keys(array_filter($this->declarations())),
            fn (string $class): bool => str_starts_with($class, self::EXCEPTIONS) && is_subclass_of($class, Throwable::class),
        );

        $this->assertSame([], array_values($internalExceptions));
    }

    /** @return array<string, array{class-string, list<string>}> */
    public static function extendedClasses(): array
    {
        return [
            'Perimeter' => [Perimeter::class, [
                'protected allows', 'protected includes', 'protected scope',
                'public admits', 'public allowed', 'public checksInMemory', 'public query', 'public reindexWhen',
                'public searchKeys', 'public should', 'public static new', 'public unrestricted',
            ]],
            'Control' => [Control::class, [
                'protected applicablePerimeters', 'protected boundary', 'protected perimeters',
                'public abilityFor', 'public applies', 'public decide', 'public reachableKeys', 'public static new',
            ]],
            'Boundary' => [Boundary::class, [
                'protected admits', 'protected includes', 'protected scope', 'protected searchKeys',
                'public checksInMemory', 'public static none', 'public static where',
            ]],
            'ControlledPolicy' => [ControlledPolicy::class, [
                'protected decideByControl', 'protected declaredControl',
                'public create', 'public delete', 'public forceDelete', 'public restore', 'public update', 'public view', 'public viewAny',
            ]],
        ];
    }

    /**
     * @param  class-string  $class
     * @param  list<string>  $members
     */
    #[Test]
    #[DataProvider('extendedClasses')]
    public function it_keeps_the_documented_members_of_the_classes_applications_extend(string $class, array $members): void
    {
        $declared = [];

        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED) as $method) {
            if (! str_contains((string) $method->getDocComment(), '@internal')) {
                $declared[] = ($method->isPublic() ? 'public ' : 'protected ').($method->isStatic() ? 'static ' : '').$method->getName();
            }
        }

        sort($declared);

        $this->assertSame($members, $declared);
    }

    /** @return array<string, bool> */
    private function declarations(): array
    {
        $declarations = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src'));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            [$class, $internal] = $this->declarationIn((string) file_get_contents($file->getPathname()));
            $declarations[$class] = $internal;
        }

        ksort($declarations);

        return $declarations;
    }

    /** @return array{string, bool} */
    private function declarationIn(string $source): array
    {
        $namespace = '';
        $docComment = '';
        $tokens = PhpToken::tokenize($source);

        foreach ($tokens as $position => $token) {
            if ($token->is(T_NAMESPACE)) {
                $namespace = $this->nextName($tokens, $position);
            } elseif ($token->is(';')) {
                $docComment = '';
            } elseif ($token->is(T_DOC_COMMENT)) {
                $docComment = $token->text;
            } elseif ($token->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM])) {
                return [$namespace.'\\'.$this->nextName($tokens, $position), str_contains($docComment, '@internal')];
            }
        }

        $this->fail('No class, interface, trait or enum is declared in a file of src.');
    }

    /** @param  list<PhpToken>  $tokens */
    private function nextName(array $tokens, int $position): string
    {
        foreach (array_slice($tokens, $position + 1) as $token) {
            if ($token->is([T_STRING, T_NAME_QUALIFIED])) {
                return $token->text;
            }
        }

        $this->fail('A declaration of src has no name.');
    }
}
