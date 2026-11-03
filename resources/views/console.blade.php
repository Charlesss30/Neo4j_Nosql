@extends('layouts.app')
@section('title', 'Cypher Console')
@section('content')
    <div class="mb-4">
        <div class="eyebrow">Khám phá dữ liệu</div>
        <h1 class="h2 mt-1">Cypher Console</h1>
        <p class="text-secondary mb-0">Chạy truy vấn đọc và xem kết quả trực tiếp từ Neo4j.</p>
    </div>
    <div class="alert alert-warning" role="note">Bộ lọc từ khóa, không phải phân quyền thật. Neo4j Community không hỗ trợ RBAC.</div>
    <div class="card mb-4"><div class="card-body p-4">
        <form method="POST" action="{{ route('console.run') }}">
            @csrf
            <label for="query" class="form-label fw-semibold">Truy vấn Cypher</label>
            <textarea id="query" name="query" class="form-control font-monospace" rows="8" maxlength="2000" required placeholder="MATCH (p:Person) RETURN p {.*} AS person LIMIT 10;">{{ $query }}</textarea>
            <div class="d-flex justify-content-between align-items-center gap-3 mt-3">
                <span class="small text-secondary">Một câu lệnh, tối đa 2000 ký tự. Hiển thị tối đa 500 dòng.</span>
                <button class="btn btn-success" type="submit">Chạy truy vấn</button>
            </div>
        </form>
        <hr>
        <div class="small fw-semibold mb-2">Chọn truy vấn mẫu để điền vào ô lệnh</div>
        <div class="d-flex flex-wrap gap-2">
            @foreach ($examples as $example)
                <button type="button" class="btn btn-sm btn-outline-secondary" data-query="{{ $example['query'] }}">{{ $example['label'] }}</button>
            @endforeach
        </div>
    </div></div>
    @if ($error)
        <div class="alert alert-danger" role="alert">{{ $error }}</div>
    @endif
    @if ($elapsed !== null)
        <div class="small text-secondary mb-3">Thời gian thực thi: <strong>{{ $elapsed }} ms</strong></div>
    @endif
    @if ($executed)
        <div class="card"><div class="card-body p-4">
            <h2 class="h5">Kết quả <span class="badge text-bg-light">{{ count($rows) }}/{{ $total }} dòng</span></h2>
            @if ($total > 500)
                <div class="alert alert-info">Kết quả có {{ $total }} dòng. Chỉ hiển thị 500 dòng đầu tiên.</div>
            @endif
            @if ($rows === [])
                <p class="text-secondary mb-0">Truy vấn không trả về dòng nào.</p>
            @else
                <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                    <thead><tr>@foreach ($columns as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
                    <tbody>@foreach ($rows as $row)<tr>@foreach ($columns as $column)
                        @php($value = $row[$column] ?? null)
                        <td class="font-monospace small text-break">{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) : (is_bool($value) ? ($value ? 'true' : 'false') : ($value ?? 'null')) }}</td>
                    @endforeach</tr>@endforeach</tbody>
                </table></div>
            @endif
        </div></div>
    @endif
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-query]').forEach(button => {
    button.addEventListener('click', () => {
        const editor = document.getElementById('query');
        editor.value = button.dataset.query;
        editor.focus();
    });
});
</script>
@endpush
