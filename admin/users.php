<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

$user = require_role('admin');
$pdo = db();


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function account_role_label(string $role): string
{
    return match ($role) {
        'admin' => 'ADMIN',
        'guru' => 'GURU',
        'orang_tua' => 'ORANG TUA',
        default => strtoupper(
            str_replace('_', ' ', $role)
        ),
    };
}


function account_date(?string $date): string
{
    if (!$date) {
        return '-';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return '-';
    }

    return date('d/m/Y H:i', $timestamp);
}


/*
|--------------------------------------------------------------------------
| GURU MENUNGGU PERSETUJUAN
|--------------------------------------------------------------------------
*/

$pendingStmt = $pdo->query(
    "
        SELECT
            id,
            username,
            email,
            full_name,
            created_at

        FROM users

        WHERE role = 'guru'
          AND approval_status = 'pending'

        ORDER BY created_at ASC
    "
);

$pendingTeachers = $pendingStmt
    ->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| SEMUA PENGGUNA
|--------------------------------------------------------------------------
|
| parent_student_links hanya aktif untuk role Orang Tua.
|--------------------------------------------------------------------------
*/

$userStmt = $pdo->query(
    "
        SELECT
            u.id,
            u.username,
            u.email,
            u.full_name,
            u.role,
            u.approval_status,
            u.is_active,
            u.created_at,

            s.id AS child_id,
            s.nama_lengkap AS child_name,
            s.nis AS child_nis,
            s.kelas AS child_class

        FROM users u

        LEFT JOIN parent_student_links psl
            ON psl.parent_id = u.id
           AND psl.status = 'active'

        LEFT JOIN students s
            ON s.id = psl.student_id

        WHERE u.approval_status <> 'rejected'

        ORDER BY
            CASE u.role
                WHEN 'guru' THEN 1
                WHEN 'orang_tua' THEN 2
                WHEN 'admin' THEN 3
                ELSE 4
            END,
            u.full_name ASC
    "
);

$accounts = $userStmt
    ->fetchAll(PDO::FETCH_ASSOC);


$pageTitle = 'Persetujuan & Manajemen Akun';

require ROOT_PATH . '/includes/header.php';

?>


<style>

/* =========================================================
   ACCOUNT MANAGEMENT
   admin/users.php
   ========================================================= */

.account-management-page {
    --am-green: #174f3d;
    --am-green-dark: #103e2f;
    --am-green-bright: #20c55a;

    --am-text: #101828;
    --am-muted: #667085;

    --am-line: #e8ecea;

    width: 100%;
}


/* =========================================================
   PAGE HEADER
   ========================================================= */

.am-page-head {
    min-height: 80px;

    margin-bottom: 25px;

    display: flex;
    align-items: flex-end;
    justify-content: space-between;

    gap: 24px;
}

.am-page-title {
    margin: 0;

    color: #08101d;

    font-size: clamp(
        29px,
        2.4vw,
        39px
    );

    font-weight: 900;

    line-height: 1.07;

    letter-spacing: -.045em;
}

.am-page-description {
    margin: 9px 0 0;

    color: #607087;

    font-size: 12px;

    line-height: 1.55;
}


/* add button */

.am-add-account {
    min-height: 44px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 8px;

    padding: 9px 17px;

    border: 0;
    border-radius: 11px;

    background:
        linear-gradient(
            135deg,
            #1fc452,
            #20bd55
        );

    color: #ffffff;

    cursor: pointer;

    font-family: inherit;

    font-size: 11px;
    font-weight: 900;

    box-shadow:
        0 6px 15px
        rgba(32, 196, 82, .19);
}

.am-add-account:hover {
    background:
        linear-gradient(
            135deg,
            #19b94a,
            #16a947
        );

    transform: translateY(-1px);
}


/* =========================================================
   PENDING APPROVAL
   ========================================================= */

.am-approval-panel {
    margin-bottom: 28px;

    overflow: hidden;

    border: 1px solid #f0cf68;
    border-radius: 20px;

    background: #ffffff;

    box-shadow:
        0 2px 5px
        rgba(16, 24, 40, .035);
}

