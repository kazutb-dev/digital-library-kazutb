<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Library\SubjectReadService;
use Illuminate\Http\JsonResponse;

class SubjectController extends Controller
{
    public function index(SubjectReadService $service): JsonResponse
    {
        return response()->json($service->listGrouped());
    }
}
