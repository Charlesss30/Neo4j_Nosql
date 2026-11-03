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
