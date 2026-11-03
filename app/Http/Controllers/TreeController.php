<?php

namespace App\Http\Controllers;

use App\Services\FamilyGraphService;
use App\Services\FamilyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TreeController extends Controller
{
    public function __construct(
        private readonly FamilyGraphService $graphs,
        private readonly FamilyService $family,
    ) {}

    public function index(Request $request): Response
    {
        $input = $this->input($request);
        $message = null;
        $status = 200;
        try {
            $graph = $this->graphs->tree($input['root'] ?? null, $request->boolean('all'));
        } catch (NotFoundHttpException) {
            $graph = ['root' => $input['root'], 'nodes' => [], 'edges' => []];
            $message = 'Không tìm thấy thành viên gốc. Hãy chọn lại mã trong danh sách.';
            $status = 404;
        }

        return response()->view('tree', ['graph' => $graph, 'people' => $this->family->choices(), 'message' => $message, 'all' => $request->boolean('all')], $status);
    }

    public function data(Request $request): JsonResponse
    {
        $input = $this->input($request);
        try {
            return response()->json($this->graphs->tree($input['root'] ?? null, $request->boolean('all')));
        } catch (NotFoundHttpException) {
            return response()->json(['message' => 'Không tìm thấy thành viên gốc.', 'nodes' => [], 'edges' => []], 404);
        }
    }

    /** @return array<string, mixed> */
    private function input(Request $request): array
    {
        return $request->validate(['root' => ['nullable', 'string', 'max:64'], 'all' => ['nullable', 'boolean']]);
    }
}
