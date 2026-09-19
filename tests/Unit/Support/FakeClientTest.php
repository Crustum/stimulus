<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Support;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Contracts\Client;
use Crustum\Stimulus\Http\RequestContext;
use Crustum\Stimulus\Http\StimulusResponse;
use Crustum\Stimulus\Test\Support\FakeClient;
use Crustum\Stimulus\Test\TestCase;
use Laminas\Diactoros\Response as PsrResponse;
use RuntimeException;
use stdClass;

/**
 * Tests for the fake server bridge client.
 */
class FakeClientTest extends TestCase
{
    /**
     * Marshal returns the request and context pair.
     *
     * @return void
     */
    public function testMarshalRequest(): void
    {
        $client = new FakeClient([]);
        $request = new ServerRequest();
        $context = new RequestContext(['request' => $request]);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertSame([$request, $context], $client->marshalRequest($context));
        $this->assertSame(1, $client->index);
    }

    /**
     * Respond records the worker response.
     *
     * @return void
     */
    public function testRespondRecordsResponse(): void
    {
        $client = new FakeClient([]);
        $response = new PsrResponse();

        $client->respond(new RequestContext(), new StimulusResponse($response));

        $this->assertSame([$response], $client->responses);
    }

    /**
     * Error records the throwable message when debug is enabled.
     *
     * @return void
     */
    public function testErrorRecordsMessageWhenDebug(): void
    {
        $previous = Configure::read('debug');
        Configure::write('debug', true);

        try {
            $client = new FakeClient([]);

            $client->error(
                new RuntimeException('Boom.'),
                new stdClass(),
                new ServerRequest(),
                new RequestContext(),
            );

            $this->assertCount(1, $client->errors);
            $this->assertStringContainsString('Boom.', $client->errors[0]);
        } finally {
            Configure::write('debug', $previous);
        }
    }

    /**
     * Error hides the throwable message when debug is disabled.
     *
     * @return void
     */
    public function testErrorHidesMessageWhenNotDebug(): void
    {
        $previous = Configure::read('debug');
        Configure::write('debug', false);

        try {
            $client = new FakeClient([]);

            $client->error(
                new RuntimeException('Boom.'),
                new stdClass(),
                new ServerRequest(),
                new RequestContext(),
            );

            $this->assertSame(['Internal server error.'], $client->errors);
        } finally {
            Configure::write('debug', $previous);
        }
    }
}
