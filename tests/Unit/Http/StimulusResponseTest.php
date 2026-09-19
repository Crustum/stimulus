<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Http;

use Crustum\Stimulus\Http\StimulusResponse;
use Crustum\Stimulus\Test\TestCase;
use Laminas\Diactoros\Response as PsrResponse;

/**
 * Tests for the worker response envelope.
 */
class StimulusResponseTest extends TestCase
{
    /**
     * Envelope carries the response with an empty output buffer by default.
     *
     * @return void
     */
    public function testEnvelopeCarriesResponse(): void
    {
        $response = new PsrResponse();
        $envelope = new StimulusResponse($response);

        $this->assertSame($response, $envelope->response);
        $this->assertNull($envelope->outputBuffer);
    }

    /**
     * Envelope carries buffered output when given.
     *
     * @return void
     */
    public function testEnvelopeCarriesOutputBuffer(): void
    {
        $envelope = new StimulusResponse(new PsrResponse(), 'buffered');

        $this->assertSame('buffered', $envelope->outputBuffer);
    }
}
