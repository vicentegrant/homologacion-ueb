<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CareerResource;
use App\Models\Carrera;
use Illuminate\Http\JsonResponse;

class CareerController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => CareerResource::collection(Carrera::query()->orderBy('nombre')->get()),
        ]);
    }
}
