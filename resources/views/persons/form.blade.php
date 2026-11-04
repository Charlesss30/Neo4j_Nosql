@extends('layouts.app')
@section('title', $person ? 'Sửa thành viên' : 'Thêm thành viên')
@section('content')
    <h1 class="h2 mb-4">{{ $person ? 'Sửa thành viên '.$person['id'] : 'Thêm thành viên' }}</h1>
    <form method="post" action="{{ $person ? route('persons.update', $person['id']) : route('persons.store') }}" class="card card-body p-4">
        @csrf
        @if($person) @method('PUT') @endif
        @if(!$person)
            <input type="hidden" name="return_to" value="{{ old('return_to', $context['return_to'] ?? 'person') }}">
            <input type="hidden" name="tree_root" value="{{ old('tree_root', $context['tree_root'] ?? '') }}">
            <input type="hidden" name="tree_all" value="{{ old('tree_all', $context['tree_all'] ?? 0) }}">
            <fieldset class="border rounded p-3 mb-4">
                <legend class="float-none w-auto fs-6 px-2">Gắn quan hệ ngay khi thêm người</legend>
                <p class="small text-secondary">Chọn vai trò của người mới với thành viên đã có. Thông tin người và quan hệ được lưu cùng nhau; nếu có lỗi, cả hai đều chưa được lưu.</p>
                <div class="row g-3">
                    <div class="col-md-5"><label for="relation_type" class="form-label">Người mới là</label><select id="relation_type" name="relation_type" class="form-select">
                        <option value="">Thành viên độc lập</option>
                        @foreach(['child' => 'Con của', 'parent' => 'Cha/mẹ của', 'spouse' => 'Vợ/chồng của'] as $value => $label)<option value="{{ $value }}" @selected(old('relation_type', $context['relation_type'] ?? '') === $value)>{{ $label }}</option>@endforeach
                    </select></div>
                    <div class="col-md-7"><label for="relative_id" class="form-label">Thành viên đã có</label><input id="relative_id" name="relative_id" list="existing-people" class="form-control" maxlength="64" value="{{ old('relative_id', $context['relative_id'] ?? '') }}" placeholder="Chọn hoặc nhập mã thành viên">@if($relative ?? null)<div class="form-text">Đang thêm người thân cho {{ $relative['name'] }} ({{ $relative['id'] }}).</div>@endif</div>
                    <div class="col-md-6" id="second-parent-field"><label for="second_parent_id" class="form-label">Cha/mẹ thứ hai của người mới (tùy chọn)</label><input id="second_parent_id" name="second_parent_id" list="existing-people" class="form-control" maxlength="64" value="{{ old('second_parent_id') }}"><div class="form-text">Chỉ dùng khi thêm con. Không tự tạo quan hệ vợ/chồng giữa hai cha mẹ.</div></div>
                    <div class="col-md-6" id="marriage-year-field"><label for="marriageYear" class="form-label">Năm kết hôn (tùy chọn)</label><input type="number" id="marriageYear" name="marriageYear" class="form-control" min="1000" max="{{ date('Y') }}" value="{{ old('marriageYear') }}"><div class="form-text">Chỉ dùng khi thêm vợ/chồng.</div></div>
                </div>
                <datalist id="existing-people">@foreach($people as $row)<option value="{{ $row['person']['id'] }}">{{ $row['person']['name'] }}</option>@endforeach</datalist>
            </fieldset>
            @push('scripts')
                <script>
                    (() => {
                        const type = document.getElementById('relation_type');
                        const update = () => {
                            for (const [field, enabled] of [['second-parent-field', type.value === 'child'], ['marriage-year-field', type.value === 'spouse']]) {
                                const container = document.getElementById(field);
                                container.hidden = !enabled;
                                container.querySelector('input').disabled = !enabled;
                            }
                            document.getElementById('relative_id').required = Boolean(type.value);
                            document.getElementById('relative_id').disabled = !type.value;
                        };
                        type.addEventListener('change', update);
                        update();
                    })();
                </script>
            @endpush
        @endif
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
