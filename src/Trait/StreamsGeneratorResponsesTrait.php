<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Trait;

use Closure;

/**
 * Streams generator-produced response chunks through bridge writers.
 *
 * The loop stops early when the writer reports a failed write, so
 * disconnected clients stop the generator instead of filling a dead
 * connection. Chunks stringify the way the envelope producer stringifies
 * them.
 */
trait StreamsGeneratorResponsesTrait
{
    /**
     * Streams each chunk yielded by the given producer through the writer.
     *
     * The writer receives every stringified chunk and stops the stream by
     * returning `false`, which ends the generator without resuming it.
     *
     * @param (\Closure():\Generator) $producer Producer of body chunks.
     * @param (\Closure(string):mixed) $write Writer receiving each chunk.
     * @return void
     */
    protected function streamGeneratorResponse(Closure $producer, Closure $write): void
    {
        foreach ($producer() as $chunk) {
            $chunk = is_string($chunk) ? $chunk : (string)$chunk;

            if ($write($chunk) === false) {
                break;
            }
        }
    }

    /**
     * Echoes and flushes the given chunk, reporting client presence.
     *
     * @param string $chunk Stream chunk to send.
     * @return bool Whether the client is still connected.
     */
    protected function writeStreamedChunk(string $chunk): bool
    {
        echo $chunk;

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();

        return ! connection_aborted();
    }
}
