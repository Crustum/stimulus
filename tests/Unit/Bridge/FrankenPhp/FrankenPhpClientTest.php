<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Bridge\FrankenPhp;

use Cake\Core\Configure;
use Cake\Http\ResponseEmitter;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Bridge\FrankenPhp\FrankenPhpClient;
use Crustum\Stimulus\Http\RequestContext;
use Crustum\Stimulus\Http\StimulusResponse;
use Crustum\Stimulus\Test\TestCase;
use Exception;
use Laminas\Diactoros\Response\TextResponse;
use Mockery;
use Psr\Http\Message\ResponseInterface;
use stdClass;

/**
 * Tests for the FrankenPHP server bridge client.
 */
class FrankenPhpClientTest extends TestCase
{
    /**
     * Debug flag configured before each test.
     *
     * @var bool
     */
    protected bool $debug = true;

    /**
     * Stores the debug flag so error tests can restore it.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->debug = (bool)Configure::read('debug');
    }

    /**
     * Restores the debug flag and closes Mockery expectations.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Configure::write('debug', $this->debug);
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Marshal request captures the worker request from globals.
     *
     * @return void
     */
    public function testMarshalRequest(): void
    {
        $requestContext = new RequestContext();
        $marshaledRequest = (new FrankenPhpClient())->marshalRequest($requestContext);

        $this->assertInstanceOf(ServerRequest::class, $marshaledRequest[0]);
        $this->assertSame($requestContext, $marshaledRequest[1]);
    }

    /**
     * Respond emits the worker response through the emitter.
     *
     * @return void
     */
    public function testResponse(): void
    {
        $emitter = Mockery::mock(ResponseEmitter::class);
        $response = new TextResponse('Hello World', 200);

        $emitter->shouldReceive('emit')->once()->with($response)->andReturn(true);

        (new FrankenPhpClient($emitter))->respond(new RequestContext(), new StimulusResponse($response));
    }

    /**
     * Error emits a generic message outside debug mode.
     *
     * @return void
     */
    public function testErrorSendsGenericMessageOutsideDebugMode(): void
    {
        Configure::write('debug', false);

        $emitter = Mockery::mock(ResponseEmitter::class);
        $captured = null;
        $emitter->shouldReceive('emit')->once()->with(Mockery::type(ResponseInterface::class))
            ->andReturnUsing(function ($response) use (&$captured): true {
                $captured = $response;

                return true;
            });

        (new FrankenPhpClient($emitter))->error(
            new Exception('Something went wrong...'),
            new stdClass(),
            new ServerRequest(),
            new RequestContext(),
        );

        $this->assertInstanceOf(ResponseInterface::class, $captured);
        $this->assertSame(500, $captured->getStatusCode());
        $this->assertSame('Internal server error.', (string)$captured->getBody());
    }

    /**
     * Error emits the exception details in debug mode.
     *
     * @return void
     */
    public function testErrorSendsExceptionDetailsInDebugMode(): void
    {
        Configure::write('debug', true);

        $e = new Exception('Something went wrong...');

        $emitter = Mockery::mock(ResponseEmitter::class);
        $captured = null;
        $emitter->shouldReceive('emit')->once()->with(Mockery::type(ResponseInterface::class))
            ->andReturnUsing(function ($response) use (&$captured): true {
                $captured = $response;

                return true;
            });

        (new FrankenPhpClient($emitter))->error($e, new stdClass(), new ServerRequest(), new RequestContext());

        $this->assertInstanceOf(ResponseInterface::class, $captured);
        $this->assertSame(500, $captured->getStatusCode());
        $this->assertSame((string)$e, (string)$captured->getBody());
    }
}
