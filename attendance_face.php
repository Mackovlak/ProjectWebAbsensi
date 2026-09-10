<?php
// Pure matching/token helpers; no database or session initialization here.
const ATTENDANCE_FACE_MIN_CONFIDENCE = 63.0;
const ATTENDANCE_FACE_TOKEN_TTL = 120;

function attendanceFaceVector($value) {
    if (!is_array($value) || array_keys($value) !== range(0, 127)) {
        throw new RuntimeException('Format descriptor wajah tidak valid. Verifikasi ulang.');
    }
    foreach ($value as $number) {
        if ((!is_int($number) && !is_float($number)) || !is_finite((float)$number) || abs($number) > 10) {
            throw new RuntimeException('Nilai descriptor wajah tidak valid. Verifikasi ulang.');
        }
    }
    return $value;
}

function attendanceFaceJson($json) {
    if (!is_string($json) || strlen($json) > 131072) throw new RuntimeException('Data wajah tidak valid.');
    try {
        return json_decode($json, false, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('Data wajah tidak valid.');
    }
}

function attendanceFaceTemplates($json) {
    $templates = attendanceFaceJson($json);
    if (!is_array($templates) || !$templates) throw new RuntimeException('Wajah belum terdaftar.');
    // Legacy single-vector enrollment remains usable.
    if (is_int($templates[0]) || is_float($templates[0])) $templates = [$templates];
    if (count($templates) > 20) throw new RuntimeException('Data registrasi wajah tidak valid. Hubungi admin.');
    return array_map('attendanceFaceVector', $templates);
}

function attendanceFaceConfidence($descriptorJson, $templatesJson) {
    $descriptor = attendanceFaceVector(attendanceFaceJson($descriptorJson));
    $best = 0.0;
    foreach (attendanceFaceTemplates($templatesJson) as $template) {
        $sum = 0.0;
        for ($i = 0; $i < 128; $i++) $sum += ($descriptor[$i] - $template[$i]) ** 2;
        $best = max($best, max(0.0, min(100.0, (1.0 - sqrt($sum)) * 100.0)));
    }
    return $best; // Threshold is checked before rounding for display/storage.
}

function attendanceFaceIssueToken(&$session, $bucket, $context, $now = null) {
    $now = $now ?? time();
    $tokens = $session[$bucket] ?? [];
    foreach ($tokens as $key => $record) {
        if ($record['expires_at'] <= $now) unset($tokens[$key]);
    }
    while (count($tokens) >= 8) array_shift($tokens);
    $token = bin2hex(random_bytes(32));
    $tokens[hash('sha256', $token)] = ['expires_at' => $now + ATTENDANCE_FACE_TOKEN_TTL, 'context' => $context];
    $session[$bucket] = $tokens;
    return $token;
}

function attendanceFaceConsumeToken(&$session, $bucket, $token, $expected, $now = null) {
    $now = $now ?? time();
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
        throw new RuntimeException('Verifikasi wajah diperlukan. Silakan verifikasi ulang.');
    }
    $key = hash('sha256', $token);
    $record = $session[$bucket][$key] ?? null;
    // PHP's session lock serializes consumption across concurrent requests.
    unset($session[$bucket][$key]);
    if (!$record || $record['expires_at'] <= $now) {
        throw new RuntimeException('Verifikasi sudah digunakan atau kedaluwarsa. Silakan verifikasi ulang.');
    }
    foreach ($expected as $field => $value) {
        if (!array_key_exists($field, $record['context']) || $record['context'][$field] !== $value) {
            throw new RuntimeException('Verifikasi tidak sesuai absensi ini. Silakan verifikasi ulang.');
        }
    }
    return $record['context'];
}

function attendanceFaceContext($employeeId, $attendance, $templates, $date) {
    return [
        'employee' => $employeeId,
        'kind' => $attendance ? 'pulang' : 'masuk',
        'attendance_id' => $attendance ? (int)$attendance['id'] : 0,
        'date' => $date,
        'template_hash' => hash('sha256', $templates ?? ''),
    ];
}
