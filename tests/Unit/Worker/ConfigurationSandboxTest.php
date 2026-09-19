<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Worker;

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Test\TestCase;
use Exception;

/**
 * Tests for the worker configuration sandbox.
 *
 * Configuration lives in process-global static state, so the sandbox
 * listener snapshots it per operation and restores it on termination. The
 * worker-level scenario resolves the listener unit-test deferral.
 */
class ConfigurationSandboxTest extends TestCase
{
    /**
     * Configuration writes during one request never leak into the next.
     *
     * @return void
     */
    public function testConfigIsResetBetweenRequests(): void
    {
        [, $worker, $client] = $this->createStimulusContext([
            new ServerRequest(['url' => '/first']),
            new ServerRequest(['url' => '/second']),
        ]);

        Configure::write('Stimulus.probe_config', 'original');

        try {
            $worker->getManager()->route('GET', '/first', function (): Response {
                Configure::write('Stimulus.probe_config', 'changed');

                return (new Response())->withStringBody('first');
            });
            $worker->getManager()->route(
                'GET',
                '/second',
                fn(): Response => (new Response())->withStringBody((string)Configure::read('Stimulus.probe_config')),
            );

            $worker->run();

            $this->assertSame('first', (string)$client->responses[0]->getBody());
            $this->assertSame('original', (string)$client->responses[1]->getBody());
            $this->assertSame('original', Configure::read('Stimulus.probe_config'));
        } finally {
            Configure::delete('Stimulus.probe_config');
        }
    }

    /**
     * Configuration writes during a failed request never leak into the next.
     *
     * @return void
     */
    public function testConfigIsResetAfterFailedRequests(): void
    {
        [, $worker, $client] = $this->createStimulusContext([
            new ServerRequest(['url' => '/first']),
            new ServerRequest(['url' => '/second']),
        ]);

        Configure::write('Stimulus.probe_config', 'original');

        try {
            $worker->getManager()->route('GET', '/first', function (): Response {
                Configure::write('Stimulus.probe_config', 'changed');

                throw new Exception('Something went wrong.');
            });
            $worker->getManager()->route(
                'GET',
                '/second',
                fn(): Response => (new Response())->withStringBody((string)Configure::read('Stimulus.probe_config')),
            );

            $worker->run();

            $this->assertCount(1, $client->errors);
            $this->assertSame('original', (string)$client->responses[0]->getBody());
            $this->assertSame('original', Configure::read('Stimulus.probe_config'));
        } finally {
            Configure::delete('Stimulus.probe_config');
        }
    }
}
