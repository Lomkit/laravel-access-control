<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Closure;
use Lomkit\Access\Exceptions\UnrestrictedPerimeterCallbackException;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class UnrestrictedDeclarationTest extends PackageTestCase
{
    /** @return array<string, array{string, Closure(Perimeter): Perimeter}> */
    public static function rowCallbacks(): array
    {
        return [
            'query' => ['query', fn (Perimeter $perimeter): Perimeter => $perimeter->query(fn () => null)],
            'should' => ['should', fn (Perimeter $perimeter): Perimeter => $perimeter->should(fn (): bool => true)],
        ];
    }

    /** @param  Closure(Perimeter): Perimeter  $declare */
    #[Test]
    #[DataProvider('rowCallbacks')]
    public function it_throws_when_a_row_callback_is_declared_on_an_unrestricted_perimeter(string $callback, Closure $declare): void
    {
        $this->expectException(UnrestrictedPerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter is unrestricted and declares a [%s] callback.', GlobalPerimeter::class, $callback));

        $declare(GlobalPerimeter::new()->unrestricted());
    }

    /** @param  Closure(Perimeter): Perimeter  $declare */
    #[Test]
    #[DataProvider('rowCallbacks')]
    public function it_throws_when_a_perimeter_with_a_row_callback_is_made_unrestricted(string $callback, Closure $declare): void
    {
        $this->expectException(UnrestrictedPerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter is unrestricted and declares a [%s] callback.', GlobalPerimeter::class, $callback));

        $declare(GlobalPerimeter::new())->unrestricted();
    }

    #[Test]
    public function it_declares_an_unrestricted_perimeter_with_only_an_allowed_callback(): void
    {
        $perimeter = GlobalPerimeter::new()
            ->allowed(fn (): bool => true)
            ->unrestricted();

        $this->assertTrue($perimeter->isUnrestricted());
        $this->assertFalse(GlobalPerimeter::new()->isUnrestricted());
    }
}
