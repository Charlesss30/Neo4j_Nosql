<?php

namespace App\Http\Controllers;

use App\Http\Requests\RelationshipRequest;
use App\Services\FamilyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class RelationshipController extends Controller
{
    public function __construct(private readonly FamilyService $service) {}

    public function index(): View
    {
        return view('relationships', ['people' => $this->service->choices()]);
    }

    public function storeParent(RelationshipRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->service->addParent($data['parentId'], $data['childId']);

        return back()->with('success', 'Đã thêm quan hệ cha mẹ – con.');
    }

    public function destroyParent(RelationshipRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->service->removeParent($data['parentId'], $data['childId']);

        return back()->with('success', 'Đã xóa quan hệ cha mẹ – con.');
    }

    public function storeSpouse(RelationshipRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->service->addSpouse($data['a'], $data['b'], isset($data['marriageYear']) ? (int) $data['marriageYear'] : null);

        return back()->with('success', 'Đã thêm quan hệ vợ chồng.');
    }

    public function destroySpouse(RelationshipRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->service->removeSpouse($data['a'], $data['b']);

        return back()->with('success', 'Đã xóa quan hệ vợ chồng.');
    }
}
