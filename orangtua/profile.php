<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

$user = require_role('orang_tua');
$pdo = db();


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function parent_profile_initial(string $name): string
{
    $name = trim($name);

    if ($name === '') {
        return 'O';
    }

    return strtoupper(
        mb_substr(
            $name,
            0,
            1
        )
    );
}


function parent_profile_completion(array $profile): int
{
    /*
     * 4 komponen utama:
     *
     * Nama   = 25%
     * Telepon = 25%
     * Alamat = 25%
     * Foto   = 25%
     */

    $fields = [
        trim(
            (string) (
                $profile['full_name']
                ?? ''
            )
        ),

        trim(
            (string) (
                $profile['phone']
                ?? ''
            )
        ),

        trim(
            (string) (
                $profile['address']
                ?? ''
            )
        ),

        trim(
            (string) (
                $profile['photo_url']
                ?? ''
            )
        ),
    ];

    $filled = 0;

    foreach ($fields as $field) {
        if ($field !== '') {
            $filled++;
        }
    }

    return $filled * 25;
}


/*
|--------------------------------------------------------------------------
| AMBIL DATA AKUN TERBARU
|--------------------------------------------------------------------------
*/

$profileStmt = $pdo->prepare(
    "
        SELECT
            id,
            username,
            email,
            full_name,
            phone,
            address,
            bio,
            photo_url,
            is_active,
            created_at,
            last_login_at

        FROM users

        WHERE id = ?
          AND role = 'orang_tua'

        LIMIT 1
    "
);

$profileStmt->execute([
    $user['id']
]);

$profile = $profileStmt->fetch(
    PDO::FETCH_ASSOC
);


if (!$profile) {
    http_response_code(404);

    exit(
        'Akun Orang Tua tidak ditemukan.'
    );
}


/*
|--------------------------------------------------------------------------
| PROSES UPDATE BIODATA
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = trim(
        (string) (
            $_POST['action']
            ?? ''
        )
    );


    if ($action === 'update_profile') {

        $fullName = trim(
            (string) (
                $_POST['full_name']
                ?? ''
            )
        );


        $phone = trim(
            (string) (
                $_POST['phone']
                ?? ''
            )
        );


        $address = trim(
            (string) (
                $_POST['address']
                ?? ''
            )
        );


        $bio = trim(
            (string) (
                $_POST['bio']
                ?? ''
            )
        );


        $photoUrl = trim(
            (string) (
                $_POST['photo_url']
                ?? ''
            )
        );


        /*
        |--------------------------------------------------------------------------
        | VALIDASI
        |--------------------------------------------------------------------------
        */

        if ($fullName === '') {

            flash(
                'error',
                'Nama lengkap wajib diisi.'
            );

            redirect(
                'orangtua/profile.php'
            );
        }


        if (
            mb_strlen(
                $fullName
            ) > 190
        ) {

            flash(
                'error',
                'Nama lengkap terlalu panjang.'
            );

            redirect(
                'orangtua/profile.php'
            );
        }


        if (
            $phone !== ''
            && !preg_match(
                '/^[0-9+\-\s()]{7,25}$/',
                $phone
            )
        ) {

            flash(
                'error',
                'Format nomor telepon tidak valid.'
            );

            redirect(
                'orangtua/profile.php'
            );
        }


        if (
            mb_strlen(
                $address
            ) > 500
        ) {

            flash(
                'error',
                'Alamat terlalu panjang.'
            );

            redirect(
                'orangtua/profile.php'
            );
        }


        if (
            mb_strlen(
                $bio
            ) > 1000
        ) {

            flash(
                'error',
                'Catatan biodata terlalu panjang.'
            );

            redirect(
                'orangtua/profile.php'
            );
        }


        if (
            $photoUrl !== ''
            && !filter_var(
                $photoUrl,
                FILTER_VALIDATE_URL
            )
        ) {

            flash(
                'error',
                'URL foto profil tidak valid.'
            );

            redirect(
                'orangtua/profile.php'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | SIMPAN
        |--------------------------------------------------------------------------
        */

        try {

            $updateStmt = $pdo->prepare(
                "
                    UPDATE users

                    SET
                        full_name = ?,
                        phone = ?,
                        address = ?,
                        bio = ?,
                        photo_url = ?,
                        updated_at = NOW()

                    WHERE id = ?
                      AND role = 'orang_tua'
                "
            );


            $updateStmt->execute([
                $fullName,
                $phone !== ''
                    ? $phone
                    : null,

                $address !== ''
                    ? $address
                    : null,

                $bio !== ''
                    ? $bio
                    : null,

                $photoUrl !== ''
                    ? $photoUrl
                    : null,

                $user['id'],
            ]);


            if (
                function_exists(
                    'audit_event'
                )
            ) {

                audit_event(
                    'parent_profile_updated',
                    'success',
                    (string) $user['id'],
                    [
                        'name' =>
                            $fullName,
                    ]
                );
            }


            flash(
                'success',
                'Biodata berhasil diperbarui.'
            );


        } catch (Throwable $error) {

            error_log(
                '[NGAJIYUK!] Update biodata Orang Tua gagal: '
                . $error->getMessage()
            );


            flash(
                'error',
                'Biodata gagal disimpan. Silakan coba kembali.'
            );
        }


        redirect(
            'orangtua/profile.php'
        );
    }
}


