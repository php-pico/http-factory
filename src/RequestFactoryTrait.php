<?php

declare(strict_types=1);

namespace PhpPico\Http\Factory;

use Override;
use PhpPico\Http\Message\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * @require-implements \Psr\Http\Message\RequestFactoryInterface
 * @require-implements \Psr\Http\Message\UriFactoryInterface
 */
trait RequestFactoryTrait
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
            $uri = $this->createUri($uri);
        }

        return new Request($method, $uri);
    }
}
