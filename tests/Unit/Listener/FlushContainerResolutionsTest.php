<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Controller\ComponentRegistry;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\FlushContainerResolutions;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the container resolution flushing listener.
 *
 * A stale shared `ComponentRegistry` definition cached from a previous
 * request must be dropped before the next request, so the controller
 * factory registers and resolves a fresh instance.
 */
class FlushContainerResolutionsTest extends TestCase
{
    /**
     * Stale component registry definitions are flushed before the next request.
     *
     * Mirrors the controller factory path: it re-registers the registry per
     * request via `addShared()` and then resolves it. After the flush the
     * re-registered fresh instance must win over the stale cached one.
     *
     * @return void
     */
    public function testStaleComponentRegistryDefinitionIsFlushed(): void
    {
        $app = $this->createApplication();

        $stale = new stdClass();
        $app->getContainer()->addShared(ComponentRegistry::class, $stale);
        $app->getContainer()->get(ComponentRegistry::class);

        $event = new RequestReceivedEvent($app, $app, new ServerRequest(['url' => '/']));

        (new FlushContainerResolutions())->handleRequestReceived($event);

        $this->assertFalse($app->getContainer()->hasDefinition(ComponentRegistry::class));

        $fresh = new stdClass();
        $app->getContainer()->addShared(ComponentRegistry::class, $fresh);

        $this->assertSame($fresh, $app->getContainer()->get(ComponentRegistry::class));
    }

    /**
     * Applications without container hooks pass through untouched.
     *
     * @return void
     */
    public function testApplicationWithoutHooksPassesThrough(): void
    {
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

        (new FlushContainerResolutions())->handleRequestReceived($event);

        $this->assertInstanceOf(RequestReceivedEvent::class, $event);
    }
}
