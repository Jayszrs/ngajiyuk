<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$admin = require_api_user('admin');

$pdo = db();


/**
 * GET USERS
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


/**
 * Hanya POST setelah ini.
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


/**
 * =========================================================
 * CREATE USER
 * =========================================================
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

    $email =
        $emailInput !== ''
            ? strtolower($emailInput)
            : null;

    $role = trim(
        (string) ($data['role'] ?? '')
    );

    $password =
        (string) ($data['password'] ?? '');

    $confirmation =
        (string) (
            $data['password_confirmation']
            ?? ''
        );


    /**
     * Validasi nama
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


    /**
     * Validasi username
     */
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


    /**
     * Validasi role
     */
    if (
        !in_array(
            $role,
            [
                'admin',
                'guru',
                'orang_tua'
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Role tidak valid.'
        );
    }


    /**
     * Validasi email
     */
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


    /**
     * Validasi password
     */
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


    /**
     * Cek username/email terlebih dahulu
     * agar pesan error lebih jelas.
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
        $email
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


    $id = uuidv4();

    $passwordHash =
        password_hash(
            $password,
            PASSWORD_DEFAULT
        );


    try {

        $pdo->beginTransaction();


        /**
         * users
         */
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
            $admin['id']
        ]);


        /**
         * user_roles
         */
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
            $role
        ]);


        /**
         * Teacher profile
         *
         * Hanya dibuat kalau role adalah guru.
         */
        if ($role === 'guru') {

            $insertTeacher =
                $pdo->prepare(
                    'INSERT INTO teacher_profiles (
                        user_id,
                        full_name
                    )
                    VALUES (?, ?)'
                );

            $insertTeacher->execute([
                $id,
                $name
            ]);
        }


        $pdo->commit();


        audit_event(
            'account_created',
            'success',
            $id,
            [
                'target' => $username,
                'role' => $role
            ]
        );


        json_response(
            true,
            'Akun berhasil dibuat.',
            [
                'id' => $id,
                'username' => $username,
                'role' => $role
            ],
            201
        );

    } catch (Throwable $error) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }


        /**
         * Jangan sembunyikan error asli dari log XAMPP.
         */
        error_log(
            'NgajiYuk create user gagal: '
            . $error->getMessage()
        );


        /**
         * MySQL duplicate key = 1062.
         */
        if ($error instanceof PDOException) {

            $mysqlErrorCode =
                (int) (
                    $error->errorInfo[1]
                    ?? 0
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


/**
 * Selain CREATE membutuhkan user_id.
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
    $targetId
]);

$target = $stmt->fetch();


if (!$target) {
    throw new RuntimeException(
        'Akun tidak ditemukan.'
    );
}


/**
 * =========================================================
 * USER ACTIONS
 * =========================================================
 */
switch ($action) {

    /**
     * APPROVE
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
            $targetId
        ]);


        audit_event(
            'account_approved',
            'success',
            $targetId,
            [
                'target' =>
                    $target['username']
            ]
        );


        json_response(
            true,
            'Akun berhasil disetujui.'
        );


    /**
     * REJECT
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
            $targetId
        ]);


        audit_event(
            'account_rejected',
            'success',
            $targetId,
            [
                'target' =>
                    $target['username']
            ]
        );


        json_response(
            true,
            'Akun ditolak dan dinonaktifkan.'
        );


    /**
     * TOGGLE ACTIVE
     */
    case 'toggle_active':

        if ($targetId === $admin['id']) {
            throw new RuntimeException(
                'Akun Admin yang sedang digunakan tidak dapat dinonaktifkan.'
            );
        }


        $active = filter_var(
            $data['active'] ?? false,
            FILTER_VALIDATE_BOOL
        );


        $stmt = $pdo->prepare(
            'UPDATE users
             SET
                is_active = ?,
                updated_at = NOW()
             WHERE id = ?'
        );

        $stmt->execute([
            $active ? 1 : 0,
            $targetId
        ]);


        audit_event(
            $active
                ? 'account_activated'
                : 'account_deactivated',
            'success',
            $targetId,
            [
                'target' =>
                    $target['username']
            ]
        );


        json_response(
            true,
            $active
                ? 'Akun diaktifkan.'
                : 'Akun dinonaktifkan.'
        );


    /**
     * CHANGE ROLE
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
                    'orang_tua'
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


        try {

            $pdo->beginTransaction();


            $updateUser = $pdo->prepare(
                'UPDATE users
                 SET
                    role = ?,
                    updated_at = NOW()
                 WHERE id = ?'
            );

            $updateUser->execute([
                $role,
                $targetId
            ]);


            /**
             * Pastikan user_roles selalu ada.
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
                $role
            ]);


            /**
             * Kalau berubah menjadi Guru,
             * pastikan teacher_profiles tersedia.
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
                    $target['full_name']
                ]);
            }


            $pdo->commit();


            audit_event(
                'role_changed',
                'success',
                $targetId,
                [
                    'from' =>
                        $target['role'],
                    'to' =>
                        $role,
                    'target' =>
                        $target['username']
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


    /**
     * CHANGE PASSWORD
     */
    case 'change_password':

        $password =
            (string) (
                $data['password']
                ?? ''
            );

        $confirmation =
            (string) (
                $data['password_confirmation']
                ?? ''
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
            $targetId
        ]);


        audit_event(
            'password_changed',
            'success',
            $targetId,
            [
                'source' =>
                    'administrator',
                'target' =>
                    $target['username']
            ]
        );


        json_response(
            true,
            'Password berhasil diganti dan langsung dapat digunakan.'
        );


    /**
     * DELETE
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
            $target['username'];

        $deletedRole =
            $target['role'];


        try {

            $stmt = $pdo->prepare(
                'DELETE FROM users
                 WHERE id = ?'
            );

            $stmt->execute([
                $targetId
            ]);


            /**
             * FK CASCADE pada schema akan menangani
             * user_roles / teacher_profiles yang terkait.
             */
            audit_event(
                'account_deleted',
                'success',
                null,
                [
                    'deleted_username' =>
                        $deletedUsername,
                    'role' =>
                        $deletedRole
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


    default:

        throw new RuntimeException(
            'Aksi akun tidak dikenali.'
        );
}