const xml = value => String(value).replace(/[&<>"']/g, character => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;'}[character]));

export function pedigreeSvg(graph, title = 'Cây gia phả') {
    if (!graph.nodes?.length) throw new Error('Không có dữ liệu để xuất.');
    const nodes = graph.nodes;
    if (nodes.some(node => !Number.isFinite(node.x) || !Number.isFinite(node.y))) throw new Error('Phả đồ chưa có vị trí cố định để xuất.');
    const byId = new Map(nodes.map(node => [node.id, node]));
    const left = Math.min(...nodes.map(node => node.x)) - 240;
    const top = Math.min(...nodes.map(node => node.y)) - 150;
    const right = Math.max(...nodes.map(node => node.x)) + 140;
    const bottom = Math.max(...nodes.map(node => node.y)) + 120;
    const width = Math.max(860, right - left), height = bottom - top;
    const parts = [`<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="${left} ${top} ${width} ${height}" role="img" aria-label="${xml(title)}">`,
        `<title>${xml(title)}</title><rect x="${left}" y="${top}" width="${width}" height="${height}" fill="#fffdf8"/>`,
        `<g font-family="Arial, sans-serif"><text x="${left + 20}" y="${top + 28}" font-size="22" font-weight="bold" fill="#143c30">${xml(title)}</text>`,
        `<text x="${left + 20}" y="${top + 53}" font-size="13" fill="#52645b">${nodes.length} thành viên · ${graph.edges.length} quan hệ · Nam xanh / Nữ hồng / † đã mất · Nét đứt: vợ chồng</text>`];
    const path = points => parts.push(`<path d="${points.map(([x, y], index) => `${index ? 'L' : 'M'}${x} ${y}`).join(' ')}" fill="none" stroke="#94713b" stroke-width="1.6"/>`);
    const rows = [...new Set(nodes.map(node => node.level))].sort((a, b) => a - b);
    rows.forEach(level => {
        const y = nodes.find(node => node.level === level).y;
        parts.push(`<rect x="${left}" y="${y - 90}" width="${width}" height="180" fill="${level % 2 ? '#f6f1e6' : '#fffdf8'}"/><text x="${left + 14}" y="${y + 5}" font-size="17" fill="#8a6a31">ĐỜI ${level + 1}</text>`);
    });
    graph.edges.filter(edge => edge.type === 'SPOUSE_OF').forEach(edge => {
        const a = byId.get(edge.from), b = byId.get(edge.to);
        if (a && b) parts.push(`<path d="M${a.x} ${a.y} L${b.x} ${b.y}" fill="none" stroke="#ba6686" stroke-width="1.6" stroke-dasharray="7 5"/>`);
    });
    const routes = [];
    (graph.families ?? []).forEach(family => {
        const parents = family.parents.map(id => byId.get(id)).filter(Boolean);
        if (!parents.length) return;
        const childrenByRow = new Map();
        family.children.map(id => byId.get(id)).filter(Boolean).forEach(node => {
            if (!childrenByRow.has(node.level)) childrenByRow.set(node.level, []);
            childrenByRow.get(node.level).push(node);
        });
        childrenByRow.forEach(children => {
            const sourceX = parents.reduce((sum, node) => sum + node.x, 0) / parents.length;
            routes.push({family, parents, children, sourceX, row: children[0].level, left: Math.min(sourceX, ...children.map(node => node.x)), right: Math.max(sourceX, ...children.map(node => node.x))});
        });
    });
    routes.sort((a, b) => a.row - b.row || a.left - b.left || a.right - b.right);
    const lanes = new Map();
    routes.forEach(route => {
        if (!lanes.has(route.row)) lanes.set(route.row, []);
        const occupied = lanes.get(route.row);
        let lane = occupied.findIndex(end => end + 20 < route.left);
        if (lane < 0) lane = occupied.length;
        occupied[lane] = route.right;
        const joined = route.family.married && route.parents.every(node => node.y === route.parents[0].y);
        const sourceY = joined ? route.parents[0].y : Math.max(...route.parents.map(node => node.y + 40)) + 14;
        if (!joined) route.parents.forEach(node => path([[node.x, node.y + 40], [node.x, sourceY], [route.sourceX, sourceY]]));
        const busY = Math.max(sourceY + 18, Math.min(...route.children.map(node => node.y - 40)) - 30 - lane * 24);
        path([[route.sourceX, sourceY], [route.sourceX, busY]]);
        path([[route.left, busY], [route.right, busY]]);
        route.children.forEach(node => {
            const arrowY = node.y - 43;
            path([[node.x, busY], [node.x, arrowY - 5]]);
            parts.push(`<path d="M${node.x} ${arrowY} l-4 -7 h8 Z" fill="#94713b"/>`);
        });
    });
    nodes.forEach(node => {
        parts.push(`<g><title>${xml(node.name)} (${xml(node.id)})</title><rect x="${node.x - 90}" y="${node.y - 40}" width="180" height="80" rx="6" fill="${xml(node.color)}" stroke="#688375"/>`);
        const [name, ...details] = String(node.label).split('\n');
        const lines = [];
        let line = '';
        for (const character of name) {
            line += character;
            if ([...line].length === 22) {lines.push(line); line = '';}
        }
        if (line) lines.push(line);
        lines.push(...details);
        lines.forEach((text, index) => parts.push(`<text x="${node.x}" y="${node.y - (lines.length - 1) * 9 + index * 18}" text-anchor="middle" font-size="14" fill="#20362d">${xml(text)}</text>`));
        parts.push('</g>');
    });
    parts.push('</g></svg>');
    return {svg: parts.join(''), width, height};
}

export function rasterSize(width, height) {
    const scale = Math.min(2, 8192 / width, 8192 / height, Math.sqrt(32000000 / (width * height)));
    return {width: Math.max(1, Math.floor(width * scale)), height: Math.max(1, Math.floor(height * scale))};
}

function save(blob, name) {
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = name;
    anchor.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export async function downloadPedigree(graph, format) {
    const {svg, width, height} = pedigreeSvg(graph);
    const blob = new Blob([svg], {type: 'image/svg+xml;charset=utf-8'});
    const file = 'gia-pha-' + (graph.root || 'toan-bo').replace(/[^a-zA-Z0-9_-]/g, '_');
    if (format === 'svg') return save(blob, file + '.svg');
    const url = URL.createObjectURL(blob);
    try {
        const image = new Image();
        image.src = url;
        await image.decode();
        const canvas = document.createElement('canvas');
        Object.assign(canvas, rasterSize(width, height));
        const context = canvas.getContext('2d');
        if (!context) throw new Error('Trình duyệt không hỗ trợ xuất PNG. Hãy chọn SVG.');
        context.drawImage(image, 0, 0, canvas.width, canvas.height);
        const png = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
        if (!png) throw new Error('Phả đồ quá lớn để xuất PNG. Hãy chọn SVG.');
        save(png, file + '.png');
    } finally {
        URL.revokeObjectURL(url);
    }
}

export function printPedigree(graph) {
    const {svg} = pedigreeSvg(graph);
    const popup = window.open('', '_blank');
    if (!popup) throw new Error('Hãy cho phép mở cửa sổ mới để in phả đồ.');
    popup.opener = null;
    popup.document.write('<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>In gia phả</title><style>@page{size:A3 landscape;margin:10mm}body{margin:0}svg{display:block;width:100%;height:auto;max-height:270mm}*{print-color-adjust:exact;-webkit-print-color-adjust:exact}</style></head><body>' + svg + '</body></html>');
    popup.document.close();
    popup.setTimeout(() => popup.print(), 250);
}
