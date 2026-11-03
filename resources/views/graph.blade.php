<div class="d-flex flex-wrap gap-3 small mb-3"><span>🔵 Nam</span><span>🌸 Nữ</span><span>† Màu xám: đã mất</span><span>→ Cha mẹ – con</span><span>┄ Vợ/chồng</span></div>
@if($graph['nodes'] === [])
    <p class="text-secondary">Không có dữ liệu để vẽ.</p>
@else
    <div id="network" class="border rounded bg-white" style="height: 620px;" role="img" aria-label="Đồ thị gia phả, bấm vào thành viên để xem chi tiết"></div>
    <p id="network-message" class="small text-secondary mt-2">Kéo để di chuyển, cuộn để phóng to; bấm thành viên để mở hồ sơ.</p>
    <p class="small">{{ count($graph['nodes']) }} thành viên · {{ count($graph['edges']) }} quan hệ</p>
    <details><summary>Danh sách thành viên trong đồ thị</summary><ul>@foreach($graph['nodes'] as $node)<li><a href="{{ route('persons.show', $node['id']) }}">{{ $node['name'] }} ({{ $node['id'] }})</a></li>@endforeach</ul></details>
    @push('scripts')
        <script src="https://unpkg.com/vis-network@9.1.9/standalone/umd/vis-network.min.js"></script>
        <script>
            (() => {
                const graph = {{ Illuminate\Support\Js::from($graph) }};
                const hierarchical = {{ Illuminate\Support\Js::from($hierarchical) }};
                if (typeof vis === 'undefined') {
                    document.getElementById('network-message').textContent = 'Chưa tải được thư viện vẽ đồ thị. Hãy kiểm tra Internet; danh sách thành viên vẫn có ở bên dưới.';
                    return;
                }
                const network = new vis.Network(document.getElementById('network'), {nodes: new vis.DataSet(graph.nodes), edges: new vis.DataSet(graph.edges)}, {
                    nodes: {shape: 'box', margin: 12, font: {size: 14}},
                    layout: {hierarchical: hierarchical ? {enabled: true, direction: 'UD', sortMethod: 'directed', levelSeparation: 120, nodeSpacing: 180} : false},
                    physics: hierarchical ? false : {stabilization: true},
                    interaction: {hover: true, navigationButtons: true},
                });
                network.on('click', ({nodes}) => {
                    if (nodes.length) window.location.href = {{ Illuminate\Support\Js::from(url('/persons')) }} + '/' + encodeURIComponent(nodes[0]);
                });
                window.familyNetwork = network;
            })();
        </script>
    @endpush
@endif
