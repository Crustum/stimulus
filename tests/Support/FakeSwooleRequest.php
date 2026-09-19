<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Support;

/**
 * In-memory Swoole request carrying bridge payloads.
 *
 * Stands in for the Swoole HTTP request in extension-gated tests so the
 * suites load and skip cleanly where the extension is absent.
 */
class FakeSwooleRequest
{
    /**
     * GET parameters.
     *
     * @var array<string, mixed>|null
     */
    public ?array $get = null;

    /**
     * POST parameters.
     *
     * @var array<string, mixed>|null
     */
    public ?array $post = null;

    /**
     * Request headers.
     *
     * @var array<string, mixed>|null
     */
    public ?array $header = null;

    /**
     * Server variables.
     *
     * @var array<string, mixed>|null
     */
    public ?array $server = null;

    /**
     * Cookies.
     *
     * @var array<string, mixed>|null
     */
    public ?array $cookie = null;

    /**
     * Uploaded files.
     *
     * @var array<string, mixed>|null
     */
    public ?array $files = null;

    /**
     * Raw request body.
     *
     * @var string
     */
    public string $content = '';

    /**
     * Reads the raw request body.
     *
     * @return string Raw request body.
     */
    public function rawContent(): string
    {
        return $this->content;
    }
}
