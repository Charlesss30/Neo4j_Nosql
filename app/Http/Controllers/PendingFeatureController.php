<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PendingFeatureController extends Controller
{
    public function __invoke(Request $request): Response|JsonResponse
    {
        $message = 'Chức năng này sẽ được triển khai trong Milestone 2.';

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => $message], 501);
        }

        return response()->view('pending', ['message' => $message], $request->isMethod('GET') ? 200 : 501);
    }
}
