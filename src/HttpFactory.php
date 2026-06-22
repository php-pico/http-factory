<?php

declare(strict_types=1);

namespace PhpPico\Http\Factory;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Override;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\RequestInterface;
use PhpPico\Http\Message\Uri;
use PhpPico\Http\Message\Request;
use Psr\Http\Message\ResponseInterface;
use PhpPico\Http\Message\Response;

final class HttpFactory implements RequestFactoryInterface, ResponseFactoryInterface
{
    /**
     * Create a new request.
     *
     * @param string $method The HTTP method associated with the request.
     * @param UriInterface|string $uri The URI associated with the request. If
     *     the value is a string, the factory MUST create a UriInterface
     *     instance based on it.
     *
     * @return RequestInterface
     */
    #[Override]
    public function createRequest(string $method, $uri): RequestInterface
    {
        if (is_string($uri)) {
            $uri = new Uri($uri);
        }    

        return new Request($method, $uri);
    }

    /**
     * Create a new response.
     *
     * @param int $code HTTP status code; defaults to 200
     * @param string $reasonPhrase Reason phrase to associate with status code
     *     in generated response; if none is provided implementations MAY use
     *     the defaults as suggested in the HTTP specification.
     *
     * @return ResponseInterface
     */
    #[Override]
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response()->withStatus($code, $reasonPhrase);
    }
}
