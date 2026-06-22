<?php

declare(strict_types=1);

namespace PhpPico\Http\Factory\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ServerRequestFactoryInterface;
use PhpPico\Http\Factory\HttpFactory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
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
        $this->assertInstanceOf($expectedClass, $factory, sprintf('HTTP Factory must be an instance of %s', $expectedClass));
    }

    #[Test]
    public function create_server_request(): void
    {
        $factory = new HttpFactory();

        $serverRequest = $factory->createServerRequest('GET', 'https://example.com/');

        $expectedClass = ServerRequestInterface::class;
        $this->assertInstanceOf($expectedClass, $serverRequest, sprintf('HttpFactory::createServerRequest() must return an instance of %s', $expectedClass));
    }

    #[Test]
    public function null_uri_parsed_as_uri_object(): void
    {
        $factory = new HttpFactory();

        $serverRequest = $factory->createServerRequest('GET', null);

        $expectedClass = UriInterface::class;
        $this->assertInstanceOf($expectedClass, $serverRequest->getUri(), 'HttpFactory::createServerRequest() must convert NULL URI into an URI object');
    }
}
