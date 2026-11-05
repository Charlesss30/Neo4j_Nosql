<?php

namespace App\Http\Controllers;

use App\Exceptions\Neo4jQueryException;
use App\Services\FamilyRepository;
use App\Services\FamilyService;
use App\Services\Neo4jService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Neo4jService $neo4j, FamilyRepository $family, FamilyService $service): View
    {
        $requestedRoot = $request->validate(['root' => ['nullable', 'string', 'max:64']], [
            'root.*' => 'Gốc thống kê phải là một mã thành viên hợp lệ, không quá 64 ký tự.',
        ])['root'] ?? null;
        $connected = $neo4j->ping();
        $error = $neo4j->lastError();
        $statisticsAvailable = false;
        $gender = [];
        $generations = [];
        $life = ['living' => 0, 'deceased' => 0];
        $topParents = [];
        $oldest = null;
        $people = [];
        $rootPerson = null;

        if ($connected) {
            try {
                $gender = $family->statsByGender();
                $people = array_column($service->choices(), 'person');
                $rootPerson = $requestedRoot !== null
                    ? ($family->getPerson($requestedRoot)[0]['person'] ?? null)
                    : ($family->getPerson('P001')[0]['person'] ?? $family->roots()[0]['person'] ?? null);
                abort_if($requestedRoot !== null && $rootPerson === null, 404, 'Không tìm thấy gốc thống kê được yêu cầu.');
                $generations = $rootPerson === null ? [] : $family->statsByGeneration($rootPerson['id']);
                $life = $family->livingVsDeceased()[0] ?? $life;
                $topParents = $family->topParents();
                $oldest = $family->oldestPerson()[0]['person'] ?? null;
                $statisticsAvailable = true;
            } catch (Neo4jQueryException $exception) {
                $error = $exception->getMessage();
            }
        }

        return view('dashboard', compact(
            'connected', 'error', 'statisticsAvailable', 'gender', 'generations', 'life', 'topParents', 'oldest', 'people', 'rootPerson',
        ));
    }
}
