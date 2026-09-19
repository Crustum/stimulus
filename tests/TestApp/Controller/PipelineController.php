<?php
declare(strict_types=1);

namespace TestApp\Controller;

use Cake\Controller\Controller;
use Cake\Http\Response;

/**
 * Probe controller proving middleware pipeline execution per operation.
 *
 * Records whether specific middleware ran during the request, so worker
 * tests verify the full middleware stack executes on every operation
 * instead of short-circuiting after the first request.
 */
class PipelineController extends Controller
{
    /**
     * Answers with a list of middleware that ran during this request.
     *
     * The response body is a JSON-encoded array of middleware names
     * collected by the probe component or through request attributes.
     *
     * @return \Cake\Http\Response JSON response listing middleware that ran.
     */
    public function index(): Response
    {
        $middleware = $this->request->getAttribute('middlewareRan') ?? [];

        return (new Response())
            ->withType('json')
            ->withStringBody(json_encode(['middleware' => $middleware]));
    }

    /**
     * Answers with the request URL path to prove routing works.
     *
     * @return \Cake\Http\Response JSON response carrying the matched path.
     */
    public function path(): Response
    {
        return (new Response())
            ->withType('json')
            ->withStringBody(json_encode(['path' => $this->request->getPath()]));
    }

    /**
     * Answers with session data to prove session isolation between operations.
     *
     * Reads the `visit_count` value from the session, increments it,
     * and returns both the old and new value.
     *
     * @return \Cake\Http\Response JSON response carrying the visit count.
     */
    public function session(): Response
    {
        $session = $this->request->getSession();
        $count = (int)$session->read('visit_count');
        $session->write('visit_count', $count + 1);

        return (new Response())
            ->withType('json')
            ->withStringBody(json_encode([
                'visit_count' => $count,
                'next_count' => $count + 1,
            ]));
    }

    /**
     * Answers with a specific status code to test error responses.
     *
     * @param int $code HTTP status code to return.
     * @return \Cake\Http\Response Response with the requested status code.
     */
    public function status(int $code = 200): Response
    {
        return (new Response())->withStatus($code);
    }
}