.am-approval-head {
    min-height: 84px;

    padding: 18px 22px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 20px;

    border-bottom:
        1px solid #f4e4ae;

    background:
        linear-gradient(
            90deg,
            #fffbed 0%,
            #fffdf6 100%
        );
}

.am-approval-title {
    margin: 0;

    color: #16120c;

    font-size: 17px;
    font-weight: 900;

    letter-spacing: -.02em;
}

.am-approval-description {
    margin: 7px 0 0;

    color: #665c45;

    font-size: 10.5px;

    line-height: 1.5;
}

.am-pending-count {
    flex: 0 0 auto;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 26px;

    padding: 5px 11px;

    border-radius: 999px;

    background: #ffe58a;

    color: #8f4700;

    font-size: 10px;
    font-weight: 900;

    white-space: nowrap;
}


/* empty pending */

.am-pending-empty {
    min-height: 63px;

    display: flex;
    align-items: center;

    padding: 17px 22px;

    color: #5f6979;

    font-size: 10.5px;
}


/* pending teacher row */

.am-pending-list {
    display: grid;
}

.am-pending-row {
    min-height: 73px;

    padding: 14px 22px;

    display: grid;

    grid-template-columns:
        minmax(180px, 1.3fr)
        minmax(180px, 1fr)
        130px
        auto;

    align-items: center;

    gap: 18px;

    border-bottom:
        1px solid var(--am-line);
}

.am-pending-row:last-child {
    border-bottom: 0;
}

.am-pending-row:hover {
    background: #fffdf8;
}

.am-pending-name {
    display: block;

    color: #121926;

    font-size: 11.5px;
    font-weight: 900;
}

.am-pending-meta {
    display: block;

    margin-top: 5px;

    color: #8390a0;

    font-size: 9px;
}

.am-pending-actions {
    display: flex;
    align-items: center;

    gap: 7px;
}

.am-approve-button,
.am-reject-button {
    min-height: 34px;

    padding: 7px 12px;

    border: 0;
    border-radius: 9px;

    cursor: pointer;

    font-family: inherit;

    font-size: 9px;
    font-weight: 900;
}

.am-approve-button {
    background: #e8faf1;

    color: #007d4f;
}

.am-approve-button:hover {
    background: #daf4e6;
}

.am-reject-button {
    background: #fff0f0;

    color: #d82b2b;
}

.am-reject-button:hover {
    background: #ffe4e4;
}


/* =========================================================
   USER LIST
   ========================================================= */

.am-user-panel {
    overflow: hidden;

    border: 1px solid #e2e7e4;
    border-radius: 21px;

    background: #fff;

    box-shadow:
        0 2px 5px
        rgba(16, 24, 40, .035);
}

.am-user-title-wrap {
    min-height: 66px;

    padding: 17px 22px;

    display: flex;
    align-items: center;

    gap: 10px;

    border-bottom: 1px solid var(--am-line);
}

.am-user-title-icon {
    color: var(--am-green);
}

.am-user-title {
    margin: 0;

    color: #111827;

    font-size: 16px;
    font-weight: 900;

    letter-spacing: -.015em;
}


/* =========================================================
   USER TABLE
   ========================================================= */

.am-table-scroll {
    width: 100%;

    overflow-x: auto;
}

.am-table {
    width: 100%;

    min-width: 1050px;

    border-collapse: collapse;

    table-layout: fixed;
}

.am-table thead th {
    height: 46px;

    padding: 12px 20px;

    border-bottom:
        1px solid var(--am-line);

    background: #f8f9fa;

    color: #4e5b70;

    font-size: 9px;
    font-weight: 900;

    text-align: left;

    letter-spacing: .02em;

    text-transform: uppercase;

    white-space: nowrap;
}


/* widths */

.am-table th:nth-child(1),
.am-table td:nth-child(1) {
    width: 17%;
}

.am-table th:nth-child(2),
.am-table td:nth-child(2) {
    width: 9%;
}

