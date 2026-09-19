<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Cache\Cache;
use Cake\Cache\Engine\ArrayEngine;
use Cake\Http\ServerRequest;
use Cake\Http\Session\CacheSession;
use Cake\Http\Session\DatabaseSession;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToDatabaseSessionHandler;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the session engine handler refreshing listener.
 *
 * Covers the database session handler scenario: database and cache handlers
 * are refreshed per sandbox while native handlers pass through.
 */
class GiveNewApplicationInstanceToDatabaseSessionHandlerTest extends TestCase
{
    /**
     * Database handlers rebuild against the fresh sandbox connection.
     *
     * @return void
     */
    public function testDatabaseHandlerIsRebuilt(): void
    {
        $request = new ServerRequest(['url' => '/']);
        $request->getSession()->engine(new DatabaseSession());

        $before = $request->getSession()->engine();
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), $request);

        (new GiveNewApplicationInstanceToDatabaseSessionHandler())->handleRequestReceived($event);

        $after = $request->getSession()->engine();

        $this->assertInstanceOf(DatabaseSession::class, $after);
        $this->assertNotSame($before, $after);
    }

    /**
     * Cache handlers are replaced with a sandbox-owned instance.
     *
     * @return void
     */
    public function testCacheHandlerIsReplaced(): void
    {
        Cache::setConfig('stimulus_test_session', ['className' => ArrayEngine::class]);

        try {
            $request = new ServerRequest(['url' => '/']);
            $request->getSession()->engine(new CacheSession(['config' => 'stimulus_test_session']));

            $before = $request->getSession()->engine();
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), $request);

            (new GiveNewApplicationInstanceToDatabaseSessionHandler())->handleRequestReceived($event);

            $after = $request->getSession()->engine();

            $this->assertInstanceOf(CacheSession::class, $after);
            $this->assertNotSame($before, $after);
        } finally {
            Cache::drop('stimulus_test_session');
        }
    }

    /**
     * Sessions without an engine pass through untouched.
     *
     * @return void
     */
    public function testMissingEnginePassesThrough(): void
    {
        $request = new ServerRequest(['url' => '/']);
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), $request);

        (new GiveNewApplicationInstanceToDatabaseSessionHandler())->handleRequestReceived($event);

        $this->assertNull($request->getSession()->engine());
    }
}
