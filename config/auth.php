<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

function current_user(bool $refresh = false): ?array
{
    static $cached = null;

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    if ($cached !== null && !$refresh) {
        return $cached;
    }

    $stmt = db()->prepare(
        'SELECT
            id,
            username,
            email,
            full_name,
            role,
            approval_status,
            is_active,
            phone,
            address,
            bio,
            photo_url,
            last_login_at,
            created_at
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $_SESSION['user_id'],
    ]);

    $user = $stmt->fetch();

    if (
        !$user ||
        !(int) $user['is_active'] ||
        $user['approval_status'] !== 'approved'
    ) {
        logout_user();

        return null;
    }

    return $cached = $user;
}


/*
|--------------------------------------------------------------------------
| LOGIN STATUS
|--------------------------------------------------------------------------
*/

function is_logged_in(): bool
{
    return current_user() !== null;
}


/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

function login_user(
    string $identifier,
    string $password
): array {
    $identifier = trim($identifier);

    if ($identifier === '' || $password === '') {
        return [
            false,
            'Username/email atau password salah.',
        ];
    }

    $stmt = db()->prepare(
        'SELECT *
         FROM users
         WHERE
            LOWER(username) = LOWER(?)
            OR LOWER(email) = LOWER(?)
         LIMIT 1'
    );

    $stmt->execute([
        $identifier,
        $identifier,
    ]);

    $user = $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | INVALID LOGIN
    |--------------------------------------------------------------------------
    */

    if (
        !$user ||
        !password_verify(
            $password,
            (string) $user['password_hash']
        )
    ) {
        audit_event(
            'login',
            'failed',
            $user['id'] ?? null,
            [
                'identifier' => $identifier,
            ]
        );

        return [
            false,
            'Username/email atau password salah.',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | ACCOUNT ACTIVE CHECK
    |--------------------------------------------------------------------------
    */

    if (!(int) $user['is_active']) {
        audit_event(
            'login',
            'blocked',
            $user['id'],
            [
                'reason' => 'inactive',
            ]
        );

        return [
            false,
            'Akun sedang dinonaktifkan. Hubungi Administrator.',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | APPROVAL CHECK
    |--------------------------------------------------------------------------
    */

    if ($user['approval_status'] !== 'approved') {
        audit_event(
            'login',
            'blocked',
            $user['id'],
            [
                'reason' => $user['approval_status'],
            ]
        );

        return [
            false,
            'Akun belum disetujui Administrator.',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE LOGIN SESSION
    |--------------------------------------------------------------------------
    */

    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];


    /*
    |--------------------------------------------------------------------------
    | UPDATE LAST LOGIN
    |--------------------------------------------------------------------------
    */

    $updateLogin = db()->prepare(
        'UPDATE users
         SET
            last_login_at = NOW(),
            updated_at = NOW()
         WHERE id = ?'
    );

    $updateLogin->execute([
        $user['id'],
    ]);


    audit_event(
        'login',
        'success',
        $user['id'],
        [
            'role' => $user['role'],
        ]
    );


    return [
        true,
        'Login berhasil.',
    ];
}


/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/

function logout_user(): void
{
    if (!empty($_SESSION['user_id'])) {
        audit_event(
            'logout',
            'success',
            (string) $_SESSION['user_id']
        );
    }


    $_SESSION = [];


    /*
    |--------------------------------------------------------------------------
    | DELETE SESSION COOKIE
    |--------------------------------------------------------------------------
    */

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'] ?: '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }


    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}


/*
|--------------------------------------------------------------------------
| REQUIRE GUEST
|--------------------------------------------------------------------------
*/

function require_guest(): void
{
    if (is_logged_in()) {
        $user = current_user();

        redirect(
            dashboard_path(
                (string) $user['role']
            )
        );
    }
}


/*
|--------------------------------------------------------------------------
| REQUIRE LOGIN
|--------------------------------------------------------------------------
*/

function require_login(): array
{
    $user = current_user();

    if (!$user) {
        redirect('login.php');
    }

    return $user;
}


/*
|--------------------------------------------------------------------------
| REQUIRE ROLE
|--------------------------------------------------------------------------
*/

function require_role(string ...$roles): array
{
    $user = require_login();

    if (
        !in_array(
            $user['role'],
            $roles,
            true
        )
    ) {
        audit_event(
            'unauthorized_access',
            'blocked',
            $user['id'],
            [
                'path' =>
                    $_SERVER['REQUEST_URI'] ?? '',

                'required' =>
                    $roles,

                'actual_role' =>
                    $user['role'],
            ]
        );


        http_response_code(403);

        exit('Akses ditolak.');
    }

    return $user;
}


/*
|--------------------------------------------------------------------------
| DASHBOARD PATH
|--------------------------------------------------------------------------
*/

function dashboard_path(string $role): string
{
    return match ($role) {
        'admin' =>
            'admin/dashboard.php',

        'guru' =>
            'guru/dashboard.php',

        'orang_tua' =>
            'orangtua/dashboard.php',

        default =>
            'login.php',
    };
}


/*
|--------------------------------------------------------------------------
| PARENT STUDENT
|--------------------------------------------------------------------------
*/

function parent_student_id(
    string $parentId
): ?string {
    $stmt = db()->prepare(
        "SELECT student_id
         FROM parent_student_links
         WHERE
            parent_id = ?
            AND status = 'active'
         LIMIT 1"
    );

    $stmt->execute([
        $parentId,
    ]);

    $value = $stmt->fetchColumn();

    return $value
        ? (string) $value
        : null;
}


/*
|--------------------------------------------------------------------------
| TEACHER STUDENT ACCESS
|--------------------------------------------------------------------------
|
| Guru hanya dianggap memiliki akses jika:
|
| students.teacher_id = user.id
|
| Admin tetap boleh mengakses semua siswa.
|
| Orang tua hanya boleh READ terhadap anak yang memiliki
| parent_student_links aktif.
|
*/

function teacher_can_access_student(
    string $teacherId,
    string $studentId
): bool {
    if (
        $teacherId === '' ||
        $studentId === ''
    ) {
        return false;
    }

    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM students
         WHERE
            id = ?
            AND teacher_id = ?'
    );

    $stmt->execute([
        $studentId,
        $teacherId,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}


/*
|--------------------------------------------------------------------------
| STUDENT AUTHORIZATION
|--------------------------------------------------------------------------
*/

function can_access_student(
    array $user,
    string $studentId,
    bool $write = false
): bool {
    if ($studentId === '') {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN
    |--------------------------------------------------------------------------
    */

    if ($user['role'] === 'admin') {
        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | GURU
    |--------------------------------------------------------------------------
    |
    | Guru hanya boleh siswa yang teacher_id-nya sama
    | dengan ID Guru tersebut.
    |
    */

    if ($user['role'] === 'guru') {
        return teacher_can_access_student(
            (string) $user['id'],
            $studentId
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ORANG TUA
    |--------------------------------------------------------------------------
    |
    | Orang tua hanya diberikan akses baca.
    | Perubahan data siswa tetap ditolak.
    |
    */

    if ($user['role'] === 'orang_tua') {
        if ($write) {
            return false;
        }

        return parent_student_id(
            (string) $user['id']
        ) === $studentId;
    }


    return false;
}