@extends('layouts.app')
@section('title', 'Cây gia phả')
@section('content')
    <h1 class="h2 mb-2">Cây gia phả</h1>
    <p class="text-secondary mb-4">Các thế hệ xếp từ trên xuống. Vợ chồng nằm cạnh nhau; con có cùng cha mẹ được nối thành từng nhánh, xếp theo năm sinh từ trái sang phải.</p>
    <form method="get" class="card card-body mb-4"><label for="root" class="form-label">Thành viên gốc của nhánh</label><div class="d-flex flex-wrap gap-2"><input name="root" id="root" list="people" maxlength="64" class="form-control w-auto flex-grow-1" value="{{ $graph['root'] }}"><button class="btn btn-success">Xem nhánh</button><button name="all" value="1" class="btn btn-outline-success">Hiện toàn bộ</button></div><datalist id="people">@foreach($people as $row)<option value="{{ $row['person']['id'] }}">{{ $row['person']['name'] }}</option>@endforeach</datalist></form>
    @if($message)<div class="alert alert-warning">{{ $message }}</div>@endif
    @if($graph['nodes'] !== [])
        <form method="get" action="{{ route('persons.create') }}" class="card card-body mb-4" id="tree-add-relative">
            <input type="hidden" name="return_to" value="tree"><input type="hidden" name="tree_root" value="{{ $graph['root'] }}"><input type="hidden" name="tree_all" value="{{ $all ? 1 : 0 }}">
            <div class="row g-2 align-items-end">
                <div class="col-lg-5"><label for="tree-relative" class="form-label">Thêm người thân cho</label><select name="relative_id" id="tree-relative" class="form-select">@foreach($graph['nodes'] as $node)<option value="{{ $node['id'] }}" @selected($node['id'] === request('selected', $graph['root']))>{{ $node['name'] }} ({{ $node['id'] }})</option>@endforeach</select></div>
                <div class="col-lg-4"><label for="tree-relation-type" class="form-label">Người mới là</label><select name="relation_type" id="tree-relation-type" class="form-select"><option value="child">Con</option><option value="parent">Cha/mẹ</option><option value="spouse">Vợ/chồng</option></select></div>
                <div class="col-lg-3"><button class="btn btn-success w-100">Thêm người kèm quan hệ</button></div>
            </div>
            <div class="form-text">Bấm một thành viên trên cây để chọn ở đây; bấm đúp để mở hồ sơ.</div>
        </form>
    @endif
    <section class="card card-body p-4"><h2 class="h5 mb-3">{{ $all ? 'Toàn bộ gia phả' : 'Nhánh của '.$graph['root'] }}</h2>@include('graph', ['hierarchical' => true])</section>
@endsection
