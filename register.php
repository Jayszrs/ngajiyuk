<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

require_guest();

$error = null;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $name = trim(
        (string) (
            $_POST['full_name']
            ?? ''
        )
    );

    $username = trim(
        (string) (
            $_POST['username']
            ?? ''
        )
    );

    $emailInput = trim(
        (string) (
            $_POST['email']
            ?? ''
        )
    );

    $email =
        $emailInput !== ''
            ? strtolower($emailInput)
            : null;

    $role =
        (string) (
            $_POST['role']
            ?? ''
        );

    $nis = trim(
        (string) (
            $_POST['nis']
            ?? ''
        )
    );

    $password =
        (string) (
            $_POST['password']
            ?? ''
        );

    $confirmation =
        (string) (
            $_POST['password_confirmation']
            ?? ''
        );


    try {

        if ($name === '') {
            throw new RuntimeException(
                'Nama lengkap wajib diisi.'
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
                    'guru',
                    'orang_tua'
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Role pendaftaran tidak valid.'
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


        if (
            $role === 'orang_tua' &&
            $nis === ''
        ) {
            throw new RuntimeException(
                'NIS anak wajib diisi untuk akun Orang Tua.'
            );
        }


        $pdo = db();


        /**
         * Duplicate check.
         */
        $check = $pdo->prepare(
            'SELECT id
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


        if ($check->fetch()) {
            throw new RuntimeException(
                'Username atau email sudah digunakan.'
            );
        }


        /**
         * Cari siswa untuk Orang Tua.
         */
        $studentId = null;


        if ($role === 'orang_tua') {

            $stmt = $pdo->prepare(
                'SELECT s.id
                 FROM students s
                 LEFT JOIN parent_student_links l
                    ON l.student_id = s.id
                    AND l.status = \'active\'
                 WHERE
                    TRIM(s.nis) = TRIM(?)
                    AND l.parent_id IS NULL
                 LIMIT 1'
            );

            $stmt->execute([
                $nis
            ]);

            $studentId =
                $stmt->fetchColumn();


            if (!$studentId) {
                throw new RuntimeException(
                    'NIS tidak ditemukan atau anak sudah terhubung dengan akun lain.'
                );
            }
        }


        $id = uuidv4();

        $approval =
            $role === 'orang_tua'
                ? 'approved'
                : 'pending';


        try {

            $pdo->beginTransaction();


            /**
             * users
             */
            $stmt = $pdo->prepare(
                'INSERT INTO users (
                    id,
                    username,
                    email,
                    password_hash,
                    full_name,
                    role,
                    approval_status,
                    is_active,
                    approved_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $id,
                $username,
                $email,
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                ),
                $name,
                $role,
                $approval,
                1,
                $approval === 'approved'
                    ? date('Y-m-d H:i:s')
                    : null
            ]);


            /**
             * user_roles
             */
            $stmt = $pdo->prepare(
                'INSERT INTO user_roles (
                    id,
                    user_id,
                    email,
                    role
                )
                VALUES (?, ?, ?, ?)'
            );

            $stmt->execute([
                uuidv4(),
                $id,
                $email,
                $role
            ]);


            /**
             * Link parent ke student.
             */
            if ($studentId) {

                $stmt = $pdo->prepare(
                    'INSERT INTO parent_student_links (
                        parent_id,
                        student_id,
                        status
                    )
                    VALUES (?, ?, \'active\')'
                );

                $stmt->execute([
                    $id,
                    $studentId
                ]);
            }


            /**
             * Teacher profile.
             */
            if ($role === 'guru') {

                $stmt = $pdo->prepare(
                    'INSERT INTO teacher_profiles (
                        user_id,
                        full_name
                    )
                    VALUES (?, ?)'
                );

                $stmt->execute([
                    $id,
                    $name
                ]);
            }


            $pdo->commit();


            audit_event(
                'account_registered',
                'success',
                $id,
                [
                    'role' =>
                        $role,
                    'approval_status' =>
                        $approval
                ]
            );


            flash(
                'success',
                $role === 'guru'
                    ? 'Pendaftaran berhasil. Tunggu persetujuan Administrator.'
                    : 'Akun Orang Tua berhasil dibuat dan terhubung dengan anak.'
            );


            redirect('login.php');

        } catch (Throwable $databaseError) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }


            error_log(
                'NgajiYuk register gagal: '
                . $databaseError->getMessage()
            );


            if (
                $databaseError
                instanceof PDOException
            ) {

                $mysqlErrorCode =
                    (int) (
                        $databaseError
                            ->errorInfo[1]
                        ?? 0
                    );


                if (
                    $mysqlErrorCode === 1062
                ) {
                    throw new RuntimeException(
                        'Username atau email sudah digunakan.'
                    );
                }
            }


            throw $databaseError;
        }

    } catch (Throwable $exception) {

        $error =
            $exception instanceof PDOException
                ? 'Pendaftaran gagal karena terjadi kesalahan database.'
                : $exception->getMessage();
    }
}

