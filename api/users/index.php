<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$admin = require_api_user('admin');
$pdo = db();


/*
|--------------------------------------------------------------------------
| GET USERS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $pdo
        ->query(
            'SELECT
                id,
                username,
                email,
                full_name,
                role,
                approval_status,
                is_active,
                last_login_at,
                created_at
             FROM users
             ORDER BY created_at DESC'
        )
        ->fetchAll();

    json_response(
        true,
        'Daftar pengguna berhasil dimuat.',
        $rows
    );
}


/*
|--------------------------------------------------------------------------
| POST ONLY
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(
        false,
        'Metode tidak diizinkan.',
        null,
        405
    );
}


verify_csrf();

$data = request_data();

$action = trim(
    (string) ($data['action'] ?? '')
);

$targetId = trim(
    (string) ($data['user_id'] ?? '')
);


if ($action === '') {
    throw new RuntimeException(
        'Aksi tidak ditemukan.'
    );
}


/*
|--------------------------------------------------------------------------
| CREATE USER
|--------------------------------------------------------------------------
*/

if ($action === 'create') {
    $name = trim(
        (string) ($data['full_name'] ?? '')
    );

    $username = trim(
        (string) ($data['username'] ?? '')
    );

    $emailInput = trim(
        (string) ($data['email'] ?? '')
    );

    $email = $emailInput !== ''
        ? strtolower($emailInput)
        : null;

    $role = trim(
        (string) ($data['role'] ?? '')
    );

    $password = (string) (
        $data['password'] ?? ''
    );

    $confirmation = (string) (
        $data['password_confirmation'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($name === '') {
        throw new RuntimeException(
            'Nama lengkap wajib diisi.'
        );
    }

    if (strlen($name) > 190) {
        throw new RuntimeException(
            'Nama lengkap terlalu panjang.'
        );
    }

    if (
        !preg_match(
            '/^[A-Za-z0-9._-]{3,80}$/D',
            $username
        )
    ) {
        throw new RuntimeException(
            'Username harus 3-80 karakter dan hanya boleh berisi huruf, angka, titik, underscore, atau tanda minus.'
        );
    }

    if (
        !in_array(
            $role,
            [
                'admin',
                'guru',
                'orang_tua',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Role tidak valid.'
        );
    }

    if (
        $email !== null &&
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        throw new RuntimeException(
            'Format email tidak valid.'
        );
    }

    if (
        $email !== null &&
        strlen($email) > 190
    ) {
        throw new RuntimeException(
            'Email terlalu panjang.'
        );
    }

    if (strlen($password) < 8) {
        throw new RuntimeException(
            'Password minimal 8 karakter.'
        );
    }

    if ($password !== $confirmation) {
        throw new RuntimeException(
            'Konfirmasi password tidak sama.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK DUPLICATE USERNAME / EMAIL
    |--------------------------------------------------------------------------
    */

    $check = $pdo->prepare(
        'SELECT
            id,
            username,
            email
         FROM users
         WHERE
            LOWER(username) = LOWER(?)
            OR (
                ? IS NOT NULL
                AND LOWER(email) = LOWER(?)
            )
         LIMIT 1'
    );

    $check->execute([
        $username,
        $email,
        $email,
    ]);

    $existingUser = $check->fetch();

    if ($existingUser) {
        if (
            strcasecmp(
                (string) $existingUser['username'],
                $username
            ) === 0
        ) {
            throw new RuntimeException(
                'Username sudah digunakan.'
            );
        }

        throw new RuntimeException(
            'Email sudah digunakan.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE ACCOUNT
    |--------------------------------------------------------------------------
    */

    $id = uuidv4();

    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );


    try {
        $pdo->beginTransaction();


        $insertUser = $pdo->prepare(
            'INSERT INTO users (
                id,
                username,
                email,
                password_hash,
                full_name,
                role,
                approval_status,
                is_active,
                approved_at,
                approved_by
            )
            VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                \'approved\',
                1,
                NOW(),
                ?
            )'
        );

        $insertUser->execute([
            $id,
            $username,
            $email,
            $passwordHash,
            $name,
            $role,
            $admin['id'],
        ]);


        $insertRole = $pdo->prepare(
            'INSERT INTO user_roles (
                id,
                user_id,
                email,
                role
            )
            VALUES (?, ?, ?, ?)'
        );

        $insertRole->execute([
            uuidv4(),
            $id,
            $email,
            $role,
        ]);


        /*
        |--------------------------------------------------------------------------
        | CREATE TEACHER PROFILE
        |--------------------------------------------------------------------------
        */

        if ($role === 'guru') {
            $insertTeacher = $pdo->prepare(
                'INSERT INTO teacher_profiles (
                    user_id,
                    full_name
                )
                VALUES (?, ?)'
            );

            $insertTeacher->execute([
                $id,
                $name,
            ]);
        }


        $pdo->commit();


        audit_event(
            'account_created',
            'success',
            $id,
            [
                'target' => $username,
                'role' => $role,
            ]
        );


        json_response(
            true,
            'Akun berhasil dibuat.',
            [
                'id' => $id,
                'username' => $username,
                'role' => $role,
            ],
            201
        );

    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }


        error_log(
            'NgajiYuk create user gagal: '
            . $error->getMessage()
        );


        if ($error instanceof PDOException) {
            $mysqlErrorCode = (int) (
                $error->errorInfo[1] ?? 0
            );

            if ($mysqlErrorCode === 1062) {
                throw new RuntimeException(
                    'Username atau email sudah digunakan.'
                );
            }
        }


        throw $error;
    }
}


/*
|--------------------------------------------------------------------------
| TARGET USER REQUIRED
|--------------------------------------------------------------------------
*/

if ($targetId === '') {
    throw new RuntimeException(
        'ID akun tidak ditemukan.'
    );
}


$stmt = $pdo->prepare(
    'SELECT *
     FROM users
     WHERE id = ?
     LIMIT 1'
);

$stmt->execute([
    $targetId,
]);

$target = $stmt->fetch();


if (!$target) {
    throw new RuntimeException(
        'Akun tidak ditemukan.'
    );
}


/*
|--------------------------------------------------------------------------
| USER ACTIONS
|--------------------------------------------------------------------------
*/

switch ($action) {


    /*
    |--------------------------------------------------------------------------
    | APPROVE
    |--------------------------------------------------------------------------
    */

    case 'approve':

        $stmt = $pdo->prepare(
            'UPDATE users
             SET
                approval_status = \'approved\',
                is_active = 1,
                approved_at = NOW(),
                approved_by = ?
             WHERE id = ?'
        );

        $stmt->execute([
            $admin['id'],
            $targetId,
        ]);


        audit_event(
            'account_approved',
            'success',
            $targetId,
            [
                'target' => $target['username'],
            ]
        );


        json_response(
            true,
            'Akun berhasil disetujui.'
        );


    /*
    |--------------------------------------------------------------------------
    | REJECT
    |--------------------------------------------------------------------------
    */

    case 'reject':

        if ($targetId === $admin['id']) {
            throw new RuntimeException(
                'Akun yang sedang digunakan tidak dapat ditolak.'
            );
        }


        $stmt = $pdo->prepare(
            'UPDATE users
             SET
                approval_status = \'rejected\',
                is_active = 0,
                updated_at = NOW()
             WHERE id = ?'
        );

        $stmt->execute([
            $targetId,
        ]);


        audit_event(
            'account_rejected',
            'success',
            $targetId,
            [
                'target' => $target['username'],
            ]
        );


        json_response(
            true,
            'Akun ditolak dan dinonaktifkan.'
        );


    /*
    |--------------------------------------------------------------------------
    | TOGGLE ACTIVE
    |--------------------------------------------------------------------------
    */

    case 'toggle_active':

        $active = filter_var(
            $data['active'] ?? false,
            FILTER_VALIDATE_BOOL
        );


        if (
            $targetId === $admin['id'] &&
            !$active
        ) {
            throw new RuntimeException(
                'Akun Admin yang sedang digunakan tidak dapat dinonaktifkan.'
            );
        }


        $stmt = $pdo->prepare(
            'UPDATE users
             SET
                is_active = ?,
                updated_at = NOW()
             WHERE id = ?'
        );

        $stmt->execute([
            $active ? 1 : 0,
            $targetId,
        ]);


        audit_event(
            $active
                ? 'account_activated'
                : 'account_deactivated',
            'success',
            $targetId,
            [
                'target' => $target['username'],
            ]
        );


        json_response(
            true,
            $active
                ? 'Akun diaktifkan.'
                : 'Akun dinonaktifkan.'
        );


    /*
    |--------------------------------------------------------------------------
    | CHANGE ROLE
    |--------------------------------------------------------------------------
    */

    case 'change_role':

        $role = trim(
            (string) ($data['role'] ?? '')
        );


        if (
            !in_array(
                $role,
                [
                    'admin',
                    'guru',
                    'orang_tua',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Role tidak valid.'
            );
        }


        if (
            $targetId === $admin['id'] &&
            $role !== 'admin'
        ) {
            throw new RuntimeException(
                'Admin tidak dapat menurunkan role akun yang sedang digunakan.'
            );
        }


        $oldRole = (string) $target['role'];


        /*
         * Kalau role sama, tidak perlu cleanup atau update data.
         */
        if ($oldRole === $role) {
            json_response(
                true,
                'Role akun tidak berubah.'
            );
        }


        try {
            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | CLEANUP ROLE GURU
            |--------------------------------------------------------------------------
            |
            | Kalau user sebelumnya Guru dan sekarang bukan Guru:
            |
            | - Lepaskan dari wali kelas.
            | - Lepaskan dari siswa aktif yang ditugaskan.
            | - Hapus teacher profile.
            |
            | Data laporan historis TIDAK dihapus.
            |
            */

            if (
                $oldRole === 'guru' &&
                $role !== 'guru'
            ) {
                $detachClasses = $pdo->prepare(
                    'UPDATE classes
                     SET
                        teacher_id = NULL,
                        wali_kelas = NULL,
                        updated_at = NOW()
                     WHERE teacher_id = ?'
                );

                $detachClasses->execute([
                    $targetId,
                ]);


                $detachStudents = $pdo->prepare(
                    'UPDATE students
                     SET
                        teacher_id = NULL,
                        updated_at = NOW()
                     WHERE teacher_id = ?'
                );

                $detachStudents->execute([
                    $targetId,
                ]);


                $deleteTeacherProfile =
                    $pdo->prepare(
                        'DELETE FROM teacher_profiles
                         WHERE user_id = ?'
                    );

                $deleteTeacherProfile->execute([
                    $targetId,
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | CLEANUP ROLE ORANG TUA
            |--------------------------------------------------------------------------
            |
            | Kalau user sebelumnya Orang Tua dan pindah role,
            | hubungan aktif dengan siswa dinonaktifkan.
            |
            | Record tidak dihapus supaya histori tetap ada.
            |
            */

            if (
                $oldRole === 'orang_tua' &&
                $role !== 'orang_tua'
            ) {
                $disableParentLinks =
                    $pdo->prepare(
                        'UPDATE parent_student_links
                         SET
                            status = \'inactive\',
                            updated_at = NOW()
                         WHERE
                            parent_id = ?
                            AND status = \'active\''
                    );

                $disableParentLinks->execute([
                    $targetId,
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE MAIN ROLE
            |--------------------------------------------------------------------------
            */

            $updateUser = $pdo->prepare(
                'UPDATE users
                 SET
                    role = ?,
                    updated_at = NOW()
                 WHERE id = ?'
            );

            $updateUser->execute([
                $role,
                $targetId,
            ]);


            /*
            |--------------------------------------------------------------------------
            | UPDATE USER_ROLES
            |--------------------------------------------------------------------------
            */

            $upsertRole = $pdo->prepare(
                'INSERT INTO user_roles (
                    id,
                    user_id,
                    email,
                    role
                )
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    email = VALUES(email),
                    role = VALUES(role),
                    updated_at = NOW()'
            );

            $upsertRole->execute([
                uuidv4(),
                $targetId,
                $target['email'],
                $role,
            ]);


            /*
            |--------------------------------------------------------------------------
            | NEW ROLE = GURU
            |--------------------------------------------------------------------------
            |
            | Buat kembali teacher profile bila diperlukan.
            |
            */

            if ($role === 'guru') {
                $teacherProfile =
                    $pdo->prepare(
                        'INSERT INTO teacher_profiles (
                            user_id,
                            full_name
                        )
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE
                            full_name = VALUES(full_name),
                            updated_at = NOW()'
                    );

                $teacherProfile->execute([
                    $targetId,
                    $target['full_name'],
                ]);
            }


            $pdo->commit();


            audit_event(
                'role_changed',
                'success',
                $targetId,
                [
                    'from' => $oldRole,
                    'to' => $role,
                    'target' => $target['username'],
                ]
            );


            json_response(
                true,
                'Role akun berhasil diubah.'
            );

        } catch (Throwable $error) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }


            error_log(
                'NgajiYuk change role gagal: '
                . $error->getMessage()
            );


            throw $error;
        }


    /*
    |--------------------------------------------------------------------------
    | CHANGE PASSWORD
    |--------------------------------------------------------------------------
    */

    case 'change_password':

        $password = (string) (
            $data['password'] ?? ''
        );

        $confirmation = (string) (
            $data['password_confirmation'] ?? ''
        );


        if (strlen($password) < 8) {
            throw new RuntimeException(
                'Password minimal 8 karakter.'
            );
        }


        if ($password !== $confirmation) {
            throw new RuntimeException(
                'Konfirmasi password tidak sama.'
            );
        }


        $stmt = $pdo->prepare(
            'UPDATE users
             SET
                password_hash = ?,
                updated_at = NOW()
             WHERE id = ?'
        );


        $stmt->execute([
            password_hash(
                $password,
                PASSWORD_DEFAULT
            ),
            $targetId,
        ]);


        audit_event(
            'password_changed',
            'success',
            $targetId,
            [
                'source' => 'administrator',
                'target' => $target['username'],
            ]
        );


        json_response(
            true,
            'Password berhasil diganti dan langsung dapat digunakan.'
        );


    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    case 'delete':

        if ($targetId === $admin['id']) {
            throw new RuntimeException(
                'Akun yang sedang digunakan tidak dapat dihapus.'
            );
        }


        if ($target['role'] === 'admin') {
            $count = (int) $pdo
                ->query(
                    'SELECT COUNT(*)
                     FROM users
                     WHERE
                        role = \'admin\'
                        AND is_active = 1'
                )
                ->fetchColumn();


            if ($count <= 1) {
                throw new RuntimeException(
                    'Admin terakhir tidak dapat dihapus.'
                );
            }
        }


        $deletedUsername =
            (string) $target['username'];

        $deletedRole =
            (string) $target['role'];


        try {
            $stmt = $pdo->prepare(
                'DELETE FROM users
                 WHERE id = ?'
            );

            $stmt->execute([
                $targetId,
            ]);


            audit_event(
                'account_deleted',
                'success',
                null,
                [
                    'deleted_username' =>
                        $deletedUsername,

                    'role' =>
                        $deletedRole,
                ]
            );


            json_response(
                true,
                'Akun berhasil dihapus.'
            );

        } catch (Throwable $error) {

            error_log(
                'NgajiYuk delete user gagal: '
                . $error->getMessage()
            );


            throw $error;
        }


    /*
    |--------------------------------------------------------------------------
    | UNKNOWN ACTION
    |--------------------------------------------------------------------------
    */

    default:

        throw new RuntimeException(
            'Aksi akun tidak dikenali.'
        );
}