.am-table th:nth-child(3),
.am-table td:nth-child(3) {
    width: 19%;
}

.am-table th:nth-child(4),
.am-table td:nth-child(4) {
    width: 12%;
}

.am-table th:nth-child(5),
.am-table td:nth-child(5) {
    width: 25%;
}

.am-table th:nth-child(6),
.am-table td:nth-child(6) {
    width: 18%;
}


.am-table tbody td {
    height: 70px;

    padding: 13px 20px;

    border-bottom:
        1px solid var(--am-line);

    vertical-align: middle;

    color: #344054;

    font-size: 10.5px;
}

.am-table tbody tr:last-child td {
    border-bottom: 0;
}

.am-table tbody tr:hover td {
    background: #fbfcfb;
}


/* =========================================================
   USER CELL
   ========================================================= */

.am-user-name {
    display: block;

    overflow: hidden;

    color: #101828;

    font-size: 12px;
    font-weight: 900;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.am-user-small {
    display: block;

    margin-top: 5px;

    overflow: hidden;

    color: #718096;

    font-size: 9px;

    text-overflow: ellipsis;
    white-space: nowrap;
}


/* =========================================================
   ROLE SELECT
   ========================================================= */

.am-role-form {
    display: inline-block;
}

.am-role-select {
    min-width: 108px;
    height: 34px;

    padding: 6px 29px 6px 11px;

    border: 1px solid #dde3e0;
    border-radius: 9px;

    outline: 0;

    background: #fff;

    color: #263547;

    cursor: pointer;

    font-family: inherit;

    font-size: 9px;
    font-weight: 900;
}

.am-role-select:hover {
    border-color: #bfcac4;
}

.am-role-select:focus {
    border-color: #54a486;

    box-shadow:
        0 0 0 3px
        rgba(0, 143, 98, .08);
}

.am-role-select:disabled {
    cursor: not-allowed;

    opacity: .6;
}


/* =========================================================
   CHILD
   ========================================================= */

.am-child {
    display: flex;
    align-items: center;

    gap: 8px;
}

.am-child-name {
    display: block;

    color: #24334a;

    font-size: 10px;
    font-weight: 800;
}

.am-child-meta {
    display: block;

    margin-top: 3px;

    color: #8c97a6;

    font-size: 8.5px;
}

.am-no-child {
    color: #8995a4;

    font-size: 10px;
}


/* =========================================================
   ACTIONS
   ========================================================= */

.am-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;

    gap: 7px;
}

.am-password-button,
.am-delete-button {
    min-height: 35px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 7px;

    padding: 7px 12px;

    border: 0;
    border-radius: 9px;

    cursor: pointer;

    font-family: inherit;

    font-size: 9px;
    font-weight: 900;

    white-space: nowrap;
}

.am-password-button {
    background: #f3f5f6;

    color: #3b4859;
}

.am-password-button:hover {
    background: #e9edeb;
}

.am-delete-button {
    background: #fff0f0;

    color: #e0202d;
}

.am-delete-button:hover {
    background: #ffe3e3;
}


/* current account */

.am-current-user {
    display: inline-flex;

    margin-top: 5px;

    padding: 3px 6px;

    border-radius: 999px;

    background: #eef8f3;

    color: #14734f;

    font-size: 7px;
    font-weight: 900;

    text-transform: uppercase;
}


/* =========================================================
   MODALS
   ========================================================= */

.am-modal .modal-card {
    width: min(
        550px,
        calc(100vw - 30px)
    );

    padding: 0;

    overflow: hidden;

    border-radius: 19px;
}

.am-modal-head {
    padding: 19px 21px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 20px;

    border-bottom:
        1px solid var(--am-line);
}

.am-modal-eyebrow {
    margin: 0 0 5px;

    color: #008a60;

    font-size: 8px;
    font-weight: 900;

    letter-spacing: .16em;

    text-transform: uppercase;
}

.am-modal-title {
    margin: 0;

    color: #111827;

    font-size: 18px;
    font-weight: 900;

    letter-spacing: -.025em;
}