/*
|--------------------------------------------------------------------------
| DATA ANAK TERHUBUNG
|--------------------------------------------------------------------------
*/

$childStmt = $pdo->prepare(
    "
        SELECT
            s.id,
            s.nis,
            s.nama_lengkap,
            s.kelas,
            s.level,
            s.status

        FROM parent_student_links psl

        INNER JOIN students s
            ON s.id = psl.student_id

        WHERE psl.parent_id = ?
          AND psl.status = 'active'

        LIMIT 1
    "
);

$childStmt->execute([
    $user['id']
]);

$child = $childStmt->fetch(
    PDO::FETCH_ASSOC
);


/*
|--------------------------------------------------------------------------
| COMPLETENESS
|--------------------------------------------------------------------------
*/

$completion =
    parent_profile_completion(
        $profile
    );


$initial =
    parent_profile_initial(
        (string) $profile[
            'full_name'
        ]
    );


$pageTitle =
    'Biodata Orang Tua';


require ROOT_PATH
    . '/includes/header.php';

?>


<style>

/* =========================================================
   BIODATA ORANG TUA
   ========================================================= */

.parent-profile-page {
    --pp-green: #174f3d;
    --pp-green-dark: #103e30;
    --pp-emerald: #009b68;

    --pp-text: #101828;
    --pp-muted: #6c788b;

    --pp-line: #e3e8e5;
    --pp-soft: #f8faf9;

    width: 100%;
}


/* =========================================================
   HEADER
   ========================================================= */

.pp-page-head {
    margin-bottom: 26px;
}


.pp-eyebrow {
    margin: 0 0 8px;

    color: #009367;

    font-size: 10px;
    font-weight: 900;

    letter-spacing: .21em;

    text-transform: uppercase;
}


.pp-title {
    margin: 0;

    color: #091321;

    font-size: clamp(
        30px,
        2.5vw,
        39px
    );

    font-weight: 900;

    line-height: 1.05;

    letter-spacing: -.045em;
}


.pp-description {
    max-width: 720px;

    margin: 10px 0 0;

    color: #647287;

    font-size: 12px;

    line-height: 1.6;
}


/* =========================================================
   LAYOUT
   ========================================================= */

.pp-grid {
    display: grid;

    grid-template-columns:
        minmax(270px, .7fr)
        minmax(0, 1.3fr);

    gap: 20px;

    align-items: start;
}


/* =========================================================
   CARD
   ========================================================= */

.pp-card {
    overflow: hidden;

    border: 1px solid var(--pp-line);
    border-radius: 20px;

    background: #fff;

    box-shadow:
        0 2px 6px
        rgba(16, 24, 40, .04);
}


/* =========================================================
   PROFILE CARD
   ========================================================= */

