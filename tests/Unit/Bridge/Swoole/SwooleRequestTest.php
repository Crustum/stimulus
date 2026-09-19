<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Bridge\Swoole;

use Cake\Http\ServerRequest;
use Crustum\Stimulus\Bridge\Swoole\Actions\ConvertSwooleRequestToCakeRequest;
use Crustum\Stimulus\Test\Support\FakeSwooleRequest;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the Swoole request converter.
 *
 * Covers the request shape: server variables, query, post, cookies,
 * files, url-encoded method bodies, and the development server header
 * correction. Anonymous fakes stand in for the Swoole request so the suite
 * loads without the extension; every test skips cleanly where the extension
 * is absent.
 */
class SwooleRequestTest extends TestCase
{
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
     * Empty payloads convert into a default request.
     *
     * @return void
     */
    public function testConvertSwooleRequestToCakeRequest(): void
    {
        $request = (new ConvertSwooleRequestToCakeRequest())->__invoke(new FakeSwooleRequest(), 'cli');

        $this->assertInstanceOf(ServerRequest::class, $request);
        $this->assertSame('GET', $request->getMethod());
    }

    /**
     * Server variables land on the request method, URI, and protocol.
     *
     * @return void
     */
    public function testServerVariables(): void
    {
        $swooleRequest = new FakeSwooleRequest();

        $swooleRequest->server = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/foo',
            'QUERY_STRING' => 'bar=baz',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
        ];
        $swooleRequest->header = [
            'host' => 'localhost',
            'content-type' => 'application/json',
        ];

        $request = (new ConvertSwooleRequestToCakeRequest())->__invoke($swooleRequest, 'cli-server');

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/foo', $request->getUri()->getPath());
        $this->assertSame('bar=baz', $request->getUri()->getQuery());
        $this->assertSame('HTTP/1.1', $request->getServerParams()['SERVER_PROTOCOL']);
        $this->assertSame('localhost', $request->getServerParams()['HTTP_HOST']);
        $this->assertSame('application/json', $request->getServerParams()['CONTENT_TYPE']);
    }

    /**
     * Query string appends to request URIs missing it.
     *
     * @return void
     */
    public function testQueryStringAppendsToRequestUri(): void
    {
        $swooleRequest = new FakeSwooleRequest();

        $swooleRequest->server = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/foo',
            'QUERY_STRING' => 'bar=baz',
        ];

        $request = (new ConvertSwooleRequestToCakeRequest())->__invoke($swooleRequest, 'cli');

        $this->assertSame('/foo?bar=baz', $request->getServerParams()['REQUEST_URI']);
    }

    /**
     * Query payloads land on the query.
     *
     * @return void
     */
    public function testGet(): void
    {
        $swooleRequest = new FakeSwooleRequest();

        $swooleRequest->get = ['foo' => 'bar'];

        $request = (new ConvertSwooleRequestToCakeRequest())->__invoke($swooleRequest, 'cli-server');

        $this->assertSame('bar', $request->getQuery('foo'));
    }

    /**
     * Post payloads land on the data.
     *
     * @return void
     */
    public function testPost(): void
    {
        $swooleRequest = new FakeSwooleRequest();

        $swooleRequest->header = ['content-type' => 'application/x-www-form-urlencoded'];
        $swooleRequest->server = ['REQUEST_METHOD' => 'POST'];
        $swooleRequest->post = ['foo' => 'bar'];

        $request = (new ConvertSwooleRequestToCakeRequest())->__invoke($swooleRequest, 'cli-server');

        $this->assertSame('bar', $request->getData('foo'));
    }

    /**
     * Url-encoded PUT bodies parse back into post data.
     *
     * @return void
     */
    public function testPut(): void
    {
        $swooleRequest = new FakeSwooleRequest();

        $swooleRequest->header = ['content-type' => 'application/x-www-form-urlencoded'];
        $swooleRequest->server = ['REQUEST_METHOD' => 'PUT'];
        $swooleRequest->content = 'foo=bar';

        $request = (new ConvertSwooleRequestToCakeRequest())->__invoke($swooleRequest, 'cli-server');

        $this->assertSame('bar', $request->getData('foo'));
    }

    /**
     * Url-encoded PATCH bodies parse back into post data.
     *
     * @return void
     */
    public function testPatch(): void
    {
        $swooleRequest = new FakeSwooleRequest();

        $swooleRequest->header = ['content-type' => 'application/x-www-form-urlencoded'];
        $swooleRequest->server = ['REQUEST_METHOD' => 'PATCH'];
        $swooleRequest->content = 'foo=bar';

        $request = (new ConvertSwooleRequestToCakeRequest())->__invoke($swooleRequest, 'cli-server');

        $this->assertSame('bar', $request->getData('foo'));
    }

    /**
     * Url-encoded DELETE bodies parse back into post data.
     *
     * @return void
     */
    public function testDelete(): void
    {
        $swooleRequest = new FakeSwooleRequest();

        $swooleRequest->header = ['content-type' => 'application/x-www-form-urlencoded'];
        $swooleRequest->server = ['REQUEST_METHOD' => 'DELETE'];
        $swooleRequest->content = 'foo=bar';

        $request = (new ConvertSwooleRequestToCakeRequest())->__invoke($swooleRequest, 'cli-server');

        $this->assertSame('bar', $request->getData('foo'));
    }

    /**
     * Cookie payloads land on the cookies.
     *
     * @return void
     */
    public function testCookie(): void
    {
        $swooleRequest = new FakeSwooleRequest();

        $swooleRequest->cookie = ['foo' => 'bar'];

        $request = (new ConvertSwooleRequestToCakeRequest())->__invoke($swooleRequest, 'cli-server');

        $this->assertSame('bar', $request->getCookie('foo'));
    }

    /**
     * Development server headers correct into content variables.
     *
     * @return void
     */
    public function testDevelopmentServerHeaderCorrection(): void
    {
        $swooleRequest = new FakeSwooleRequest();

        $swooleRequest->header = [
            'content-length' => '11',
            'content-type' => 'text/plain',
        ];

        $request = (new ConvertSwooleRequestToCakeRequest())->__invoke($swooleRequest, 'cli-server');

        $this->assertSame('11', $request->getServerParams()['CONTENT_LENGTH']);
        $this->assertSame('text/plain', $request->getServerParams()['CONTENT_TYPE']);
    }

    /**
     * Raw bodies travel as the request input.
     *
     * @return void
     */
    public function testRawContentTravelsAsInput(): void
    {
        $swooleRequest = new FakeSwooleRequest();

        $swooleRequest->content = 'Hello World';

        $request = (new ConvertSwooleRequestToCakeRequest())->__invoke($swooleRequest, 'cli');

        $this->assertSame('Hello World', (string)$request->getBody());
    }

    /**
     * Converted requests carry a root webroot so asset URLs stay absolute.
     *
     * Without explicit base attributes helper-generated asset URLs come out
     * page-relative and leak the current page scope into the browser request.
     *
     * @return void
     */
    public function testBaseAndWebrootDefaultToRoot(): void
    {
        $request = (new ConvertSwooleRequestToCakeRequest())->__invoke(new FakeSwooleRequest(), 'cli');

        $this->assertSame('', $request->getAttribute('base'));
        $this->assertSame('/', $request->getAttribute('webroot'));
    }
}