.am-modal-description {
    margin: 6px 0 0;

    color: #7a8795;

    font-size: 9.5px;

    line-height: 1.5;
}

.am-modal-close {
    width: 34px;
    height: 34px;

    display: grid;
    place-items: center;

    flex: 0 0 34px;

    border: 0;
    border-radius: 9px;

    background: #f3f5f4;

    color: #627066;

    cursor: pointer;

    font-size: 20px;
}

.am-modal-close:hover {
    background: #e9eeeb;

    color: #194f3d;
}


/* modal form */

.am-modal-form {
    padding: 21px;

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 14px;
}

.am-modal-field {
    min-width: 0;
}

.am-modal-field.full {
    grid-column: 1 / -1;
}

.am-modal-field label {
    display: block;

    margin-bottom: 7px;

    color: #526157;

    font-size: 8.5px;
    font-weight: 900;

    letter-spacing: .04em;

    text-transform: uppercase;
}

.am-modal-input,
.am-modal-select {
    width: 100%;
    height: 41px;

    padding: 8px 11px;

    border: 1px solid #d9e0dc;
    border-radius: 10px;

    outline: 0;

    background: #fff;

    color: #22312a;

    font-family: inherit;

    font-size: 10.5px;
}

.am-modal-input:focus,
.am-modal-select:focus {
    border-color: #54a486;

    box-shadow:
        0 0 0 3px
        rgba(0, 143, 98, .08);
}

.am-submit {
    grid-column: 1 / -1;

    min-height: 42px;

    margin-top: 3px;

    border: 0;
    border-radius: 10px;

    background:
        linear-gradient(
            135deg,
            #174f3d,
            #176247
        );

    color: #fff;

    cursor: pointer;

    font-family: inherit;

    font-size: 10px;
    font-weight: 900;
}

.am-submit:hover {
    background:
        linear-gradient(
            135deg,
            #103f30,
            #124f39
        );
}


/* password target */

.am-password-target {
    grid-column: 1 / -1;

    padding: 10px 12px;

    border-radius: 10px;

    background: #f5f8f6;

    color: #546359;

    font-size: 9.5px;
}

.am-password-target strong {
    color: #173d2f;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 900px) {

    .am-pending-row {
        grid-template-columns:
            1fr 1fr;
    }

    .am-pending-actions {
        grid-column: 1 / -1;
    }

}


@media (max-width: 760px) {

    .am-page-head {
        align-items: flex-start;

        flex-direction: column;
    }

    .am-add-account {
        width: 100%;
    }

    .am-approval-head {
        align-items: flex-start;

        flex-direction: column;
    }

    .am-pending-row {
        grid-template-columns: 1fr;
    }

    .am-pending-actions {
        grid-column: auto;
    }

    .am-modal-form {
        grid-template-columns: 1fr;
    }

    .am-modal-field.full,
    .am-submit,
    .am-password-target {
        grid-column: auto;
    }

}

</style>


