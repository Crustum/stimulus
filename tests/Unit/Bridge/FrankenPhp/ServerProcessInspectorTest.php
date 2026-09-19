<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Bridge\FrankenPhp;

use Cake\Http\Client;
use Cake\Http\Client\Exception\NetworkException;
use Cake\Http\Client\Request as ClientRequest;
use Cake\Http\Client\Response as ClientResponse;
use Crustum\Stimulus\Bridge\FrankenPhp\ServerProcessInspector;
use Crustum\Stimulus\Bridge\FrankenPhp\ServerStateFile;
use Crustum\Stimulus\Test\TestCase;
use Mockery;

/**
 * Tests for the FrankenPHP server process inspector.
 *
 * Covers the inspector against the HTTP client.
 */
class ServerProcessInspectorTest extends TestCase
{
    /**
     * Closes Mockery expectations after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Inspector reports the server stopped without a recorded process.
     *
     * @return void
     */
    public function testServerIsNotRunningWithoutMasterProcessId(): void
    {
        $http = Mockery::mock(Client::class);
        $http->shouldNotReceive('get');

        $inspector = new ServerProcessInspector(
            $stateFile = new ServerStateFile(sys_get_temp_dir() . '/stimulus-frankenphp-inspector-test.json'),
            $http,
        );

        $stateFile->delete();

        $this->assertFalse($inspector->serverIsRunning());
    }

    /**
     * Inspector reports the server running when the admin endpoint answers.
     *
     * @return void
     */
    public function testServerIsRunningWhenAdminEndpointAnswers(): void
    {
        $http = Mockery::mock(Client::class);
        $http->shouldReceive('get')->once()
            ->with('http://localhost:2019/config/apps/frankenphp')
            ->andReturn(new ClientResponse(['HTTP/1.1 200 OK'], '{"apps":{}}'));

        $inspector = new ServerProcessInspector(
            $stateFile = new ServerStateFile(sys_get_temp_dir() . '/stimulus-frankenphp-inspector-test.json'),
            $http,
        );

        $stateFile->writeProcessId(1);

        $this->assertTrue($inspector->serverIsRunning());

        $stateFile->delete();
    }

    /**
     * Inspector reports the server stopped when the admin endpoint fails.
     *
     * @return void
     */
    public function testServerIsNotRunningWhenAdminEndpointFails(): void
    {
        $http = Mockery::mock(Client::class);
        $http->shouldReceive('get')->once()
            ->with('http://localhost:2019/config/apps/frankenphp')
            ->andReturn(new ClientResponse(['HTTP/1.1 500 Internal Server Error'], ''));

        $inspector = new ServerProcessInspector(
            $stateFile = new ServerStateFile(sys_get_temp_dir() . '/stimulus-frankenphp-inspector-test.json'),
            $http,
        );

        $stateFile->writeProcessId(1);

        $this->assertFalse($inspector->serverIsRunning());

        $stateFile->delete();
    }

    /**
     * Inspector reports the server stopped when the endpoint is unreachable.
     *
     * @return void
     */
    public function testServerIsNotRunningWhenAdminEndpointIsUnreachable(): void
    {
        $http = Mockery::mock(Client::class);
        $http->shouldReceive('get')->once()->andThrow(
            new NetworkException(
                'Connection refused',
                new ClientRequest('http://localhost:2019/config/apps/frankenphp'),
            ),
        );

        $inspector = new ServerProcessInspector(
            $stateFile = new ServerStateFile(sys_get_temp_dir() . '/stimulus-frankenphp-inspector-test.json'),
            $http,
        );

        $stateFile->writeProcessId(1);

        $this->assertFalse($inspector->serverIsRunning());

        $stateFile->delete();
    }

