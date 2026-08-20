<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function initials(string $name, int $limit = 2): string
{
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, max(1, $limit)) as $part) {
        $letters .= function_exists('mb_substr')
            ? mb_substr($part, 0, 1, 'UTF-8')
            : substr($part, 0, 1);
    }
    return strtoupper($letters ?: '?');
}

function format_month_year_id(string $date): string
{
    $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }
    return $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

function url(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . (str_starts_with($path, '/') ? $path : url($path)));
    exit;
}

function uuidv4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf(
        '%s%s-%s-%s-%s-%s%s%s',
        str_split(bin2hex($data), 4)
    );
}

function request_data(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (str_contains(strtolower($contentType), 'application/json')) {
        $decoded = json_decode(
            (string) file_get_contents('php://input'),
            true
        );

        return is_array($decoded) ? $decoded : [];
    }

    return $_POST;
}

function json_response(
    bool $success,
    string $message,
    mixed $data = null,
    int $status = 200
): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function pull_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];

    unset($_SESSION['flash']);

    return is_array($messages) ? $messages : [];
}

function normalize_class_name(?string $value): string
{
    $value = strtoupper(trim((string) $value));

    $value = preg_replace('/^KELAS\s*/', '', $value) ?? $value;
    $value = preg_replace('/[^0-9A-Z]/', '', $value) ?? $value;

    if (preg_match('/^([1-6])([AB])$/', $value, $matches)) {
        return $matches[1] . $matches[2];
    }

    if (preg_match('/^([1-6])$/', $value, $matches)) {
        return $matches[1] . 'A';
    }

    return $value;
}

function level_name(int|string $level): string
{
    $number = (int) $level;

    return match ($number) {
        7 => 'Mustawa Muttawasit 1',
        8 => 'Mustawa Muttawasit 2',
        9 => 'Mustawa Muttawasit 3',
        default => 'Level ' . max(1, min(6, $number)),
    };
}

function predicate(float $score): array
{
    if ($score >= 90) {
        return ['Mumtaz', 'Istimewa', 'A'];
    }

    if ($score >= 80) {
        return ['Jayyid Jiddan', 'Sangat Bagus', 'A-'];
    }

    if ($score >= 65) {
        return ['Jayyid', 'Bagus', 'B'];
    }

    if ($score >= 50) {
        return ['Maqbul', 'Diterima/Lulus', 'C'];
    }

    if ($score >= 35) {
        return ['Dhaif', 'Lemah', 'D'];
    }

    return ['Dhaif Jiddan', 'Sangat Lemah', 'E'];
}

function score_status(float $score, float $minimum = 75): string
{
    return $score >= $minimum ? 'Lulus' : 'Mengulang';
}

function all_class_names(): array
{
    $classes = [];

    for ($grade = 1; $grade <= 6; $grade++) {
        foreach (['A', 'B'] as $rombel) {
            $classes[] = $grade . $rombel;
        }
    }

    return $classes;
}

function academic_year_from_date(?string $date = null): string
{
    $timestamp = $date ? strtotime($date) : time();

    if (!$timestamp) {
        $timestamp = time();
    }

    $year = (int) date('Y', $timestamp);
    $month = (int) date('n', $timestamp);

    $start = $month >= 7
        ? $year
        : $year - 1;

    return $start . '/' . ($start + 1);
}

function valid_academic_year(?string $value): bool
{
    if (!is_string($value) || !preg_match('/^(\d{4})\/(\d{4})$/', $value, $matches)) {
        return false;
    }

    return (int) $matches[2] === (int) $matches[1] + 1;
}

