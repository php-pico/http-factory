<?php

declare(strict_types=1);

namespace PhpPico\Http\Factory\Tests;

use PhpPico\Http\Factory\HttpFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

#[CoversClass(StreamFactoryInterface::class)]
#[CoversClass(HttpFactory::class)]
final class StreamFactoryTest extends TestCase
{
    #[Test]
    public function implements_interface(): void
    {
        $factory = new HttpFactory();

        $expectedClass = StreamFactoryInterface::class;
        $this->assertInstanceOf(
            $expectedClass,
            $factory,
            sprintf('HTTP Factory must be an instance of %s', $expectedClass),
        );
    }

    #[Test]
    public function create_stream_from_string(): void
    {
        $factory = new HttpFactory();

        $content = 'test';
        $stream = $factory->createStream($content);

        $expectedClass = StreamInterface::class;
        $this->assertInstanceOf(
            $expectedClass,
            $stream,
            sprintf('HttpFactory::createStream() must return an instance of %s', $expectedClass),
        );
        $this->assertEquals($content, (string) $stream, 'Stream contents did not match expected content');
    }

    #[Test]
    public function create_stream_from_file(): void
    {
        $factory = new HttpFactory();

        $file = tmpfile();
        $this->assertIsResource($file);

        $content = 'test';
        fwrite($file, $content);

        $path = stream_get_meta_data($file)['uri'];
        $stream = $factory->createStreamFromFile($path);

        $expectedClass = StreamInterface::class;
        $this->assertInstanceOf(
            $expectedClass,
            $stream,
            sprintf('HttpFactory::createStreamFromFile() must return an instance of %s', $expectedClass),
        );
        $this->assertEquals($content, (string) $stream, 'Stream contents did not match expected content');
    }

    #[Test]
    public function create_stream_from_resource(): void
    {
        $factory = new HttpFactory();

        $file = tmpfile();
        $this->assertIsResource($file);

        $content = 'test';
        fwrite($file, $content);

        $stream = $factory->createStreamFromResource($file);

        $expectedClass = StreamInterface::class;
        $this->assertInstanceOf(
            $expectedClass,
            $stream,
            sprintf('HttpFactory::createStreamFromResource() must return an instance of %s', $expectedClass),
        );
        $this->assertEquals($content, (string) $stream, 'Stream contents did not match expected content');
    }
}
