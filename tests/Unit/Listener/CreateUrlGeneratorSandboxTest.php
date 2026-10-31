<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\CreateUrlGeneratorSandbox;
use Crustum\Stimulus\Test\TestCase;
use ReflectionProperty;
use stdClass;

/**
 * Tests for the URL generator sandbox listener.
 */
class CreateUrlGeneratorSandboxTest extends TestCase
{
    /**
     * URL generation follows the fresh request instead of the first one.
     *
     * @return void
     */
    public function testUrlGenerationFollowsFreshRequest(): void
    {
        $requestProperty = new ReflectionProperty(Router::class, 'request');
        $contextProperty = new ReflectionProperty(Router::class, 'requestContext');
        $originalRequest = $requestProperty->getValue();
        $originalContext = $contextProperty->getValue();

        try {
            $first = new ServerRequest([
                'url' => '/first',
                'environment' => ['HTTP_HOST' => 'first.test', 'REQUEST_URI' => '/first'],
            ]);
            Router::setRequest($first);

            $second = new ServerRequest([
                'url' => '/second',
                'environment' => [
                    'HTTP_HOST' => 'second.test',
                    'REQUEST_URI' => '/second',
                    'HTTPS' => 'on',
                ],
            ]);

            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), $second);

            (new CreateUrlGeneratorSandbox())->handleRequestReceived($event);

            $this->assertSame($second, Router::getRequest());

            $context = $contextProperty->getValue();
            $this->assertIsArray($context);
            $this->assertSame('https', $context['_scheme']);
            $this->assertSame('second.test', $context['_host']);
        } finally {
            $requestProperty->setValue(null, $originalRequest);
            $contextProperty->setValue(null, $originalContext);
        }
    }
}
