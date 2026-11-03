<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Gia phả') · Gia phả Neo4j</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7f5; color: #20362d; }
        .navbar { background: #143c30; }
        .navbar-brand { font-weight: 700; }
        .navbar .nav-link { color: #d9e8e0; }
        .navbar .nav-link.active { color: #fff; text-decoration: underline; text-underline-offset: .4rem; }
        .card { border: 1px solid #dce6df; border-radius: 1rem; box-shadow: 0 3px 16px #143c3005; }
        .eyebrow { color: #507766; font-size: .78rem; letter-spacing: .12em; text-transform: uppercase; font-weight: 700; }
        .stat-number { font-size: 2.5rem; font-weight: 700; line-height: 1.3; }
        a { color: #216448; }
        .table > :not(caption) > * > * { padding: .9rem 1rem; }
    </style>
    @stack('styles')
</head>
<body>
<nav class="navbar navbar-expand-xl navbar-dark" aria-label="Điều hướng chính">
    <div class="container py-2">
        <a class="navbar-brand me-4" href="{{ route('dashboard') }}">Gia phả <span class="fw-light opacity-75">/ Neo4j</span></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Mở menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto gap-xl-1">
                @foreach ([['/', 'Tổng quan'], ['/persons', 'Thành viên'], ['/relationships', 'Quan hệ'], ['/tree', 'Cây gia phả'], ['/relation', 'Tra cứu quan hệ'], ['/admin', 'Quản trị']] as [$path, $label])
                    @php($active = $path === '/' ? request()->is('/') : request()->is(ltrim($path, '/'), ltrim($path, '/').'/*'))
                    <li class="nav-item"><a class="nav-link {{ $active ? 'active' : '' }}" href="{{ url($path) }}" @if($active) aria-current="page" @endif>{{ $label }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
</nav>
<main class="container py-4 py-lg-5">
    @foreach (['success' => 'success', 'error' => 'danger'] as $key => $style)
        @if (session($key))
            <div class="alert alert-{{ $style }} alert-dismissible fade show" role="alert">
                {{ session($key) }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng thông báo"></button>
            </div>
        @endif
    @endforeach
    @if ($errors->any())
        <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>
<footer class="container pb-4 small text-secondary">Đồ án NoSQL · Quản lý thông tin gia phả bằng cơ sở dữ liệu đồ thị Neo4j</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
