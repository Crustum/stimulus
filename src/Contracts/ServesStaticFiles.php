<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Contracts;

use Cake\Http\ServerRequest;
use Crustum\Stimulus\Http\RequestContext;

/**
 * Contract for server bridge clients able to serve static files.
 */
interface ServesStaticFiles
{
    /**
     * Determine if the request can be served as a static file.
     *
     * @param \Cake\Http\ServerRequest $request Incoming request.
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context from the server.
     * @return bool Whether the request maps to a static file.
     */
    public function canServeRequestAsStaticFile(ServerRequest $request, RequestContext $context): bool;

    /**
     * Serve the static file that was requested.
     *
     * @param \Cake\Http\ServerRequest $request Incoming request.
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context from the server.
     * @return void
     */
    public function serveStaticFile(ServerRequest $request, RequestContext $context): void;
}
