@extends('layouts.app')
@section('title', 'Xác nhận xóa')
@section('content')
    <div class="card card-body p-4"><h1 class="h3">Xác nhận xóa thành viên</h1><p>@include('persons._link')</p><p>Thành viên này có <strong>{{ $relationshipCount }}</strong> quan hệ. Xóa thành viên sẽ xóa cả các quan hệ đó; những thành viên khác vẫn được giữ lại.</p><form method="post" action="{{ route('persons.destroy', $person['id']) }}">@csrf @method('DELETE')<button class="btn btn-danger">Xác nhận xóa</button> <a class="btn btn-outline-secondary" href="{{ route('persons.show', $person['id']) }}">Hủy</a></form></div>
@endsection