.pp-profile-top {
    padding: 28px 22px;

    text-align: center;

    background:
        linear-gradient(
            145deg,
            #effff7,
            #ffffff 75%
        );

    border-bottom:
        1px solid #e5eee9;
}


.pp-avatar {
    width: 82px;
    height: 82px;

    margin:
        0 auto
        15px;

    overflow: hidden;

    display: grid;
    place-items: center;

    border: 4px solid #fff;
    border-radius: 50%;

    background:
        linear-gradient(
            135deg,
            #174f3d,
            #0b9968
        );

    color: #fff;

    font-size: 28px;
    font-weight: 900;

    box-shadow:
        0 8px 22px
        rgba(23, 79, 61, .17);
}


.pp-avatar img {
    width: 100%;
    height: 100%;

    object-fit: cover;
}


.pp-profile-name {
    margin: 0;

    color: #101828;

    font-size: 19px;
    font-weight: 900;

    letter-spacing: -.02em;
}


.pp-profile-role {
    display: inline-flex;

    margin-top: 8px;

    padding: 5px 9px;

    border-radius: 999px;

    background: #eafaf2;

    color: #007d52;

    font-size: 8px;
    font-weight: 900;

    letter-spacing: .05em;

    text-transform: uppercase;
}


.pp-profile-email {
    display: block;

    margin-top: 10px;

    overflow-wrap: anywhere;

    color: #778599;

    font-size: 9.5px;
}


/* =========================================================
   COMPLETENESS
   ========================================================= */

.pp-completeness {
    padding: 19px 20px;
}


.pp-completeness-top {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 12px;

    margin-bottom: 9px;
}


.pp-completeness-label {
    color: #46566b;

    font-size: 10px;
    font-weight: 900;
}


.pp-completeness-value {
    color: #00865a;

    font-size: 11px;
    font-weight: 900;
}


.pp-progress {
    height: 8px;

    overflow: hidden;

    border-radius: 999px;

    background: #edf1ef;
}


.pp-progress span {
    display: block;

    height: 100%;

    border-radius: inherit;

    background:
        linear-gradient(
            90deg,
            #009b68,
            #34c98a
        );
}


.pp-completeness-note {
    margin: 10px 0 0;

    color: #84909f;

    font-size: 8.5px;

    line-height: 1.5;
}


/* =========================================================
   LINKED CHILD
   ========================================================= */

.pp-child {
    margin:
        0 20px
        20px;

    padding: 15px;

    border: 1px solid #deebe4;
    border-radius: 13px;

    background: #f7fbf9;
}


.pp-child-eyebrow {
    margin: 0 0 8px;

    color: #00845a;

    font-size: 8px;
    font-weight: 900;

    letter-spacing: .11em;

    text-transform: uppercase;
}


.pp-child-name {
    display: block;

    color: #172033;

    font-size: 11px;
    font-weight: 900;
}


.pp-child-meta {
    display: block;

    margin-top: 5px;

    color: #7c8999;

    font-size: 8.5px;
}


/* =========================================================
   FORM CARD HEADER
   ========================================================= */

.pp-form-head {
    padding: 21px 22px;

    display: flex;
    align-items: center;

    gap: 12px;

    border-bottom:
        1px solid var(--pp-line);
}


.pp-form-icon {
    width: 40px;
    height: 40px;

    flex: 0 0 40px;

    display: grid;
    place-items: center;

    border-radius: 11px;

    background: #edf5f1;

    color: var(--pp-green);
}


.pp-form-title {
    margin: 0;

    color: #101828;

    font-size: 17px;
    font-weight: 900;

    letter-spacing: -.02em;
}


.pp-form-description {
    margin: 5px 0 0;

    color: #7b8797;

    font-size: 9px;
}


/* =========================================================
   FORM
   ========================================================= */

.pp-form {
    padding: 22px;

    display: grid;

    grid-template-columns:
        repeat(
            2,
            minmax(0, 1fr)
        );

    gap: 16px;
}


.pp-field {
    min-width: 0;
}


.pp-field.full {
    grid-column: 1 / -1;
}


.pp-field label {
    display: block;

    margin-bottom: 7px;

    color: #506057;

    font-size: 8.5px;
    font-weight: 900;

    letter-spacing: .04em;

    text-transform: uppercase;
}


