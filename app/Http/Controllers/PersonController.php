<?php

namespace App\Http\Controllers;

use App\Http\Requests\PersonRequest;
use App\Services\FamilyRepository;
use App\Services\FamilyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PersonController extends Controller
{
    public function __construct(
        private readonly FamilyService $service,
        private readonly FamilyRepository $family,
    ) {}

    public function index(Request $request): View
    {
        $input = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'between:1,100000']]);
        $query = $input['q'] ?? '';
        $page = (int) ($input['page'] ?? 1);
        $total = (int) ($this->family->countPersons($query)[0]['total'] ?? 0);
        $people = new LengthAwarePaginator($this->family->searchPersons($query, ($page - 1) * 10, 10), $total, 10, $page, [
            'path' => route('persons.index'), 'query' => $request->only('q'),
        ]);

        return view('persons.index', compact('people', 'query'));
    }

    public function create(): View
    {
        return view('persons.form', ['person' => null]);
    }

    public function store(PersonRequest $request): RedirectResponse
    {
        $person = $this->service->createPerson($request->personData());

        return redirect()->route('persons.show', $person['id'])->with('success', 'Đã thêm thành viên.');
    }

    public function show(string $id): View
    {
        return view('persons.show', $this->service->details($id));
    }

    public function edit(string $id): View
    {
        return view('persons.form', ['person' => $this->service->person($id)]);
    }

    public function update(PersonRequest $request, string $id): RedirectResponse
    {
        $this->service->updatePerson($id, $request->personData());

        return redirect()->route('persons.show', $id)->with('success', 'Đã cập nhật thành viên.');
    }

    public function confirmDelete(string $id): View
    {
        return view('persons.delete', [
            'person' => $this->service->person($id),
            'relationshipCount' => $this->family->relationshipCount($id)[0]['total'] ?? 0,
        ]);
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->service->deletePerson($id);

        return redirect()->route('persons.index')->with('success', 'Đã xóa thành viên và các quan hệ liên quan.');
    }
}
