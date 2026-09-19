<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestTerminatedEvent;
use Crustum\Stimulus\Listener\FlushUploadedFiles;
use Crustum\Stimulus\Test\TestCase;
use Laminas\Diactoros\UploadedFile;
use stdClass;

/**
 * Tests for the uploaded file flushing listener.
 *
 * Temporary files behind successful uploads are deleted while moved or
 * failed uploads are left untouched.
 */
class FlushUploadedFilesTest extends TestCase
{
    /**
     * Temporary files behind uploads are removed.
     *
     * @return void
     */
    public function testFilesAreRemoved(): void
    {
        $firstPath = tempnam(sys_get_temp_dir(), 'stimulus-');
        $secondPath = tempnam(sys_get_temp_dir(), 'stimulus-');
        $thirdPath = tempnam(sys_get_temp_dir(), 'stimulus-');

        $this->assertIsString($firstPath);
        $this->assertIsString($secondPath);
        $this->assertIsString($thirdPath);

        file_put_contents($firstPath, 'first-body');
        file_put_contents($secondPath, 'second-body');
        file_put_contents($thirdPath, 'third-body');

        $movedPath = $thirdPath . '-moved';

        try {
            $this->assertTrue(rename($thirdPath, $movedPath));

            $request = (new ServerRequest(['url' => '/upload']))->withUploadedFiles([
                [new UploadedFile($firstPath, filesize($firstPath), UPLOAD_ERR_OK, 'first.txt', 'text/plain')],
                [new UploadedFile($secondPath, filesize($secondPath), UPLOAD_ERR_OK, 'second.txt', 'text/plain')],
                new UploadedFile($thirdPath, 0, UPLOAD_ERR_OK, 'third.txt', 'text/plain'),
                new UploadedFile('', 0, UPLOAD_ERR_NO_FILE),
            ]);

            $event = new RequestTerminatedEvent(new stdClass(), new stdClass(), $request, new Response());

            (new FlushUploadedFiles())->handleRequestTerminated($event);

            $this->assertFileDoesNotExist($firstPath);
            $this->assertFileDoesNotExist($secondPath);
            $this->assertFileExists($movedPath);
        } finally {
            foreach ([$firstPath, $secondPath, $movedPath] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
}
