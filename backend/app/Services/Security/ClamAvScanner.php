<?php

namespace App\Services\Security;

use RuntimeException;
use Throwable;

class ClamAvScanner implements VirusScanner
{
    public function __construct(
        private readonly string $host = 'clamav',
        private readonly int $port = 3310,
        private readonly float $timeout = 10.0,
    ) {}

    /**
     * Scan a file by streaming it to the ClamAV daemon via INSTREAM protocol.
     */
    public function isSafe(string $filePath): bool
    {
        if (! file_exists($filePath) || filesize($filePath) === 0) {
            return true;
        }

        try {
            $socket = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, $this->timeout);
            if (! $socket) {
                report(new RuntimeException("ClamAV daemon connection failed ({$this->host}:{$this->port}): {$errstr} ({$errno})"));

                return false;
            }

            stream_set_timeout($socket, (int) ceil($this->timeout));

            // Send zINSTREAM command (null-terminated)
            fwrite($socket, "zINSTREAM\0");

            $handle = fopen($filePath, 'rb');
            if (! $handle) {
                fclose($socket);

                return false;
            }

            while (! feof($handle)) {
                $chunk = fread($handle, 8192);
                if ($chunk === false || $chunk === '') {
                    break;
                }

                $length = strlen($chunk);
                $packet = pack('N', $length).$chunk;
                fwrite($socket, $packet);
            }
            fclose($handle);

            // Terminate stream with zero length chunk
            fwrite($socket, pack('N', 0));

            $response = trim((string) fgets($socket));
            fclose($socket);

            if (str_contains($response, 'FOUND')) {
                return false;
            }

            if (str_contains($response, 'OK')) {
                return true;
            }

            // Unknown or ERROR response
            report(new RuntimeException("ClamAV daemon unexpected response: {$response}"));

            return false;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
