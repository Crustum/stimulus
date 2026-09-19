<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit;

use Crustum\Stimulus\Test\Support\FakeClient;
use Crustum\Stimulus\Test\Support\FakeWorker;
use Crustum\Stimulus\Test\TestCase;
use TestApp\Application;

/**
 * Tests for the default Stimulus test case.
 */
class TestCaseTest extends TestCase
{
    /**
     * The context helper returns the booted application, worker, and client triple.
     *
     * @return void
     */
    public function testCreateStimulusContextReturnsTriple(): void
    {
        [$app, $worker, $client] = $this->createStimulusContext(['first']);

        $this->assertInstanceOf(Application::class, $app);
        $this->assertInstanceOf(FakeWorker::class, $worker);
        $this->assertInstanceOf(FakeClient::class, $client);
        $this->assertSame(['first'], $client->requests);
    }

    /**
     * The config helper loads the plugin configuration file.
     *
     * @return void
     */
    public function testConfigLoadsPluginConfiguration(): void
    {
        $config = $this->config();

        $this->assertArrayHasKey('Stimulus', $config);
    }
}
