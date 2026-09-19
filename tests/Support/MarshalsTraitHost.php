<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Support;

use Crustum\Stimulus\Http\StimulusResponse;
use Crustum\Stimulus\Worker\MarshalsPsr7RequestsAndResponsesTrait;
use Psr\Http\Message\ResponseInterface;

/**
 * Host exposing the response marshalling trait for tests.
 */
class MarshalsTraitHost
{
    use MarshalsPsr7RequestsAndResponsesTrait;

    /**
     * Wraps the given response into the worker envelope.
     *
     * @param \Psr\Http\Message\ResponseInterface $response Response to wrap.
     * @param string|null $outputBuffer Output buffered while handling the request.
     * @return \Crustum\Stimulus\Http\StimulusResponse Worker response envelope.
     */
    public function toEnvelope(ResponseInterface $response, ?string $outputBuffer = null): StimulusResponse
    {
        return $this->toStimulusResponse($response, $outputBuffer);
    }
}
