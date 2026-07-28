<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Library\LibraryHealthReadService;
use Illuminate\Http\JsonResponse;

class LibraryController extends Controller
{
    public function healthSummary(LibraryHealthReadService $service): JsonResponse
    {
        return response()->json($service->summary());
    }
}
