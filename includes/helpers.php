<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, '/') ? $path : url($path)));
    exit;
}

function uuidv4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function request_data(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains(strtolower($contentType), 'application/json')) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function json_response(bool $success, string $message, mixed $data = null, int $status = 200): never
{
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
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
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
    if ($score >= 90) return ['Mumtaz', 'Istimewa', 'A'];
    if ($score >= 80) return ['Jayyid Jiddan', 'Sangat Bagus', 'A-'];
    if ($score >= 65) return ['Jayyid', 'Bagus', 'B'];
    if ($score >= 50) return ['Maqbul', 'Diterima/Lulus', 'C'];
    if ($score >= 35) return ['Dhaif', 'Lemah', 'D'];
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
        foreach (['A', 'B'] as $rombel) $classes[] = $grade . $rombel;
    }
    return $classes;
}

function academic_year_from_date(?string $date = null): string
{
    $timestamp = $date ? strtotime($date) : time();
    $year = (int) date('Y', $timestamp ?: time());
    $month = (int) date('n', $timestamp ?: time());
    $start = $month >= 7 ? $year : $year - 1;
    return $start . '/' . ($start + 1);
}

function audit_event(string $eventType, string $status, ?string $targetId = null, array $details = []): void
{
    try {
        $actorId = $_SESSION['user_id'] ?? null;
        if ($targetId !== null) {
            $targetCheck = db()->prepare('SELECT COUNT(*) FROM users WHERE id = ?');
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
        $fingerprint = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $stmt->execute([$actorId, $targetId, $eventType, $status, $fingerprint, json_encode($details, JSON_UNESCAPED_UNICODE)]);
    } catch (Throwable $error) {
        error_log('Audit NgajiYuk gagal: ' . $error->getMessage());
    }
}

function format_date_id(?string $value, bool $withTime = false): string
{
    if (!$value) return '-';
    $timestamp = strtotime($value);
    if (!$timestamp) return e($value);
    $months = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
    $text = date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
    return $withTime ? $text . ', ' . date('H.i', $timestamp) : $text;
}
