<?php

declare(strict_types=1);

namespace PhpPico\Http\Factory\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseFactoryInterface;
use PhpPico\Http\Factory\HttpFactory;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ResponseFactoryInterface::class)]
#[CoversClass(HttpFactory::class)]
final class ResponseFactoryTest extends TestCase
{
    #[Test]
    public function create_response(): void
    {
        $factory = new HttpFactory();

        $response = $factory->createResponse();
        $this->assertInstanceOf(ResponseInterface::class, $response, 'HttpFactory::createResponse() must return an instance of Psr\Http\Message\ResponseInterface');
    }

    #[Test]
    public function change_status_code_and_reason_phrase(): void
    {
        $factory = new HttpFactory();

        $code = 418;

        $response = $factory->createResponse($code);
        $this->assertEquals($code, $response->getStatusCode(), 'The response must have status code: 418');
        $this->assertEquals("I'm a teapot", $response->getReasonPhrase(), "The response must have reason phrase: I'm a teapot");
    }

    #[Test]
    #[DataProvider('invalidStatusCodes')]
    public function throws_exception_if_status_code_is_out_of_bounds(int $code): void
    {
        $factory = new HttpFactory();

        $this->expectException(InvalidArgumentException::class);
        $factory->createResponse($code);
    }

    public static function invalidStatusCodes(): array
    {
        return [
            [050],
            [999],
        ];
    }
}
