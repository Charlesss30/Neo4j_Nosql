@extends('layouts.app')
@section('title', 'Tra cứu quan hệ')
@section('content')
    <h1 class="h2 mb-4">Tra cứu quan hệ</h1>
    <form method="get" class="card card-body mb-4"><div class="row g-3 align-items-end">@foreach(['a' => 'Người thứ nhất', 'b' => 'Người thứ hai'] as $field => $label)<div class="col-md-5"><label for="{{ $field }}" class="form-label">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" list="people" maxlength="64" required class="form-control" value="{{ $input[$field] ?? '' }}"></div>@endforeach<div class="col-md-2"><button class="btn btn-success w-100">Tra cứu</button></div></div><datalist id="people">@foreach($people as $row)<option value="{{ $row['person']['id'] }}">{{ $row['person']['name'] }}</option>@endforeach</datalist></form>
    @if($message)<div class="alert alert-warning">{{ $message }}</div>@endif
    @if($graph)<section class="card card-body p-4"><h2 class="h5">Đường đi quan hệ ngắn nhất</h2><p class="lead" id="relation-sentence">{{ $graph['sentence'] }}</p><p class="small text-secondary">Có thể tồn tại nhiều đường đi cùng độ dài. Câu diễn giải mô tả từng liên kết trên đường đi Neo4j trả về.</p>@include('graph', ['hierarchical' => false])</section>@endif
@endsection
