<?php

namespace App\Http\Responses;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ServerSentEventStream extends StreamedResponse
{
    /**
     * Create an SSE streamed response with session unlock, output flushing, and standard SSE headers.
     *
     * @param  callable(callable(string, mixed): void, callable(): bool): void  $callback
     */
    public static function createStream(callable $callback, int $status = 200, array $headers = []): self
    {
        $defaultHeaders = [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];

        return new self(function () use ($callback) {
            // Immediately release session lock to prevent blocking concurrent browser requests
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');

            $sendEvent = function (string $event, mixed $data): void {
                if ($event !== '') {
                    echo "event: {$event}\n";
                }
                echo 'data: '.json_encode($data)."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            $isAborted = fn (): bool => connection_aborted();

            $callback($sendEvent, $isAborted);
        }, $status, array_merge($defaultHeaders, $headers));
    }
}
