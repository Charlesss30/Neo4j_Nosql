@extends('layouts.app')
@section('title', 'Kết nối chưa sẵn sàng')
@section('content')
    <div class="alert alert-warning" role="alert"><h1 class="h4">Chưa thể tải gia phả</h1><p>{{ $message }}</p><a href="{{ route('dashboard') }}">Trở về tổng quan</a></div>
@endsection
