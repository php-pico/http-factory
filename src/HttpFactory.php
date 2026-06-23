<?php

declare(strict_types=1);

namespace PhpPico\Http\Factory;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

final class HttpFactory implements
    RequestFactoryInterface,
    ResponseFactoryInterface,
    ServerRequestFactoryInterface,
    StreamFactoryInterface,
    UploadedFileFactoryInterface,
    UriFactoryInterface
{
    use RequestFactoryTrait;
    use ResponseFactoryTrait;
    use ServerRequestFactoryTrait;
    use StreamFactoryTrait;
    use UploadedFileFactoryTrait;
    use UriFactoryTrait;
}
