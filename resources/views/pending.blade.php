@extends('layouts.app')
@section('title', 'Đang phát triển')
@section('content')
    <div class="card"><div class="card-body p-4 p-lg-5">
        <div class="eyebrow mb-3">Gia phả Neo4j</div>
        <h1 class="h3">Chức năng đang phát triển</h1>
        <p class="text-secondary my-3">{{ $message }}</p>
        <a class="btn btn-success" href="{{ route('dashboard') }}">Về tổng quan</a>
    </div></div>
@endsection
