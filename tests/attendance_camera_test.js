// Run with: node tests/attendance_camera_test.js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/face-recognition.js'), 'utf8');
const context = {window: {}, console};
vm.runInNewContext(source, context);
const system = new context.window.FaceRecognitionSystem();
let rect = {left: 100, top: 60, width: 260, height: 252};
system.videoElement = {
    videoWidth: 640, videoHeight: 480,
    getBoundingClientRect: () => ({left: 30, top: 36, width: 400, height: 300})
};
const guide = {getBoundingClientRect: () => rect};
const quality = (x, y, width, height) => system.validateFaceQuality({detection: {box: {x, y, width, height}}}, guide);
assert.equal(quality(180, 100, 280, 280).valid, true);
assert.match(quality(290, 210, 60, 60).message, /Dekatkan/);
assert.match(quality(100, 40, 440, 440).message, /Jauhkan/);
assert.match(quality(0, 100, 280, 280).message, /tengah oval/);
// An off-center guide must be mapped back through the mirrored video.
rect = {left: 30, top: 36, width: 200, height: 300};
assert.equal(quality(380, 100, 200, 280).valid, true);
assert.equal(quality(60, 100, 200, 280).valid, false);
rect.width = 0;
assert.equal(quality(180, 100, 280, 280).valid, false);

// A still frame is accepted while the live stream is not fully buffered,
// but the live video itself still needs readyState 4.
async function descriptorCheck() {
    const faceapiStub = {detectSingleFace: () => ({withFaceLandmarks: () => ({withFaceDescriptor: async () => null})})};
    const ctx = {window: {}, console, faceapi: {...faceapiStub, TinyFaceDetectorOptions: function () {}}};
    vm.runInNewContext(source, ctx);
    const sys = new ctx.window.FaceRecognitionSystem();
    sys.isModelLoaded = true;
    sys.videoElement = {readyState: 2, videoWidth: 640, videoHeight: 480};
    await assert.rejects(sys.captureFaceDescriptor(), /Video belum siap/);
    await assert.rejects(sys.captureFaceDescriptor({}), error => !/Video belum siap/.test(error.message));
}

const page = fs.readFileSync(path.join(__dirname, '../absen.php'), 'utf8');
const flow = page.slice(page.indexOf('        async function verifyFace()'), page.indexOf('        function cancelFaceVerification()'));

