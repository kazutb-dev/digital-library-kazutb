<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Library\BookDetailReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookController extends Controller
{
    private function respondWithBook(string $identifier, BookDetailReadService $service): JsonResponse
    {
        $book = $service->findByIdentifier($identifier);

        if (! $book) {
            return response()->json([
                'error' => 'Book not found',
                'success' => false,
            ], 404);
        }

        return response()->json([
            'data' => $book,
            'success' => true,
        ]);
    }

    public function dbShow(Request $request, BookDetailReadService $service): JsonResponse
    {
        $identifier = (string) $request->route('isbn');
        return $this->respondWithBook($identifier, $service);
    }
}
