<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\FrankenPhp;

use Cake\Http\Client;
use Cake\Http\Client\Exception\NetworkException;
use Crustum\Stimulus\Contracts\ServerProcessInspector as ServerProcessInspectorContract;

/**
 * Inspects and controls the FrankenPHP server process.
 *
 * An injected HTTP client performs liveness probes, worker reloads, and
 * server stops through the FrankenPHP admin endpoint.
 */
class ServerProcessInspector implements ServerProcessInspectorContract
{
    /**
     * HTTP client talking to the FrankenPHP admin endpoint.
     *
     * @var \Cake\Http\Client
     */
    protected Client $httpClient;

    /**
     * Creates a new FrankenPHP server process inspector.
     *
     * @param \Crustum\Stimulus\Bridge\FrankenPhp\ServerStateFile $serverStateFile State file tracking the server process.
     * @param \Cake\Http\Client|null $httpClient Client for the admin endpoint, or null for a default client.
     */
    public function __construct(
        protected ServerStateFile $serverStateFile,
        ?Client $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? new Client();
    }

    /**
     * Determines if the FrankenPHP server process is running.
     *
     * @return bool Whether the recorded master process answers on the admin endpoint.
     */
    public function serverIsRunning(): bool
    {
        if ($this->serverStateFile->read()['masterProcessId'] ?? null) {
            try {
                return $this->httpClient->get($this->adminConfigUrl())->isOk();
            } catch (NetworkException) {
                return false;
            }
        }

        return false;
    }

    /**
     * Reloads the FrankenPHP workers.
     *
     * @return void
     */
    public function reloadServer(): void
    {
        $configUrl = $this->adminConfigUrl();

        try {
            $body = (string)$this->httpClient->get($configUrl)->getBody();

            $this->httpClient->patch($configUrl, $body, [
                'headers' => [
                    'Cache-Control' => 'must-revalidate',
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (NetworkException) {
            return;
        }
    }

    /**
     * Stops the FrankenPHP server.
     *
     * @return bool Whether the admin endpoint accepted the stop request.
     */
    public function stopServer(): bool
    {
        try {
            return $this->httpClient->post($this->adminUrl() . '/stop')->isOk();
        } catch (NetworkException) {
            return false;
        }
    }

    /**
     * Gets the URL to the FrankenPHP admin panel.
     *
     * @return string Base URL of the FrankenPHP admin endpoint.
     */
    protected function adminUrl(): string
    {
        $serverStateFile = $this->serverStateFile->read();

        $adminHost = $serverStateFile['state']['adminHost'] ?? 'localhost';
        $adminPort = $serverStateFile['state']['adminPort'] ?? 2019;

        return "http://{$adminHost}:{$adminPort}";
    }

    /**
     * Gets the URL to the FrankenPHP admin panel's configuration endpoint.
     *
     * @return string URL of the FrankenPHP application configuration endpoint.
     */
    protected function adminConfigUrl(): string
    {
        return "{$this->adminUrl()}/config/apps/frankenphp";
    }
}
