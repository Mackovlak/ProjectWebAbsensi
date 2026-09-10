<?php
require __DIR__ . '/../attendance_face.php';
$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) throw new Exception('FAIL: ' . $label);
    $checks++;
}
function rejects($call, $label) {
    try { $call(); } catch (RuntimeException $e) { check(true, $label); return; }
    check(false, $label);
}
$base = array_fill(0, 128, 0.1);
$other = array_fill(0, 128, 0.9);
$templates = json_encode([$other, $base]);
$probe = $base;
$probe[0] += 0.1;
check(abs(attendanceFaceConfidence(json_encode($probe), $templates) - 90) < 0.00001, 'best template / Euclidean score');
check(attendanceFaceConfidence(json_encode($base), json_encode($base)) === 100.0, 'legacy single vector');
check(attendanceFaceConfidence(json_encode($other), json_encode([$base])) === 0.0, 'mismatch clamped');
foreach ([0.36999, 0.37001] as $distance) {
    $candidate = $base;
    $candidate[0] += $distance;
    $score = attendanceFaceConfidence(json_encode($candidate), $templates);
    check(($score >= ATTENDANCE_FACE_MIN_CONFIDENCE) === ($distance < 0.37), 'unrounded threshold');
}
foreach (['[1]', 'null', '"[1]"', '{}', '[NaN]', '[1e999]', str_repeat(' ', 131073)] as $bad) {
    rejects(fn() => attendanceFaceConfidence($bad, $templates), 'malformed descriptor');
}
foreach (['0.1', true, null, [], INF, NAN, 11] as $bad) {
    $vector = $base;
    $vector[4] = $bad;
    rejects(fn() => attendanceFaceVector($vector), 'invalid numeric component');
}
$object = new stdClass();
foreach ($base as $i => $number) $object->{(string)$i} = $number;
rejects(fn() => attendanceFaceConfidence(json_encode($object), $templates), 'object is not a descriptor array');
rejects(fn() => attendanceFaceTemplates('[]'), 'empty enrollment');
rejects(fn() => attendanceFaceTemplates(json_encode(array_fill(0, 21, $base))), 'bounded enrollment');

$session = [];
$context = attendanceFaceContext('20260908001', null, $templates, '2026-09-08');
$token = attendanceFaceIssueToken($session, 'test', $context, 1000);
check(strlen($token) === 64, '256-bit token');
check(attendanceFaceConsumeToken($session, 'test', $token, $context, 1119) === $context, 'valid token');
rejects(function () use (&$session, $token, $context) { attendanceFaceConsumeToken($session, 'test', $token, $context, 1119); }, 'replay');
$token = attendanceFaceIssueToken($session, 'test', $context, 1000);
rejects(function () use (&$session, $token, $context) { attendanceFaceConsumeToken($session, 'test', $token, $context, 1120); }, 'exact expiration');
foreach (['employee' => '20260908002', 'kind' => 'pulang', 'attendance_id' => 8, 'date' => '2026-09-09', 'template_hash' => 'reset'] as $field => $value) {
    $token = attendanceFaceIssueToken($session, 'test', $context, 1000);
    $wrong = array_replace($context, [$field => $value]);
    rejects(function () use (&$session, $token, $wrong) { attendanceFaceConsumeToken($session, 'test', $token, $wrong, 1001); }, 'binding: ' . $field);
    rejects(function () use (&$session, $token, $context) { attendanceFaceConsumeToken($session, 'test', $token, $context, 1002); }, 'wrong binding consumes token');
}
$token = attendanceFaceIssueToken($session, 'test', $context, 1000);
$differentSession = [];
rejects(function () use (&$differentSession, $token, $context) { attendanceFaceConsumeToken($differentSession, 'test', $token, $context, 1001); }, 'another session');
rejects(function () use (&$session, $token, $context) { attendanceFaceConsumeToken($session, 'receipt', $token, $context, 1001); }, 'challenge cannot serve as receipt');
for ($i = 0; $i < 20; $i++) attendanceFaceIssueToken($session, 'test', $context, 1001);
check(count($session['test']) === 8, 'bounded session storage');
attendanceFaceIssueToken($session, 'test', $context, 2000);
check(count($session['test']) === 1, 'expired token pruning');
echo "PASS: $checks matching, descriptor validation, threshold, binding, expiry and replay checks.\n";
