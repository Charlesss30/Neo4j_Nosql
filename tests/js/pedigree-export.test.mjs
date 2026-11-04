import test from 'node:test';
import assert from 'node:assert/strict';
import {pedigreeSvg, rasterSize} from '../../public/js/pedigree-export.js';

const person = (id, x, y, name = id) => ({id, x, y, name, level: y / 210, color: '#b9d9ff', label: name + '\n' + id});
test('exports every node, including off-screen coordinates, and escapes text', () => {
    const result = pedigreeSvg({nodes: [person('P1', -2000, 0, '<script>&"'), person('P2', 4000, 210, '† Nguyễn An')], edges: [], families: []});
    assert.ok(result.width > 6000);
    assert.match(result.svg, /&lt;script&gt;&amp;&quot;/);
    assert.doesNotMatch(result.svg, /<script>/);
    assert.match(result.svg, /† Nguyễn An/);
    assert.equal((result.svg.match(/<g><title>/g) || []).length, 2);
});
test('co-parents are connected without inventing a marriage', () => {
    const graph = {nodes: [person('A', 0, 0), person('B', 216, 0), person('C', 100, 210)], edges: [{from: 'A', to: 'C', type: 'PARENT_OF'}, {from: 'B', to: 'C', type: 'PARENT_OF'}], families: [{parents: ['A', 'B'], children: ['C'], married: false}]};
    const result = pedigreeSvg(graph);
    assert.doesNotMatch(result.svg, /stroke-dasharray=/);
    graph.edges.push({from: 'A', to: 'B', type: 'SPOUSE_OF'});
    graph.families[0].married = true;
    assert.match(pedigreeSvg(graph).svg, /stroke-dasharray="7 5"/);
});
test('PNG dimensions respect browser memory limits; SVG remains full size', () => {
    const size = rasterSize(100000, 50000);
    assert.ok(size.width <= 8192 && size.height <= 8192);
    assert.ok(size.width * size.height <= 32000000);
    assert.ok(pedigreeSvg({nodes: [person('P1', 0, 0)], edges: []}).width >= 860);
    assert.throws(() => pedigreeSvg({nodes: [], edges: []}), /Không có dữ liệu/);
});
