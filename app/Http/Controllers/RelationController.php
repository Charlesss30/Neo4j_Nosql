<?php

namespace App\Http\Controllers;

use App\Services\FamilyGraphService;
use App\Services\FamilyService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RelationController extends Controller
{
    public function __invoke(Request $request, FamilyGraphService $graphs, FamilyService $family): Response
    {
        $input = $request->validate(['a' => ['nullable', 'required_with:b', 'string', 'max:64'], 'b' => ['nullable', 'required_with:a', 'string', 'max:64']]);
        $graph = null;
        $message = null;
        $status = 200;
        if (isset($input['a'], $input['b'])) {
            try {
                $graph = $graphs->relation($input['a'], $input['b']);
            } catch (NotFoundHttpException) {
                $message = 'Không tìm thấy một trong hai thành viên. Hãy chọn mã trong danh sách.';
                $status = 404;
            }
        }

        return response()->view('relation', ['graph' => $graph, 'people' => $family->choices(), 'input' => $input, 'message' => $message], $status);
    }
}