<div class="account-management-page">


    <!-- =====================================================
         PAGE HEADER
         ===================================================== -->

    <header class="am-page-head">

        <div>

            <h1 class="am-page-title">
                Persetujuan &amp; Manajemen Akun
            </h1>

            <p class="am-page-description">
                Setujui pendaftaran Guru serta kelola akun,
                password, role, dan hubungan anak.
            </p>

        </div>


        <button
            type="button"
            class="am-add-account"
            data-modal-open="create-account"
        >

            <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
            >
                <circle
                    cx="9"
                    cy="8"
                    r="3"
                ></circle>

                <path
                    d="M3 20v-2c0-3 2-5 6-5"
                ></path>

                <path
                    d="M18 8v6"
                ></path>

                <path
                    d="M15 11h6"
                ></path>
            </svg>

            Tambah Akun

        </button>

    </header>



    <!-- =====================================================
         PENDING TEACHER APPROVAL
         ===================================================== -->

    <section class="am-approval-panel">

        <header class="am-approval-head">

            <div>

                <h2 class="am-approval-title">
                    Persetujuan Akun Guru
                </h2>

                <p class="am-approval-description">
                    Guru yang mendaftar sendiri belum memperoleh
                    akses sebelum disetujui.
                </p>

            </div>


            <span class="am-pending-count">

                <?= count($pendingTeachers) ?>
                menunggu

            </span>

        </header>


        <?php if (!$pendingTeachers): ?>

            <div class="am-pending-empty">
                Tidak ada pendaftaran Guru yang menunggu.
            </div>


        <?php else: ?>

            <div class="am-pending-list">

                <?php foreach (
                    $pendingTeachers
                    as $pending
                ): ?>

                    <article class="am-pending-row">

                        <div>

                            <strong class="am-pending-name">
                                <?= e(
                                    $pending['full_name']
                                ) ?>
                            </strong>

                            <span class="am-pending-meta">

                                @<?= e(
                                    $pending['username']
                                ) ?>

                            </span>

                        </div>


                        <div>

                            <strong class="am-pending-name">
                                <?= e(
                                    $pending['email']
                                    ?: 'Email tidak tersedia'
                                ) ?>
                            </strong>

                            <span class="am-pending-meta">
                                Email pendaftaran
                            </span>

                        </div>


                        <div>

                            <strong class="am-pending-name">
                                <?= e(
                                    account_date(
                                        $pending['created_at']
                                    )
                                ) ?>
                            </strong>

                            <span class="am-pending-meta">
                                Waktu daftar
                            </span>

                        </div>


                        <form
                            action="<?= e(
                                url(
                                    'api/users/index.php'
                                )
                            ) ?>"
                            method="post"
                            data-ajax
                            class="am-pending-actions"
                        >

                            <?= csrf_field() ?>

                            <input
                                type="hidden"
                                name="user_id"
                                value="<?= e(
                                    $pending['id']
                                ) ?>"
                            >


                            <button
                                type="submit"
                                name="action"
                                value="approve"
                                class="am-approve-button"
                            >
                                Setujui
                            </button>


                            <button
                                type="submit"
                                name="action"
                                value="reject"
                                class="am-reject-button"
                                data-confirm="
                                    Tolak pendaftaran Guru ini?
                                "
                            >
                                Tolak
                            </button>

                        </form>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>



    <!-- =====================================================
         USERS TABLE
         ===================================================== -->

    <section class="am-user-panel">


        <header class="am-user-title-wrap">

            <svg
                class="am-user-title-icon"
                width="21"
                height="21"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
            >
                <circle
                    cx="9"
                    cy="8"
                    r="3"
                ></circle>

                <path
                    d="
                        M3 20v-2
                        c0-3 2-5 6-5
                        s6 2 6 5
                        v2
                    "
                ></path>

                <path
                    d="
                        M17 11
                        c2 0 4 2 4 5
                        v3
                    "
                ></path>
            </svg>


            <h2 class="am-user-title">

                Daftar Pengguna
                (<?= count($accounts) ?>)

            </h2>

        </header>


        <div class="am-table-scroll">

            <table class="am-table">

                <thead>

                    <tr>

                        <th>Nama</th>

                        <th>Username</th>

                        <th>Email</th>

                        <th>Role</th>

                        <th>
                            Anak Terhubung
                        </th>

                        <th style="text-align:right">
                            Aksi
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach (
                    $accounts
                    as $account
                ): ?>

                    <?php

                    $accountId =
                        (string) $account['id'];

                    $isCurrentUser =
                        $accountId
                        === (string) $user['id'];

                    ?>


                    <tr>


                        <!-- NAMA -->

                        <td>

                            <strong class="am-user-name">

                                <?= e(
                                    $account[
                                        'full_name'
                                    ]
                                ) ?>

                            </strong>


                            <?php if (
                                $isCurrentUser
                            ): ?>

                                <span class="am-current-user">
                                    Akun Anda
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- USERNAME -->

                        <td>

                            <strong class="am-user-name">

                                @<?= e(
                                    $account[
                                        'username'
                                    ]
                                ) ?>

                            </strong>

                        </td>


                        <!-- EMAIL -->

                        <td>

                            <span
                                class="am-user-name"
                                style="
                                    font-size:10.5px;
                                    font-weight:650;
                                "
                            >

                                <?= e(
                                    $account['email']
                                    ?: '-'
                                ) ?>

                            </span>

                        </td>


                        <!-- ROLE -->

                        <td>

                            <form
                                action="<?= e(
                                    url(
                                        'api/users/index.php'
                                    )
                                ) ?>"
                                method="post"
                                data-ajax
                                class="am-role-form"
                                data-role-form
                            >

                                <?= csrf_field() ?>


                                <input
                                    type="hidden"
                                    name="action"
                                    value="change_role"
                                >


                                <input
                                    type="hidden"
                                    name="user_id"
                                    value="<?= e(
                                        $accountId
                                    ) ?>"
                                >


                                <select
                                    name="role"
                                    class="am-role-select"
                                    data-role-select
                                    <?= $isCurrentUser
                                        ? 'disabled'
                                        : ''
                                    ?>
                                >

                                    <?php foreach (
                                        [
                                            'admin',
                                            'guru',
                                            'orang_tua',
                                        ]
                                        as $role
                                    ): ?>

                                        <option
                                            value="<?= e(
                                                $role
                                            ) ?>"
                                            <?= $account[
                                                'role'
                                            ] === $role
                                                ? 'selected'
                                                : ''
                                            ?>
                                        >

                                            <?= e(
                                                account_role_label(
                                                    $role
                                                )
                                            ) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </form>

                        </td>


                        <!-- ANAK TERHUBUNG -->

                        <td>

                            <?php if (
                                $account['role']
                                === 'orang_tua'
                            ): ?>

                                <?php if (
                                    !empty(
                                        $account[
                                            'child_name'
                                        ]
                                    )
                                ): ?>

                                    <div class="am-child">

                                        <svg
                                            width="15"
                                            height="15"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            style="
                                                color:#0b9967;
                                            "
                                        >
                                            <path
                                                d="
                                                    M10 13
                                                    a5 5 0 0 0
                                                    7.07.07
                                                    l2-2
                                                "
                                            ></path>

                                            <path
                                                d="
                                                    M14 11
                                                    a5 5 0 0 0
                                                    -7.07-.07
                                                    l-2 2
                                                "
                                            ></path>
                                        </svg>


                                        <div>

                                            <strong
                                                class="
                                                    am-child-name
                                                "
                                            >
                                                <?= e(
                                                    $account[
                                                        'child_name'
                                                    ]
                                                ) ?>
                                            </strong>

                                            <span
                                                class="
                                                    am-child-meta
                                                "
                                            >
                                                <?= e(
                                                    $account[
                                                        'child_class'
                                                    ]
                                                ) ?>

                                                · NIS

                                                <?= e(
                                                    $account[
                                                        'child_nis'
                                                    ]
                                                ) ?>
                                            </span>

                                        </div>

                                    </div>


                                <?php else: ?>

                                    <span class="am-no-child">
                                        Belum terhubung
                                    </span>

                                <?php endif; ?>


                            <?php else: ?>

                                <span class="am-no-child">
                                    -
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- ACTION -->

                        <td>

                            <div class="am-actions">


                                <button
                                    type="button"
                                    class="
                                        am-password-button
                                    "
                                    data-modal-open="password-modal"
                                    data-password-user="<?= e(
                                        $accountId
                                    ) ?>"
                                    data-password-name="<?= e(
                                        $account[
                                            'full_name'
                                        ]
                                    ) ?>"
                                >

                                    <svg
                                        width="15"
                                        height="15"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <circle
                                            cx="8"
                                            cy="15"
                                            r="4"
                                        ></circle>

                                        <path
                                            d="
                                                m11 12
                                                8-8
                                            "
                                        ></path>

                                        <path
                                            d="
                                                m15 8
                                                2 2
                                            "
                                        ></path>
                                    </svg>

                                    Ubah Sandi

                                </button>


                                <?php if (
                                    !$isCurrentUser
                                ): ?>

                                    <form
                                        action="<?= e(
                                            url(
                                                'api/users/index.php'
                                            )
                                        ) ?>"
                                        method="post"
                                        data-ajax
                                    >

                                        <?= csrf_field() ?>


                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >


                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= e(
                                                $accountId
                                            ) ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="
                                                am-delete-button
                                            "
                                            data-confirm="
                                                Hapus akun ini secara permanen?
                                            "
                                        >

                                            <svg
                                                width="15"
                                                height="15"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    d="
                                                        M4 7h16
                                                    "
                                                ></path>

                                                <path
                                                    d="
                                                        M10 11v6
                                                    "
                                                ></path>

                                                <path
                                                    d="
                                                        M14 11v6
                                                    "
                                                ></path>

                                                <path
                                                    d="
                                                        M6 7
                                                        l1 14
                                                        h10
                                                        l1-14
                                                    "
                                                ></path>

                                                <path
                                                    d="
                                                        M9 7V4
                                                        h6v3
                                                    "
                                                ></path>
                                            </svg>

                                            Hapus

                                        </button>

                                    </form>

                                <?php endif; ?>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </section>