?>

<!doctype html>

<html lang="id">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Buat akun · NgajiYuk
    </title>

    <link
        rel="stylesheet"
        href="<?= e(
            url('assets/css/app.css')
        ) ?>"
    >

</head>

<body>

<div class="auth-shell">

    <section class="auth-visual">

        <a
            class="brand"
            href="<?= e(url()) ?>"
        >

            <img
                src="<?= e(
                    url(
                        'assets/images/logo.png'
                    )
                ) ?>"
                alt="Logo"
            >

            <span>

                <strong>
                    NGAJIYUK
                </strong>

                <small
                    style="color:#d4f6e5"
                >
                    SD ISLAM LABSCHOOL BANI SALEH
                </small>

            </span>

        </a>


        <div class="auth-message">

            <p
                class="eyebrow"
                style="color:#ffe477"
            >
                Portal Pendaftaran
            </p>

            <h2>
                Mulai pemantauan mengaji
                yang terarah.
            </h2>

            <p style="font-size:18px">
                Akun Guru memerlukan
                persetujuan Admin.
                Akun Orang Tua diverifikasi
                menggunakan NIS anak dan
                hanya dapat terhubung ke
                satu siswa.
            </p>

        </div>

        <small>
            Data dilindungi dengan akses
            sesuai peran.
        </small>

    </section>


    <main class="auth-panel">

        <div class="auth-card">

            <a
                class="auth-back"
                href="<?= e(url()) ?>"
            >
                ← Kembali ke beranda
            </a>


            <p
                class="eyebrow"
                style="margin-top:24px"
            >
                Pendaftaran Akun
            </p>


            <h1>
                Buat akun baru
            </h1>


            <?php if ($error): ?>

                <div
                    class="alert alert-error"
                >
                    <?= e($error) ?>
                </div>

            <?php endif; ?>


            <form
                method="post"
                class="form-grid"
            >

                <?= csrf_field() ?>


                <div class="field full">

                    <label>
                        Nama Lengkap
                    </label>

                    <input
                        class="input"
                        name="full_name"
                        maxlength="190"
                        required
                        value="<?= e(
                            $_POST[
                                'full_name'
                            ] ?? ''
                        ) ?>"
                    >

                </div>


                <div class="field">

                    <label>
                        Username
                    </label>

                    <input
                        class="input"
                        name="username"
                        minlength="3"
                        maxlength="80"
                        required
                        pattern="[A-Za-z0-9._\-]{3,80}"
                        title="Username 3-80 karakter. Gunakan huruf, angka, titik, underscore, atau tanda minus."
                        value="<?= e(
                            $_POST[
                                'username'
                            ] ?? ''
                        ) ?>"
                    >

                </div>


                <div class="field">

                    <label>
                        Email (opsional)
                    </label>

                    <input
                        class="input"
                        type="email"
                        name="email"
                        maxlength="190"
                        value="<?= e(
                            $_POST[
                                'email'
                            ] ?? ''
                        ) ?>"
                    >

                </div>


                <div class="field">

                    <label>
                        Role
                    </label>

                    <select
                        class="select"
                        name="role"
                        id="register-role"
                        required
                    >

                        <option
                            value="guru"
                            <?= (
                                $_POST[
                                    'role'
                                ] ?? ''
                            ) === 'guru'
                                ? 'selected'
                                : '' ?>
                        >
                            Guru
                        </option>

                        <option
                            value="orang_tua"
                            <?= (
                                $_POST[
                                    'role'
                                ] ?? ''
                            ) === 'orang_tua'
                                ? 'selected'
                                : '' ?>
                        >
                            Orang Tua
                        </option>

                    </select>

                </div>


                <div
                    class="field"
                    id="nis-field"
                >

                    <label>
                        NIS Anak
                        (untuk Orang Tua)
                    </label>

                    <input
                        class="input"
                        name="nis"
                        value="<?= e(
                            $_POST[
                                'nis'
                            ] ?? ''
                        ) ?>"
                    >

                </div>


                <div class="field">

                    <label>
                        Password
                    </label>

                    <input
                        class="input"
                        type="password"
                        name="password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                </div>


                <div class="field">

                    <label>
                        Konfirmasi Password
                    </label>

                    <input
                        class="input"
                        type="password"
                        name="password_confirmation"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                </div>


                <button
                    class="
                        btn
                        btn-primary
                        btn-block
                        field
                        full
                    "
                    type="submit"
                >
                    Daftar Akun
                </button>

            </form>


            <p class="auth-links">

                Sudah punya akun?

                <a
                    href="<?= e(
                        url('login.php')
                    ) ?>"
                >
                    Masuk di sini
                </a>

            </p>

        </div>

    </main>

</div>

</body>

</html>