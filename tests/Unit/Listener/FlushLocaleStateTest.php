<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\I18n\I18n;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\FlushLocaleState;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the locale state flushing listener.
 *
 * Covers the worker locale state scenario: a locale switched mid-request is
 * restored to the configured default afterwards.
 */
class FlushLocaleStateTest extends TestCase
{
    /**
     * A switched locale is restored to the configured default.
     *
     * @return void
     */
    public function testLocaleIsRestoredToDefault(): void
    {
        $previous = I18n::getLocale();
        Configure::write('App.defaultLocale', 'fr_FR');

        try {
            I18n::setLocale('de_DE');

            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new FlushLocaleState())->handleRequestReceived($event);

            $this->assertSame('fr_FR', I18n::getLocale());
        } finally {
            Configure::delete('App.defaultLocale');
            I18n::setLocale($previous);
        }
    }
}
