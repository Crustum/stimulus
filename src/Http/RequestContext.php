<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Http;

use ArrayAccess;
use ReturnTypeWillChange;

/**
 * Value object carrying the raw request payload handed to the worker client.
 *
 * Behaves like an array and exposes its entries as virtual properties so
 * server bridges can pass arbitrary context alongside the request.
 *
 * @implements \ArrayAccess<string, mixed>
 */
class RequestContext implements ArrayAccess
{
    /**
     * Creates a new request context.
     *
     * @param array<string, mixed> $data Context entries.
     */
    public function __construct(public array $data = [])
    {
    }

    /**
     * Determines whether the given context entry exists.
     *
     * @param mixed $offset Entry key.
     * @return bool Whether the entry exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    /**
     * Returns the context entry for the given key.
     *
     * @param mixed $offset Entry key.
     * @return mixed Entry value.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset];
    }

    /**
     * Sets the context entry for the given key.
     *
     * @param mixed $offset Entry key.
     * @param mixed $value Entry value.
     * @return void
     */
    #[ReturnTypeWillChange]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    /**
     * Removes the context entry for the given key.
     *
     * @param mixed $offset Entry key.
     * @return void
     */
    #[ReturnTypeWillChange]
    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * Returns the context entry for the given key.
     *
     * @param string $key Entry key.
     * @return mixed Entry value.
     */
    public function __get(string $key): mixed
    {
        return $this->data[$key];
    }

    /**
     * Sets the context entry for the given key.
     *
     * @param string $key Entry key.
     * @param mixed $value Entry value.
     * @return void
     */
    public function __set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }
}
