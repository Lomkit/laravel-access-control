<?php

namespace Lomkit\Access\Tests\Unit\Search;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Search\ClosureSource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ClosureSourceTest extends TestCase
{
    /** @var list<string> */
    protected array $files = [];

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            unlink($file);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_ignores_whitespace_and_comments(): void
    {
        $formatted = $this->closure("fn (\\Illuminate\\Database\\Eloquent\\Model \$row): mixed => \$row->getAttribute('client_id')");
        $reformatted = $this->closure("fn(\\Illuminate\\Database\\Eloquent\\Model \$row):mixed=>\$row->getAttribute('client_id')  /* reformatted */");
        $multiline = $this->closure("fn (\\Illuminate\\Database\\Eloquent\\Model \$row): mixed /* inline */ => \$row->getAttribute(\n    'client_id'\n)");

        $this->assertSame(ClosureSource::of($formatted), ClosureSource::of($reformatted));
        $this->assertSame(ClosureSource::of($formatted), ClosureSource::of($multiline));
    }

    #[Test]
    public function it_changes_when_the_code_changes(): void
    {
        $client = $this->closure("fn (\\Illuminate\\Database\\Eloquent\\Model \$row): mixed => \$row->getAttribute('client_id')");
        $author = $this->closure("fn (\\Illuminate\\Database\\Eloquent\\Model \$row): mixed => \$row->getAttribute('author_id')");

        $this->assertNotSame(ClosureSource::of($client), ClosureSource::of($author));
    }

    #[Test]
    public function it_changes_when_a_captured_value_changes(): void
    {
        $closures = [];

        foreach (['client_id', 'author_id'] as $column) {
            $closures[] = fn (Model $row): mixed => $row->getAttribute($column);
        }

        $this->assertNotSame(ClosureSource::of($closures[0]), ClosureSource::of($closures[1]));
    }

    protected function closure(string $code): Closure
    {
        $file = tempnam(sys_get_temp_dir(), 'closure-source');

        if ($file === false) {
            $this->fail('No temporary file could be created.');
        }

        $this->files[] = $file;
        file_put_contents($file, "<?php\n\nreturn [\n    {$code},\n];\n");

        $closures = require $file;

        if (! is_array($closures) || ! ($closures[0] ?? null) instanceof Closure) {
            $this->fail("The file [{$file}] does not return a closure.");
        }

        return $closures[0];
    }
}
