<?php

declare(strict_types=1);

namespace PhpPico\Http\Factory\Tests;

use PhpPico\Http\Factory\HttpFactory;
use PhpPico\Http\Message\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;

#[CoversClass(UploadedFileFactoryInterface::class)]
#[CoversClass(HttpFactory::class)]
final class UploadedFileFactoryTest extends TestCase
{
    #[Test]
    public function implements_interface(): void
    {
        $factory = new HttpFactory();

        $expectedClass = UploadedFileFactoryInterface::class;
        $this->assertInstanceOf(
            $expectedClass,
            $factory,
            sprintf('HTTP Factory must be an instance of %s', $expectedClass),
        );
    }

    #[Test]
    public function create_uploaded_file(): void
    {
        $factory = new HttpFactory();

        $stream = Stream::create('');
        $uploadedFile = $factory->createUploadedFile($stream);

        $expectedClass = UploadedFileInterface::class;
        $this->assertInstanceOf(
            $expectedClass,
            $uploadedFile,
            sprintf('HttpFactory::createUploadedFile() must return an instance of %s', $expectedClass),
        );
    }
}
