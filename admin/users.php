<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

$user = require_role('admin');

$users = db()
    ->query(
        'SELECT
            id,
            username,
            email,
            full_name,
            role,
            approval_status,
            is_active,
            created_at
         FROM users
         ORDER BY created_at DESC'
    )
    ->fetchAll();

$pageTitle = 'Persetujuan & Manajemen Akun';

require ROOT_PATH . '/includes/header.php';

?>

<div class="page-head">
    <div>
        <p class="eyebrow">
            Keamanan Akses
        </p>

        <h1 class="page-title">
            Persetujuan & Manajemen Akun
        </h1>

        <p class="page-description">
            Buat akun internal, setujui Guru,
            ubah role dan password, serta hapus
            akun dengan aman.
        </p>
    </div>

    <button
        class="btn btn-primary"
        type="button"
        data-modal-open="create-account"
    >
        + Buat Akun
    </button>
</div>

<section class="card">

    <div class="filter-row">

        <input
            class="input"
            type="search"
            data-search-table="#users-table"
            placeholder="Cari nama, username, email, atau role..."
        >

        <span class="badge badge-blue">
            <?= count($users) ?> Pengguna
        </span>

    </div>

    <div class="table-wrap">

        <table
            class="table"
            id="users-table"
        >

            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Username/Email</th>
                    <th>Role</th>
                    <th>Persetujuan</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($users as $account): ?>

                <tr>

                    <td class="name">
                        <?= e($account['full_name']) ?>
                    </td>

                    <td>
                        <?= e($account['username']) ?>

                        <br>

                        <small class="muted">
                            <?= e(
                                $account['email']
                                ?: 'Email opsional'
                            ) ?>
                        </small>
                    </td>

                    <td>
                        <span class="badge badge-blue">
                            <?= e(
                                ucwords(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $account['role']
                                    )
                                )
                            ) ?>
                        </span>
                    </td>

                    <td>

                        <?php
                        $approvalClass = match (
                            $account['approval_status']
                        ) {
                            'approved' => 'badge-green',
                            'pending' => 'badge-yellow',
                            'rejected' => 'badge-red',
                            default => 'badge-gray',
                        };
                        ?>

                        <span
                            class="badge <?= $approvalClass ?>"
                        >
                            <?= e(
                                ucfirst(
                                    $account[
                                        'approval_status'
                                    ]
                                )
                            ) ?>
                        </span>

                    </td>

                    <td>

                        <span
                            class="badge <?= $account['is_active']
                                ? 'badge-green'
                                : 'badge-gray' ?>"
                        >
                            <?= $account['is_active']
                                ? 'Aktif'
                                : 'Nonaktif' ?>
                        </span>

                    </td>

                    <td>

                        <details>

                            <summary
                                class="btn btn-soft btn-sm"
                            >
                                Kelola
                            </summary>

                            <div
                                class="grid"
                                style="
                                    min-width:280px;
                                    padding:12px 0;
                                "
                            >

                                <!-- APPROVE / REJECT / ACTIVE -->

                                <form
                                    action="<?= e(
                                        url(
                                            'api/users/index.php'
                                        )
                                    ) ?>"
                                    method="post"
                                    data-ajax
                                    class="actions"
                                >

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?= e(
                                            $account['id']
                                        ) ?>"
                                    >

                                    <?php if (
                                        $account[
                                            'approval_status'
                                        ] === 'pending'
                                    ): ?>

                                        <button
                                            class="
                                                btn
                                                btn-primary
                                                btn-sm
                                            "
                                            type="submit"
                                            name="action"
                                            value="approve"
                                        >
                                            Setujui
                                        </button>

                                        <button
                                            class="
                                                btn
                                                btn-danger
                                                btn-sm
                                            "
                                            type="submit"
                                            name="action"
                                            value="reject"
                                        >
                                            Tolak
                                        </button>

                                    <?php endif; ?>

                                    <input
                                        type="hidden"
                                        name="active"
                                        value="<?= $account[
                                            'is_active'
                                        ]
                                            ? '0'
                                            : '1' ?>"
                                    >

                                    <button
                                        class="
                                            btn
                                            btn-warning
                                            btn-sm
                                        "
                                        type="submit"
                                        name="action"
                                        value="toggle_active"
                                    >
                                        <?= $account[
                                            'is_active'
                                        ]
                                            ? 'Nonaktifkan'
                                            : 'Aktifkan' ?>
                                    </button>

                                </form>


                                <!-- CHANGE ROLE -->

                                <form
                                    action="<?= e(
                                        url(
                                            'api/users/index.php'
                                        )
                                    ) ?>"
                                    method="post"
                                    data-ajax
                                    class="actions"
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
                                            $account['id']
                                        ) ?>"
                                    >

                                    <select
                                        class="select"
                                        name="role"
                                        required
                                    >

                                        <?php foreach (
                                            [
                                                'admin',
                                                'guru',
                                                'orang_tua'
                                            ] as $role
                                        ): ?>

                                            <option
                                                value="<?= e(
                                                    $role
                                                ) ?>"
                                                <?= $account[
                                                    'role'
                                                ] === $role
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= e(
                                                    ucwords(
                                                        str_replace(
                                                            '_',
                                                            ' ',
                                                            $role
                                                        )
                                                    )
                                                ) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                    <button
                                        class="
                                            btn
                                            btn-blue
                                            btn-sm
                                        "
                                        type="submit"
                                    >
                                        Ubah Role
                                    </button>

                                </form>


                                <!-- CHANGE PASSWORD -->

                                <form
                                    action="<?= e(
                                        url(
                                            'api/users/index.php'
                                        )
                                    ) ?>"
                                    method="post"
                                    data-ajax
                                    class="grid"
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
                                        value="<?= e(
                                            $account['id']
                                        ) ?>"
                                    >

                                    <input
                                        class="input"
                                        type="password"
                                        name="password"
                                        minlength="8"
                                        autocomplete="new-password"
                                        placeholder="Password baru"
                                        required
                                    >

                                    <input
                                        class="input"
                                        type="password"
                                        name="password_confirmation"
                                        minlength="8"
                                        autocomplete="new-password"
                                        placeholder="Konfirmasi password"
                                        required
                                    >

                                    <button
                                        class="
                                            btn
                                            btn-soft
                                            btn-sm
                                        "
                                        type="submit"
                                    >
                                        Ubah Password
                                    </button>

                                </form>


                                <!-- DELETE USER -->

                                <?php if (
                                    $account['id'] !==
                                    $user['id']
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
                                                $account['id']
                                            ) ?>"
                                        >

                                        <button
                                            class="
                                                btn
                                                btn-danger
                                                btn-sm
                                            "
                                            type="submit"
                                            data-confirm="
                                                Hapus akun beserta data profil terkait?
                                            "
                                        >
                                            Hapus Akun
                                        </button>

                                    </form>

                                <?php endif; ?>

                            </div>

                        </details>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</section>


