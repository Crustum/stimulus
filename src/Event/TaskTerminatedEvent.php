<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Event;

use Crustum\Stimulus\Contracts\OperationTerminated;

/**
 * Event dispatched after a task operation terminates.
 */
class TaskTerminatedEvent extends StimulusEvent implements OperationTerminated
{
    use HasApplicationAndSandboxTrait;

    /**
     * Task payload.
     *
     * @var mixed
     */
    public mixed $payload;

    /**
     * Creates a new task terminated event.
     *
     * @param object $app Base application instance.
     * @param object $sandbox Sandbox application instance that handled the task.
     * @param mixed $data Task payload.
     * @param mixed $result Task result.
     */
    public function __construct(
        public object $app,
        public object $sandbox,
        mixed $data,
        public mixed $result,
    ) {
        $this->payload = $data;
        parent::__construct([
            'app' => $app,
            'sandbox' => $sandbox,
            'data' => $data,
            'result' => $result,
        ]);
    }

    /**
     * Gets the base application instance.
     *
     * @return object Base application instance.
     */
    public function getApp(): object
    {
        return $this->app;
    }

    /**
     * Gets the sandbox application instance that handled the task.
     *
     * @return object Sandbox application instance that handled the task.
     */
    public function getSandbox(): object
    {
        return $this->sandbox;
    }

    /**
     * Gets the task payload.
     *
     * @return mixed Task payload.
     */
    public function getPayload(): mixed
    {
        return $this->payload;
    }

    /**
     * Gets the task result.
     *
     * Named apart from the event result so the payload result and
     * the listener result channel stay distinct.
     *
     * @return mixed Task result.
     */
    public function getTaskResult(): mixed
    {
        return $this->result;
    }
}
