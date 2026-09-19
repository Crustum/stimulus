<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Cache\Cache;
use Cake\Http\ServerRequest;
use Cake\I18n\I18n;
use Cake\I18n\Translator;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\FlushTranslatorCache;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the translator cache flushing listener.
 *
 * The translator registry is dropped and parsed translations are cleared
 * while translation itself keeps working.
 */
class FlushTranslatorCacheTest extends TestCase
{
    /**
     * Parsed translation keys and translators are flushed.
     *
     * @return void
     */
    public function testParsedKeysCacheIsFlushed(): void
    {
        Cache::write('stimulus_probe_key', 'value', '_cake_translations_');
        I18n::getTranslator();

        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

        (new FlushTranslatorCache())->handleRequestReceived($event);

        $this->assertNull(Cache::read('stimulus_probe_key', '_cake_translations_'));
        $this->assertInstanceOf(Translator::class, I18n::getTranslator());
    }
}
