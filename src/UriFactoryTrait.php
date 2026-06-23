<?php

declare(strict_types=1);

namespace PhpPico\Http\Factory;

use InvalidArgumentException;
use Override;
use PhpPico\Http\Message\Uri;
use Psr\Http\Message\UriInterface;

/**
 * @require-implements \Psr\Http\Message\UriFactoryInterface
 */
trait UriFactoryTrait
{
    /**
     * Create a new URI.
     *
     * @param string $uri
     *
     * @return UriInterface
     *
     * @throws InvalidArgumentException If the given URI cannot be parsed.
     */
    #[Override]
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }
}