</div>



<!-- =========================================================
     CREATE ACCOUNT MODAL
     ========================================================= -->

<div
    class="modal am-modal"
    id="create-account"
>

    <div class="modal-card">


        <header class="am-modal-head">

            <div>

                <p class="am-modal-eyebrow">
                    Akun Internal
                </p>

                <h2 class="am-modal-title">
                    Tambah Akun
                </h2>

                <p class="am-modal-description">
                    Buat akun Guru, Orang Tua,
                    atau Administrator secara langsung.
                </p>

            </div>


            <button
                type="button"
                class="am-modal-close"
                data-modal-close
                aria-label="Tutup"
            >
                ×
            </button>

        </header>


        <form
            action="<?= e(
                url(
                    'api/users/index.php'
                )
            ) ?>"
            method="post"
            data-ajax
            class="am-modal-form"
        >

            <?= csrf_field() ?>


            <input
                type="hidden"
                name="action"
                value="create"
            >


            <!-- Nama -->

            <div class="am-modal-field full">

                <label>
                    Nama Lengkap
                </label>

                <input
                    type="text"
                    name="full_name"
                    class="am-modal-input"
                    placeholder="Masukkan nama lengkap"
                    maxlength="190"
                    required
                >

            </div>


            <!-- Username -->

            <div class="am-modal-field">

                <label>
                    Username
                </label>

                <input
                    type="text"
                    name="username"
                    class="am-modal-input"
                    placeholder="contoh: mami"
                    pattern="[A-Za-z0-9._-]{3,80}"
                    maxlength="80"
                    autocomplete="off"
                    required
                >

            </div>


            <!-- Email -->

            <div class="am-modal-field">

                <label>
                    Email
                </label>

                <input
                    type="email"
                    name="email"
                    class="am-modal-input"
                    placeholder="email@example.com"
                    maxlength="190"
                >

            </div>


            <!-- Role -->

            <div class="am-modal-field full">

                <label>
                    Role Akun
                </label>

                <select
                    name="role"
                    class="am-modal-select"
                    required
                >

                    <option value="guru">
                        Guru
                    </option>

                    <option value="orang_tua">
                        Orang Tua
                    </option>

                    <option value="admin">
                        Administrator
                    </option>

                </select>

            </div>


            <!-- Password -->

            <div class="am-modal-field">

                <label>
                    Password
                </label>

                <input
                    type="password"
                    name="password"
                    class="am-modal-input"
                    placeholder="Minimal 8 karakter"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

            </div>


            <!-- Confirm -->

            <div class="am-modal-field">

                <label>
                    Konfirmasi Password
                </label>

                <input
                    type="password"
                    name="password_confirmation"
                    class="am-modal-input"
                    placeholder="Ulangi password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

            </div>


            <button
                type="submit"
                class="am-submit"
            >
                Tambah Akun
            </button>

        </form>

    </div>

