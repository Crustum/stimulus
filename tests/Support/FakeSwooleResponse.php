<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Support;

/**
 * In-memory Swoole response recording the bridge client calls.
 *
 * Stands in for the Swoole HTTP response in extension-gated tests so the
 * suites load and skip cleanly where the extension is absent.
 */
class FakeSwooleResponse
{
    /**
     * Recorded status calls.
     *
     * @var list<array<int, mixed>>
     */
    public array $statuses = [];

    /**
     * Recorded header calls without the format flag.
     *
     * @var list<array{0: string, 1: string}>
     */
    public array $headers = [];

    /**
     * Recorded cookie calls.
     *
     * @var list<array<int, mixed>>
     */
    public array $cookies = [];

    /**
     * Recorded body chunk writes.
     *
     * @var list<string>
     */
    public array $writes = [];

    /**
     * Recorded end calls.
     *
     * @var list<array<int, mixed>>
     */
    public array $ends = [];

    /**
     * Recorded file paths.
     *
     * @var list<string>
     */
    public array $files = [];

    /**
     * Records a status call.
     *
     * @param int $code Response status code.
     * @param string $reason Reason phrase for non-standard codes.
     * @return void
     */
    public function status(int $code, string $reason = ''): void
    {
        $this->statuses[] = $reason === '' ? [$code] : [$code, $reason];
    }

    /**
     * Records a header call.
     *
     * @param string $name Header name.
     * @param string $value Header value.
     * @param bool $format Whether the header name is formatted.
     * @return void
     */
    public function header(string $name, string $value, bool $format = true): void
    {
        $this->headers[] = [$name, $value];
    }

    /**
     * Records a cookie call.
     *
     * @param mixed ...$params Cookie parameters.
     * @return void
     */
    public function cookie(mixed ...$params): void
    {
        $this->cookies[] = $params;
    }

    /**
     * Records a body chunk write.
     *
     * @param string $data Body chunk.
     * @return void
     */
    public function write(string $data): void
    {
        $this->writes[] = $data;
    }

    /**
     * Records the response end.
     *
     * @param string $data Final body.
     * @return void
     */
    public function end(string $data = ''): void
    {
        $this->ends[] = [$data];
    }

    /**
     * Records a file send.
     *
     * @param string $path Absolute path to the file.
     * @return void
     */
    public function sendfile(string $path): void
    {
        $this->files[] = $path;
    }
}
