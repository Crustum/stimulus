<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Worker;

use Cake\Http\CallbackStream;
use Cake\Http\Response;
use Crustum\Stimulus\Http\StimulusResponse;
use Crustum\Stimulus\Test\Support\MarshalsTraitHost;
use Crustum\Stimulus\Test\TestCase;
use Generator;

/**
 * Tests for the worker response marshalling trait.
 *
 * Proves callback-streamed bodies (the `CallbackStream` behind
 * `AbstractStreamResponse` server streaming) resolve into envelope chunk
 * producers when their callback is a generator or declares a generator or
 * string return, while every other response stays buffered with a working body.
 */
class MarshalsPsr7RequestsAndResponsesTraitTest extends TestCase
{
    /**
     * Plain responses wrap without a chunk producer.
     *
     * @return void
     */
    public function testPlainResponseHasNoStreamer(): void
    {
        $envelope = $this->host()->toEnvelope(new Response(), 'buffer');

        $this->assertInstanceOf(StimulusResponse::class, $envelope);
        $this->assertNull($envelope->streamer);
        $this->assertSame('buffer', $envelope->outputBuffer);
    }

    /**
     * Generator callbacks resolve into string chunk producers.
     *
     * @return void
     */
    public function testGeneratorCallbackResolvesProducer(): void
    {
        $response = (new Response())->withBody(new CallbackStream(static function (): Generator {
            yield 'Hello';
            yield ' World';
        }));

        $envelope = $this->host()->toEnvelope($response);

        $this->assertNotNull($envelope->streamer);

        $producer = $envelope->streamer;

        $this->assertSame(['Hello', ' World'], iterator_to_array($producer()));
        $this->assertInstanceOf(CallbackStream::class, $envelope->response->getBody());
    }

    /**
     * String callbacks resolve into single-chunk producers.
     *
     * @return void
     */
    public function testStringCallbackResolvesProducer(): void
    {
        $response = (new Response())->withBody(new CallbackStream(static fn(): string => 'Hello'));

        $envelope = $this->host()->toEnvelope($response);

        $this->assertNotNull($envelope->streamer);

        $producer = $envelope->streamer;

        $this->assertSame(['Hello'], iterator_to_array($producer()));
    }

    /**
     * Untyped generator callbacks resolve into chunk producers.
     *
     * @return void
     */
    public function testUntypedGeneratorCallbackResolvesProducer(): void
    {
        $response = (new Response())->withBody(new CallbackStream(static function () {
            yield 'Hello';
            yield ' World';
        }));

        $envelope = $this->host()->toEnvelope($response);

        $this->assertNotNull($envelope->streamer);

        $producer = $envelope->streamer;

        $this->assertSame(['Hello', ' World'], iterator_to_array($producer()));
    }

    /**
     * Echo-style callbacks stay buffered with a working body.
     *
     * @return void
     */
    public function testVoidCallbackStaysBuffered(): void
    {
        $response = (new Response())->withBody(new CallbackStream(static function (): void {
            echo 'Hello';
        }));

        $envelope = $this->host()->toEnvelope($response);

        $this->assertNull($envelope->streamer);
        $this->assertInstanceOf(CallbackStream::class, $envelope->response->getBody());
    }

    /**
     * Creates a marshalling host.
     *
     * @return \Crustum\Stimulus\Test\Support\MarshalsTraitHost Marshalling host under test.
     */
    protected function host(): MarshalsTraitHost
    {
        return new MarshalsTraitHost();
    }
}
