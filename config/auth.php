<?php
declare(strict_types=1);

function current_user(bool $refresh = false): ?array
{
    static $cached = null;
    if (empty($_SESSION['user_id'])) return null;
    if ($cached !== null && !$refresh) return $cached;

    $stmt = db()->prepare(
        'SELECT id, username, email, full_name, role, approval_status, is_active,
                phone, address, bio, photo_url, last_login_at, created_at
         FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || !(int) $user['is_active']) {
        logout_user();
        return null;
    }
    return $cached = $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function login_user(string $identifier, string $password): array
{
    $identifier = trim($identifier);
    $stmt = db()->prepare(
        'SELECT * FROM users WHERE (LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)) LIMIT 1'
    );
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        audit_event('login', 'failed', $user['id'] ?? null, ['identifier' => $identifier]);
        return [false, 'Username/email atau password salah.'];
    }
    if (!(int) $user['is_active']) {
        audit_event('login', 'blocked', $user['id'], ['reason' => 'inactive']);
        return [false, 'Akun sedang dinonaktifkan. Hubungi Administrator.'];
    }
    if ($user['approval_status'] !== 'approved') {
        audit_event('login', 'blocked', $user['id'], ['reason' => $user['approval_status']]);
        return [false, 'Akun belum disetujui Administrator.'];
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    db()->prepare('UPDATE users SET last_login_at = NOW(), updated_at = NOW() WHERE id = ?')->execute([$user['id']]);
    audit_event('login', 'success', $user['id'], ['role' => $user['role']]);
    return [true, 'Login berhasil.'];
}

function logout_user(): void
{
    if (!empty($_SESSION['user_id'])) audit_event('logout', 'success', (string) $_SESSION['user_id']);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}

function require_guest(): void
{
    if (is_logged_in()) redirect(dashboard_path((string) current_user()['role']));
}

function require_login(): array
{
    $user = current_user();
    if (!$user) redirect('login.php');
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        audit_event('unauthorized_access', 'blocked', $user['id'], ['path' => $_SERVER['REQUEST_URI'] ?? '', 'required' => $roles]);
        http_response_code(403);
        exit('Akses ditolak.');
    }
    return $user;
}

function dashboard_path(string $role): string
{
    return match ($role) {
        'admin' => 'admin/dashboard.php',
        'guru' => 'guru/dashboard.php',
        'orang_tua' => 'orangtua/dashboard.php',
        default => 'login.php',
    };
}

function parent_student_id(string $parentId): ?string
{
    $stmt = db()->prepare("SELECT student_id FROM parent_student_links WHERE parent_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$parentId]);
    $value = $stmt->fetchColumn();
    return $value ? (string) $value : null;
}

function can_access_student(array $user, string $studentId, bool $write = false): bool
{
    if ($user['role'] === 'admin') return true;
    if ($user['role'] === 'guru') return true;
    if ($write) return false;
    return $user['role'] === 'orang_tua' && parent_student_id($user['id']) === $studentId;
}

