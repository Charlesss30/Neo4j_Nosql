<div class="d-flex flex-wrap gap-3 small mb-3"><span>🔵 Nam</span><span>🌸 Nữ</span><span>† Màu xám: đã mất</span><span>→ Cha mẹ – con</span><span>┄ Vợ/chồng</span></div>
@if($graph['nodes'] === [])
    <p class="text-secondary">Không có dữ liệu để vẽ.</p>
@else
    @if($hierarchical)
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3" role="group" aria-label="Điều khiển phả đồ">
            <button type="button" id="network-fit" class="btn btn-sm btn-outline-success">Vừa khung</button>
            <button type="button" id="network-reset" class="btn btn-sm btn-outline-success">Cỡ chữ rõ</button>
            <button type="button" id="network-zoom-in" class="btn btn-sm btn-outline-secondary" aria-label="Phóng to">＋</button>
            <button type="button" id="network-zoom-out" class="btn btn-sm btn-outline-secondary" aria-label="Thu nhỏ">−</button>
            <span class="small text-secondary">Các đời từ trên xuống · Con lớn ở bên trái</span>
        </div>
    @endif
    <div id="network" class="border rounded" style="height: clamp(460px, 72vh, 820px); background: #fffdf8; min-width: 0;" role="img" aria-label="Đồ thị gia phả, bấm vào thành viên để xem chi tiết"></div>
    <p id="network-message" class="small text-secondary mt-2">Kéo để xem các nhánh, dùng nút ＋/− hoặc chụm hai ngón tay để đổi độ phóng; bấm thành viên để mở hồ sơ.</p>
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
                const pedigree = hierarchical && Array.isArray(graph.families);
                const container = document.getElementById('network');
                const nodes = graph.nodes.map(node => pedigree ? {...node, widthConstraint: {minimum: 156, maximum: 156}, heightConstraint: {minimum: 56}, margin: 12} : node);
                const edges = graph.edges.map(edge => ({...edge, hidden: pedigree && edge.type === 'PARENT_OF'}));
                const network = new vis.Network(container, {nodes: new vis.DataSet(nodes), edges: new vis.DataSet(edges)}, {
                    nodes: {shape: 'box', margin: 12, font: {size: 14, face: 'Arial', color: '#20362d'}},
                    layout: {improvedLayout: !pedigree},
                    physics: pedigree ? false : {stabilization: true},
                    interaction: {hover: true, navigationButtons: !pedigree, dragNodes: !pedigree},
                });
                if (pedigree) {
                    const byId = Object.fromEntries(nodes.map(node => [node.id, node]));
                    const bounds = {left: Math.min(...nodes.map(node => node.x)) - 240, right: Math.max(...nodes.map(node => node.x)) + 130, top: Math.min(...nodes.map(node => node.y)) - 90, bottom: Math.max(...nodes.map(node => node.y)) + 90};
                    const rows = [...new Set(nodes.map(node => node.level))].sort((a, b) => a - b);
                    const box = node => network.getBoundingBox(node.id);
                    const routes = [];
                    graph.families.forEach(family => {
                        const parents = family.parents.map(id => byId[id]);
                        const childrenByRow = {};
                        family.children.forEach(id => {
                            const node = byId[id];
                            (childrenByRow[node.level] ??= []).push(node);
                        });
                        Object.values(childrenByRow).forEach(children => {
                            const sourceX = parents.reduce((sum, node) => sum + node.x, 0) / parents.length;
                            routes.push({family, parents, children, sourceX, row: children[0].level, left: Math.min(sourceX, ...children.map(node => node.x)), right: Math.max(sourceX, ...children.map(node => node.x))});
                        });
                    });
                    routes.sort((a, b) => a.row - b.row || a.left - b.left || a.right - b.right);
                    const lanes = {};
                    routes.forEach(route => {
                        const occupied = lanes[route.row] ??= [];
                        let lane = occupied.findIndex(right => right + 20 < route.left);
                        if (lane === -1) lane = occupied.length;
                        occupied[lane] = route.right;
                        route.lane = lane;
                    });
                    const line = (context, points) => {
                        context.beginPath();
                        points.forEach(([x, y], index) => index === 0 ? context.moveTo(x, y) : context.lineTo(x, y));
                        context.stroke();
                    };
                    network.on('beforeDrawing', context => {
                        context.save();
                        rows.forEach(level => {
                            const y = nodes.find(node => node.level === level).y;
                            context.fillStyle = level % 2 ? '#f6f1e6' : '#fffdf8';
                            context.fillRect(bounds.left, y - 90, bounds.right - bounds.left, 180);
                            context.fillStyle = '#8a6a31';
                            context.font = '600 17px Arial';
                            context.fillText('ĐỜI ' + (level + 1), bounds.left + 14, y + 5);
                        });
                        context.strokeStyle = '#94713b';
                        context.fillStyle = '#94713b';
                        context.lineWidth = 1.6;
                        context.lineJoin = 'round';
                        routes.forEach(route => {
                            const sameRow = route.parents.every(node => node.y === route.parents[0].y);
                            const joinedAtMarriage = route.family.married && sameRow;
                            const sourceY = joinedAtMarriage ? route.parents[0].y : Math.max(...route.parents.map(node => box(node).bottom)) + 14;
                            if (!joinedAtMarriage) {
                                route.parents.forEach(node => line(context, [[node.x, box(node).bottom], [node.x, sourceY], [route.sourceX, sourceY]]));
                            }
                            const busY = Math.max(sourceY + 18, Math.min(...route.children.map(node => box(node).top)) - 30 - route.lane * 24);
                            line(context, [[route.sourceX, sourceY], [route.sourceX, busY]]);
                            line(context, [[route.left, busY], [route.right, busY]]);
                            route.children.forEach(node => {
                                const top = box(node).top - 3;
                                line(context, [[node.x, busY], [node.x, top - 5]]);
                                context.beginPath();
                                context.moveTo(node.x, top);
                                context.lineTo(node.x - 4, top - 7);
                                context.lineTo(node.x + 4, top - 7);
                                context.closePath();
                                context.fill();
                            });
                        });
                        context.restore();
                    });
                    const fitTree = () => network.moveTo({position: {x: (bounds.left + bounds.right) / 2, y: (bounds.top + bounds.bottom) / 2}, scale: Math.min(1, (container.clientWidth - 24) / (bounds.right - bounds.left), (container.clientHeight - 24) / (bounds.bottom - bounds.top)), animation: false});
                    const rootPartners = new Set([graph.root]);
                    graph.edges.filter(edge => edge.type === 'SPOUSE_OF' && (edge.from === graph.root || edge.to === graph.root)).forEach(edge => {rootPartners.add(edge.from); rootPartners.add(edge.to);});
                    const rootFamily = nodes.filter(node => rootPartners.has(node.id));
                    const familyLeft = Math.min(...rootFamily.map(node => node.x)) - 90;
                    const familyRight = Math.max(...rootFamily.map(node => node.x)) + 90;
                    const readableTree = () => network.moveTo({position: {x: (familyLeft + familyRight) / 2, y: byId[graph.root].y}, scale: Math.min(0.95, (container.clientWidth - 24) / (familyRight - familyLeft)), offset: {x: 0, y: -container.clientHeight / 2 + 85}, animation: false});
                    document.getElementById('network-fit').addEventListener('click', fitTree);
                    document.getElementById('network-reset').addEventListener('click', readableTree);
                    document.getElementById('network-zoom-in').addEventListener('click', () => network.moveTo({scale: Math.min(2, network.getScale() * 1.25), animation: false}));
                    document.getElementById('network-zoom-out').addEventListener('click', () => network.moveTo({scale: Math.max(0.08, network.getScale() / 1.25), animation: false}));
                    network.once('afterDrawing', () => container.clientWidth < 768 ? readableTree() : fitTree());
                    network.redraw();
                }
                network.on('click', ({nodes}) => {
                    if (nodes.length) window.location.href = {{ Illuminate\Support\Js::from(url('/persons')) }} + '/' + encodeURIComponent(nodes[0]);
                });
                window.familyNetwork = network;
            })();
        </script>
    @endpush
@endif
