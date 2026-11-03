@extends('layouts.app')
@section('title', $person['name'])
@section('content')
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><div><div class="eyebrow">{{ $person['id'] }}</div><h1 class="h2 mt-2">{{ $person['gender'] === 'M' ? '♂' : '♀' }} {{ $person['name'] }}@if(isset($person['deathYear'])) †@endif</h1><p>{{ $person['birthYear'] ?? '—' }}@if(isset($person['deathYear'])) – {{ $person['deathYear'] }}@endif · {{ $person['birthPlace'] ?? 'Chưa rõ nơi sinh' }}</p><p class="text-secondary text-break">{{ $person['note'] ?? '' }}</p></div><div><a class="btn btn-outline-success" href="{{ route('persons.edit', $person['id']) }}">Sửa</a> <a class="btn btn-outline-danger" href="{{ route('persons.confirm-delete', $person['id']) }}">Xóa</a> <a class="btn btn-success" href="{{ route('tree.index', ['root' => $person['id']]) }}">Xem nhánh</a></div></div>
    <div class="row g-3">
        @foreach(['parents' => 'Cha mẹ', 'spouses' => 'Vợ/chồng', 'children' => 'Con', 'siblings' => 'Anh chị em', 'uncles' => 'Chú bác cô dì', 'cousins' => 'Anh chị em họ', 'ancestors' => 'Tổ tiên', 'descendants' => 'Hậu duệ'] as $key => $title)
            <section class="col-md-6"><div class="card h-100"><div class="card-body"><h2 class="h5">{{ $title }}</h2><ul class="list-unstyled mb-0" data-section="{{ $key }}">
            @forelse($sections[$key] as $row)
                <li class="py-1">@include('persons._link', ['person' => $row['person']])@if(isset($row['marriageYear'])) · Kết hôn {{ $row['marriageYear'] }}@endif</li>
            @empty
                <li class="text-secondary">Chưa có thông tin.</li>
            @endforelse
            </ul></div></div></section>
        @endforeach
    </div>
@endsection
