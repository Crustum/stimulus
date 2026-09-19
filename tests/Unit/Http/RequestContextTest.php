<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Http;

use ArrayAccess;
use Crustum\Stimulus\Http\RequestContext;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the worker request context value object.
 */
class RequestContextTest extends TestCase
{
    /**
     * Context exposes entries as array offsets and virtual properties.
     *
     * @return void
     */
    public function testContextExposesEntries(): void
    {
        $context = new RequestContext(['request' => 'payload']);

        $this->assertInstanceOf(ArrayAccess::class, $context);
        $this->assertTrue(isset($context['request']));
        $this->assertSame('payload', $context['request']);
        $this->assertSame('payload', $context->request);
    }

    /**
     * Context entries can be written and removed.
     *
     * @return void
     */
    public function testContextEntriesCanBeWrittenAndRemoved(): void
    {
        $context = new RequestContext();

        $context['key'] = 'value';
        $context->other = 'data';

        $this->assertSame('value', $context['key']);
        $this->assertSame('data', $context['other']);

        unset($context['key']);

        $this->assertFalse(isset($context['key']));
    }

    /**
     * Context defaults to an empty data set.
     *
     * @return void
     */
    public function testContextDefaultsToEmptyData(): void
    {
        $context = new RequestContext();

        $this->assertSame([], $context->data);
    }
}