<!-- CREATE ACCOUNT MODAL -->

<div
    class="modal"
    id="create-account"
>

    <div class="modal-card">

        <div class="modal-head">

            <div>

                <p class="eyebrow">
                    Akun Internal
                </p>

                <h2>
                    Buat Akun Baru
                </h2>

            </div>

            <button
                class="modal-close"
                type="button"
                data-modal-close
                aria-label="Tutup"
            >
                ×
            </button>

        </div>


        <form
            action="<?= e(
                url('api/users/index.php')
            ) ?>"
            method="post"
            data-ajax
            class="form-grid"
            autocomplete="off"
        >

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="action"
                value="create"
            >


            <div class="field full">

                <label for="create-full-name">
                    Nama Lengkap
                </label>

                <input
                    class="input"
                    id="create-full-name"
                    name="full_name"
                    maxlength="190"
                    required
                >

            </div>


            <div class="field">

                <label for="create-username">
                    Username
                </label>

                <input
                    class="input"
                    id="create-username"
                    name="username"
                    minlength="3"
                    maxlength="80"
                    pattern="[A-Za-z0-9._\-]{3,80}"
                    title="
                        Username 3-80 karakter.
                        Gunakan huruf, angka,
                        titik, underscore,
                        atau tanda minus.
                    "
                    autocomplete="off"
                    required
                >

            </div>


            <div class="field">

                <label for="create-email">
                    Email (opsional)
                </label>

                <input
                    class="input"
                    id="create-email"
                    type="email"
                    name="email"
                    maxlength="190"
                    autocomplete="off"
                >

            </div>


            <div class="field">

                <label for="create-role">
                    Role
                </label>

                <select
                    class="select"
                    id="create-role"
                    name="role"
                    required
                >

                    <option value="guru">
                        Guru
                    </option>

                    <option value="orang_tua">
                        Orang Tua
                    </option>

                    <option value="admin">
                        Admin
                    </option>

                </select>

            </div>


            <div></div>


            <div class="field">

                <label for="create-password">
                    Password
                </label>

                <input
                    class="input"
                    id="create-password"
                    type="password"
                    name="password"
                    minlength="8"
                    maxlength="255"
                    autocomplete="new-password"
                    required
                >

            </div>


            <div class="field">

                <label
                    for="create-password-confirmation"
                >
                    Konfirmasi Password
                </label>

                <input
                    class="input"
                    id="create-password-confirmation"
                    type="password"
                    name="password_confirmation"
                    minlength="8"
                    maxlength="255"
                    autocomplete="new-password"
                    required
                >

            </div>


            <button
                class="
                    btn
                    btn-primary
                    field
                    full
                "
                type="submit"
            >
                Buat Akun Tanpa Verifikasi Email
            </button>

        </form>

    </div>

</div>

<?php

require ROOT_PATH . '/includes/footer.php';