<?php

declare(strict_types=1);

namespace PhpPico\Http\Factory\Tests;

use PhpPico\Http\Factory\HttpFactory;
use PhpPico\Http\Message\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

#[CoversClass(UriFactoryInterface::class)]
#[CoversClass(HttpFactory::class)]
final class UriFactoryTest extends TestCase
{
    #[Test]
    public function implements_interface(): void
    {
        $factory = new HttpFactory();

        $expectedClass = UriFactoryInterface::class;
        $this->assertInstanceOf(
            $expectedClass,
            $factory,
            sprintf('HTTP Factory must be an instance of %s', $expectedClass),
        );
    }

    #[Test]
    public function create_uri(): void
    {
        $factory = new HttpFactory();

        $test = 'https://example.com';
        $uri = $factory->createUri($test);

        $expectedClass = UriInterface::class;
        $this->assertInstanceOf(
            $expectedClass,
            $uri,
            sprintf('HttpFactory::createUri() must return an instance of %s', $expectedClass),
        );
        $this->assertEquals($test, (string) $uri, 'Returned URI must be equal to the original passed string');
    }
}
