<?php
declare(strict_types=1);

namespace TestApp\Controller;

use Cake\Controller\Controller;
use Cake\Http\Response;

/**
 * Probe controller proving per-operation controller freshness.
 *
 * Counts its own invocations, so two worker operations answering `probe:1`
 * prove the sandbox built a fresh controller per operation instead of
 * reusing one across the long-lived process.
 */
class ProbeController extends Controller
{
    /**
     * Invocations served by this controller instance.
     *
     * @var int
     */
    protected int $invocations = 0;

    /**
     * Answers with this instance invocation count.
     *
     * @return \Cake\Http\Response Probe response carrying the invocation count.
     */
    public function index(): Response
    {
        $this->invocations++;

        return (new Response())->withStringBody('probe:' . $this->invocations);
    }
}
