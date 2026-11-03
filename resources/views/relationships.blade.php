@extends('layouts.app')
@section('title', 'Quản lý quan hệ')
@section('content')
    <h1 class="h2">Quản lý quan hệ</h1><p class="text-secondary mb-4">Chọn mã từ danh sách gợi ý. Quan hệ cha mẹ – con có hướng; quan hệ vợ chồng được tra cứu hai chiều.</p>
    <datalist id="people">@foreach($people as $row)<option value="{{ $row['person']['id'] }}">{{ $row['person']['name'] }}</option>@endforeach</datalist>
    <div class="row g-4">
    @foreach([['parent', 'store', 'Thêm cha mẹ – con'], ['parent', 'destroy', 'Xóa cha mẹ – con'], ['spouse', 'store', 'Thêm vợ chồng'], ['spouse', 'destroy', 'Xóa vợ chồng']] as [$type, $action, $title])
        <section class="col-md-6"><form method="post" action="{{ route('relationships.'.$type.'.'.$action) }}" class="card card-body h-100 p-4">@csrf @if($action === 'destroy') @method('DELETE') @endif
            <h2 class="h5 mb-3">{{ $title }}</h2>
            @foreach($type === 'parent' ? ['parentId' => 'Cha/mẹ', 'childId' => 'Con'] : ['a' => 'Người thứ nhất', 'b' => 'Người thứ hai'] as $field => $label)
                <div class="mb-3"><label for="{{ $type.'-'.$action.'-'.$field }}" class="form-label">{{ $label }}</label><input id="{{ $type.'-'.$action.'-'.$field }}" name="{{ $field }}" list="people" class="form-control" required maxlength="64" value="{{ old($field) }}" placeholder="Ví dụ: P010"></div>
            @endforeach
            @if($type === 'spouse' && $action === 'store')<div class="mb-3"><label for="marriageYear" class="form-label">Năm kết hôn (không bắt buộc)</label><input type="number" id="marriageYear" name="marriageYear" min="1000" max="{{ date('Y') }}" value="{{ old('marriageYear') }}" class="form-control"></div>@endif
            <button class="btn btn-{{ $action === 'store' ? 'success' : 'outline-danger' }} mt-auto">{{ $title }}</button>
        </form></section>
    @endforeach
    </div>
@endsection
