<?php

namespace Lomkit\Access\Tests\Unit\Search;

use Lomkit\Access\Search\SearchKeyDigest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SearchKeyDigestTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function keys(): array
    {
        return [
            'a short key' => ['client.7'],
            'an escaped key' => ['team._41'],
            'a parent key' => ['through.folder.1.workspace_id.3.client.7'],
            'an empty key' => [''],
            'a long key' => [str_repeat('a', 500)],
        ];
    }

    #[Test]
    #[DataProvider('keys')]
    public function it_digests_a_key_into_32_lowercase_hexadecimal_characters(string $key): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', SearchKeyDigest::of($key));
    }

    #[Test]
    public function it_digests_a_key_into_the_same_digest_every_time(): void
    {
        $this->assertSame(SearchKeyDigest::of('client.7'), SearchKeyDigest::of('client.7'));
        $this->assertSame(substr(hash('sha256', 'lac1:client.7'), 0, 32), SearchKeyDigest::of('client.7'));
    }

    #[Test]
    public function it_digests_the_key_behind_a_version_prefix(): void
    {
        $this->assertNotSame(substr(hash('sha256', 'client.7'), 0, 32), SearchKeyDigest::of('client.7'));
    }

    /** @return array<string, array{string, string}> */
    public static function nearMisses(): array
    {
        return [
            'a tuple of ints split differently' => ['author.1.23', 'author.12.3'],
            'an escaped string and an int' => ['team._41', 'team.41'],
            'an underscore in the name' => ['team_a.1', 'teama.1'],
            'a dot moved into the name' => ['brand1.7', 'brand.17'],
            'another case' => ['client.7', 'CLIENT.7'],
            'two long keys sharing their first 100 characters' => ['shared.'.str_repeat('a', 93).str_repeat('b', 50), 'shared.'.str_repeat('a', 93).str_repeat('c', 50)],
            'a key of 469 bytes and its first 468 bytes' => [str_repeat('a', 468), str_repeat('a', 469)],
        ];
    }

    #[Test]
    #[DataProvider('nearMisses')]
    public function it_gives_different_digests_to_keys_that_an_engine_tokenizer_would_merge(string $key, string $other): void
    {
        $this->assertNotSame(SearchKeyDigest::of($key), SearchKeyDigest::of($other));
    }

    #[Test]
    public function it_digests_every_key_of_a_document_in_order(): void
    {
        $this->assertSame(
            ['_access' => [SearchKeyDigest::of('author.7'), SearchKeyDigest::of('featured.yes')], '_boundary' => []],
            SearchKeyDigest::document(['_access' => ['author.7', 'featured.yes'], '_boundary' => []]),
        );
        $this->assertSame([SearchKeyDigest::of('a'), SearchKeyDigest::of('b')], SearchKeyDigest::all(['a', 'b']));
    }

    #[Test]
    public function it_recognises_a_digest_and_nothing_else(): void
    {
        $digest = SearchKeyDigest::of('client.7');

        $this->assertTrue(SearchKeyDigest::isDigest($digest));
        $this->assertFalse(SearchKeyDigest::isDigest('#closed'));
        $this->assertFalse(SearchKeyDigest::isDigest('client.7'));
        $this->assertFalse(SearchKeyDigest::isDigest(strtoupper($digest)));
        $this->assertFalse(SearchKeyDigest::isDigest(substr($digest, 0, 31)));
        $this->assertFalse(SearchKeyDigest::isDigest($digest.'a'));
        $this->assertFalse(SearchKeyDigest::isDigest($digest."\n"));
    }
}
