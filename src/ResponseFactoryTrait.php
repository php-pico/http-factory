<?php

declare(strict_types=1);

namespace PhpPico\Http\Factory;

use Override;
use PhpPico\Http\Message\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * @require-implements \Psr\Http\Message\ResponseFactoryInterface
 */
trait ResponseFactoryTrait
{
    /**
     * Create a new response.
     *
     * @param int $code HTTP status code; defaults to 200
     * @param string $reasonPhrase Reason phrase to associate with status code
     *     in generated response; if none is provided implementations MAY use
     *     the defaults as suggested in the HTTP specification.
     *
     * @return ResponseInterface
     */
    #[Override]
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response()->withStatus($code, $reasonPhrase);
    }
}
