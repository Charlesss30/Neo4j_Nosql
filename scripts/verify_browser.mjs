import assert from 'node:assert/strict';
import {mkdir, readFile, writeFile} from 'node:fs/promises';
import {resolve, join} from 'node:path';
import {pathToFileURL} from 'node:url';

const [verificationPath, destination] = process.argv.slice(2);
if (!verificationPath || !destination) throw new Error('Usage: node scripts/verify_browser.mjs verification.json output-directory');
const verified = JSON.parse((await readFile(verificationPath, 'utf8')).replace(/^\uFEFF/, ''));
assert.ok(verified.passed && verified.project.startsWith('familytree-verify-'), 'Use only the isolated Docker verification environment.');
const url = verified.appUrl;
assert.equal(new URL(url).hostname, '127.0.0.1');
const output = resolve(destination);
await mkdir(output, {recursive: true});
const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(resolve(process.env.PLAYWRIGHT_MODULE)).href : 'playwright');
const browser = await chromium.launch({headless: true, ...(process.env.PLAYWRIGHT_EXECUTABLE ? {executablePath: process.env.PLAYWRIGHT_EXECUTABLE} : {})});
const context = await browser.newContext({viewport: {width: 1440, height: 1000}, acceptDownloads: true});
await context.addInitScript(() => {window.print = () => {window.__pedigreePrintCalled = true;};});
const page = await context.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
const graph = async () => {
    const response = await context.request.get(url + '/api/tree?all=1');
    assert.equal(response.status(), 200);
    return response.json();
};
const original = await graph();
assert.equal(original.nodes.length, 30);
let created = null;
const result = {project: verified.project, sourceCommit: verified.sourceCommit, passed: false};
const removeCreated = async () => {
    if (!created) return;
    await page.goto(url + '/persons/' + encodeURIComponent(created) + '/delete');
    await page.getByRole('button', {name: 'Xác nhận xóa', exact: true}).click();
    await page.waitForURL('**/persons');
    created = null;
};
try {
    await page.goto(url + '/persons/P010');
    await page.getByRole('link', {name: 'Thêm con', exact: true}).click();
    assert.equal(await page.inputValue('#relative_id'), 'P010');
    assert.equal(await page.inputValue('#relation_type'), 'child');
    await page.fill('#name', 'Người thử giao diện');
    await page.fill('#birthYear', '2005');
    await page.getByRole('button', {name: 'Lưu thành viên', exact: true}).click();
    await page.waitForURL(/\/persons\/P\d+$/);
    created = new URL(page.url()).pathname.split('/').pop();
    assert.ok((await page.locator('[data-section="parents"]').innerText()).includes('P010'));
    assert.equal((await graph()).nodes.length, 31);
    await page.screenshot({path: join(output, 'created-relative.png'), fullPage: true});
    await removeCreated();
    assert.deepEqual(await graph(), original);
    result.profileCreateFlow = true;

    await page.goto(url + '/tree?all=1');
    await page.waitForFunction(() => Boolean(window.familyNetwork), {timeout: 30000});
    await page.locator('#network').scrollIntoViewIfNeeded();
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    const point = await page.evaluate(() => window.familyNetwork.canvasToDOM(window.familyNetwork.getPositions(['P010']).P010));
    assert.equal(await page.evaluate(point => window.familyNetwork.getNodeAt(point), point), 'P010');
    const frame = await page.locator('#network').boundingBox();
    await page.mouse.click(frame.x + point.x, frame.y + point.y);
    await page.waitForFunction(() => document.getElementById('tree-relative').value === 'P010');
    assert.equal(await page.inputValue('#tree-relative'), 'P010');
    assert.ok(new URL(page.url()).pathname === '/tree');
    await page.screenshot({path: join(output, 'tree-actions.png'), fullPage: true});

    for (const format of ['svg', 'png']) {
        const [download] = await Promise.all([page.waitForEvent('download'), page.click('#network-export-' + format)]);
        await download.saveAs(join(output, 'family-tree.' + format));
    }
    const svg = await readFile(join(output, 'family-tree.svg'), 'utf8');
    assert.equal((svg.match(/<g><title>/g) || []).length, 30);
    assert.match(svg, /†/);
    const png = await readFile(join(output, 'family-tree.png'));
    assert.equal(png.subarray(1, 4).toString(), 'PNG');
    result.png = {width: png.readUInt32BE(16), height: png.readUInt32BE(20), bytes: png.length};
    assert.ok(result.png.width > 1440, 'The export must include the full tree rather than a viewport screenshot.');
    const [popup] = await Promise.all([context.waitForEvent('page'), page.click('#network-print')]);
    await popup.waitForFunction(() => window.__pedigreePrintCalled === true);
    assert.equal(await popup.locator('svg g > title').count(), 30);
    await popup.pdf({path: join(output, 'family-tree.pdf'), preferCSSPageSize: true, printBackground: true});
    await popup.close();
    result.fullTreeExports = ['SVG', 'PNG', 'PDF'];

    await page.selectOption('#tree-relative', 'P001');
    await page.selectOption('#tree-relation-type', 'parent');
    await page.getByRole('button', {name: 'Thêm người kèm quan hệ', exact: true}).click();
    await page.fill('#name', 'Gốc thử giao diện');
    await page.fill('#birthYear', '1890');
    await page.getByRole('button', {name: 'Lưu thành viên', exact: true}).click();
    await page.waitForURL(/\/tree\?/);
    created = new URL(page.url()).searchParams.get('selected');
    assert.ok(created?.startsWith('P'));
    assert.equal(new URL(page.url()).searchParams.get('root'), created);
    assert.equal(await page.inputValue('#tree-relative'), created);
    await removeCreated();
    assert.deepEqual(await graph(), original);
    result.treeCreateFlow = true;

    await page.setViewportSize({width: 390, height: 844});
    await page.goto(url + '/tree?root=P001');
    await page.waitForFunction(() => Boolean(window.familyNetwork));
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
    await page.screenshot({path: join(output, 'tree-mobile.png'), fullPage: true});
    result.mobileNoHorizontalOverflow = true;
    assert.deepEqual(errors, []);
    result.seedPreserved = true;
    result.passed = true;
    console.log(JSON.stringify(result));
} finally {
    try {await removeCreated();} finally {
        result.pageErrors = errors;
        await writeFile(join(output, 'browser-verification.json'), JSON.stringify(result, null, 2));
        await browser.close();
    }
}
