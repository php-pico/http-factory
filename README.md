# php-pico/http-factory

PSR-17 compliant HTTP Factory package.

## Installation

```bash
composer require php-pico/http-factory
```

## Factory usage

`HttpFactory` implements every PSR-17 factory interface.

```php
use PhpPico\Http\Factory\HttpFactory;

$factory = new HttpFactory();

$request  = $factory->createRequest('GET', 'https://example.com');
$response = $factory->createResponse(200);
$uri      = $factory->createUri('https://example.com/path?q=1');

$stream = $factory->createStream('hello');
$stream = $factory->createStreamFromFile('/path/to/file.txt', 'r');
$stream = $factory->createStreamFromResource(fopen('php://memory', 'r+'));

$file = $factory->createUploadedFile($stream, size: 5, clientFilename: 'hello.txt');
```

## Building a server request

`ServerRequestCreator` builds a PSR-7 `ServerRequestInterface` from the PHP superglobals.

```php
use PhpPico\Http\Factory\HttpFactory;
use PhpPico\Http\Factory\ServerRequestCreator;

$creator = new ServerRequestCreator(new HttpFactory());

$request = $creator->fromGlobals();
```

Use `fromArrays()` when you want to supply the input explicitly:

```php
$request = $creator->fromArrays(
    server: $_SERVER,
    get: $_GET,
    post: $_POST,
    cookie: $_COOKIE,
    files: $_FILES,
);
```

## Composing your own factory

`HttpFactory` is just the six PSR-17 interfaces composed from one trait each.
Pull in only the traits you need to build a custom factory:

```php
use PhpPico\Http\Factory\RequestFactoryTrait;
use PhpPico\Http\Factory\ResponseFactoryTrait;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;

final class MyFactory implements RequestFactoryInterface, ResponseFactoryInterface
{
    use RequestFactoryTrait;
    use ResponseFactoryTrait;
}
```

Available traits: `RequestFactoryTrait`, `ResponseFactoryTrait`,
`ServerRequestFactoryTrait`, `StreamFactoryTrait`, `UploadedFileFactoryTrait`,
`UriFactoryTrait` — all under `PhpPico\Http\Factory`.

## Trusted proxies

Pass trusted proxies (exact IPs or CIDR, IPv4/IPv6) to honor `X-Forwarded-Proto`,
`X-Forwarded-Host` and `X-Forwarded-Port` — but only when `REMOTE_ADDR` matches a
trusted proxy. Untrusted remotes are ignored.

```php
$creator = new ServerRequestCreator(new HttpFactory(), ['10.0.0.0/8']);

$request = $creator->fromGlobals();
```
