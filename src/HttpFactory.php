<?php

declare(strict_types=1);

namespace PhpPico\Http\Factory;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Override;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\RequestInterface;
use PhpPico\Http\Message\Uri;
use PhpPico\Http\Message\Request;
use Psr\Http\Message\ResponseInterface;
use PhpPico\Http\Message\Response;
use Psr\Http\Message\ServerRequestInterface;
use PhpPico\Http\Message\ServerRequest;

final class HttpFactory implements RequestFactoryInterface, ResponseFactoryInterface, ServerRequestFactoryInterface
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

    /**
     * Create a new server request.
     *
     * Note that server-params are taken precisely as given - no parsing/processing
     * of the given values is performed, and, in particular, no attempt is made to
     * determine the HTTP method or URI, which must be provided explicitly.
     *
     * @param string $method The HTTP method associated with the request.
     * @param UriInterface|string $uri The URI associated with the request. If
     *     the value is a string, the factory MUST create a UriInterface
     *     instance based on it.
     * @param array $serverParams Array of SAPI parameters with which to seed
     *     the generated request instance.
     *
     * @return ServerRequestInterface
     */
    #[Override]
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        if (is_string($uri)) {
            $uri = new Uri($uri);
        }

        return new ServerRequest($method, $uri, $serverParams);
    }
}
