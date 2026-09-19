<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Worker;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for request state isolation between worker operations.
 *
 * The worker points the current-application seam at each received
 * request and dispatches through a fresh sandbox clone, so consecutive
 * operations observe their own request.
 */
class RequestStateTest extends TestCase
{
    /**
     * Each operation observes its own rebound request.
     *
     * @return void
     */
    public function testRequestIsReboundOnSandbox(): void
    {
        [, $worker, $client] = $this->createStimulusContext([
            new ServerRequest(['url' => '/first?name=Taylor']),
            new ServerRequest(['url' => '/first?name=Abigail']),
        ]);

        $worker->getManager()->route(
            'GET',
            '/first',
            fn(ServerRequest $request): Response => (new Response())->withStringBody(
                (string)$request->getQuery('name'),
            ),
        );

        $worker->run();

        $this->assertSame('Taylor', (string)$client->responses[0]->getBody());
        $this->assertSame('Abigail', (string)$client->responses[1]->getBody());
    }

    /**
     * Controllers do not leak state across operations.
     *
     * Controllers are per-request instances built inside the sandbox
     * handle, so two operations through the real application handle both
     * answer the first invocation count.
     *
     * @return void
     */
    public function testRequestRoutesControllerDoesNotLeak(): void
    {
        try {
            [, $worker, $client] = $this->createStimulusContext([
                new ServerRequest([
                    'url' => '/probe',
                    'params' => [
                        'plugin' => null,
                        'controller' => 'Probe',
                        'action' => 'index',
                        'pass' => [],
                    ],
                ]),
                new ServerRequest([
                    'url' => '/probe',
                    'params' => [
                        'plugin' => null,
                        'controller' => 'Probe',
                        'action' => 'index',
                        'pass' => [],
                    ],
                ]),
            ]);

            $worker->run();

            $this->assertCount(2, $client->responses);
            $this->assertSame('probe:1', (string)$client->responses[0]->getBody());
            $this->assertSame('probe:1', (string)$client->responses[1]->getBody());
        } finally {
            Router::reload();
        }
    }
}
