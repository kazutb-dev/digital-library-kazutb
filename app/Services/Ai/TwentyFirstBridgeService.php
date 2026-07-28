<?php

namespace App\Services\Ai;

use RuntimeException;
use Symfony\Component\Process\Process;

class TwentyFirstBridgeService
{
    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function createToken(array $payload): array
    {
        return $this->runBridge('create-token', $payload);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function createSession(array $payload): array
    {
        return $this->runBridge('create-session', $payload);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function createThread(array $payload): array
    {
        return $this->runBridge('create-thread', $payload);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function runBridge(string $command, array $payload): array
    {
        $apiKey = (string) config('services.twentyfirst.api_key', '');
        if (trim($apiKey) === '') {
            throw new RuntimeException('API_KEY_21ST is not configured.');
        }

        $process = new Process([
            'node',
            base_path('scripts/21st/bridge.mjs'),
            $command,
            json_encode($payload, JSON_THROW_ON_ERROR),
        ], base_path(), [
            'API_KEY_21ST' => $apiKey,
        ]);

        $process->setTimeout(45);
        $process->run();

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput()) ?: '21st bridge execution failed.';
            throw new RuntimeException($message);
        }

        $decoded = json_decode($process->getOutput(), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('21st bridge returned an invalid response.');
        }

        return $decoded;
    }
}