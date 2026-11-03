<?php

namespace App\Http\Controllers;

use App\Exceptions\Neo4jQueryException;
use App\Services\FamilyRepository;
use App\Services\Neo4jService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(Neo4jService $neo4j, FamilyRepository $family): View
    {
        $connected = $neo4j->ping();
        $error = $neo4j->lastError();
        $statisticsAvailable = false;
        $gender = [];
        $generations = [];
        $life = ['living' => 0, 'deceased' => 0];
        $topParents = [];
        $oldest = null;

        if ($connected) {
            try {
                $gender = $family->statsByGender();
                $generations = $family->statsByGeneration('P001');
                $life = $family->livingVsDeceased()[0] ?? $life;
                $topParents = $family->topParents();
                $oldest = $family->oldestPerson()[0]['person'] ?? null;
                $statisticsAvailable = true;
            } catch (Neo4jQueryException $exception) {
                $error = $exception->getMessage();
            }
        }

        return view('dashboard', compact(
            'connected', 'error', 'statisticsAvailable', 'gender', 'generations', 'life', 'topParents', 'oldest',
        ));
    }
}
