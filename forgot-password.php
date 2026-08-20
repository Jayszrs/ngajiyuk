<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

require_guest();

$notice = null;
$resetLink = null;


/*
|--------------------------------------------------------------------------
| LOCAL REQUEST CHECK
|--------------------------------------------------------------------------
|
| Link reset password hanya boleh ditampilkan jika halaman dibuka
| langsung dari komputer server/XAMPP.
|
| localhost:
| - 127.0.0.1
| - ::1
|
| Jika dibuka dari perangkat lain melalui LAN atau hosting,
| link reset tidak akan ditampilkan.
|
*/

$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

$isLocalRequest = in_array(
    $remoteAddress,
    [
        '127.0.0.1',
        '::1',
    ],
    true
);


/*
|--------------------------------------------------------------------------
| PASSWORD RESET REQUEST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $identifier = trim(
        (string) ($_POST['identifier'] ?? '')
    );

    /*
    |--------------------------------------------------------------------------
    | GENERIC RESPONSE
    |--------------------------------------------------------------------------
    |
    | Pesan dibuat sama baik akun ditemukan maupun tidak.
    | Ini mencegah orang menebak username/email yang terdaftar.
    |
    */

    $notice = $isLocalRequest
        ? 'Jika akun ditemukan, permintaan reset telah dibuat. Pada instalasi lokal XAMPP, tautan reset akan tersedia di bawah.'
        : 'Jika akun ditemukan, permintaan reset telah dibuat. Silakan hubungi Administrator untuk melanjutkan proses reset password.';


    if ($identifier !== '') {
        $stmt = db()->prepare(
            'SELECT
                id,
                email
             FROM users
             WHERE LOWER(username) = LOWER(?)
                OR LOWER(email) = LOWER(?)
             LIMIT 1'
        );

        $stmt->execute([
            $identifier,
            $identifier,
        ]);

        $account = $stmt->fetch();


        /*
        |--------------------------------------------------------------------------
        | ACCOUNT FOUND
        |--------------------------------------------------------------------------
        */

        if ($account) {
            $token = bin2hex(
                random_bytes(32)
            );

            $tokenHash = hash(
                'sha256',
                $token
            );


            /*
            |--------------------------------------------------------------------------
            | DELETE OLD TOKENS
            |--------------------------------------------------------------------------
            |
            | Token reset lama untuk user ini dihapus supaya tidak menumpuk
            | dan supaya hanya request reset terbaru yang berlaku.
            |
            */

            $deleteOldTokens = db()->prepare(
                'DELETE FROM password_reset_tokens
                 WHERE user_id = ?'
            );

            $deleteOldTokens->execute([
                $account['id'],
            ]);


            /*
            |--------------------------------------------------------------------------
            | CREATE NEW TOKEN
            |--------------------------------------------------------------------------
            */

            $createToken = db()->prepare(
                'INSERT INTO password_reset_tokens (
                    user_id,
                    token_hash,
                    expires_at
                )
                VALUES (
                    ?,
                    ?,
                    DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                )'
            );

            $createToken->execute([
                $account['id'],
                $tokenHash,
            ]);


            /*
            |--------------------------------------------------------------------------
            | LOCAL RESET LINK
            |--------------------------------------------------------------------------
            |
            | Hanya tampil jika request berasal dari localhost.
            |
            */

            if ($isLocalRequest) {
                $resetLink = url(
                    'reset-password.php?token='
                    . urlencode($token)
                );
            }


            /*
            |--------------------------------------------------------------------------
            | AUDIT LOG
            |--------------------------------------------------------------------------
            */

            audit_event(
                'password_reset_requested',
                'success',
                $account['id'],
                [
                    'delivery' => $isLocalRequest
                        ? 'local_link'
                        : (
                            !empty($account['email'])
                                ? 'email_not_configured'
                                : 'administrator'
                        ),
                ]
            );
        }
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
        Lupa password · Catatan Mengaji Digital
    </title>

    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/app.css')) ?>"
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
                src="<?= e(url('assets/images/logo.png')) ?>"
                alt="Logo"
            >

            <span>
                <strong>CATATAN MENGAJI DIGITAL</strong>

                <small style="color:#d4f6e5">
                    SD ISLAM LABSCHOOL BANI SALEH
                </small>
            </span>
        </a>

        <div class="auth-message">
            <p
                class="eyebrow"
                style="color:#ffe477"
            >
                Pemulihan Akun
            </p>

            <h2>
                Kembali mengelola perjalanan belajar.
            </h2>
        </div>

    </section>


    <main class="auth-panel">

        <div class="auth-card">

            <a
                class="auth-back"
                href="<?= e(url('login.php')) ?>"
            >
                ← Kembali ke login
            </a>

            <p
                class="eyebrow"
                style="margin-top:28px"
            >
                Keamanan Akun
            </p>

            <h1>
                Lupa password
            </h1>

            <p class="muted">
                Masukkan email atau username yang terdaftar.
            </p>


            <?php if ($notice): ?>

                <div class="alert alert-success">
                    <?= e($notice) ?>
                </div>

            <?php endif; ?>


            <?php if ($resetLink): ?>

                <a
                    class="btn btn-primary btn-block"
                    href="<?= e($resetLink) ?>"
                >
                    Buka Form Password Baru
                </a>

            <?php else: ?>

                <form
                    method="post"
                    class="grid"
                    style="margin-top:26px"
                >

                    <?= csrf_field() ?>

                    <div class="field">

                        <label>
                            Email atau Username
                        </label>

                        <input
                            class="input"
                            name="identifier"
                            autocomplete="username"
                            required
                        >

                    </div>

                    <button
                        class="btn btn-primary"
                        type="submit"
                    >
                        Buat Permintaan Reset
                    </button>

                </form>

            <?php endif; ?>

        </div>

    </main>

</div>

</body>
</html>
