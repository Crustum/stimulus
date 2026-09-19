<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Bridge\RoadRunner;

use Cake\Core\Configure;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Bridge\RoadRunner\RoadRunnerClient;
use Crustum\Stimulus\Http\RequestContext;
use Crustum\Stimulus\Http\StimulusResponse;
use Crustum\Stimulus\Test\TestCase;
use Exception;
use Generator;
use InvalidArgumentException;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequestFactory;
use Mockery;
use Psr\Http\Message\ResponseInterface;
use Spiral\RoadRunner\Http\HttpWorker;
use Spiral\RoadRunner\Http\PSR7Worker;
use stdClass;

/**
 * Tests for the RoadRunner server bridge client.
 */
class RoadRunnerClientTest extends TestCase
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
     * Marshal request builds a request from the PSR-7 request.
     *
     * @return void
     */
    public function testMarshalRequestMarshalsCakeRequest(): void
    {
        $client = new RoadRunnerClient(Mockery::mock(PSR7Worker::class));

        $psr7Request = (new ServerRequestFactory())->createServerRequest('GET', '/home');
        $psr7Request = $psr7Request->withQueryParams(['name' => 'Taylor']);

        [$request, $context] = $client->marshalRequest(new RequestContext([
            'psr7Request' => $psr7Request,
        ]));

        $this->assertInstanceOf(ServerRequest::class, $request);
        $this->assertSame('Taylor', $request->getQuery('name'));
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($psr7Request, $context->data['psr7Request']);
    }

    /**
     * Marshal request derives the host from the URI when the server omits it.
     *
     * @return void
     */
    public function testMarshalRequestDerivesHostFromUri(): void
    {
        $client = new RoadRunnerClient(Mockery::mock(PSR7Worker::class));

        $psr7Request = (new ServerRequestFactory())->createServerRequest('GET', 'http://example.test:8080/home');

        [$request,] = $client->marshalRequest(new RequestContext([
            'psr7Request' => $psr7Request,
        ]));

        $this->assertSame('example.test:8080', $request->getEnv('HTTP_HOST'));
        $this->assertSame('example.test:8080', $request->getHeaderLine('Host'));
    }

    /**
     * Marshal request keeps multi-value headers as value lists.
     *
     * @return void
     */
    public function testMarshalRequestCopiesMultiValueHeadersSingly(): void
    {
        $client = new RoadRunnerClient(Mockery::mock(PSR7Worker::class));

        $psr7Request = (new ServerRequestFactory())->createServerRequest('GET', 'http://example.test/home');
        $psr7Request = $psr7Request->withAddedHeader('X-Multi', 'one')->withAddedHeader('X-Multi', 'two');

        [$request,] = $client->marshalRequest(new RequestContext([
            'psr7Request' => $psr7Request,
        ]));

        $this->assertSame(['one', 'two'], $request->getHeader('X-Multi'));
    }

    /**
     * Marshal request trusts proxies behind the worker server.
     *
     * @return void
     */
    public function testMarshalRequestTrustsProxies(): void
    {
        $client = new RoadRunnerClient(Mockery::mock(PSR7Worker::class));

        $psr7Request = (new ServerRequestFactory())->createServerRequest('GET', 'http://example.test/home');

        [$request,] = $client->marshalRequest(new RequestContext([
            'psr7Request' => $psr7Request,
        ]));

        $this->assertTrue($request->trustProxy);
    }

    /**
     * Marshal request parses basic-auth credentials into the environment.
     *
     * @return void
     */
    public function testMarshalRequestParsesBasicAuth(): void
    {
        $client = new RoadRunnerClient(Mockery::mock(PSR7Worker::class));

        $psr7Request = (new ServerRequestFactory())->createServerRequest('GET', 'http://example.test/home');
        $psr7Request = $psr7Request->withHeader('Authorization', 'Basic ' . base64_encode('user:secret'));

        [$request,] = $client->marshalRequest(new RequestContext([
            'psr7Request' => $psr7Request,
        ]));

        $this->assertSame('user', $request->getEnv('PHP_AUTH_USER'));
        $this->assertSame('secret', $request->getEnv('PHP_AUTH_PW'));
    }

    /**
     * Respond sends pending cookies as Set-Cookie headers.
     *
     * @return void
     */
    public function testRespondMethodSendsCakeCookiesAsSetCookieHeaders(): void
    {
        $worker = Mockery::mock(PSR7Worker::class);
        $client = new RoadRunnerClient($worker);

        $captured = null;
        $worker->shouldReceive('respond')->once()->with(Mockery::type(ResponseInterface::class))
            ->andReturnUsing(function ($response) use (&$captured): void {
                $captured = $response;
            });

        $response = (new Response())
            ->withStringBody('Hello World')
            ->withCookie(Cookie::create('session', 'abc'));

        $client->respond(new RequestContext(), new StimulusResponse($response));

        $this->assertInstanceOf(ResponseInterface::class, $captured);
        $this->assertStringContainsString('session=abc', $captured->getHeaderLine('Set-Cookie'));
    }

    /**
     * Marshal request rejects contexts without a PSR-7 request.
     *
     * @return void
     */
    public function testMarshalRequestRejectsContextWithoutPsr7Request(): void
    {
        $client = new RoadRunnerClient(Mockery::mock(PSR7Worker::class));

        $this->expectException(InvalidArgumentException::class);

        $client->marshalRequest(new RequestContext());
    }

    /**
     * Respond sends the worker response to RoadRunner.
     *
     * @return void
     */
    public function testRespondMethodSendsResponseToRoadRunner(): void
    {
        $worker = Mockery::mock(PSR7Worker::class);
        $client = new RoadRunnerClient($worker);

        $worker->shouldReceive('respond')->once()->with(Mockery::type(ResponseInterface::class));

        $client->respond(new RequestContext(), new StimulusResponse(new TextResponse('Hello World', 200)));
    }

    /**
     * Respond prepends buffered output to the worker response body.
     *
     * @return void
     */
    public function testRespondMethodPrependsOutputBuffer(): void
    {
        $worker = Mockery::mock(PSR7Worker::class);
        $client = new RoadRunnerClient($worker);

        $captured = null;
        $worker->shouldReceive('respond')->once()->with(Mockery::type(ResponseInterface::class))
            ->andReturnUsing(function ($response) use (&$captured): void {
                $captured = $response;
            });

        $client->respond(
            new RequestContext(),
            new StimulusResponse(new TextResponse('Hello World', 200), 'Buffered '),
        );

        $this->assertInstanceOf(ResponseInterface::class, $captured);
        $this->assertSame('Buffered Hello World', (string)$captured->getBody());
    }

    /**
     * Respond sends envelopes without a chunk producer buffered.
     *
     * @return void
     */
    public function testRespondMethodSendsBufferedResponseToRoadRunner(): void
    {
        $worker = Mockery::mock(PSR7Worker::class);
        $client = new RoadRunnerClient($worker);

        $worker->shouldReceive('respond')->once()->with(Mockery::type(ResponseInterface::class));

        $client->respond(
            new RequestContext(),
            new StimulusResponse(new TextResponse('Hello World', 200)),
        );
    }

    /**
     * Respond streams envelope chunk producers through the HTTP worker.
     *
     * Covers the generator behavior with mocked workers — no live server is
     * involved. The worker loop attaches the producer when the application
     * answered with a callback-streamed body.
     *
     * @return void
     */
    public function testRespondMethodSendsStreamedGeneratorResponseToRoadRunner(): void
    {
        $worker = Mockery::mock(PSR7Worker::class);
        $client = new RoadRunnerClient($worker);

        $httpWorker = Mockery::mock(HttpWorker::class);

        $worker->shouldReceive('getHttpWorker')->once()->andReturn($httpWorker);
        $httpWorker->shouldReceive('respond')->once()->with(
            200,
            Mockery::type(Generator::class),
            Mockery::hasKey('cache-control'),
        );

        $producer = static function (): Generator {
            yield 'Hello World';
        };

        $client->respond(
            new RequestContext(),
            new StimulusResponse(
                new TextResponse('Hello World', 200, ['cache-control' => 'no-cache']),
                null,
                $producer,
            ),
        );
    }

    /**
     * Error sends a generic message to RoadRunner outside debug mode.
     *
     * @return void
     */
    public function testErrorMethodSendsErrorResponseToRoadRunner(): void
    {
        $worker = Mockery::mock(PSR7Worker::class);
        $worker->shouldReceive('getWorker->error')->once()->with('Internal server error.');

        $client = new RoadRunnerClient($worker);

        Configure::write('debug', false);

        $client->error(new Exception('Something went wrong...'), new stdClass(), new ServerRequest(), new RequestContext());
    }

    /**
     * Error sends the exception details to RoadRunner in debug mode.
     *
     * @return void
     */
    public function testErrorMethodSendsDetailedErrorResponseToRoadRunnerInDebugMode(): void
    {
        $e = new Exception('Something went wrong...');

        $worker = Mockery::mock(PSR7Worker::class);
        $worker->shouldReceive('getWorker->error')->once()->with((string)$e);

        $client = new RoadRunnerClient($worker);

        Configure::write('debug', true);

        $client->error($e, new stdClass(), new ServerRequest(), new RequestContext());
    }

    /**
     * Stop halts the underlying RoadRunner worker.
     *
     * @return void
     */
    public function testStopHaltsUnderlyingWorker(): void
    {
        $worker = Mockery::mock(PSR7Worker::class);
        $worker->shouldReceive('getWorker->stop')->once();

        (new RoadRunnerClient($worker))->stop();
    }
}
