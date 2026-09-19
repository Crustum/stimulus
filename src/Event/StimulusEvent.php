<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Event;

use Cake\Event\Event;
use ReflectionClass;

/**
 * Base class for Stimulus worker events.
 *
 * Carries the event payload in the event data so listeners read
 * context through `getData()` and the typed getters, following the sibling
 * Monitor event style. Subclasses keep their promoted typed properties for
 * direct access and pass the same payload to this constructor, so the public
 * properties and the event data stay in sync at construction. Getters read
 * the promoted properties so in-flight mutation through setters stays
 * coherent with the event data, which setters update alongside.
 *
 * @extends \Cake\Event\Event<object>
 */
abstract class StimulusEvent extends Event
{
    /**
     * Creates a new Stimulus event.
     *
     * @param array<string, mixed> $data Event payload transported with the event.
     */
    public function __construct(array $data = [])
    {
        parent::__construct(static::eventName(), null, $data);
    }

    /**
     * Gets the name of the event.
     *
     * The name derives from the short class name via reflection as
     * `'Stimulus.' . lcfirst(name without the `Event` suffix)`, which keeps
     * the historical `Stimulus.*` strings without hardcoding them per class.
     *
     * @return string Name of the event.
     */
    public static function eventName(): string
    {
        $shortName = (new ReflectionClass(static::class))->getShortName();

        if (str_ends_with($shortName, 'Event')) {
            $shortName = substr($shortName, 0, -strlen('Event'));
        }

        return 'Stimulus.' . lcfirst($shortName);
    }
}