function academic_settings(bool $refresh = false): array
{
    static $cached = null;

    if ($cached !== null && !$refresh) {
        return $cached;
    }

    $fallback = [
        'active_year' => defined('ACTIVE_ACADEMIC_YEAR') ? ACTIVE_ACADEMIC_YEAR : academic_year_from_date(),
        'daily_weight' => 30,
        'level_exam_weight' => 30,
        'munaqosyah_weight' => 40,
        'minimum_level_score' => 75.0,
        'minimum_munaqosyah_score' => 75.0,
    ];

    try {
        $statement = db()->query(
            'SELECT active_year, daily_weight, level_exam_weight, munaqosyah_weight,
                    minimum_level_score, minimum_munaqosyah_score
             FROM academic_settings WHERE id = 1 LIMIT 1'
        );
        $row = $statement->fetch();
        if ($row && valid_academic_year((string) $row['active_year'])) {
            return $cached = array_merge($fallback, $row);
        }
    } catch (Throwable $error) {
        error_log('Academic settings gagal dibaca: ' . $error->getMessage());
    }

    return $cached = $fallback;
}

function active_academic_year(): string
{
    return (string) academic_settings()['active_year'];
}

function academic_minimum_score(string $type): float
{
    $settings = academic_settings();

    return $type === 'munaqosyah'
        ? (float) $settings['minimum_munaqosyah_score']
        : (float) $settings['minimum_level_score'];
}

function svg_icon(string $name, int $size = 20): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'school' => '<path d="m3 10 9-7 9 7"/><path d="M5 9v11h14V9M9 20v-6h6v6"/><path d="M9 10h.01M15 10h.01"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
        'award' => '<circle cx="12" cy="8" r="6"/><path d="M15.5 13 17 22l-5-3-5 3 1.5-9"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/><path d="M4 5.5v14M12 3v14"/>',
        'printer' => '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'help' => '<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 1 1 5.8 1c0 2-3 2-3 4M12 18h.01"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21H9v-.1A1.7 1.7 0 0 0 8 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 3.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H2V9h.1A1.7 1.7 0 0 0 3.6 8a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 8 3.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V2H14v.1A1.7 1.7 0 0 0 15 3.6a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 8c.12.38.33.72.6 1 .3.28.68.42 1.1.4h.1V14h-.1a1.7 1.7 0 0 0-1.7 1z"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>',
        'activity' => '<path d="M3 12h4l3-9 4 18 3-9h4"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        'clipboard' => '<path d="M9 5h6M9 3h6v4H9z"/><rect x="5" y="5" width="14" height="17" rx="2"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
    ];

    $content = $paths[$name] ?? $paths['file'];

    return '<svg aria-hidden="true" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $content . '</svg>';
}

function audit_event(
    string $eventType,
    string $status,
    ?string $targetId = null,
    array $details = []
): void {
    try {
        $actorId = $_SESSION['user_id'] ?? null;

        if ($targetId !== null) {
            $targetCheck = db()->prepare(
                'SELECT COUNT(*) FROM users WHERE id = ?'
            );

            $targetCheck->execute([$targetId]);

            if (!(bool) $targetCheck->fetchColumn()) {
                $details['target_entity_id'] = $targetId;
                $targetId = null;
            }
        }

        $stmt = db()->prepare(
            'INSERT INTO account_security_events
             (actor_user_id, target_user_id, event_type, status, request_fingerprint, details)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $fingerprint = hash(
            'sha256',
            ($_SERVER['REMOTE_ADDR'] ?? '')
            . '|'
            . ($_SERVER['HTTP_USER_AGENT'] ?? '')
        );

        $stmt->execute([
            $actorId,
            $targetId,
            $eventType,
            $status,
            $fingerprint,
            json_encode(
                $details,
                JSON_UNESCAPED_UNICODE
            ),
        ]);
    } catch (Throwable $error) {
        error_log(
            'Audit NgajiYuk gagal: '
            . $error->getMessage()
        );
    }
}

function format_date_id(
    ?string $value,
    bool $withTime = false
): string {
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime($value);

    if (!$timestamp) {
        return e($value);
    }

    $months = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    $text =
        date('j', $timestamp)
        . ' '
        . $months[(int) date('n', $timestamp)]
        . ' '
        . date('Y', $timestamp);

    return $withTime
        ? $text . ', ' . date('H.i', $timestamp)
        : $text;
}
