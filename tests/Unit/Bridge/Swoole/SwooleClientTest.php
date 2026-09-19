<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Bridge\Swoole;

use Cake\Http\Cookie\Cookie;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Bridge\Swoole\SwooleClient;
use Crustum\Stimulus\Http\RequestContext;
use Crustum\Stimulus\Http\StimulusResponse;
use Crustum\Stimulus\Test\Support\FakeSwooleRequest;
use Crustum\Stimulus\Test\Support\FakeSwooleResponse;
use Crustum\Stimulus\Test\TestCase;
use Exception;
use stdClass;

/**
 * Tests for the Swoole bridge client.
 *
 * Covers requests and PSR-7 worker responses: request marshalling,
 * static file detection and serving, buffered, chunked, and streamed
 * responses, non-standard status reasons, and error reporting. Swoole
 * responses arrive as in-memory fakes so the suite loads without the
 * extension; every test skips cleanly where the extension is absent.
 */
class SwooleClientTest extends TestCase
{
    /**
     * Temporary paths removed after each test.
     *
     * @var list<string>
     */
    protected array $temporaryPaths = [];

    /**
     * Requires the Swoole extension before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('swoole') && !extension_loaded('openswoole')) {
            $this->markTestSkipped('The Swoole or OpenSwoole extension is not installed on this host.');
        }
    }

    /**
     * Removes temporary paths after each test.
     *
     * Files unlink before directories remove, since extra fixtures may join
     * the tracked paths after the public directory itself.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        foreach ($this->temporaryPaths as $path) {
            if (is_dir($path)) {
                rmdir($path);
            }
        }

        $this->temporaryPaths = [];

        parent::tearDown();
    }

    /**
     * Marshalled contexts carry the request and the given context.
     *
     * @return void
     */
    public function testMarshalRequestMarshalsCakeRequest(): void
    {
        $client = new SwooleClient();

        $swooleRequest = new FakeSwooleRequest();

        $swooleRequest->get = ['name' => 'Taylor'];
        $swooleRequest->cookie = ['color' => 'blue'];
        $swooleRequest->server = [
            'HTTP_HOST' => 'localhost',
            'PATH_INFO' => '/foo/bar',
            'REMOTE_ADDR' => '127.0.0.1',
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/foo/bar',
            'QUERY_STRING' => 'name=Taylor',
        ];
        $swooleRequest->content = 'Hello World';

        [$request, $context] = $client->marshalRequest($givenContext = new RequestContext([
            'swooleRequest' => $swooleRequest,
        ]));

        $this->assertInstanceOf(ServerRequest::class, $request);
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('Hello World', (string)$request->getBody());
        $this->assertSame('/foo/bar', $request->getUri()->getPath());
        $this->assertSame('Taylor', $request->getQuery('name'));
        $this->assertSame('blue', $request->getCookie('color'));
        $this->assertSame($givenContext, $context);
    }

    /**
     * Static files serve when configured and inside the public directory.
     *
     * @return void
     */
    public function testCanServeStaticFilesIfConfiguredToAndFileIsWithinPublicDirectory(): void
    {
        $client = new SwooleClient();

        $context = new RequestContext([
            'publicPath' => $this->publicPath(),
            'stimulusConfig' => [],
        ]);

        $this->assertTrue($client->canServeRequestAsStaticFile(
            new ServerRequest(['url' => '/foo.txt']),
            $context,
        ));
    }

    /**
     * Static files serve when explicitly enabled.
     *
     * @return void
     */
    public function testCanServeStaticFilesWhenExplicitlyEnabled(): void
    {
        $client = new SwooleClient();

        $context = new RequestContext([
            'publicPath' => $this->publicPath(),
            'stimulusConfig' => ['serve_static_files' => true],
        ]);

        $this->assertTrue($client->canServeRequestAsStaticFile(
            new ServerRequest(['url' => '/foo.txt']),
            $context,
        ));
    }

    /**
     * Static files stay unserved when disabled.
     *
     * @return void
     */
    public function testCantServeStaticFilesWhenDisabled(): void
    {
        $client = new SwooleClient();

        $context = new RequestContext([
            'publicPath' => $this->publicPath(),
            'stimulusConfig' => ['serve_static_files' => false],
        ]);

        $this->assertFalse($client->canServeRequestAsStaticFile(
            new ServerRequest(['url' => '/foo.txt']),
            $context,
        ));
    }