</div>



<!-- =========================================================
     PASSWORD MODAL
     ========================================================= -->

<div
    class="modal am-modal"
    id="password-modal"
>

    <div class="modal-card">


        <header class="am-modal-head">

            <div>

                <p class="am-modal-eyebrow">
                    Keamanan Akun
                </p>

                <h2 class="am-modal-title">
                    Ubah Sandi
                </h2>

                <p class="am-modal-description">
                    Password baru akan langsung berlaku
                    setelah disimpan.
                </p>

            </div>


            <button
                type="button"
                class="am-modal-close"
                data-modal-close
                aria-label="Tutup"
            >
                ×
            </button>

        </header>


        <form
            action="<?= e(
                url(
                    'api/users/index.php'
                )
            ) ?>"
            method="post"
            data-ajax
            class="am-modal-form"
        >

            <?= csrf_field() ?>


            <input
                type="hidden"
                name="action"
                value="change_password"
            >


            <input
                type="hidden"
                name="user_id"
                id="password-user-id"
                value=""
            >


            <div class="am-password-target">

                Mengubah password untuk:

                <strong id="password-user-name">
                    -
                </strong>

            </div>


            <div class="am-modal-field">

                <label>
                    Password Baru
                </label>

                <input
                    type="password"
                    name="password"
                    class="am-modal-input"
                    minlength="8"
                    placeholder="Minimal 8 karakter"
                    autocomplete="new-password"
                    required
                >

            </div>


            <div class="am-modal-field">

                <label>
                    Konfirmasi Password
                </label>

                <input
                    type="password"
                    name="password_confirmation"
                    class="am-modal-input"
                    minlength="8"
                    placeholder="Ulangi password"
                    autocomplete="new-password"
                    required
                >

            </div>


            <button
                type="submit"
                class="am-submit"
            >
                Simpan Password Baru
            </button>

        </form>

    </div>

