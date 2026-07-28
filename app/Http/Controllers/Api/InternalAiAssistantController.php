<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ai\TwentyFirstBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class InternalAiAssistantController extends Controller
{
    public function __construct(
        private readonly TwentyFirstBridgeService $bridge,
    ) {}

    public function token(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);

        try {
            $result = $this->bridge->createToken([
                'agent' => $this->agentSlug(),
                'userId' => $this->userIdentifier($user),
                'expiresIn' => (string) config('services.twentyfirst.token_expires_in', '1h'),
            ]);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }

        return response()->json([
            'token' => $result['token'] ?? null,
            'expiresAt' => $result['expiresAt'] ?? null,
        ]);
    }

    public function session(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);

        try {
            $result = $this->bridge->createSession([
                'agent' => $this->agentSlug(),
                'name' => (string) $request->input('name', 'Frontend Chat'),
                'userId' => $this->userIdentifier($user),
            ]);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }

        return response()->json([
            'agent' => $this->agentSlug(),
            'sandboxId' => $result['sandboxId'] ?? null,
            'threadId' => $result['threadId'] ?? null,
        ]);
    }

    public function thread(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sandboxId' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $this->bridge->createThread([
                'sandboxId' => $validated['sandboxId'],
                'name' => $validated['name'] ?? 'New frontend thread',
            ]);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }

        return response()->json([
            'threadId' => $result['threadId'] ?? null,
        ]);
    }

    /**
     * @return array<string,string>
     */
    private function staffUser(Request $request): array
    {
        $user = $request->attributes->get('internal_staff_user');

        return is_array($user) ? $user : [];
    }

    /**
     * @param array<string,string> $user
     */
    private function userIdentifier(array $user): string
    {
        foreach (['id', 'login', 'email', 'name'] as $key) {
            $value = trim((string) ($user[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'library-staff';
    }

    private function agentSlug(): string
    {
        return (string) config('services.twentyfirst.agent', 'frontend-dev-agent');
    }

    private function errorResponse(string $message): JsonResponse
    {
        return response()->json([
            'error' => 'twentyfirst_bridge_failed',
            'message' => $message,
            'success' => false,
        ], 500);
    }
}