@extends('layouts.app')
@section('title', 'Tổng quan')
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="eyebrow mb-2">Kết nối các thế hệ</div>
            <h1 class="fw-bold mb-2">Tổng quan gia phả</h1>
            <p class="text-secondary mb-0">Theo dõi thành viên và những mối liên kết trong dòng họ.</p>
        </div>
        <span class="badge rounded-pill text-bg-{{ $connected ? 'success' : 'danger' }} px-3 py-2" role="status">Neo4j · {{ $connected ? 'Đã kết nối' : 'Chưa kết nối' }}</span>
    </div>
    @if ($error)
        <div class="alert alert-warning" role="alert">
            <strong>{{ $error }}</strong>
            <div class="small mt-1">Sau khi kiểm tra cấu hình, tải lại trang để thử kết nối lại.</div>
        </div>
    @endif
    <div class="row g-3 mb-4">
        @foreach ([['Tổng thành viên', $life['living'] + $life['deceased'], 'Tất cả thành viên trong gia phả'], ['Còn sống', $life['living'], 'Chưa ghi nhận năm mất'], ['Đã mất', $life['deceased'], 'Đã ghi nhận năm mất']] as [$label, $value, $description])
            <div class="col-md-4"><section class="card h-100"><div class="card-body p-4">
                <h2 class="h6 text-secondary">{{ $label }}</h2>
                <div class="stat-number my-2">{{ $statisticsAvailable ? number_format($value) : '—' }}</div>
                <p class="small text-secondary mb-0">{{ $description }}</p>
            </div></section></div>
        @endforeach
    </div>
    @if($statisticsAvailable)
        <div class="row g-4 mb-4">
            <section class="col-md-7"><div class="card card-body h-100 p-4"><h2 class="h5">Thành viên theo thế hệ</h2><p class="small text-secondary">Tính từ P001, gồm vợ/chồng của các thành viên trong nhánh.</p><div style="height: 260px;"><canvas id="generation-chart" role="img" aria-label="Biểu đồ thành viên theo thế hệ"></canvas></div><p class="small mt-3 mb-0">@forelse($generations as $row)Thế hệ {{ $row['generation'] }}: {{ $row['total'] }}@if(!$loop->last) · @endif @empty Chưa có dữ liệu thế hệ từ P001. @endforelse</p></div></section>
            <section class="col-md-5"><div class="card card-body h-100 p-4"><h2 class="h5">Tỷ lệ giới tính</h2><div style="height: 300px;"><canvas id="gender-chart" role="img" aria-label="Biểu đồ tỷ lệ giới tính"></canvas></div><p id="chart-message" class="small text-secondary mt-2 mb-0">Số liệu chi tiết có trong các bảng bên dưới.</p></div></section>
        </div>
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js"></script>
            <script>
                (() => {
                    if (typeof Chart === 'undefined') {
                        document.getElementById('chart-message').textContent = 'Chưa tải được biểu đồ. Hãy kiểm tra Internet; bảng số liệu vẫn có ở bên dưới.';
                        return;
                    }
                    const generations = {{ Illuminate\Support\Js::from($generations) }};
                    const gender = {{ Illuminate\Support\Js::from($gender) }};
                    new Chart(document.getElementById('generation-chart'), {
                        type: 'bar', data: {labels: generations.map(row => 'Thế hệ ' + row.generation), datasets: [{label: 'Thành viên', data: generations.map(row => row.total), backgroundColor: '#507766'}]},
                        options: {maintainAspectRatio: false, scales: {y: {beginAtZero: true, ticks: {precision: 0}}}, plugins: {legend: {display: false}}},
                    });
                    new Chart(document.getElementById('gender-chart'), {
                        type: 'doughnut', data: {labels: gender.map(row => row.gender === 'M' ? 'Nam' : 'Nữ'), datasets: [{data: gender.map(row => row.total), backgroundColor: gender.map(row => row.gender === 'M' ? '#76aeea' : '#e8a1bc')}]},
                        options: {maintainAspectRatio: false},
                    });
                })();
            </script>
        @endpush
    @endif
    <div class="row g-4">
        <div class="col-lg-7">
            <section class="card h-100">
                <div class="card-body p-4 pb-2"><h2 class="h5 mb-2">Thành viên có nhiều con nhất</h2><p class="small text-secondary mb-2">Năm thành viên có số con trực tiếp cao nhất.</p></div>
                <div class="table-responsive"><table class="table mb-0 align-middle">
                    <thead class="table-light"><tr><th scope="col">Mã</th><th scope="col">Họ và tên</th><th scope="col" class="text-end">Số con</th></tr></thead>
                    <tbody>
                    @if ($statisticsAvailable)
                        @forelse ($topParents as $parent)
                            <tr><td class="text-secondary">{{ $parent['id'] }}</td><td><a href="{{ route('persons.show', $parent['id']) }}">{{ $parent['name'] }}</a></td><td class="text-end fw-semibold">{{ $parent['childCount'] }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-secondary">Chưa có quan hệ cha mẹ – con.</td></tr>
                        @endforelse
                    @else
                        <tr><td colspan="3" class="text-secondary">Thống kê chưa sẵn sàng.</td></tr>
                    @endif
                    </tbody>
                </table></div>
            </section>
        </div>
        <div class="col-lg-5 d-flex flex-column gap-4">
            <section class="card"><div class="card-body p-4">
                <h2 class="h5 mb-3">Thành viên theo giới tính</h2>
                @if ($statisticsAvailable)
                    @forelse ($gender as $row)
                        <div class="d-flex justify-content-between py-2 border-bottom"><span>{{ match($row['gender']) { 'M' => 'Nam', 'F' => 'Nữ', default => 'Chưa xác định' } }}</span><strong>{{ $row['total'] }}</strong></div>
                    @empty
                        <p class="text-secondary mb-0">Chưa có thành viên. Hãy nạp dữ liệu mẫu của Member A.</p>
                    @endforelse
                @else
                    <p class="text-secondary mb-0">Thống kê chưa sẵn sàng.</p>
                @endif
            </div></section>
            <section class="card"><div class="card-body p-4">
                <h2 class="h5 mb-3">Thành viên sinh sớm nhất</h2>
                @if ($statisticsAvailable && $oldest)
                    <a class="fs-5 fw-semibold" href="{{ route('persons.show', $oldest['id']) }}">{{ $oldest['name'] }}</a>
                    <p class="text-secondary mt-2 mb-0">Năm sinh {{ $oldest['birthYear'] }}@if(isset($oldest['deathYear'])) · Đã mất năm {{ $oldest['deathYear'] }}@endif</p>
                @else
                    <p class="text-secondary mb-0">{{ $statisticsAvailable ? 'Chưa có thông tin năm sinh.' : 'Thống kê chưa sẵn sàng.' }}</p>
                @endif
            </div></section>
        </div>
    </div>
@endsection
