<?php

declare(strict_types=1);

namespace PhpPico\Http\Factory\Tests;

use PhpPico\Http\Factory\HttpFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

#[CoversClass(ServerRequestFactoryInterface::class)]
#[CoversClass(HttpFactory::class)]
final class ServerRequestFactoryTest extends TestCase
{
    #[Test]
    public function implements_interface(): void
    {
        $factory = new HttpFactory();

        $expectedClass = ServerRequestFactoryInterface::class;
        $this->assertInstanceOf(
            $expectedClass,
            $factory,
            sprintf('HTTP Factory must be an instance of %s', $expectedClass),
        );
    }

    #[Test]
    public function create_server_request(): void
    {
        $factory = new HttpFactory();

        $serverRequest = $factory->createServerRequest('GET', 'https://example.com/');

        $expectedClass = ServerRequestInterface::class;
        $this->assertInstanceOf(
            $expectedClass,
            $serverRequest,
            sprintf('HttpFactory::createServerRequest() must return an instance of %s', $expectedClass),
        );
    }
}
