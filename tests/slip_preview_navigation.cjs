// Run: node tests/slip_preview_navigation.cjs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/slip-preview-navigation.js'), 'utf8');

function setup(standalone, ios = false) {
    const handlers = {};
    vm.runInNewContext(source, {
        URL,
        window: {
            matchMedia: () => ({ matches: standalone }),
            navigator: { standalone: ios },
            location: { href: 'https://example.test/absensi/laporan.php', origin: 'https://example.test' }
        },
        document: {
            addEventListener: (type, handler) => { handlers[type] = handler; },
            createElement: () => ({})
        }
    });
    return handlers;
}

for (const [standalone, ios] of [[false, false], [true, false], [false, true]]) {
    const handlers = setup(standalone, ios);
    const inApp = standalone || ios;
    // Admin form begins as a salary recap and switches to slip batch dynamically.
    const form = { action: 'laporan_gaji_print.php', target: '_blank', querySelector: () => null,
        appendChild(input) { this.hiddenAction = input; } };
    const submitter = { name: 'action', value: 'print', removeAttribute() {} };
    handlers.submit({ target: form, submitter });
    assert.equal(form.target, '_blank');
    assert.equal(submitter.value, 'print');
    form.action = 'laporan_slip_batch.php';
    handlers.submit({ target: form, submitter });
    assert.equal(form.target, inApp ? '_self' : '_blank');
    assert.equal(submitter.value, inApp ? 'preview' : 'print');

    // Staff's form has no named action button; keyboard submission also works.
    handlers.submit({ target: form });
    assert.equal(form.hiddenAction?.value, inApp ? 'preview' : undefined);
    const link = { href: 'https://example.test/absensi/laporan_slip_batch.php?document_type=disnaker&user_id=12&action=pdf', target: '_blank' };
    handlers.click({ target: { closest: () => link } });
    const url = new URL(link.href);
    assert.equal(link.target, inApp ? '_self' : '_blank');
    assert.equal(url.searchParams.get('action'), inApp ? 'preview' : 'pdf');
    assert.equal(url.searchParams.get('document_type'), 'disnaker');
    assert.equal(url.searchParams.get('user_id'), '12');

    const cancelled = { action: 'laporan_slip_batch.php', target: '_blank' };
    handlers.submit({ target: cancelled, defaultPrevented: true });
    assert.equal(cancelled.target, '_blank');
    const external = { href: 'https://other.test/laporan_slip_batch.php', target: '_blank' };
    handlers.click({ target: { closest: () => external } });
    assert.equal(external.target, '_blank');
}
console.log('Slip preview navigation passed (browser, standalone PWA, iOS standalone).');
