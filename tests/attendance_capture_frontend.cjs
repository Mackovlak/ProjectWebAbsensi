// Run: node tests/attendance_capture_frontend.cjs
// Exercises camera encoding without requiring a webcam or browser packages.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const source = fs.readFileSync(path.join(__dirname, '..', 'absen.php'), 'utf8');
for (const script of source.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)) {
    const js = script[1].replace(/<\?php[\s\S]*?\?>/g, 'null');
    if (js.trim()) new vm.Script(js);
}

const capture = source.slice(
    source.indexOf('async function captureAttendancePhoto('),
    source.indexOf('async function verifyFace()')
);
const frame = {
    getContext: () => ({ drawImage: (...args) => { frame.draw = args; } }),
    toBlob: (callback, type, quality) => {
        assert.equal(type, 'image/jpeg');
        assert.equal(quality, 0.75);
        callback(new Blob(['photo'], { type }));
    }
};
const context = vm.createContext({ document: { createElement: () => frame }, Blob });
vm.runInContext(capture, context);

(async () => {
    const landscape = { videoWidth: 1920, videoHeight: 1080 };
    await context.captureAttendancePhoto(landscape);
    assert.equal(frame.width, 1280);
    assert.equal(frame.height, 720);
    assert.equal(frame.draw[0], landscape);
    assert.deepEqual(frame.draw.slice(1), [0, 0, 1280, 720]);

    await context.captureAttendancePhoto({ videoWidth: 1080, videoHeight: 1920 });
    assert.equal(frame.width, 720);
    assert.equal(frame.height, 1280);
    await context.captureAttendancePhoto({ videoWidth: 640, videoHeight: 480 });
    assert.equal(frame.width, 640);
    assert.equal(frame.height, 480);

    await assert.rejects(context.captureAttendancePhoto({ videoWidth: 0, videoHeight: 0 }));
    frame.toBlob = callback => callback(null);
    await assert.rejects(context.captureAttendancePhoto(landscape));
    frame.toBlob = callback => callback(new Blob([Buffer.alloc(513 * 1024)]));
    await assert.rejects(context.captureAttendancePhoto(landscape));
    console.log('PASS: JavaScript syntax, full frame landscape/portrait JPEG, no upscaling, camera/encoding/size failures.');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
