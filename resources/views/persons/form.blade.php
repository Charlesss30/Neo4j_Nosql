@extends('layouts.app')
@section('title', $person ? 'Sửa thành viên' : 'Thêm thành viên')
@section('content')
    <h1 class="h2 mb-4">{{ $person ? 'Sửa thành viên '.$person['id'] : 'Thêm thành viên' }}</h1>
    <form method="post" action="{{ $person ? route('persons.update', $person['id']) : route('persons.store') }}" class="card card-body p-4">
        @csrf
        @if($person) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-8"><label for="name" class="form-label">Họ và tên</label><input id="name" name="name" class="form-control" required maxlength="100" value="{{ old('name', $person['name'] ?? '') }}"></div>
            <div class="col-md-4"><label for="gender" class="form-label">Giới tính</label><select id="gender" name="gender" class="form-select" required><option value="M" @selected(old('gender', $person['gender'] ?? 'M') === 'M')>Nam</option><option value="F" @selected(old('gender', $person['gender'] ?? 'M') === 'F')>Nữ</option></select></div>
            @foreach(['birthYear' => 'Năm sinh', 'deathYear' => 'Năm mất (nếu có)'] as $field => $label)
                <div class="col-md-6"><label for="{{ $field }}" class="form-label">{{ $label }}</label><input type="number" id="{{ $field }}" name="{{ $field }}" class="form-control" min="1000" max="{{ date('Y') }}" @required($field === 'birthYear') value="{{ old($field, $person[$field] ?? '') }}"></div>
            @endforeach
            <div class="col-12"><label for="birthPlace" class="form-label">Nơi sinh</label><input id="birthPlace" name="birthPlace" class="form-control" maxlength="100" value="{{ old('birthPlace', $person['birthPlace'] ?? '') }}"></div>
            <div class="col-12"><label for="note" class="form-label">Ghi chú</label><textarea id="note" name="note" class="form-control" rows="3" maxlength="500">{{ old('note', $person['note'] ?? '') }}</textarea></div>
        </div>
        <div class="d-flex gap-2 mt-4"><button class="btn btn-success">Lưu thành viên</button><a class="btn btn-outline-secondary" href="{{ $person ? route('persons.show', $person['id']) : route('persons.index') }}">Hủy</a></div>
    </form>
@endsection
