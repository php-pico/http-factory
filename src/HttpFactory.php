<?php

declare(strict_types=1);

namespace PhpPico\Http\Factory;

use Psr\Http\Message\RequestFactoryInterface;
use Override;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\RequestInterface;
use PhpPico\Http\Message\Uri;
use PhpPico\Http\Message\Request;

final class HttpFactory implements RequestFactoryInterface
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
}