.pp-required {
    color: #e24545;
}


.pp-input,
.pp-textarea {
    width: 100%;

    border: 1px solid #d9e0dc;
    border-radius: 10px;

    outline: 0;

    background: #fff;

    color: #253349;

    font-family: inherit;

    font-size: 10.5px;

    transition:
        border-color .15s ease,
        box-shadow .15s ease;
}


.pp-input {
    height: 43px;

    padding: 9px 12px;
}


.pp-textarea {
    min-height: 105px;

    padding: 12px;

    resize: vertical;

    line-height: 1.6;
}


.pp-input:hover,
.pp-textarea:hover {
    border-color: #c4cec8;
}


.pp-input:focus,
.pp-textarea:focus {
    border-color: #52a486;

    box-shadow:
        0 0 0 3px
        rgba(0, 143, 98, .08);
}


.pp-input[readonly] {
    background: #f5f7f6;

    color: #83908a;

    cursor: not-allowed;
}


.pp-field-help {
    display: block;

    margin-top: 6px;

    color: #919ba8;

    font-size: 8px;

    line-height: 1.45;
}


/* =========================================================
   ACCOUNT NOTICE
   ========================================================= */

.pp-account-note {
    grid-column: 1 / -1;

    padding: 13px 14px;

    display: grid;

    grid-template-columns:
        18px 1fr;

    gap: 9px;

    border: 1px solid #d3e2f9;
    border-radius: 11px;

    background: #f2f7ff;

    color: #48648d;

    font-size: 8.5px;

    line-height: 1.55;
}


.pp-account-note svg {
    color: #3678df;
}


/* =========================================================
   ACTION
   ========================================================= */

.pp-actions {
    grid-column: 1 / -1;

    padding-top: 5px;

    display: flex;
    align-items: center;
    justify-content: flex-end;

    gap: 9px;
}


.pp-reset {
    min-height: 42px;

    padding: 8px 15px;

    border: 1px solid #dce3df;
    border-radius: 10px;

    background: #fff;

    color: #617069;

    cursor: pointer;

    font-family: inherit;

    font-size: 9.5px;
    font-weight: 900;
}


.pp-reset:hover {
    background: #f6f8f7;
}


.pp-save {
    min-height: 42px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 8px;

    padding: 8px 17px;

    border: 0;
    border-radius: 10px;

    background:
        linear-gradient(
            135deg,
            #174f3d,
            #0d7551
        );

    color: #fff;

    cursor: pointer;

    font-family: inherit;

    font-size: 9.5px;
    font-weight: 900;

    box-shadow:
        0 6px 14px
        rgba(23, 79, 61, .14);
}


