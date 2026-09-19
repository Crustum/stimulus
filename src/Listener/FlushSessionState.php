<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Event\RequestTerminatedEvent;

/**
 * Resets process-global session state around each worker request.
 *
 * The persisted session data survives across operations. Resets the process-global session leftovers on receive so the incoming request
 * starts from its own session cookie, and closes plus resets them on
 * terminate so nothing leaks into the next operation. Persisted data is
 * never destroyed here: destroying on receive wipes the token store that
 * form protection and authentication validate against.
 *
 * Listened on the request received and request terminated events.
 */
class FlushSessionState implements EventListenerInterface
{
    /**
     * Returns the events this listener handles.
     *
     * @return array<string, mixed> Event names mapped to handler methods.
     */
    public function implementedEvents(): array
    {
        return [
            'Stimulus.requestReceived' => 'handleRequestReceived',
            'Stimulus.requestTerminated' => 'handleRequestTerminated',
        ];
    }

    /**
     * Clears stale process-global session state before the request runs.
     *
     * The incoming request has not started its session yet, so resetting the
     * globals only drops leftovers from the previous operation. The session
     * identifier is pointed at the incoming request cookie explicitly instead
     * of relying on cookie fallback, so the session start about to happen
     * resumes the current client and never the previous operation.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        $cookie = $event->getRequest()->getCookie((string)session_name());

        $this->resetProcessGlobals(is_string($cookie) ? $cookie : '');
    }

    /**
     * Persists the request session and clears it from the worker process.
     *
     * Closing writes the session data back to storage before the globals are
     * reset, so the next operation finds the stored data but never the
     * in-memory copy.
     *
     * @param \Crustum\Stimulus\Event\RequestTerminatedEvent $event Terminated request operation.
     * @return void
     */
    public function handleRequestTerminated(RequestTerminatedEvent $event): void
    {
        $event->getRequest()->getSession()->close();

        $this->resetProcessGlobals();
    }

    /**
     * Drops process-global session leftovers from the previous operation.
     *
     * An active leftover session is closed first so its data is persisted,
     * then the superglobal is emptied and the identifier pointed at the given
     * session identifier so the next session start resumes the current
     * request cookie instead of the previous operation.
     *
     * @param string $sessionId Session identifier to resume, or empty for a new one.
     * @return void
     */
    protected function resetProcessGlobals(string $sessionId = ''): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $_SESSION = [];

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_id($sessionId);
        }
    }
}