    /**
     * Files outside the public directory stay unserved.
     *
     * @return void
     */
    public function testCantServeStaticFilesIfFileIsOutsidePublicDirectory(): void
    {
        $client = new SwooleClient();

        $context = new RequestContext([
            'publicPath' => $this->publicPath() . DIRECTORY_SEPARATOR . 'files',
            'stimulusConfig' => [],
        ]);

        $this->assertFalse($client->canServeRequestAsStaticFile(
            new ServerRequest(['url' => '/../foo.txt']),
            $context,
        ));
    }

    /**
     * Files with forbidden extensions stay unserved.
     *
     * @return void
     */
    public function testCantServeStaticFilesIfFileHasForbiddenExtension(): void
    {
        $publicPath = $this->publicPath();

        file_put_contents($publicPath . DIRECTORY_SEPARATOR . 'foo.php', '<?php echo "hi";');

        $this->temporaryPaths[] = $publicPath . DIRECTORY_SEPARATOR . 'foo.php';

        $client = new SwooleClient();

        $context = new RequestContext([
            'publicPath' => $publicPath,
            'stimulusConfig' => [],
        ]);

        $this->assertFalse($client->canServeRequestAsStaticFile(
            new ServerRequest(['url' => '/foo.php']),
            $context,
        ));
    }

    /**
     * The root path never serves a static file.
     *
     * @return void
     */
    public function testCantServeStaticFilesForRootPath(): void
    {
        $client = new SwooleClient();

        $context = new RequestContext([
            'publicPath' => $this->publicPath(),
            'stimulusConfig' => [],
        ]);

        $this->assertFalse($client->canServeRequestAsStaticFile(
            new ServerRequest(['url' => '/']),
            $context,
        ));
    }

    /**
     * Served static files carry the status, type, and file path.
     *
     * @return void
     */
    public function testStaticFileCanBeServed(): void
    {
        $client = new SwooleClient();

        $publicPath = $this->publicPath();

        $context = new RequestContext([
            'swooleResponse' => $swooleResponse = new FakeSwooleResponse(),
            'publicPath' => $publicPath,
            'stimulusConfig' => [],
        ]);

        $client->serveStaticFile(new ServerRequest(['url' => '/foo.txt']), $context);

        $this->assertSame([[200]], $swooleResponse->statuses);
        $this->assertSame([['Content-Type', 'text/plain']], $swooleResponse->headers);
        $this->assertSame([realpath($publicPath . DIRECTORY_SEPARATOR . 'foo.txt')], $swooleResponse->files);
    }

    /**
     * Configured static file headers precede the content type.
     *
     * @return void
     */
    public function testStaticFileHeadersCanBeSent(): void
    {
        $client = new SwooleClient();

        $context = new RequestContext([
            'swooleResponse' => $swooleResponse = new FakeSwooleResponse(),
            'publicPath' => $this->publicPath(),
            'stimulusConfig' => [
                'static_file_headers' => [
                    'foo.txt' => ['X-Test-Header' => 'Valid'],
                ],
            ],
        ]);

        $client->serveStaticFile(new ServerRequest(['url' => '/foo.txt']), $context);

        $this->assertSame([['X-Test-Header', 'Valid'], ['Content-Type', 'text/plain']], $swooleResponse->headers);
    }

    /**
     * Buffered responses end with the full content.
     *
     * @return void
     */
    public function testRespondMethodSendsResponseToSwoole(): void
    {
        $client = new SwooleClient();

        $swooleResponse = new FakeSwooleResponse();

        $response = (new Response())
            ->withStringBody('Hello World')
            ->withStatus(200)
            ->withHeader('Content-Type', 'text/html')
            ->withCookie(Cookie::create('new', 'value'))
            ->withExpiredCookie(Cookie::create('cleared', 'deleted')->withExpired());

        $client->respond(new RequestContext(['swooleResponse' => $swooleResponse]), new StimulusResponse($response));

        $this->assertSame([[200]], $swooleResponse->statuses);
        $this->assertContains(['Content-Type', 'text/html'], $swooleResponse->headers);
        $this->assertContains('Date', array_column($swooleResponse->headers, 0));
        $this->assertSame(['Hello World'], array_column($swooleResponse->ends, 0));
        $this->assertSame('new', $swooleResponse->cookies[0][0]);
        $this->assertSame('value', $swooleResponse->cookies[0][1]);
        $this->assertSame('cleared', $swooleResponse->cookies[1][0]);
        $this->assertSame('deleted', $swooleResponse->cookies[1][1]);
    }

