<?php

namespace Lomkit\Access\Tests\Unit\Search\Tokens;

use Algolia\AlgoliaSearch\Api\SearchClient;
use DateTimeImmutable;
use Lomkit\Access\Search\Tokens\SearchTokenKey;
use Lomkit\Access\Search\Tokens\Signers\AlgoliaSigner;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class AlgoliaSignerTest extends PackageTestCase
{
    private const string KEY = 'search-only-key';

    private const string FILTER = "(_access:'client.7' OR _access:'featured.yes')";

    protected function sign(?string $filter): string
    {
        return (new AlgoliaSigner)->sign(SearchTokenKey::algolia(self::KEY), 'posts', $filter, new DateTimeImmutable('2026-11-15T00:00:00+00:00'));
    }

    /** @return array{string, array<array-key, mixed>} */
    protected function decode(string $token): array
    {
        $content = base64_decode($token, true);
        $this->assertIsString($content);
        $query = substr($content, 64);
        parse_str($query, $restrictions);

        $this->assertSame(hash_hmac('sha256', $query, self::KEY), substr($content, 0, 64));

        return [$query, $restrictions];
    }

    #[Test]
    public function it_signs_the_query_string_of_the_restrictions_with_hmac_sha256_as_algolia_documents_it(): void
    {
        $this->assertSame(
            'NTUyMTJmOTg4MTZhMWNmOTE5NTMyN2Q4MzBlNjNiOWZlZDU2NjFjYzJkMzg3NDk3ODQ3Zjg1ZGNhYTIxYjI4MGVuYWJsZUFCVGVzdD1mYWxzZSZlbmFibGVSdWxlcz1mYWxzZSZmaWx0ZXJzPSUyOF9hY2Nlc3MlM0ElMjdjbGllbnQuNyUyNyUyME9SJTIwX2FjY2VzcyUzQSUyN2ZlYXR1cmVkLnllcyUyNyUyOSZyZXN0cmljdEluZGljZXM9cG9zdHMmdmFsaWRVbnRpbD0xNzk0NzAwODAw',
            $this->sign(self::FILTER),
        );
    }

    #[Test]
    public function it_embeds_the_filter_the_rule_and_test_switches_the_index_and_the_expiry(): void
    {
        [$query, $restrictions] = $this->decode($this->sign(self::FILTER));

        $this->assertSame('enableABTest=false&enableRules=false&filters=%28_access%3A%27client.7%27%20OR%20_access%3A%27featured.yes%27%29&restrictIndices=posts&validUntil=1794700800', $query);
        $this->assertSame(['enableABTest' => 'false', 'enableRules' => 'false', 'filters' => self::FILTER, 'restrictIndices' => 'posts', 'validUntil' => '1794700800'], $restrictions);
    }

    #[Test]
    public function it_embeds_no_filter_for_an_unrestricted_reader(): void
    {
        [, $restrictions] = $this->decode($this->sign(null));

        $this->assertSame(['restrictIndices' => 'posts', 'validUntil' => '1794700800'], $restrictions);
    }

    #[Test]
    public function it_signs_the_same_key_as_the_official_client_when_it_is_installed(): void
    {
        if (! class_exists(SearchClient::class)) {
            $this->markTestSkipped('The Algolia client is not installed.');
        }

        $this->assertSame(
            SearchClient::generateSecuredApiKey(self::KEY, ['enableABTest' => 'false', 'enableRules' => 'false', 'filters' => self::FILTER, 'restrictIndices' => 'posts', 'validUntil' => 1794700800]),
            $this->sign(self::FILTER),
        );
    }
}
