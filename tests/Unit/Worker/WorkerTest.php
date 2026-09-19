<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Worker;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Http\CurrentApplication;
use Crustum\Stimulus\Test\Support\FakeClient;
use Crustum\Stimulus\Test\Support\FakeWorker;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the long-lived worker core.
 *
 * Answers through the in-worker manager routes when the request matches and
 * through the sandbox application handle otherwise, so the scenarios
 * register manager routes.
 */
class WorkerTest extends TestCase
{
    /**
     * The worker dispatches requests and returns responses to the client.
     *
     * @return void
     */
    public function testWorkerDispatchesRequestsAndReturnsResponses(): void
    {
        [$app, $worker, $client] = $this->createStimulusContext([
            new ServerRequest(['url' => '/first']),
            new ServerRequest(['url' => '/second']),
        ]);

        $worker->getManager()->route(
            'GET',
            '/first',
            fn(): Response => (new Response())->withStringBody('First Response'),
        );
        $worker->getManager()->route(
            'GET',
            '/second',
            fn(): Response => (new Response())->withStringBody('Second Response'),
        );

        $worker->run();

        $this->assertSame($app, $worker->application());
        $this->assertCount(2, $client->responses);
        $this->assertSame('First Response', (string)$client->responses[0]->getBody());
        $this->assertSame('Second Response', (string)$client->responses[1]->getBody());
    }

    /**
     * The worker dispatches tasks and returns their raw results.
     *
     * Successful results return raw values, matching the sequential dispatcher contract.
     *
     * @return void
     */
    public function testWorkerDispatchesTasksAndReturnsResponses(): void
    {
        $worker = new FakeWorker($this->appFactory(), new FakeClient([
            fn(): string => 'foo',
            fn(): string => 'bar',
            function (): void {
            },
        ]));

        $worker->boot();

        $responses = $worker->runTasks();

        $this->assertSame('foo', $responses[0]);
        $this->assertSame('bar', $responses[1]);
        $this->assertNull($responses[2]);
    }

    /**
     * The worker dispatches ticks received from the client.
     *
     * @return void
     */
    public function testWorkerDispatchesTicks(): void
    {
        $worker = new FakeWorker($this->appFactory(), new FakeClient([null, null]));

        $worker->boot();

        $this->assertSame([null, null], $worker->runTicks());
    }

    /**
     * The worker does not throw when handlers close output buffers.
     *
     * Cleans output levels above the handler baseline before answering.
     *
     * @return void
     */
    public function testWorkerDoesntThrowBufferError(): void
    {
        [, $worker, $client] = $this->createStimulusContext([
            new ServerRequest(['url' => '/test']),
        ]);

        $worker->getManager()->route('GET', '/test', function (): Response {
            $baselineBufferLevel = ob_get_level();

            while (ob_get_level() > $baselineBufferLevel) {
                ob_end_clean();
            }

            return (new Response())->withStringBody('Test Response');
        });

        $worker->run();

        $this->assertCount(1, $client->responses);
        $this->assertSame('Test Response', (string)$client->responses[0]->getBody());
    }

    /**
     * Request handled callbacks observe every handled request.
     *
     * @return void
     */
    public function testRequestHandledCallbacksObserveHandledRequests(): void
    {
        [, $worker] = $this->createStimulusContext([
            new ServerRequest(['url' => '/first']),
        ]);

        $worker->getManager()->route(
            'GET',
            '/first',
            fn(): Response => (new Response())->withStringBody('First Response'),
        );

        $seen = [];

        $worker->onRequestHandled(function ($request, $response, $sandbox) use (&$seen): void {
            $seen[] = [$request, $response, $sandbox];
        });

        $worker->run();

        $this->assertCount(1, $seen);
        $this->assertSame('/first', $seen[0][0]->getPath());
        $this->assertSame('First Response', (string)$seen[0][1]->getBody());
    }

    /**
     * Terminate dispatches the stopping event and resets the request seam.
     *
     * @return void
     */
    public function testTerminateStopsWorker(): void
    {
        [$app, $worker] = $this->createStimulusContext([]);

        $worker->terminate();

        $this->assertSame($app, $worker->application());
        $this->assertNull(CurrentApplication::getRequest());
    }
}