    /**
     * Buffered output precedes streamed chunks through writes.
     *
     * @return void
     */
    public function testRespondMethodSendsStreamedResponseToSwoole(): void
    {
        $client = new SwooleClient();

        $swooleResponse = new FakeSwooleResponse();

        $response = (new Response())->withStatus(200)->withHeader('Content-Type', 'text/html');

        $client->respond(
            new RequestContext(['swooleResponse' => $swooleResponse]),
            new StimulusResponse($response, null, static function (): iterable {
                yield 'Hello World';
            }),
        );

        $this->assertSame(['Hello World'], $swooleResponse->writes);
        $this->assertCount(1, $swooleResponse->ends);
    }

    /**
     * Non-standard status codes carry their reason phrase.
     *
     * @return void
     */
    public function testRespondMethodWithStimulusSpecificStatusCodeSendsResponseToSwoole(): void
    {
        $client = new SwooleClient();

        $swooleResponse = new FakeSwooleResponse();

        $response = (new Response())->withStringBody('Hello World')->withStatus(419);

        $client->respond(new RequestContext(['swooleResponse' => $swooleResponse]), new StimulusResponse($response));

        $this->assertSame([[419, 'Page Expired']], $swooleResponse->statuses);
    }

    /**
     * Empty responses end without content.
     *
     * @return void
     */
    public function testRespondMethodSendsEmptyResponseToSwoole(): void
    {
        $client = new SwooleClient();

        $swooleResponse = new FakeSwooleResponse();

        $client->respond(
            new RequestContext(['swooleResponse' => $swooleResponse]),
            new StimulusResponse((new Response())->withStatus(200)),
        );

        $this->assertSame([], $swooleResponse->writes);
        $this->assertCount(1, $swooleResponse->ends);
    }

    /**
     * Large responses chunk through writes.
     *
     * @return void
     */
    public function testRespondMethodSendsChunkedResponseToSwoole(): void
    {
        $client = new SwooleClient(6);

        $swooleResponse = new FakeSwooleResponse();

        $response = (new Response())->withStringBody('Hello World')->withStatus(200);

        $client->respond(new RequestContext(['swooleResponse' => $swooleResponse]), new StimulusResponse($response));

        $this->assertSame(['Hello ', 'World'], $swooleResponse->writes);
        $this->assertCount(1, $swooleResponse->ends);
    }

    /**
     * Errors report the throwable in debug mode.
     *
     * @return void
     */
    public function testErrorMethodSendsDetailedErrorResponseToSwooleInDebugMode(): void
    {
        $client = new SwooleClient();

        $swooleResponse = new FakeSwooleResponse();

        $context = new RequestContext(['swooleResponse' => $swooleResponse]);

        $client->error(
            $throwable = new Exception('Something went wrong...'),
            new stdClass(),
            new ServerRequest(['url' => '/']),
            $context,
        );

        $this->assertContains(['Status', '500 Internal Server Error'], $swooleResponse->headers);
        $this->assertContains(['Content-Type', 'text/plain'], $swooleResponse->headers);
        $this->assertSame([(string)$throwable], array_column($swooleResponse->ends, 0));
    }

    /**
     * Builds a tracked public directory holding a text fixture.
     *
     * @return string Absolute path to the public directory.
     */
    protected function publicPath(): string
    {
        $publicPath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'stimulus-swoole-public-' . getmypid() . '-' . count($this->temporaryPaths);

        if (!is_dir($publicPath)) {
            mkdir($publicPath, 0777, true);
        }

        $fixture = $publicPath . DIRECTORY_SEPARATOR . 'foo.txt';

        if (!is_file($fixture)) {
            file_put_contents($fixture, 'static');
        }

        $this->temporaryPaths[] = $fixture;
        $this->temporaryPaths[] = $publicPath;

        return $publicPath;
    }
}
