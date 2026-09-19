<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Support;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Contracts\Worker as WorkerContract;
use Crustum\Stimulus\Exception\DdException;
use Crustum\Stimulus\Exception\TaskException;
use Crustum\Stimulus\Exception\TaskExceptionResult;
use Crustum\Stimulus\Test\Support\FakeClient;
use Crustum\Stimulus\Test\Support\FakeWorker;
use Crustum\Stimulus\Test\TestCase;
use Exception;

/**
 * Tests for the fake worker test double.
 *
 * The fake extends the real worker, so replays exercise the production boot,
 * sandbox, gateway, and event paths with the fake client feeding raw entries.
 */
class FakeWorkerTest extends TestCase
{
    /**
     * The fake worker extends the real worker.
     *
     * @return void
     */
    public function testExtendsRealWorker(): void
    {
        [, $worker] = $this->createStimulusContext([]);

        $this->assertInstanceOf(WorkerContract::class, $worker);
    }

    /**
     * Run replays requests through the manager routes and records responses.
     *
     * @return void
     */
    public function testRunReplaysRequests(): void
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

        $this->assertCount(2, $client->responses);
        $this->assertSame(2, $client->index);
        $this->assertSame('First Response', (string)$client->responses[0]->getBody());
        $this->assertSame('Second Response', (string)$client->responses[1]->getBody());
        $this->assertSame($app, $worker->application());
    }

    /**
     * Run tasks invokes every payload and returns keyed results.
     *
     * @return void
     */
    public function testRunTasks(): void
    {
        $taskWorker = new FakeWorker($this->appFactory(), new FakeClient([
            'first' => fn(): string => 'foo',
            'second' => fn(): string => 'bar',
            'third' => function (): void {
            },
        ]));

        $taskWorker->boot();

        $this->assertSame(['first' => 'foo', 'second' => 'bar', 'third' => null], $taskWorker->runTasks());
    }

    /**
     * Run tasks captures failures as exception snapshots.
     *
     * @return void
     */
    public function testRunTasksCapturesFailures(): void
    {
        $worker = new FakeWorker($this->appFactory(), new FakeClient([
            'first' => function (): void {
                throw new Exception('Something went wrong.');
            },
        ]));

        $worker->boot();

        $results = $worker->runTasks();

        $this->assertInstanceOf(TaskExceptionResult::class, $results['first']);

        $original = $results['first']->getOriginal();

        $this->assertInstanceOf(TaskException::class, $original);
        $this->assertSame('Something went wrong.', $original->getMessage());
    }

    /**
     * Run tasks captures dump-and-die payloads as dump exceptions.
     *
     * @return void
     */
    public function testRunTasksCapturesDdCalls(): void
    {
        $worker = new FakeWorker($this->appFactory(), new FakeClient([
            'first' => function (): void {
                throw new DdException(['foo' => 'bar']);
            },
        ]));

        $worker->boot();

        $results = $worker->runTasks();

        $this->assertInstanceOf(DdException::class, $results['first']->getOriginal());
    }

    /**
     * Run ticks replays one tick per client entry.
     *
     * @return void
     */
    public function testRunTicks(): void
    {
        $worker = new FakeWorker($this->appFactory(), new FakeClient([null, null]));

        $worker->boot();

        $this->assertSame([null, null], $worker->runTicks());
    }
}
