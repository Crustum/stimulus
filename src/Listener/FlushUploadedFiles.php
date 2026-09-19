<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Event\RequestTerminatedEvent;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

/**
 * Deletes uploaded files once the request terminates.
 *
 * Walks the PSR-7 uploaded file tree of the terminated request and unlinks
 * the temporary file behind each successful upload so worker temporary
 * directories do not fill up. Uploads that failed, carry no readable path, or were already moved are left untouched.
 *
 * Listened on the request terminated event.
 */
class FlushUploadedFiles implements EventListenerInterface
{
    /**
     * Returns the events this listener handles.
     *
     * @return array<string, mixed> Event names mapped to handler methods.
     */
    public function implementedEvents(): array
    {
        return [
            'Stimulus.requestTerminated' => 'handleRequestTerminated',
        ];
    }

    /**
     * Deletes the temporary files of the terminated request.
     *
     * @param \Crustum\Stimulus\Event\RequestTerminatedEvent $event Terminated request operation.
     * @return void
     */
    public function handleRequestTerminated(RequestTerminatedEvent $event): void
    {
        $this->flushFiles($event->getRequest()->getUploadedFiles());
    }

    /**
     * Recursively deletes temporary files behind the given uploads.
     *
     * Recurses into nested upload groups.
     *
     * @param array<mixed> $files Uploaded files or nested upload groups.
     * @return void
     */
    protected function flushFiles(array $files): void
    {
        foreach ($files as $file) {
            if (is_array($file)) {
                $this->flushFiles($file);

                continue;
            }

            if (!$file instanceof UploadedFileInterface) {
                continue;
            }

            if ($file->getError() !== UPLOAD_ERR_OK) {
                continue;
            }

            $path = $this->uploadedFilePath($file);

            if ($path === null) {
                continue;
            }

            clearstatcache(true, $path);

            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Resolves the temporary path behind the given upload.
     *
     * Files the application already moved are expected here, so filesystem
     * warnings raised while opening their stream are discarded instead of
     * reaching the worker logs.
     *
     * @param \Psr\Http\Message\UploadedFileInterface $file Uploaded file.
     * @return string|null Temporary path or null when it cannot be resolved.
     */
    protected function uploadedFilePath(UploadedFileInterface $file): ?string
    {
        set_error_handler(static fn(): bool => true);

        try {
            $path = $file->getStream()->getMetadata('uri');
        } catch (Throwable) {
            return null;
        } finally {
            restore_error_handler();
        }

        if (!is_string($path) || $path === '') {
            return null;
        }

        return $path;
    }
}