async function runFlow({rateStart = false, mismatch = false, abortCooldown = false, invalidPosition = false, failVerify = null} = {}) {
    let now = 0;
    let cancelActiveVerification = null;
    const calls = [], statuses = [], classes = new Set();
    let descriptors = 0, stops = 0, detections = 0;
    const canvas = {getContext: () => ({clearRect() {}, drawImage() {}})};
    const container = {
        querySelector: () => guide,
        classList: {
            remove: (...names) => names.forEach(name => classes.delete(name)),
            add: name => classes.add(name),
            toggle: (name, enabled) => enabled ? classes.add(name) : classes.delete(name)
        }
    };
    const elements = {'face-overlay': {style: {}}, 'face-canvas': canvas, 'liveness-instruction': {style: {}}, 'global-id-karyawan': {value: '20260908001'}};
    const fakeSystem = {
        settings: {}, isModelLoaded: true,
        videoElement: {videoWidth: 640, videoHeight: 480},
        async startCamera() {}, stopCamera() {stops++;},
        async detectFace() {detections++; return detections === 1 ? null : {box: {}};},
        validateFaceQuality: () => ({valid: !invalidPosition, message: 'move'}),
        async captureFaceDescriptor(input) {assert.ok(input); descriptors++; return {descriptor: [1]};},
        drawDetection() {}
    };
    const sandbox = {
        AbortController, Blob, FormData,
        Date: {now: () => now},
        setTimeout(callback, ms) {
            now += ms;
            queueMicrotask(() => {
                if (abortCooldown && ms >= 3000) sandbox.cancelActiveVerification();
                else callback();
            });
            return 1;
        },
        clearTimeout() {},
        document: {getElementById: id => elements[id], querySelector: () => container, createElement: () => canvas},
        faceSystem: fakeSystem, currentAbsenType: 'masuk', attendanceCsrf: 'csrf', cancelActiveVerification,
        updateFaceStatus(type, message) {statuses.push({type, message, now});},
        async captureAttendancePhoto(frame) {assert.equal(frame, canvas); return new Blob(['photo']);},
        async fetch(url, {body}) {
            const action = body.get('action');
            calls.push({action, now, nonce: body.get('nonce')});
            let status = 200, data = {success: true, nonce: 'nonce-' + calls.length, confidence: 80, verification_token: 'receipt'};
            if (rateStart && calls.length === 1) {status = 429; data = {success: false, retry_after: 7};}
            if (mismatch && calls.filter(c => c.action === 'verify').length === 1 && action === 'verify') {
                data = {success: false, code: 'face_mismatch', retry_after: 3, message: 'mismatch'};
            }
            let badJson = false;
            if (failVerify && action === 'verify' && (failVerify === 'fatal' || calls.filter(c => c.action === 'verify').length === 1)) {
                if (failVerify === 'network') throw new TypeError('Failed to fetch');
                if (failVerify === 'server') {status = 500; data = {success: false, message: 'Verifikasi server gagal. Silakan coba lagi.'};}
                if (failVerify === 'badjson') {status = 502; badJson = true;}
                if (failVerify === 'nonce') data = {success: false, code: 'nonce_invalid', retry_after: 0, message: 'Silakan verifikasi ulang.'};
                if (failVerify === 'fatal') data = {success: false, code: null, retry_after: 0, message: 'Registrasi wajah aktif diperlukan.'};
            }
            return {ok: status === 200, status, headers: {get: () => null},
                json: async () => { if (badJson) throw new SyntaxError('Unexpected token <'); return data; }};
        }
    };
    vm.createContext(sandbox);
    vm.runInContext(flow + '\nthis.run = verifyFace;', sandbox);
    if (abortCooldown) await assert.rejects(sandbox.run(), /dibatalkan/);
    else if (invalidPosition) await assert.rejects(sandbox.run(), /Waktu verifikasi habis/);
    else if (failVerify === 'fatal') await assert.rejects(sandbox.run(), /Registrasi wajah aktif diperlukan/);
    else assert.equal((await sandbox.run()).token, 'receipt');
    assert.equal(stops, 1);
    assert.equal(elements['face-overlay'].style.display, 'none');
    assert.equal(sandbox.cancelActiveVerification, null);
    if (invalidPosition) {assert.equal(descriptors, 0); assert.equal(calls.length, 0);}
    if (rateStart && !abortCooldown) assert.ok(calls[1].now - calls[0].now >= 7000);
    if (mismatch) {
        const verifies = calls.filter(c => c.action === 'verify');
        assert.ok(verifies[1].now - verifies[0].now >= 3000);
        assert.notEqual(verifies[0].nonce, verifies[1].nonce);
    }
    if (abortCooldown) assert.equal(calls.length, 1);
    if (failVerify === 'fatal') assert.equal(calls.filter(c => c.action === 'verify').length, 1);
    else if (failVerify) {
        // Transient failure: cooldown, fresh nonce, then a second verify succeeds.
        const verifies = calls.filter(c => c.action === 'verify');
        assert.equal(verifies.length, 2);
        assert.ok(verifies[1].now - verifies[0].now >= 3000);
        assert.notEqual(verifies[0].nonce, verifies[1].nonce);
    }
    assert.ok(statuses.some(s => s.message === 'Wajah belum terlihat.'));
}

(async () => {
    await runFlow();
    await runFlow({rateStart: true});
    await runFlow({mismatch: true});
    await runFlow({rateStart: true, abortCooldown: true});
    await runFlow({invalidPosition: true});
    for (const failVerify of ['network', 'server', 'badjson', 'nonce', 'fatal']) await runFlow({failVerify});
    await descriptorCheck();
    console.log('PASS: guide geometry, positioning gate, cooldowns, nonce renewal, transient-error retry, still-frame capture and cancellation.');
})().catch(error => {console.error(error); process.exitCode = 1;});
