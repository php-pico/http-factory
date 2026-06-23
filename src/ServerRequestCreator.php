<?php

declare(strict_types=1);

namespace PhpPico\Http\Factory;

use PhpPico\Utility\IpAddress;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

final readonly class ServerRequestCreator
{
    /**
     * @param HttpFactory $factory
     * @param string[]    $trustedProxies
     */
    public function __construct(
        protected HttpFactory $factory,
        protected array $trustedProxies = [],
    ) {}

    /**
     * Build a ServerRequest object from PHP superglobals.
     *
     * @return ServerRequestInterface
     */
    public function fromGlobals(): ServerRequestInterface
    {
        return $this->fromArrays(
            $_SERVER,
            $_GET,
            $_POST,
            $_COOKIE,
            $_FILES,
            $this->factory->createStreamFromFile('php://input', 'r'),
        );
    }

    /**
     * Build a ServerRequest object from arrays.
     *
     * @param array<string, mixed>    $server
     * @param array<array-key, mixed> $get
     * @param array<array-key, mixed> $post
     * @param array<array-key, mixed> $cookie
     * @param array<array-key, mixed> $files
     *
     * @return ServerRequestInterface
     */
    public function fromArrays(
        array $server,
        array $get = [],
        array $post = [],
        array $cookie = [],
        array $files = [],
        StreamInterface|string|null $body = null,
    ): ServerRequestInterface {
        $headers = $this->marshalHeaders($server);
        $stream = $this->resolveBody($body);

        $request = $this->factory
            ->createServerRequest($this->getMethod($server), $this->getUri($server, $headers), $server)
            ->withProtocolVersion($this->getProtocolVersion($server));

        foreach ($headers as $name => $values) {
            $request = $request->withHeader($name, $values);
        }

        return $request
            ->withQueryParams($get)
            ->withCookieParams($cookie)
            ->withParsedBody($this->parseBody($headers, $post, $stream))
            ->withUploadedFiles($this->normalizeFiles($files))
            ->withBody($stream);
    }

    /**
     * Parse method.
     *
     * @param array<string, mixed> $server
     *
     * @return string Defaults to 'GET'
     */
    protected function getMethod(array $server): string
    {
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? ''));

        return $method === '' ? 'GET' : $method;
    }

    /**
     * Parse protocol version.
     *
     * @param array<string, mixed> $server
     *
     * @return string
     */
    protected function getProtocolVersion(array $server): string
    {
        $protocol = (string) ($server['SERVER_PROTOCOL'] ?? '');
        $matches = [];

        return preg_match('#^HTTP/(\d+(?:\.\d+)?)$#', $protocol, $matches) === 1 ? $matches[1] : '1.1';
    }

    /**
     * Parse/marshal headers.
     *
     * @param array<string, mixed> $server
     *
     * @return array<string, list<string>>
     */
    protected function marshalHeaders(array $server): array
    {
        $headers = [];

        // @mago-expect analysis:mixed-assignment -- server values are mixed and cast to string below.
        foreach ($server as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $headers[$this->normalizeHeaderName(substr($key, 5))] = [(string) $value];
            } elseif ($key === 'CONTENT_TYPE') {
                $headers['Content-Type'] = [(string) $value];
            } elseif ($key === 'CONTENT_LENGTH') {
                $headers['Content-Length'] = [(string) $value];
            }
        }

        return $headers;
    }

    /**
     * Normalize HTTP header name.
     *
     * @param string $raw
     *
     * @return string
     */
    protected function normalizeHeaderName(string $raw): string
    {
        return ucwords(str_replace('_', '-', strtolower($raw)), '-');
    }

    /**
     * Build URI object.
     *
     * @param array<string, mixed>        $server
     * @param array<string, list<string>> $headers
     *
     * @return UriInterface
     */
    protected function getUri(array $server, array $headers): UriInterface
    {
        $scheme = $this->getScheme($server, $headers);
        [$host, $port] = $this->getHostAndPort($server, $headers, $scheme);
        [$path, $query] = $this->getPathAndQuery($server);

        $authority = $port === null ? $host : "{$host}:{$port}";
        $url = "{$scheme}://{$authority}{$path}";

        if ($query !== '') {
            $url .= "?{$query}";
        }

        return $this->factory->createUri($url);
    }

    /**
     * Parse scheme.
     *
     * @param array<string, mixed>        $server
     * @param array<string, list<string>> $headers
     *
     * @return string
     */
    protected function getScheme(array $server, array $headers): string
    {
        // @mago-expect analysis:mixed-assignment -- HTTPS is a mixed server value, narrowed below.
        $https = $server['HTTPS'] ?? null;
        $scheme = is_string($https) && $https !== '' && strtolower($https) !== 'off' ? 'https' : 'http';

        if ($scheme === 'http' && (int) ($server['SERVER_PORT'] ?? 0) === 443) {
            $scheme = 'https';
        }

        if ($this->isFromTrustedProxy($server)) {
            $forwarded = $this->firstHeaderValue($headers, 'X-Forwarded-Proto');

            if ($forwarded !== null) {
                $scheme = strtolower(trim(explode(',', $forwarded)[0])) === 'https' ? 'https' : 'http';
            }
        }

        return $scheme;
    }

    /**
     * Parse host and port.
     *
     * @param array<string, mixed>        $server
     * @param array<string, list<string>> $headers
     *
     * @return array{0: string, 1: int|null}
     */
    protected function getHostAndPort(array $server, array $headers, string $scheme): array
    {
        $rawHost = (string) ($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? $server['SERVER_ADDR'] ?? 'localhost');
        [$host, $port] = $this->splitHostPort($rawHost);

        if ($port === null && isset($server['SERVER_PORT'])) {
            $port = (int) $server['SERVER_PORT'];
        }

        if ($this->isFromTrustedProxy($server)) {
            $forwardedHost = $this->firstHeaderValue($headers, 'X-Forwarded-Host');

            if ($forwardedHost !== null) {
                [$host, $forwardedPort] = $this->splitHostPort(trim(explode(',', $forwardedHost)[0]));
                $port = $forwardedPort;
            }

            $forwardedPort = $this->firstHeaderValue($headers, 'X-Forwarded-Port');

            if ($forwardedPort !== null && ctype_digit(trim($forwardedPort))) {
                $port = (int) trim($forwardedPort);
            }
        }

        $defaults = ['http' => 80, 'https' => 443];

        if ($port !== null && ($defaults[$scheme] ?? null) === $port) {
            $port = null;
        }

        return [$host, $port];
    }

    /**
     * Split host and port into an array.
     *
     * @param string $host E.g. "localhost:8000"
     *
     * @return array{0: string, 1: int|null}
     */
    protected function splitHostPort(string $host): array
    {
        $host = trim($host);

        if ($host === '') {
            return ['localhost', null];
        }

        if (str_starts_with($host, '[')) {
            $close = strpos($host, ']');

            if ($close === false) {
                return [$host, null];
            }

            $literal = substr($host, 0, $close + 1);
            $rest = substr($host, $close + 1);
            $port = str_starts_with($rest, ':') && ctype_digit(substr($rest, 1)) ? (int) substr($rest, 1) : null;

            return [$literal, $port];
        }

        if (substr_count($host, ':') === 1) {
            [$name, $port] = explode(':', $host, 2);

            return ctype_digit($port) ? [$name, (int) $port] : [$host, null];
        }

        return [$host, null];
    }

    /**
     * Parse path and query.
     *
     * @param array<string, mixed> $server
     *
     * @return array{0: string, 1: string}
     */
    protected function getPathAndQuery(array $server): array
    {
        // @mago-expect analysis:mixed-assignment -- REQUEST_URI is a mixed server value, narrowed below.
        $requestUri = $server['REQUEST_URI'] ?? null;

        if (is_string($requestUri) && $requestUri !== '') {
            $parts = explode('?', $requestUri, 2);

            return [$parts[0], $parts[1] ?? (string) ($server['QUERY_STRING'] ?? '')];
        }

        $path = (string) ($server['PHP_SELF'] ?? '/');

        return [$path === '' ? '/' : $path, (string) ($server['QUERY_STRING'] ?? '')];
    }

    /**
     * Resolve body as a Stream.
     *
     * @param StreamInterface|string|null $body
     *
     * @return StreamInterface
     */
    protected function resolveBody(StreamInterface|string|null $body): StreamInterface
    {
        if ($body instanceof StreamInterface) {
            return $body;
        }

        return $this->factory->createStream($body ?? '');
    }

    /**
     * Parse request body.
     *
     * @param array<string, list<string>> $headers
     * @param array<array-key, mixed>     $post
     * @param StreamInterface             $body
     *
     * @return array<array-key, mixed>|object|null
     */
    protected function parseBody(array $headers, array $post, StreamInterface $body): array|object|null
    {
        $type = $this->normalizedContentType($headers);

        if ($type === 'application/x-www-form-urlencoded' || $type === 'multipart/form-data') {
            return $post;
        }

        if ($type === 'application/json' || str_ends_with($type, '+json')) {
            $raw = $this->readRaw($body);

            if ($raw === '') {
                return null;
            }

            // @mago-expect analysis:mixed-assignment -- json_decode returns mixed; narrowed to array below.
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /**
     * Normalize HTTP content-type header.
     *
     * @param array<string, list<string>> $headers
     *
     * @return string
     */
    protected function normalizedContentType(array $headers): string
    {
        $line = $this->firstHeaderValue($headers, 'Content-Type');

        if ($line === null) {
            return '';
        }

        $line = strtolower(trim($line));
        $semicolon = strpos($line, ';');

        return $semicolon === false ? $line : trim(substr($line, 0, $semicolon));
    }

    /**
     * Get the first value of a header.
     *
     * @param array<string, list<string>> $headers
     * @param string                      $name
     *
     * @return string|null
     */
    protected function firstHeaderValue(array $headers, string $name): ?string
    {
        $lower = strtolower($name);

        foreach ($headers as $key => $values) {
            if (strtolower($key) === $lower) {
                return $values[0] ?? null;
            }
        }

        return null;
    }

    /**
     * Read raw contents of body stream.
     *
     * @param StreamInterface $body
     *
     * @return string
     */
    protected function readRaw(StreamInterface $body): string
    {
        if ($body->isSeekable()) {
            $body->rewind();
        }

        $contents = $body->getContents();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        return $contents;
    }

    /**
     * Normalize uploaded files array for parsing.
     *
     * @param array<array-key, mixed> $files
     *
     * @return array<array-key, mixed>
     */
    protected function normalizeFiles(array $files): array
    {
        $normalized = [];

        // @mago-expect analysis:mixed-assignment -- the $_FILES tree is mixed and validated structurally below.
        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;
            } elseif (is_array($value) && isset($value['tmp_name'])) {
                $normalized[$key] = $this->normalizeFileSpec($value);
            } elseif (is_array($value)) {
                $normalized[$key] = $this->normalizeFiles($value);
            }
        }

        return $normalized;
    }

    /**
     * Normalize file spec for parsing.
     *
     * @param array<array-key, mixed> $spec
     *
     * @return UploadedFileInterface|array<array-key, mixed>
     */
    protected function normalizeFileSpec(array $spec): UploadedFileInterface|array
    {
        // @mago-expect analysis:mixed-assignment -- the $_FILES spec is mixed and narrowed below.
        $tmpName = $spec['tmp_name'] ?? null;

        if (is_array($tmpName)) {
            return $this->normalizeNestedFileSpec($spec, $tmpName);
        }

        return $this->createUploadedFileFromSpec(
            (string) ($spec['tmp_name'] ?? ''),
            isset($spec['size']) ? (int) $spec['size'] : null,
            (int) ($spec['error'] ?? \UPLOAD_ERR_OK),
            isset($spec['name']) ? (string) $spec['name'] : null,
            isset($spec['type']) ? (string) $spec['type'] : null,
        );
    }

    /**
     * Normalize nested file spec for parsing.
     *
     * @param array<array-key, mixed> $spec
     * @param array<array-key, mixed> $tmpNames
     *
     * @return array<array-key, mixed>
     */
    protected function normalizeNestedFileSpec(array $spec, array $tmpNames): array
    {
        $result = [];

        foreach (array_keys($tmpNames) as $subKey) {
            $result[$subKey] = $this->normalizeFileSpec([
                'name' => $this->subValue($spec['name'] ?? null, $subKey),
                'type' => $this->subValue($spec['type'] ?? null, $subKey),
                'tmp_name' => $tmpNames[$subKey] ?? null,
                'error' => $this->subValue($spec['error'] ?? null, $subKey) ?? \UPLOAD_ERR_OK,
                'size' => $this->subValue($spec['size'] ?? null, $subKey),
            ]);
        }

        return $result;
    }

    /**
     * Get sub value.
     *
     * @param mixed      $values
     * @param int|string $key
     *
     * @return mixed
     */
    protected function subValue(mixed $values, int|string $key): mixed
    {
        return is_array($values) ? $values[$key] ?? null : null;
    }

    /**
     * Create uploaded file instance from spec.
     *
     * @param string      $tmpName
     * @param int|null    $size
     * @param int         $error
     * @param string|null $clientFilename
     * @param string|null $clientMediaType
     *
     * @return UploadedFileInterface
     */
    protected function createUploadedFileFromSpec(
        string $tmpName,
        ?int $size,
        int $error,
        ?string $clientFilename,
        ?string $clientMediaType,
    ): UploadedFileInterface {
        $stream =
            $error === \UPLOAD_ERR_OK && $tmpName !== ''
                ? $this->factory->createStreamFromFile($tmpName, 'r')
                : $this->factory->createStream('');

        return $this->factory->createUploadedFile($stream, $size, $error, $clientFilename, $clientMediaType);
    }

    /**
     * Check if the request was made from a trusted proxy.
     *
     * @param array<string, mixed> $server
     *
     * @return bool
     */
    protected function isFromTrustedProxy(array $server): bool
    {
        if ($this->trustedProxies === []) {
            return false;
        }

        // @mago-expect analysis:mixed-assignment -- REMOTE_ADDR is a mixed server value, narrowed below.
        $remote = $server['REMOTE_ADDR'] ?? null;

        if (!is_string($remote)) {
            return false;
        }

        $ip = new IpAddress();

        if (!$ip->isIpv4($remote) && !$ip->isIpv6($remote)) {
            return false;
        }

        foreach ($this->trustedProxies as $proxy) {
            if ($ip->matches($remote, $proxy)) {
                return true;
            }
        }

        return false;
    }
}
