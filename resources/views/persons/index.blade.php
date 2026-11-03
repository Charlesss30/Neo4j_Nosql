@extends('layouts.app')
@section('title', 'Thành viên')
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4"><h1 class="h2">Thành viên gia phả</h1><a class="btn btn-success" href="{{ route('persons.create') }}">Thêm thành viên</a></div>
    <form method="get" class="card card-body mb-4"><label class="form-label" for="q">Tìm theo họ tên</label><div class="input-group"><input id="q" name="q" class="form-control" value="{{ $query }}" maxlength="100"><button class="btn btn-success">Tìm kiếm</button></div><div class="form-text">Không phân biệt chữ hoa/thường; hãy gõ có dấu tiếng Việt.</div></form>
    <div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Thành viên</th><th>Năm sinh</th><th>Nơi sinh</th><th></th></tr></thead><tbody>
    @forelse($people as $row)
        @php($person = $row['person'])
        <tr><td>@include('persons._link')</td><td>{{ $person['birthYear'] ?? '—' }}</td><td>{{ $person['birthPlace'] ?? '—' }}</td><td><a href="{{ route('persons.edit', $person['id']) }}">Sửa</a></td></tr>
    @empty
        <tr><td colspan="4">Không có thành viên phù hợp.</td></tr>
    @endforelse
    </tbody></table></div></div>
    <p class="small text-secondary mt-3">Tổng {{ $people->total() }} thành viên</p>
    {{ $people->links('pagination::bootstrap-5') }}
@endsection
