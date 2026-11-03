@extends('layouts.app')
@section('title', 'Cây gia phả')
@section('content')
    <h1 class="h2 mb-2">Cây gia phả</h1>
    <p class="text-secondary mb-4">Các thế hệ xếp từ trên xuống. Vợ chồng nằm cạnh nhau; con có cùng cha mẹ được nối thành từng nhánh, xếp theo năm sinh từ trái sang phải.</p>
    <form method="get" class="card card-body mb-4"><label for="root" class="form-label">Thành viên gốc của nhánh</label><div class="d-flex flex-wrap gap-2"><input name="root" id="root" list="people" maxlength="64" class="form-control w-auto flex-grow-1" value="{{ $graph['root'] }}"><button class="btn btn-success">Xem nhánh</button><button name="all" value="1" class="btn btn-outline-success">Hiện toàn bộ</button></div><datalist id="people">@foreach($people as $row)<option value="{{ $row['person']['id'] }}">{{ $row['person']['name'] }}</option>@endforeach</datalist></form>
    @if($message)<div class="alert alert-warning">{{ $message }}</div>@endif
    <section class="card card-body p-4"><h2 class="h5 mb-3">{{ $all ? 'Toàn bộ gia phả' : 'Nhánh của '.$graph['root'] }}</h2>@include('graph', ['hierarchical' => true])</section>
@endsection