.pp-save:hover {
    background:
        linear-gradient(
            135deg,
            #103f30,
            #096745
        );
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 950px) {

    .pp-grid {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 650px) {

    .pp-title {
        font-size: 28px;
    }


    .pp-form {
        grid-template-columns: 1fr;

        padding: 18px;
    }


    .pp-field.full,
    .pp-account-note,
    .pp-actions {
        grid-column: auto;
    }


    .pp-actions {
        align-items: stretch;

        flex-direction: column-reverse;
    }


    .pp-reset,
    .pp-save {
        width: 100%;
    }

}

</style>



<div class="parent-profile-page">


    <!-- =====================================================
         PAGE HEADER
         ===================================================== -->

    <header class="pp-page-head">

        <p class="pp-eyebrow">
            Profil Akun Wali
        </p>


        <h1 class="pp-title">
            Biodata Orang Tua
        </h1>


        <p class="pp-description">
            Lengkapi informasi kontak agar sekolah dan
            Guru dapat menghubungi Anda dengan lebih mudah.
        </p>

    </header>



    <div class="pp-grid">


        <!-- =================================================
             LEFT PROFILE
             ================================================= -->

        <aside class="pp-card">


            <div class="pp-profile-top">


                <div class="pp-avatar">

                    <?php if (
                        !empty(
                            $profile[
                                'photo_url'
                            ]
                        )
                    ): ?>

                        <img
                            src="<?= e(
                                $profile[
                                    'photo_url'
                                ]
                            ) ?>"
                            alt="Foto profil"
                            onerror="
                                this.style.display='none';
                                this.nextElementSibling.style.display='grid';
                            "
                        >

                        <span
                            style="display:none"
                        >
                            <?= e($initial) ?>
                        </span>


                    <?php else: ?>

                        <?= e($initial) ?>

                    <?php endif; ?>

                </div>


                <h2 class="pp-profile-name">

                    <?= e(
                        $profile[
                            'full_name'
                        ]
                    ) ?>

                </h2>


                <span class="pp-profile-role">
                    Orang Tua
                </span>


                <span class="pp-profile-email">

                    <?= e(
                        $profile['email']
                        ?: '@'
                            . $profile[
                                'username'
                            ]
                    ) ?>

                </span>

            </div>



            <!-- COMPLETENESS -->

            <div class="pp-completeness">

                <div class="pp-completeness-top">

                    <span class="pp-completeness-label">
                        Kelengkapan Biodata
                    </span>

                    <strong class="pp-completeness-value">
                        <?= $completion ?>%
                    </strong>

                </div>


                <div class="pp-progress">

                    <span
                        style="
                            width:
                            <?= $completion ?>%;
                        "
                    ></span>

                </div>


                <p class="pp-completeness-note">
                    Lengkapi nama, nomor telepon,
                    alamat, dan foto profil untuk
                    mencapai 100%.
                </p>

            </div>



            <!-- CHILD -->

            <div class="pp-child">

                <p class="pp-child-eyebrow">
                    Anak Terhubung
                </p>


                <?php if ($child): ?>

                    <strong class="pp-child-name">

                        <?= e(
                            $child[
                                'nama_lengkap'
                            ]
                        ) ?>

                    </strong>


                    <span class="pp-child-meta">

                        NIS
                        <?= e(
                            $child['nis']
                        ) ?>

                        · Kelas

                        <?= e(
                            $child['kelas']
                            ?: '-'
                        ) ?>

                    </span>


                <?php else: ?>

                    <strong class="pp-child-name">
                        Belum terhubung
                    </strong>

                    <span class="pp-child-meta">
                        Hubungkan anak melalui menu
                        Daftar Siswa &amp; Progres.
                    </span>

                <?php endif; ?>

            </div>

        </aside>



        <!-- =================================================
             RIGHT FORM
             ================================================= -->

        <section class="pp-card">


            <header class="pp-form-head">

                <div class="pp-form-icon">

                    <svg
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <circle
                            cx="12"
                            cy="8"
                            r="4"
                        ></circle>

                        <path
                            d="
                                M4 21v-2
                                c0-4
                                3-7
                                8-7
                                s8 3
                                8 7
                                v2
                            "
                        ></path>
                    </svg>

                </div>


                <div>

                    <h2 class="pp-form-title">
                        Informasi Orang Tua
                    </h2>

                    <p class="pp-form-description">
                        Perbarui biodata dan informasi
                        kontak akun Anda.
                    </p>

                </div>

            </header>



            <form
                method="post"
                action="<?= e(
                    url(
                        'orangtua/profile.php'
                    )
                ) ?>"
                class="pp-form"
            >

                <?= csrf_field() ?>


                <input
                    type="hidden"
                    name="action"
                    value="update_profile"
                >



                <!-- NAMA -->

                <div class="pp-field full">

                    <label for="parent-full-name">

                        Nama Lengkap

                        <span class="pp-required">
                            *
                        </span>

                    </label>


                    <input
                        type="text"
                        id="parent-full-name"
                        name="full_name"
                        class="pp-input"
                        value="<?= e(
                            (string) (
                                $profile[
                                    'full_name'
                                ]
                                ?? ''
                            )
                        ) ?>"
                        maxlength="190"
                        autocomplete="name"
                        required
                    >

                </div>



                <!-- USERNAME -->

                <div class="pp-field">

                    <label>
                        Username
                    </label>


                    <input
                        type="text"
                        class="pp-input"
                        value="@<?= e(
                            $profile[
                                'username'
                            ]
                        ) ?>"
                        readonly
                    >


                    <span class="pp-field-help">
                        Username tidak dapat diubah
                        dari halaman ini.
                    </span>

                </div>



                <!-- EMAIL -->

                <div class="pp-field">

                    <label>
                        Email
                    </label>


                    <input
                        type="email"
                        class="pp-input"
                        value="<?= e(
                            (string) (
                                $profile[
                                    'email'
                                ]
                                ?? ''
                            )
                        ) ?>"
                        readonly
                    >


                    <span class="pp-field-help">
                        Email digunakan sebagai
                        identitas akun.
                    </span>

                </div>



                <!-- PHONE -->

                <div class="pp-field full">

                    <label for="parent-phone">
                        Nomor Telepon / WhatsApp
                    </label>


                    <input
                        type="tel"
                        id="parent-phone"
                        name="phone"
                        class="pp-input"
                        value="<?= e(
                            (string) (
                                $profile[
                                    'phone'
                                ]
                                ?? ''
                            )
                        ) ?>"
                        placeholder="Contoh: 081234567890"
                        maxlength="25"
                        autocomplete="tel"
                    >

                </div>



                <!-- ADDRESS -->

                <div class="pp-field full">

                    <label for="parent-address">
                        Alamat
                    </label>


                    <textarea
                        id="parent-address"
                        name="address"
                        class="pp-textarea"
                        maxlength="500"
                        placeholder="Masukkan alamat tempat tinggal..."
                        autocomplete="street-address"
                    ><?= e(
                        (string) (
                            $profile[
                                'address'
                            ]
                            ?? ''
                        )
                    ) ?></textarea>

                </div>



                <!-- PHOTO -->

                <div class="pp-field full">

                    <label for="parent-photo">
                        URL Foto Profil
                    </label>


                    <input
                        type="url"
                        id="parent-photo"
                        name="photo_url"
                        class="pp-input"
                        value="<?= e(
                            (string) (
                                $profile[
                                    'photo_url'
                                ]
                                ?? ''
                            )
                        ) ?>"
                        placeholder="https://..."
                    >


                    <span class="pp-field-help">
                        Gunakan URL gambar publik.
                        Kosongkan jika belum ingin
                        menggunakan foto profil.
                    </span>

                </div>



                <!-- BIO -->

                <div class="pp-field full">

                    <label for="parent-bio">
                        Catatan Tambahan
                    </label>


                    <textarea
                        id="parent-bio"
                        name="bio"
                        class="pp-textarea"
                        maxlength="1000"
                        placeholder="Tambahkan informasi yang diperlukan sekolah..."
                    ><?= e(
                        (string) (
                            $profile[
                                'bio'
                            ]
                            ?? ''
                        )
                    ) ?></textarea>

                </div>



                <!-- INFO -->

                <div class="pp-account-note">

                    <svg
                        width="17"
                        height="17"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <circle
                            cx="12"
                            cy="12"
                            r="9"
                        ></circle>

                        <path d="M12 11v5"></path>

                        <path d="M12 8h.01"></path>
                    </svg>


                    <span>
                        Biodata ini digunakan untuk
                        kebutuhan komunikasi sekolah.
                        Data akademik anak tetap hanya
                        dapat diubah oleh Guru atau
                        Administrator.
                    </span>

                </div>



                <!-- ACTION -->

                <div class="pp-actions">

                    <button
                        type="reset"
                        class="pp-reset"
                    >
                        Batal
                    </button>


                    <button
                        type="submit"
                        class="pp-save"
                    >

                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                d="
                                    M5 3
                                    h12
                                    l2 2
                                    v16
                                    H5
                                    z
                                "
                            ></path>

                            <path
                                d="
                                    M8 3v6h8V3
                                "
                            ></path>

                            <path
                                d="
                                    M8 21v-7h8v7
                                "
                            ></path>
                        </svg>

                        Simpan Perubahan

                    </button>

                </div>

            </form>

        </section>

    </div>

</div>


<?php

require ROOT_PATH
    . '/includes/footer.php';

?>