</div>



<script>
(() => {

    /*
    |--------------------------------------------------------------------------
    | UBAH ROLE LANGSUNG DARI SELECT
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll(
            '[data-role-select]'
        )
        .forEach(select => {

            let previousValue =
                select.value;


            select.addEventListener(
                'focus',
                () => {
                    previousValue =
                        select.value;
                }
            );


            select.addEventListener(
                'change',
                () => {

                    const confirmed =
                        window.confirm(
                            'Ubah role pengguna dari '
                            + previousValue
                                .replace('_', ' ')
                            + ' menjadi '
                            + select.value
                                .replace('_', ' ')
                            + '?'
                        );


                    if (!confirmed) {

                        select.value =
                            previousValue;

                        return;
                    }


                    const form =
                        select.closest(
                            '[data-role-form]'
                        );


                    if (form) {
                        form.requestSubmit();
                    }

                }
            );

        });


    /*
    |--------------------------------------------------------------------------
    | ISI MODAL PASSWORD
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'click',
        event => {

            const button =
                event.target.closest(
                    '[data-password-user]'
                );


            if (!button) {
                return;
            }


            const userId =
                button.dataset
                    .passwordUser
                || '';


            const userName =
                button.dataset
                    .passwordName
                || '-';


            const idInput =
                document.querySelector(
                    '#password-user-id'
                );


            const nameOutput =
                document.querySelector(
                    '#password-user-name'
                );


            if (idInput) {
                idInput.value =
                    userId;
            }


            if (nameOutput) {
                nameOutput.textContent =
                    userName;
            }


            /*
             * Kosongkan password lama dari modal.
             */
            const modal =
                document.querySelector(
                    '#password-modal'
                );


            if (modal) {

                modal
                    .querySelectorAll(
                        'input[type="password"]'
                    )
                    .forEach(input => {
                        input.value = '';
                    });

            }

        }
    );

})();
</script>


<?php

require ROOT_PATH
    . '/includes/footer.php';

?>
