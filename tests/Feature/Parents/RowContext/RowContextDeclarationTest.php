<?php

namespace Lomkit\Access\Tests\Feature\Parents\RowContext;

use Lomkit\Access\Exceptions\DuplicatePerimeterCallbackException;
use Lomkit\Access\Exceptions\ImpurePerimetersException;
use Lomkit\Access\Exceptions\InvalidRowContextColumnException;
use Lomkit\Access\Exceptions\RowContextInLevelsException;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\Parents\ParentInRowContext;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Inheritance\Controls\CommentControl;
use Lomkit\Access\Tests\Fixtures\RowContext\ColumnFromConfigCommentControl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RowContextDeclarationTest extends RowContextTestCase
{
    #[Test]
    public function it_throws_on_a_second_column(): void
    {
        $this->expectException(DuplicatePerimeterCallbackException::class);
        $this->expectExceptionMessage("The ThroughParent perimeter through the [post] relation calls decidedInRowContext() twice, after decidedInRowContext('workspace_id')");

        ThroughParent::belongsTo('post')
            ->decidedInRowContext('workspace_id')
            ->decidedInRowContext('brand_id');
    }

    /** @return iterable<string, array{string}> */
    public static function invalidColumns(): iterable
    {
        yield 'empty' => [''];
        yield 'qualified' => ['comments.workspace_id'];
        yield 'expression' => ['workspace_id = 1'];
    }

    #[Test]
    #[DataProvider('invalidColumns')]
    public function it_throws_on_a_column_that_is_not_a_plain_column_name(string $column): void
    {
        $this->expectException(InvalidRowContextColumnException::class);
        $this->expectExceptionMessage("The ThroughParent perimeter through the [post] relation is decidedInRowContext('{$column}'), which is not the name of a column of the child model.");

        ThroughParent::belongsTo('post')->decidedInRowContext($column);
    }

    #[Test]
    public function it_throws_when_it_is_a_level_of_a_group(): void
    {
        $this->expectException(RowContextInLevelsException::class);
        $this->expectExceptionMessage("Level #2 given to Lomkit\\Access\\Perimeters\\Levels::of() is a ThroughParent through the [post] relation decidedInRowContext('workspace_id').");

        Levels::of(ThroughParent::belongsTo('author'), $this->throughPost());
    }

    #[Test]
    public function it_throws_when_the_column_changes_between_two_builds(): void
    {
        $this->app->instance(CommentControl::class, new ColumnFromConfigCommentControl);
        config(['blog.row_context_column' => 'workspace_id']);
        $this->visibleComments($this->ada);

        config(['blog.row_context_column' => 'author_id']);

        $this->expectException(ImpurePerimetersException::class);
        $this->expectExceptionMessage(sprintf('The perimeters() or boundary() method of the [%s] control built a different declaration twice.', ColumnFromConfigCommentControl::class));

        $this->visibleComments($this->ada);
    }

    #[Test]
    public function it_decides_the_parent_in_the_row_context_only_when_it_declares_a_column(): void
    {
        $this->assertNotInstanceOf(ParentInRowContext::class, $this->plainThroughPost()->boundTo(new CommentControl)->decision());
        $this->assertInstanceOf(ParentInRowContext::class, $this->throughPost()->boundTo(new CommentControl)->decision());
    }
}
