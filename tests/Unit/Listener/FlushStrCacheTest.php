<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Http\ServerRequest;
use Cake\Utility\Inflector;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\FlushStrCache;
use Crustum\Stimulus\Test\TestCase;
use ReflectionProperty;
use stdClass;

/**
 * Tests for the string cache flushing listener.
 *
 * The inflector memoization populated during one request is empty again
 * afterwards.
 */
class FlushStrCacheTest extends TestCase
{
    /**
     * Inflector caches are reset.
     *
     * @return void
     */
    public function testStrCacheIsFlushed(): void
    {
        $this->assertSame('oxen', Inflector::pluralize('ox'));

        $property = new ReflectionProperty(Inflector::class, 'cache');
        $this->assertNotEmpty($property->getValue());

        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

        (new FlushStrCache())->handleRequestReceived($event);

        $this->assertEmpty($property->getValue());
        $this->assertSame('oxen', Inflector::pluralize('ox'));
    }
}
