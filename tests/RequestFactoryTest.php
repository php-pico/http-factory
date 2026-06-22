<?php

declare(strict_types=1);

namespace PhpPico\Http\Factory\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\RequestFactoryInterface;
use PhpPico\Http\Factory\HttpFactory;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use PhpPico\Http\Message\Uri;

#[CoversClass(RequestFactoryInterface::class)]
#[CoversClass(HttpFactory::class)]
final class RequestFactoryTest extends TestCase
{
    #[Test]
    public function create_request(): void
    {
        $factory = new HttpFactory();

        $request = $factory->createRequest('GET', 'https://example.com');
        $this->assertInstanceOf(RequestInterface::class, $request, 'HttpFactory::createRequest() must return an instance of Psr\Http\Message\RequestInterface');
    }

    #[Test]
    public function create_request_with_string_uri(): void
    {
        $factory = new HttpFactory();

        $method = 'GET';
        $uri    = 'https://example.com/test';

        $request = $factory->createRequest($method, $uri);
        $this->assertEquals($method, $request->getMethod(), 'The request method must persist through the factory');
        $this->assertEquals($uri, (string) $request->getUri(), 'The request URI must persist through the factory');
    }

    #[Test]
    public function create_request_with_uri_object(): void
    {
        $factory = new HttpFactory();

        $method = 'GET';
        $uri    = new Uri('https://example.com/test');

        $request = $factory->createRequest($method, $uri);
        $this->assertEquals($method, $request->getMethod(), 'The request method must persist through the factory');
        $this->assertEquals($uri, (string) $request->getUri(), 'The request URI must persist through the factory');
    }
}