    /**
     * Inspector reads custom admin connection details from the state file.
     *
     * @return void
     */
    public function testServerReadsAdminConnectionDetailsFromStateFile(): void
    {
        $http = Mockery::mock(Client::class);
        $http->shouldReceive('get')->once()
            ->with('http://127.0.0.1:2020/config/apps/frankenphp')
            ->andReturn(new ClientResponse(['HTTP/1.1 200 OK'], '{"apps":{}}'));

        $inspector = new ServerProcessInspector(
            $stateFile = new ServerStateFile(sys_get_temp_dir() . '/stimulus-frankenphp-inspector-test.json'),
            $http,
        );

        $stateFile->writeProcessId(1);
        $stateFile->writeState(['adminHost' => '127.0.0.1', 'adminPort' => 2020]);

        $this->assertTrue($inspector->serverIsRunning());

        $stateFile->delete();
    }

    /**
     * Inspector reloads the workers through the admin configuration endpoint.
     *
     * @return void
     */
    public function testReloadServerReappliesAdminConfiguration(): void
    {
        $http = Mockery::mock(Client::class);
        $http->shouldReceive('get')->once()
            ->with('http://localhost:2019/config/apps/frankenphp')
            ->andReturn(new ClientResponse(['HTTP/1.1 200 OK'], '{"apps":{}}'));
        $http->shouldReceive('patch')->once()->with(
            'http://localhost:2019/config/apps/frankenphp',
            '{"apps":{}}',
            [
                'headers' => [
                    'Cache-Control' => 'must-revalidate',
                    'Content-Type' => 'application/json',
                ],
            ],
        )->andReturn(new ClientResponse(['HTTP/1.1 200 OK'], ''));

        $inspector = new ServerProcessInspector(
            $stateFile = new ServerStateFile(sys_get_temp_dir() . '/stimulus-frankenphp-inspector-test.json'),
            $http,
        );

        $inspector->reloadServer();

        $stateFile->delete();
    }

    /**
     * Inspector tolerates an unreachable endpoint while reloading.
     *
     * @return void
     */
    public function testReloadServerToleratesUnreachableEndpoint(): void
    {
        $http = Mockery::mock(Client::class);
        $http->shouldReceive('get')->once()->andThrow(
            new NetworkException(
                'Connection refused',
                new ClientRequest('http://localhost:2019/config/apps/frankenphp'),
            ),
        );
        $http->shouldNotReceive('patch');

        $inspector = new ServerProcessInspector(
            $stateFile = new ServerStateFile(sys_get_temp_dir() . '/stimulus-frankenphp-inspector-test.json'),
            $http,
        );

        $inspector->reloadServer();

        $this->assertTrue(true);

        $stateFile->delete();
    }

    /**
     * Inspector stops the server through the admin endpoint.
     *
     * @return void
     */
    public function testStopServerPostsToAdminEndpoint(): void
    {
        $http = Mockery::mock(Client::class);
        $http->shouldReceive('post')->once()
            ->with('http://localhost:2019/stop')
            ->andReturn(new ClientResponse(['HTTP/1.1 200 OK'], ''));

        $inspector = new ServerProcessInspector(
            $stateFile = new ServerStateFile(sys_get_temp_dir() . '/stimulus-frankenphp-inspector-test.json'),
            $http,
        );

        $this->assertTrue($inspector->stopServer());

        $stateFile->delete();
    }

    /**
     * Inspector reports a failed stop when the endpoint is unreachable.
     *
     * @return void
     */
    public function testStopServerReturnsFalseWhenEndpointIsUnreachable(): void
    {
        $http = Mockery::mock(Client::class);
        $http->shouldReceive('post')->once()->andThrow(
            new NetworkException('Connection refused', new ClientRequest('http://localhost:2019/stop')),
        );

        $inspector = new ServerProcessInspector(
            $stateFile = new ServerStateFile(sys_get_temp_dir() . '/stimulus-frankenphp-inspector-test.json'),
            $http,
        );

        $this->assertFalse($inspector->stopServer());

        $stateFile->delete();
    }
}
