<?php

declare(strict_types=1);

namespace PhpPico\Http\Factory\Tests;

use PhpPico\Http\Factory\HttpFactory;
use PhpPico\Http\Factory\ServerRequestCreator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

#[CoversClass(ServerRequestCreator::class)]
final class ServerRequestCreatorTest extends TestCase
{
    /** @param array<int, string> $trustedProxies */
    private function creator(array $trustedProxies = []): ServerRequestCreator
    {
        return new ServerRequestCreator(new HttpFactory(), $trustedProxies);
    }

    #[Test]
    public function method_defaults_to_get_when_missing(): void
    {
        $request = $this->creator()->fromArrays(['HTTP_HOST' => 'example.com']);

        $this->assertEquals('GET', $request->getMethod(), 'Missing REQUEST_METHOD must default to GET');
    }

    #[Test]
    public function method_defaults_to_get_when_empty(): void
    {
        $request = $this->creator()->fromArrays(['REQUEST_METHOD' => '', 'HTTP_HOST' => 'example.com']);

        $this->assertEquals('GET', $request->getMethod(), 'Empty REQUEST_METHOD must default to GET');
    }

    #[Test]
    public function method_is_taken_from_server(): void
    {
        $request = $this->creator()->fromArrays(['REQUEST_METHOD' => 'post', 'HTTP_HOST' => 'example.com']);

        $this->assertEquals('POST', $request->getMethod(), 'REQUEST_METHOD must be uppercased');
    }

    #[Test]
    public function protocol_version_is_parsed(): void
    {
        $request = $this->creator()->fromArrays(['SERVER_PROTOCOL' => 'HTTP/2', 'HTTP_HOST' => 'example.com']);

        $this->assertEquals('2', $request->getProtocolVersion(), 'HTTP/2 must yield protocol version 2');
    }

    #[Test]
    public function protocol_version_defaults_when_missing_or_garbage(): void
    {
        $missing = $this->creator()->fromArrays(['HTTP_HOST' => 'example.com']);
        $garbage = $this->creator()->fromArrays(['SERVER_PROTOCOL' => 'nonsense', 'HTTP_HOST' => 'example.com']);

        $this->assertEquals('1.1', $missing->getProtocolVersion(), 'Missing protocol must default to 1.1');
        $this->assertEquals('1.1', $garbage->getProtocolVersion(), 'Garbage protocol must default to 1.1');
    }

    #[Test]
    public function headers_are_derived_from_server(): void
    {
        $request = $this->creator()->fromArrays([
            'HTTP_HOST' => 'example.com',
            'HTTP_ACCEPT' => 'text/html',
            'HTTP_ACCEPT_ENCODING' => 'gzip',
            'CONTENT_TYPE' => 'text/plain',
            'CONTENT_LENGTH' => '42',
        ]);

        $this->assertEquals('text/html', $request->getHeaderLine('Accept'), 'HTTP_ACCEPT must become Accept');
        $this->assertEquals(
            'gzip',
            $request->getHeaderLine('Accept-Encoding'),
            'HTTP_ACCEPT_ENCODING must become Accept-Encoding',
        );
        $this->assertEquals(
            'text/plain',
            $request->getHeaderLine('Content-Type'),
            'CONTENT_TYPE must become Content-Type',
        );
        $this->assertEquals(
            '42',
            $request->getHeaderLine('Content-Length'),
            'CONTENT_LENGTH must become Content-Length',
        );
    }

