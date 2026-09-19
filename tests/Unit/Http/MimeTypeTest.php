<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Http;

use Crustum\Stimulus\Http\MimeType;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the extension to MIME type map.
 */
class MimeTypeTest extends TestCase
{
    /**
     * Get returns the full extension map when no extension is given.
     *
     * @return void
     */
    public function testGetReturnsFullMapWithoutExtension(): void
    {
        $mimes = MimeType::get();

        $this->assertIsArray($mimes);
        $this->assertSame('application/json', $mimes['json']);
        $this->assertSame('image/png', $mimes['png']);
    }

    /**
     * Get returns the MIME type for a known extension.
     *
     * @return void
     */
    public function testGetReturnsMimeTypeForKnownExtension(): void
    {
        $this->assertSame('application/json', MimeType::get('json'));
        $this->assertSame('text/html', MimeType::get('html'));
        $this->assertSame('video/mp4', MimeType::get('mp4'));
    }

    /**
     * Get returns a generic binary fallback for an unknown extension.
     *
     * @return void
     */
    public function testGetReturnsFallbackForUnknownExtension(): void
    {
        $this->assertSame('application/octet-stream', MimeType::get('no-such-extension'));
    }
}
