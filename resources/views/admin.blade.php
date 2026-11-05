@extends('layouts.app')
@section('title', 'Quản trị Neo4j')
@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div><div class="eyebrow">Cấu trúc và hiệu năng</div><h1 class="h2 mt-1">Quản trị Neo4j</h1><p class="text-secondary mb-0">Theo dõi schema, thống kê đồ thị và kế hoạch thực thi Cypher.</p></div>
        <span class="badge text-bg-light border">Môi trường: {{ app()->environment() }}</span>
    </div>
    @if ($error)<div class="alert alert-danger" role="alert">{{ $error }}</div>@endif
    <section id="backup-restore" class="card border-success mb-4" aria-labelledby="backup-heading">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                <div><div class="eyebrow">Bảo toàn dữ liệu</div><h2 id="backup-heading" class="h4 mt-1">Sao lưu &amp; khôi phục gia phả</h2></div>
                <span class="badge text-bg-light border">Tệp JSON · tối đa 10 MB</span>
            </div>
            <p class="text-secondary small">Bản sao lưu chứa toàn bộ thông tin thành viên, quan hệ cha mẹ–con và vợ chồng. Giữ tệp trên máy để có thể khôi phục khi cần. Tài khoản, cấu hình và các index riêng của Neo4j không nằm trong tệp này.</p>
            @if ($backupError)<div class="alert alert-danger" role="alert">{{ $backupError }}</div>@endif
            <div class="row g-4">
                <div class="col-lg-5">
                    <h3 class="h5">Tạo bản sao lưu</h3>
                    <p class="text-secondary small">Lưu một bản trên máy chủ và tải tệp JSON về máy của bạn.</p>
                    <form method="POST" action="{{ route('admin.backups.store') }}">
                        @csrf
                        <button id="create-backup" class="btn btn-success" type="submit" @disabled(! $local)>Tạo &amp; tải bản sao lưu</button>
                    </form>
                </div>
                <div class="col-lg-7">
                    <h3 class="h5">Khôi phục từ bản sao lưu</h3>
                    <p class="text-secondary small">Thay thế gia phả hiện tại bằng dữ liệu trong tệp. Hệ thống tự lưu bản dữ liệu trước khi khôi phục vào lịch sử bên dưới. Nếu có lỗi trong quá trình ghi, giao dịch được hoàn tác.</p>
                    <form method="POST" action="{{ route('admin.restore') }}" enctype="multipart/form-data">
                        @csrf
                        <label class="form-label" for="restore-backup">Tệp sao lưu gia phả (.json)</label>
                        <input id="restore-backup" name="backup" type="file" class="form-control mb-3" accept=".json,application/json" required @disabled(! $local)>
                        <div class="form-check mb-3">
                            <input id="confirm-restore" name="confirm_restore" type="checkbox" class="form-check-input" value="yes" required @disabled(! $local)>
                            <label class="form-check-label small" for="confirm-restore">Tôi xác nhận thay toàn bộ gia phả hiện tại bằng bản sao lưu đã chọn.</label>
                        </div>
                        <button id="restore-backup-button" class="btn btn-outline-danger" type="submit" @disabled(! $local)>Khôi phục từ tệp</button>
                    </form>
                </div>
            </div>
            <hr class="my-4">
            <h3 class="h6">10 bản sao lưu gần nhất <span class="text-secondary fw-normal">(giờ Việt Nam)</span></h3>
            <div class="table-responsive">
                <table class="table table-hover small align-middle mb-0">
                    <thead><tr><th>Thời gian</th><th>Loại bản sao</th><th>Thành viên</th><th>Quan hệ</th><th>Tệp sao lưu</th></tr></thead>
                    <tbody>
                        @forelse ($backups as $backup)
                            <tr>
                                <td class="text-nowrap">{{ \Carbon\Carbon::parse($backup['created_at'])->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $backup['reason'] === 'before_restore' ? 'Tự lưu trước khôi phục' : 'Sao lưu thủ công' }}</td>
                                <td>{{ $backup['counts']['persons'] }}</td>
                                <td>{{ $backup['counts']['parentOf'] + $backup['counts']['spouseOf'] }}</td>
                                <td><a class="btn btn-sm btn-outline-success text-nowrap" href="{{ route('admin.backups.download', $backup['id']) }}">Tải JSON</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-secondary">Chưa có bản sao lưu. Bấm “Tạo &amp; tải bản sao lưu” để lưu dữ liệu hiện tại.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @unless ($local)<p class="small text-secondary mt-3 mb-0">Sao lưu và khôi phục chỉ bật khi APP_ENV=local.</p>@endunless
        </div>
    </section>
    <div class="row g-3 mb-4">
        @foreach ([['Tổng nút', $nodeTotal], ['Tổng quan hệ', $relationshipTotal]] as [$label, $total])
            <div class="col-md-6"><div class="card h-100"><div class="card-body p-4"><div class="eyebrow">{{ $label }}</div><div class="stat-number mt-2">{{ $total ?? '—' }}</div></div></div></div>
        @endforeach
        @foreach ([['Theo nhãn nút', $labels, 'label'], ['Theo loại quan hệ', $relationshipTypes, 'type']] as [$title, $items, $key])
            <div class="col-md-6"><div class="card h-100"><div class="card-body p-4"><h2 class="h5">{{ $title }}</h2>
                <table class="table mb-0"><thead><tr><th>Tên</th><th>Số lượng</th></tr></thead><tbody>
                    @forelse ($items as $item)<tr><td>{{ $item[$key] }}</td><td>{{ $item['total'] }}</td></tr>@empty<tr><td colspan="2" class="text-secondary">Chưa có dữ liệu.</td></tr>@endforelse
                </tbody></table>
            </div></div></div>
        @endforeach
    </div>
    @foreach ([['Constraints', $constraints], ['Indexes', $indexes]] as [$title, $items])
        <div class="card mb-4"><div class="card-body p-4"><h2 class="h5">{{ $title }}</h2>
            @if ($items === [])<p class="text-secondary mb-0">Không có thông tin {{ $title }}.</p>@else
                @php($columns = array_keys($items[0]))
                <div class="table-responsive"><table class="table table-hover small mb-0"><thead><tr>@foreach ($columns as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
                    <tbody>@foreach ($items as $item)<tr>@foreach ($columns as $column)
                        @php($value = $item[$column] ?? null)
                        <td class="text-break">{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (is_bool($value) ? ($value ? 'true' : 'false') : ($value ?? 'null')) }}</td>
                    @endforeach</tr>@endforeach</tbody>
                </table></div>
            @endif
        </div></div>
    @endforeach
    <div class="card mb-4"><div class="card-body p-4">
        <h2 class="h5">PROFILE truy vấn mẫu</h2>
        <p class="text-secondary small">Chạy mẫu đọc từ danh mục để xem operator, số dòng và db hits thực tế.</p>
        <form method="POST" action="{{ route('admin.profile') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-9"><label class="form-label" for="sample">Truy vấn</label><select class="form-select" name="sample" id="sample">
                @foreach ($samples as $key => $sample)<option value="{{ $key }}" @selected($selected === $key)>{{ $sample['label'] }}</option>@endforeach
            </select></div>
            <div class="col-md-3"><button class="btn btn-success w-100" type="submit">Chạy PROFILE</button></div>
        </form>
        <hr class="my-4">
        <h3 class="h6">So sánh tra cứu tên bằng dấu bằng (=)</h3>
        <p class="text-secondary small">Dùng cùng truy vấn <code>WHERE p.name = $name</code> khi có và không có index. Thao tác tạm bỏ <code>person_name</code> rồi khôi phục trong <code>finally</code>; chỉ dùng ở local.</p>
        <form method="POST" action="{{ route('admin.profile') }}" class="row g-3 align-items-end">
            @csrf
            <input type="hidden" name="sample" value="nameIndex">
            <div class="col-md-9"><label class="form-label" for="lookup-name">Tên chính xác</label><input id="lookup-name" name="name" class="form-control" value="{{ $lookupName }}" maxlength="100" required @disabled(! $local)></div>
            <div class="col-md-3"><button class="btn btn-outline-success w-100" type="submit" @disabled(! $local)>So sánh index</button></div>
        </form>
        @unless ($local)<p class="small text-secondary mt-2 mb-0">Chỉ bật khi APP_ENV=local.</p>@endunless
    </div></div>
    @foreach ($profiles as $profile)
        <div class="card mb-4"><div class="card-body p-4">
            <h2 class="h5">{{ $profile['label'] }} <span class="badge text-bg-light">{{ $profile['mode'] }}</span></h2>
            <pre class="bg-light rounded p-3 small text-wrap">{{ $profile['query'] }}</pre>
            <p class="small text-secondary">Tham số: <code>{{ json_encode($profile['params'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></p>
            @if ($profile['mode'] === 'EXPLAIN')<div class="alert alert-info">Client không cung cấp PROFILE. Đang hiển thị EXPLAIN; số dòng là ước tính, không có db hits thực tế.</div>@endif
            <div class="d-flex flex-wrap gap-4 small mb-3"><span>Số dòng: <strong>{{ $profile['rows'] ?? '—' }}</strong></span><span>Tổng db hits: <strong>{{ $profile['dbHits'] ?? '—' }}</strong></span><span>Thời gian: <strong>{{ $profile['elapsed'] }} ms</strong></span></div>
            <div class="table-responsive"><table class="table small"><thead><tr><th>Operator</th><th>Rows</th><th>Db hits</th><th>Estimated rows</th><th>Chi tiết</th></tr></thead><tbody>
                @forelse ($profile['operators'] as $operator)<tr><td class="font-monospace" style="padding-left: {{ 1 + $operator['depth'] }}rem">{{ $operator['operator'] }}</td><td>{{ $operator['rows'] ?? '—' }}</td><td>{{ $operator['dbHits'] ?? '—' }}</td><td>{{ $operator['estimated'] ?? '—' }}</td><td class="text-break">{{ $operator['details'] }}</td></tr>@empty<tr><td colspan="5">Client không trả về kế hoạch thực thi.</td></tr>@endforelse
            </tbody></table></div>
            @if ($profile['planText'])<details class="mb-3"><summary class="small">Xem kế hoạch dạng văn bản</summary><pre class="small bg-light rounded p-3 mt-2">{{ $profile['planText'] }}</pre></details>@endif
            <details><summary class="small">Xem kết quả truy vấn</summary><pre class="small bg-light rounded p-3 mt-2">{{ json_encode($profile['results'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details>
        </div></div>
    @endforeach
    <div class="card border-danger"><div class="card-body p-4">
        <h2 class="h5">Đặt lại dữ liệu demo</h2>
        <p class="text-secondary small">Xóa toàn bộ nút và quan hệ, rồi nạp lại schema + seed từ tệp của A. Phần xóa và nạp seed dùng cùng một giao dịch; nếu lỗi, dữ liệu được hoàn tác.</p>
        <form method="POST" action="{{ route('admin.reset') }}">
            @csrf
            <div class="form-check mb-3"><input id="confirm-reset" class="form-check-input" type="checkbox" name="confirm_reset" value="yes" required @disabled(! $local)><label for="confirm-reset" class="form-check-label">Tôi xác nhận xóa dữ liệu demo hiện tại và nạp lại mẫu.</label></div>
            <button class="btn btn-outline-danger" type="submit" @disabled(! $local)>Đặt lại &amp; nạp dữ liệu mẫu</button>
        </form>
        @unless ($local)<p class="small text-secondary mt-2 mb-0">Chỉ bật khi APP_ENV=local.</p>@endunless
    </div></div>
@endsection