    #[Test]
    public function non_http_server_keys_do_not_become_headers(): void
    {
        $request = $this->creator()->fromArrays(['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'example.com']);

        $this->assertFalse(
            $request->hasHeader('Request-Method'),
            'Non HTTP_ server keys must not be turned into headers',
        );
    }

    #[Test]
    public function uri_is_reconstructed_from_server(): void
    {
        $request = $this->creator()->fromArrays([
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/path?foo=bar',
        ]);

        $this->assertEquals(
            'http://example.com/path?foo=bar',
            (string) $request->getUri(),
            'URI must be reconstructed from HTTP_HOST and REQUEST_URI',
        );
    }

    #[Test]
    public function uri_uses_https_from_https_flag(): void
    {
        $request = $this->creator()->fromArrays([
            'HTTPS' => 'on',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/',
        ]);

        $this->assertEquals('https', $request->getUri()->getScheme(), 'HTTPS=on must yield https scheme');
    }

    #[Test]
    public function uri_uses_https_from_server_port(): void
    {
        $request = $this->creator()->fromArrays([
            'SERVER_PORT' => '443',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/',
        ]);

        $this->assertEquals('https', $request->getUri()->getScheme(), 'SERVER_PORT=443 must yield https scheme');
    }

    #[Test]
    public function uri_preserves_explicit_non_default_port(): void
    {
        $request = $this->creator()->fromArrays([
            'HTTP_HOST' => 'example.com:8080',
            'REQUEST_URI' => '/',
        ]);

        $this->assertEquals(8080, $request->getUri()->getPort(), 'Explicit non-default port must be preserved');
    }

    #[Test]
    public function uri_omits_default_port(): void
    {
        $request = $this->creator()->fromArrays([
            'HTTPS' => 'on',
            'HTTP_HOST' => 'example.com:443',
            'REQUEST_URI' => '/',
        ]);

        $this->assertNull($request->getUri()->getPort(), 'Default https port 443 must be omitted');
    }

    #[Test]
    public function uri_handles_ipv6_host(): void
    {
        $request = $this->creator()->fromArrays([
            'HTTP_HOST' => '[::1]:8080',
            'REQUEST_URI' => '/',
        ]);

        $this->assertEquals('[::1]', $request->getUri()->getHost(), 'IPv6 host literal must be preserved');
        $this->assertEquals(8080, $request->getUri()->getPort(), 'IPv6 host port must be parsed');
    }

    #[Test]
    public function uri_falls_back_to_server_name_and_php_self(): void
    {
        $request = $this->creator()->fromArrays([
            'SERVER_NAME' => 'fallback.test',
            'PHP_SELF' => '/index.php',
            'QUERY_STRING' => 'a=1',
        ]);

        $this->assertEquals(
            'http://fallback.test/index.php?a=1',
            (string) $request->getUri(),
            'URI must fall back to SERVER_NAME, PHP_SELF and QUERY_STRING',
        );
    }

    #[Test]
    public function query_and_cookie_params_are_set(): void
    {
        $request = $this->creator()->fromArrays(
            ['HTTP_HOST' => 'example.com'],
            ['q' => 'search'],
            [],
            ['session' => 'abc'],
        );

        $this->assertEquals(['q' => 'search'], $request->getQueryParams(), 'Query params must be set from $get');
        $this->assertEquals(
            ['session' => 'abc'],
            $request->getCookieParams(),
            'Cookie params must be set from $cookie',
        );
    }

    #[Test]
    public function server_params_are_passed_verbatim(): void
    {
        $server = ['HTTP_HOST' => 'example.com', 'REQUEST_METHOD' => 'GET', 'CUSTOM' => 'value'];

        $request = $this->creator()->fromArrays($server);

        $this->assertEquals($server, $request->getServerParams(), 'Server params must be returned verbatim');
    }

    #[Test]
    public function client_host_header_is_preserved(): void
    {
        $request = $this->creator()->fromArrays([
            'HTTP_HOST' => 'example.com:8080',
            'REQUEST_URI' => '/',
        ]);

        $this->assertEquals(
            'example.com:8080',
            $request->getHeaderLine('Host'),
            'Client-sent Host header must win over the reconstructed one',
        );
    }

    #[Test]
    public function string_body_is_attached_and_readable(): void
    {
        $request = $this->creator()->fromArrays(['HTTP_HOST' => 'example.com'], [], [], [], [], 'hello body');

        $this->assertEquals('hello body', (string) $request->getBody(), 'String body must be attached and readable');
    }

    #[Test]
    public function parsed_body_form_urlencoded_uses_post(): void
    {
        $request = $this->creator()->fromArrays(
            ['HTTP_HOST' => 'example.com', 'CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            [],
            ['a' => 'b'],
        );

        $this->assertEquals(['a' => 'b'], $request->getParsedBody(), 'Form-urlencoded body must use $post');
    }

    #[Test]
    public function parsed_body_multipart_uses_post(): void
    {
        $request = $this->creator()->fromArrays(
            ['HTTP_HOST' => 'example.com', 'CONTENT_TYPE' => 'multipart/form-data; boundary=xyz'],
            [],
            ['field' => 'value'],
        );

        $this->assertEquals(['field' => 'value'], $request->getParsedBody(), 'Multipart body must use $post');
    }

    #[Test]
    public function parsed_body_json_is_decoded(): void
    {
        $request = $this->creator()->fromArrays(
            ['HTTP_HOST' => 'example.com', 'CONTENT_TYPE' => 'application/json'],
            [],
            [],
            [],
            [],
            '{"a":1,"b":[2,3]}',
        );

        $this->assertEquals(['a' => 1, 'b' => [2, 3]], $request->getParsedBody(), 'JSON body must decode to array');
    }

    #[Test]
    public function parsed_body_json_with_charset_is_decoded(): void
    {
        $request = $this->creator()->fromArrays(
            ['HTTP_HOST' => 'example.com', 'CONTENT_TYPE' => 'application/json; charset=utf-8'],
            [],
            [],
            [],
            [],
            '{"a":1}',
        );

        $this->assertEquals(['a' => 1], $request->getParsedBody(), 'JSON with charset param must decode');
    }

    #[Test]
    public function parsed_body_suffix_json_is_decoded(): void
    {
        $request = $this->creator()->fromArrays(
            ['HTTP_HOST' => 'example.com', 'CONTENT_TYPE' => 'application/ld+json'],
            [],
            [],
            [],
            [],
            '{"a":1}',
        );

        $this->assertEquals(['a' => 1], $request->getParsedBody(), '+json suffix must decode');
    }

    #[Test]
    public function parsed_body_invalid_json_is_null(): void
    {
        $request = $this->creator()->fromArrays(
            ['HTTP_HOST' => 'example.com', 'CONTENT_TYPE' => 'application/json'],
            [],
            [],
            [],
            [],
            '{not valid}',
        );

        $this->assertNull($request->getParsedBody(), 'Invalid JSON must yield null parsed body');
    }

    #[Test]
    public function parsed_body_non_array_json_is_null(): void
    {
        $request = $this->creator()->fromArrays(
            ['HTTP_HOST' => 'example.com', 'CONTENT_TYPE' => 'application/json'],
            [],
            [],
            [],
            [],
            '"just a string"',
        );

        $this->assertNull($request->getParsedBody(), 'Non-array JSON must yield null parsed body');
    }

    #[Test]
    public function parsed_body_other_content_type_is_null(): void
    {
        $request = $this->creator()->fromArrays(
            ['HTTP_HOST' => 'example.com', 'CONTENT_TYPE' => 'text/plain'],
            [],
            ['a' => 'b'],
            [],
            [],
            'raw text',
        );

        $this->assertNull($request->getParsedBody(), 'Unknown content type must yield null parsed body');
    }

    #[Test]
    public function uploaded_file_single_is_normalized(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'upl');
        self::assertIsString($tmp);
        file_put_contents($tmp, 'file contents');

        try {
            $request = $this->creator()->fromArrays(
                ['HTTP_HOST' => 'example.com'],
                [],
                [],
                [],
                [
                    'avatar' => [
                        'name' => 'photo.png',
                        'type' => 'image/png',
                        'tmp_name' => $tmp,
                        'error' => \UPLOAD_ERR_OK,
                        'size' => 13,
                    ],
                ],
            );

            $files = $request->getUploadedFiles();
            $this->assertInstanceOf(
                UploadedFileInterface::class,
                $files['avatar'],
                'Single file must be normalized to UploadedFileInterface',
            );
            $this->assertEquals(
                'photo.png',
                $files['avatar']->getClientFilename(),
                'Client filename must be preserved',
            );
            $this->assertEquals('image/png', $files['avatar']->getClientMediaType(), 'Media type must be preserved');
            $this->assertEquals(13, $files['avatar']->getSize(), 'Size must be preserved');
            $this->assertEquals('file contents', (string) $files['avatar']->getStream(), 'Stream must read the file');
        } finally {
            unlink($tmp);
        }
    }

    #[Test]
    public function uploaded_file_with_error_uses_empty_stream(): void
    {
        $request = $this->creator()->fromArrays(
            ['HTTP_HOST' => 'example.com'],
            [],
            [],
            [],
            [
                'doc' => [
                    'name' => '',
                    'type' => '',
                    'tmp_name' => '',
                    'error' => \UPLOAD_ERR_NO_FILE,
                    'size' => 0,
                ],
            ],
        );

        $files = $request->getUploadedFiles();
        $this->assertInstanceOf(
            UploadedFileInterface::class,
            $files['doc'],
            'Errored upload must still be an UploadedFile',
        );
        $this->assertEquals(\UPLOAD_ERR_NO_FILE, $files['doc']->getError(), 'Upload error code must be preserved');
    }

    #[Test]
    public function uploaded_files_nested_shape_is_normalized(): void
    {
        $tmpA = tempnam(sys_get_temp_dir(), 'upl');
        $tmpB = tempnam(sys_get_temp_dir(), 'upl');
        self::assertIsString($tmpA);
        self::assertIsString($tmpB);
        file_put_contents($tmpA, 'A');
        file_put_contents($tmpB, 'B');

        try {
            $request = $this->creator()->fromArrays(
                ['HTTP_HOST' => 'example.com'],
                [],
                [],
                [],
                [
                    'docs' => [
                        'name' => ['a.txt', 'b.txt'],
                        'type' => ['text/plain', 'text/plain'],
                        'tmp_name' => [$tmpA, $tmpB],
                        'error' => [\UPLOAD_ERR_OK, \UPLOAD_ERR_OK],
                        'size' => [1, 1],
                    ],
                ],
            );

            $files = $request->getUploadedFiles();
            $this->assertIsArray($files['docs'], 'Nested upload shape must normalize to an array tree');
            $this->assertInstanceOf(
                UploadedFileInterface::class,
                $files['docs'][0],
                'First nested file must be an UploadedFile',
            );
            $this->assertInstanceOf(
                UploadedFileInterface::class,
                $files['docs'][1],
                'Second nested file must be an UploadedFile',
            );
            $this->assertEquals(
                'a.txt',
                $files['docs'][0]->getClientFilename(),
                'First nested filename must be preserved',
            );
            $this->assertEquals(
                'b.txt',
                $files['docs'][1]->getClientFilename(),
                'Second nested filename must be preserved',
            );
        } finally {
            unlink($tmpA);
            unlink($tmpB);
        }
    }

    #[Test]
    public function uploaded_files_empty_is_empty(): void
    {
        $request = $this->creator()->fromArrays(['HTTP_HOST' => 'example.com']);

        $this->assertEquals([], $request->getUploadedFiles(), 'No files must yield an empty uploaded-files array');
    }

    #[Test]
    public function trusted_proxy_exact_ip_honors_forwarded_proto(): void
    {
        $request = $this->creator(['10.0.0.1'])->fromArrays([
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/',
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        $this->assertEquals('https', $request->getUri()->getScheme(), 'Trusted proxy must honor X-Forwarded-Proto');
    }

    #[Test]
    public function trusted_proxy_cidr_honors_forwarded_host_and_port(): void
    {
        $request = $this->creator(['10.0.0.0/8'])->fromArrays([
            'HTTP_HOST' => 'internal.local',
            'REQUEST_URI' => '/',
            'REMOTE_ADDR' => '10.5.6.7',
            'HTTP_X_FORWARDED_HOST' => 'public.example.com',
            'HTTP_X_FORWARDED_PORT' => '8443',
        ]);

        $this->assertEquals(
            'public.example.com',
            $request->getUri()->getHost(),
            'Trusted CIDR proxy must honor X-Forwarded-Host',
        );
        $this->assertEquals(8443, $request->getUri()->getPort(), 'Trusted CIDR proxy must honor X-Forwarded-Port');
    }

    #[Test]
    public function untrusted_proxy_ignores_forwarded_headers(): void
    {
        $request = $this->creator(['10.0.0.0/8'])->fromArrays([
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/',
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        $this->assertEquals('http', $request->getUri()->getScheme(), 'Untrusted proxy must ignore forwarded headers');
    }

    #[Test]
    public function empty_trusted_proxies_ignores_forwarded_headers(): void
    {
        $request = $this->creator()->fromArrays([
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/',
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        $this->assertEquals(
            'http',
            $request->getUri()->getScheme(),
            'Default empty trusted proxies must ignore forwarded headers',
        );
    }

    #[Test]
    public function trusted_proxy_ipv6_exact_ip_honors_forwarded_proto(): void
    {
        $request = $this->creator(['::1'])->fromArrays([
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/',
            'REMOTE_ADDR' => '::1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        $this->assertEquals(
            'https',
            $request->getUri()->getScheme(),
            'Trusted IPv6 proxy must honor X-Forwarded-Proto',
        );
    }

    #[Test]
    public function trusted_proxy_ipv6_cidr_honors_forwarded_proto(): void
    {
        $request = $this->creator(['2001:db8::/32'])->fromArrays([
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/',
            'REMOTE_ADDR' => '2001:db8::1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        $this->assertEquals(
            'https',
            $request->getUri()->getScheme(),
            'Trusted IPv6 CIDR proxy must honor X-Forwarded-Proto',
        );
    }

    #[Test]
    public function invalid_remote_addr_ignores_forwarded_headers(): void
    {
        $request = $this->creator(['10.0.0.1'])->fromArrays([
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/',
            'REMOTE_ADDR' => 'not-an-ip',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        $this->assertEquals(
            'http',
            $request->getUri()->getScheme(),
            'A malformed REMOTE_ADDR must fail closed without throwing',
        );
    }

    #[Test]
    public function from_globals_builds_request_from_superglobals(): void
    {
        $server = $_SERVER;
        $get = $_GET;
        $post = $_POST;
        $cookie = $_COOKIE;
        $files = $_FILES;

        try {
            $_SERVER = ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'globals.test', 'REQUEST_URI' => '/g?x=1'];
            $_GET = ['x' => '1'];
            $_POST = [];
            $_COOKIE = ['c' => 'd'];
            $_FILES = [];

            $request = $this->creator()->fromGlobals();

            $this->assertEquals('GET', $request->getMethod(), 'fromGlobals must read REQUEST_METHOD');
            $this->assertEquals(
                'http://globals.test/g?x=1',
                (string) $request->getUri(),
                'fromGlobals must reconstruct the URI from superglobals',
            );
            $this->assertEquals(['x' => '1'], $request->getQueryParams(), 'fromGlobals must read $_GET');
            $this->assertEquals(['c' => 'd'], $request->getCookieParams(), 'fromGlobals must read $_COOKIE');
        } finally {
            $_SERVER = $server;
            $_GET = $get;
            $_POST = $post;
            $_COOKIE = $cookie;
            $_FILES = $files;
        }
    }
}
