<?php

namespace Lomkit\Access\Tests\Fixtures\Engines;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RecordingHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    #[\Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return new Response(200, ['Content-Type' => 'application/json'], '{"results": [{"hits": [], "found": 0, "out_of": 0, "page": 1}]}');
    }

    /** @return list<array<string, mixed>> */
    public function multiSearches(): array
    {
        $searches = [];

        foreach ($this->requests as $request) {
            if (str_ends_with($request->getUri()->getPath(), '/multi_search')) {
                $body = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
                $searches = [...$searches, ...$body['searches']];
            }
        }

        return $searches;
    }
}
