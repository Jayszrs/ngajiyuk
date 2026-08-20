<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

$user = require_role('admin');
$pdo = db();


/*
|--------------------------------------------------------------------------
| Helper halaman
|--------------------------------------------------------------------------
*/

function teacher_time_ago(?string $datetime): string
{
    if (!$datetime) {
        return 'Belum ada aktivitas';
    }

    $timestamp = strtotime($datetime);

    if (!$timestamp) {
        return 'Belum ada aktivitas';
    }

    $diff = max(0, time() - $timestamp);

    if ($diff < 60) {
        return 'Baru saja';
    }

    if ($diff < 3600) {
        $minutes = max(1, (int) floor($diff / 60));

        return $minutes . ' menit lalu';
    }

    if ($diff < 86400) {
        $hours = max(1, (int) floor($diff / 3600));

        return $hours . ' jam lalu';
    }

    if ($diff < 604800) {
        $days = max(1, (int) floor($diff / 86400));

        return $days . ' hari lalu';
    }

    if ($diff < 2592000) {
        $weeks = max(1, (int) floor($diff / 604800));

        return $weeks . ' minggu lalu';
    }

    $days = max(1, (int) floor($diff / 86400));

    return $days . ' hari lalu';
}


/*
|--------------------------------------------------------------------------
| Ambil seluruh Guru
|--------------------------------------------------------------------------
|
| today_reports
| = jumlah siswa yang sudah memiliki laporan hari ini
|
| week_reports
| = jumlah laporan 7 hari terakhir
|
| latest_report_at
| = aktivitas laporan terakhir Guru
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    "
        SELECT
            u.id,
            u.username,
            u.email,
            u.full_name,
            u.is_active,
            u.last_login_at,

            GROUP_CONCAT(
                DISTINCT c.nama_kelas
                ORDER BY c.nama_kelas
                SEPARATOR ', '
            ) AS classes,

            COUNT(
                DISTINCT CASE
                    WHEN s.status = 'aktif'
                    THEN s.id
                END
            ) AS students,

            COUNT(
                DISTINCT CASE
                    WHEN d.tanggal = CURDATE()
                    THEN d.student_id
                END
            ) AS today_reports,

            COUNT(
                DISTINCT CASE
                    WHEN d.tanggal >= DATE_SUB(
                        CURDATE(),
                        INTERVAL 6 DAY
                    )
                    THEN d.id
                END
            ) AS week_reports,

            MAX(d.created_at) AS latest_report_at

        FROM users u

        LEFT JOIN classes c
            ON c.teacher_id = u.id
           AND c.aktif = 1

        LEFT JOIN students s
            ON s.teacher_id = u.id
           AND s.status = 'aktif'

        LEFT JOIN daily_student_reports d
            ON d.teacher_id = u.id

        WHERE u.role = 'guru'

        GROUP BY
            u.id,
            u.username,
            u.email,
            u.full_name,
            u.is_active,
            u.last_login_at

        ORDER BY
            u.full_name ASC
    "
);

$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Hitung nilai turunan
|--------------------------------------------------------------------------
*/

foreach ($teachers as &$teacher) {

    $students = (int) $teacher['students'];
    $todayReports = (int) $teacher['today_reports'];
    $weekReports = (int) $teacher['week_reports'];

    /*
     * Progress hari ini
     */
    $teacher['today_percentage'] = $students > 0
        ? min(
            100,
            (int) round(
                ($todayReports / $students) * 100
            )
        )
        : 0;


    /*
     * Target 7 hari:
     * jumlah siswa x 7 hari.
     *
     * Contoh:
     * 1 siswa + 1 laporan selama 7 hari
     * = sekitar 14%.
     */
    $weekTarget = $students * 7;

    $teacher['week_percentage'] = $weekTarget > 0
        ? min(
            100,
            (int) round(
                ($weekReports / $weekTarget) * 100
            )
        )
        : 0;


    /*
     * Status aktivitas laporan.
     */
    $teacher['last_activity_text'] =
        teacher_time_ago(
            $teacher['latest_report_at']
                ?: null
        );

    $teacher['last_login_text'] =
        $teacher['last_login_at']
            ? teacher_time_ago(
                $teacher['last_login_at']
            )
            : 'Belum ada aktivitas';
}

unset($teacher);


$pageTitle = 'Monitoring Guru';

require ROOT_PATH . '/includes/header.php';

?>


<style>

/* =========================================================
   MONITORING GURU
   admin/teachers.php
   ========================================================= */

.teacher-monitor-page {
    --tm-green: #19533f;
    --tm-green-hover: #124330;
    --tm-green-light: #ebfaf3;

    --tm-blue: #1d4ed8;

    --tm-red: #dc2626;
    --tm-red-light: #fff1f1;

    --tm-text: #101828;
    --tm-text-soft: #344054;
    --tm-muted: #7b889b;

    --tm-line: #e8ecea;
    --tm-bg: #ffffff;

    width: 100%;
}


/* =========================================================
   PAGE HEADER
   ========================================================= */

.tm-page-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;

    gap: 24px;

    margin-bottom: 25px;
}

.tm-eyebrow {
    margin: 0 0 8px;

    color: #008f62;

    font-size: 10px;
    font-weight: 900;

    letter-spacing: .21em;
    text-transform: uppercase;
}

.tm-title {
    margin: 0;

    color: #070d18;

    font-size: clamp(
        30px,
        2.5vw,
        39px
    );

    font-weight: 900;

    line-height: 1.05;

    letter-spacing: -.045em;
}

.tm-description {
    margin: 10px 0 0;

    color: #627086;

    font-size: 12px;

    line-height: 1.6;
}


/* update pill */

.tm-update {
    flex: 0 0 auto;

    display: inline-flex;
    align-items: center;

    gap: 7px;

    min-height: 37px;

    padding: 8px 14px;

    border: 1px solid #dde4e0;
    border-radius: 11px;

    background: #ffffff;

    color: #667085;

    font-size: 10px;
    font-weight: 800;

    box-shadow:
        0 2px 5px
        rgba(16, 24, 40, .05);
}

.tm-update svg {
    color: #00a875;
}


/* =========================================================
   MAIN PANEL
   ========================================================= */

.tm-panel {
    overflow: hidden;

    border: 1px solid #e1e6e3;

    border-radius: 20px;

    background: #ffffff;

    box-shadow:
        0 2px 5px
        rgba(16, 24, 40, .035);
}


/* =========================================================
   TOOLBAR
   ========================================================= */

.tm-toolbar {
    min-height: 77px;

    padding: 17px 19px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 20px;

    border-bottom:
        1px solid var(--tm-line);
}

.tm-search {
    position: relative;

    width: min(
        410px,
        100%
    );
}

.tm-search-icon {
    position: absolute;

    top: 50%;
    left: 14px;

    width: 17px;
    height: 17px;

    color: #98a2b3;

    pointer-events: none;

    transform: translateY(-50%);
}

.tm-search input {
    width: 100%;
    height: 42px;

    padding:
        9px
        14px
        9px
        42px;

    border: 1px solid #dce2df;
    border-radius: 11px;

    outline: 0;

    background: #ffffff;

    color: #344054;

    font-family: inherit;

    font-size: 11px;
    font-weight: 600;
}

.tm-search input::placeholder {
    color: #7c8998;
}

.tm-search input:hover {
    border-color: #c7d1cc;
}

.tm-search input:focus {
    border-color: #52a486;

    box-shadow:
        0 0 0 3px
        rgba(0, 143, 98, .09);
}


/* teacher count */

.tm-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-width: 52px;
    min-height: 23px;

    padding: 4px 9px;

    border: 1px solid #b9d6ff;
    border-radius: 999px;

    background: #f1f6ff;

    color: #175cd3;

    font-size: 9px;
    font-weight: 900;

    white-space: nowrap;
}


/* =========================================================
   TABLE
   ========================================================= */

.tm-table-scroll {
    width: 100%;

    overflow-x: auto;

    scrollbar-width: thin;
}

.tm-table {
    width: 100%;

    min-width: 1180px;

    border-collapse: collapse;

    table-layout: fixed;
}

.tm-table thead th {
    height: 45px;

    padding: 12px 18px;

    border-bottom:
        1px solid var(--tm-line);

    background: #f8f9fa;

    color: #546176;

    font-size: 9px;
    font-weight: 900;

    text-align: left;

    text-transform: uppercase;

    letter-spacing: .01em;

    white-space: nowrap;
}


/*
 * Column width
 */

.tm-table th:nth-child(1),
.tm-table td:nth-child(1) {
    width: 17%;
}

.tm-table th:nth-child(2),
.tm-table td:nth-child(2) {
    width: 27%;
}

.tm-table th:nth-child(3),
.tm-table td:nth-child(3) {
    width: 13%;
}

.tm-table th:nth-child(4),
.tm-table td:nth-child(4) {
    width: 8%;
}

.tm-table th:nth-child(5),
.tm-table td:nth-child(5) {
    width: 15%;
}

.tm-table th:nth-child(6),
.tm-table td:nth-child(6) {
    width: 7%;
}

.tm-table th:nth-child(7),
.tm-table td:nth-child(7) {
    width: 13%;
}


.tm-table tbody tr {
    transition:
        background .15s ease;
}

.tm-table tbody tr:hover {
    background: #fbfcfb;
}

.tm-table tbody td {
    height: 67px;

    padding: 13px 18px;

    border-bottom:
        1px solid var(--tm-line);

    vertical-align: middle;

    color: var(--tm-text-soft);

    font-size: 10.5px;
}

.tm-table tbody tr:last-child td {
    border-bottom: 0;
}


/* =========================================================
   GURU
   ========================================================= */

.tm-person-name {
    display: block;

    overflow: hidden;

    color: #101828;

    font-size: 12px;
    font-weight: 900;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.tm-person-contact {
    display: block;

    max-width: 220px;

    margin-top: 5px;

    overflow: hidden;

    color: #64748b;

    font-size: 9px;

    text-overflow: ellipsis;
    white-space: nowrap;
}


/* =========================================================
   CLASS
   ========================================================= */

.tm-class-name {
    display: block;

    max-width: 380px;

    overflow: hidden;

    color: #172033;

    font-size: 11.5px;
    font-weight: 900;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.tm-class-meta {
    display: block;

    margin-top: 5px;

    color: #7a879a;

    font-size: 9px;
}


/* =========================================================
   DAILY REPORT
   ========================================================= */

.tm-report-number {
    display: block;

    color: #101828;

    font-size: 12px;
    font-weight: 900;
}

.tm-progress {
    width: 102px;
    height: 7px;

    margin-top: 8px;

    overflow: hidden;

    border-radius: 999px;

    background: #f0f2f4;
}

.tm-progress span {
    display: block;

    height: 100%;

    border-radius: inherit;

    background:
        linear-gradient(
            90deg,
            #0ca76e,
            #3ac88c
        );
}


/* =========================================================
   WEEK
   ========================================================= */

.tm-week-percent {
    display: block;

    color: #101828;

    font-size: 12px;
    font-weight: 900;
}

.tm-week-meta {
    display: block;

    margin-top: 5px;

    color: #7c8998;

    font-size: 9px;
}


/* =========================================================
   ACTIVITY
   ========================================================= */

.tm-activity-primary {
    display: block;

    color: #172033;

    font-size: 10.5px;
    font-weight: 900;
}

.tm-activity-login {
    display: block;

    margin-top: 5px;

    color: #8b96a7;

    font-size: 9px;
}


/* =========================================================
   STATUS
   ========================================================= */

.tm-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 23px;

    padding: 4px 9px;

    border-radius: 999px;

    font-size: 9px;
    font-weight: 900;
}

.tm-status.active {
    border: 1px solid #9ce8c0;

    background: #ecfff5;

    color: #008a4d;
}

.tm-status.inactive {
    border: 1px solid #f4b9b9;

    background: #fff2f2;

    color: #d72d2d;
}


/* =========================================================
   ACTION
   ========================================================= */

.tm-actions {
    display: flex;
    align-items: center;

    gap: 8px;
}

.tm-action-more {
    width: 35px;
    height: 35px;

    flex: 0 0 35px;

    display: inline-grid;
    place-items: center;

    padding: 0;

    border: 0;
    border-radius: 10px;

    background: #f5f6f7;

    color: #526071;

    cursor: pointer;
}

.tm-action-more:hover {
    background: #e9eeeb;

    color: #174f3d;
}

.tm-action-toggle {
    min-height: 35px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 7px;

    padding: 7px 12px;

    border: 0;
    border-radius: 10px;

    cursor: pointer;

    font-family: inherit;

    font-size: 9.5px;
    font-weight: 900;

    white-space: nowrap;
}

.tm-action-toggle.disable {
    background: #fff1f1;

    color: #d51f2d;
}

.tm-action-toggle.disable:hover {
    background: #ffe5e5;
}

.tm-action-toggle.enable {
    background: #ecfaf3;

    color: #008452;
}

.tm-action-toggle.enable:hover {
    background: #dff6ea;
}


/* eye/icon */

.tm-action-toggle svg {
    width: 15px;
    height: 15px;
}


/* =========================================================
   DETAIL ROW
   ========================================================= */

.tm-detail-row {
    display: none;
}

.tm-detail-row.open {
    display: table-row;
}

.tm-detail-row td {
    height: auto !important;

    padding: 0 !important;

    background: #f8faf9;
}

.tm-detail-content {
    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 20px;

    padding: 16px 20px 18px;
}

.tm-detail-item {
    min-width: 0;
}

.tm-detail-label {
    display: block;

    margin-bottom: 5px;

    color: #89958f;

    font-size: 8px;
    font-weight: 900;

    letter-spacing: .06em;

    text-transform: uppercase;
}

.tm-detail-value {
    display: block;

    overflow-wrap: anywhere;

    color: #344054;

    font-size: 10px;
    font-weight: 750;
}


/* =========================================================
   EMPTY SEARCH
   ========================================================= */

.tm-search-empty {
    display: none;

    padding: 45px 20px;

    color: #7b8794;

    font-size: 11px;

    text-align: center;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 820px) {

    .tm-page-head {
        align-items: flex-start;

        flex-direction: column;

        gap: 15px;
    }

    .tm-toolbar {
        align-items: stretch;

        flex-direction: column;

        gap: 11px;
    }

    .tm-search {
        width: 100%;
    }

    .tm-count {
        align-self: flex-start;
    }

    .tm-detail-content {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

}


@media (max-width: 520px) {

    .tm-title {
        font-size: 28px;
    }

    .tm-detail-content {
        grid-template-columns: 1fr;
    }

}

</style>


<div class="teacher-monitor-page">


    <!-- =====================================================
         HEADER
         ===================================================== -->

    <header class="tm-page-head">

        <div>

            <p class="tm-eyebrow">
                Tenaga Pengajar
            </p>

            <h1 class="tm-title">
                Monitoring Guru
            </h1>

            <p class="tm-description">
                Pantau kelas, jumlah siswa, aktivitas laporan,
                kelengkapan profil, dan status setiap Guru.
            </p>

        </div>


        <div class="tm-update">

            <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <circle
                    cx="12"
                    cy="12"
                    r="9"
                ></circle>

                <path
                    d="M12 7v5l3 2"
                ></path>
            </svg>

            <span>
                Perbarui · Baru saja
            </span>

        </div>

    </header>


    <!-- =====================================================
         TABLE PANEL
         ===================================================== -->

    <section class="tm-panel">


        <!-- Toolbar -->

        <div class="tm-toolbar">

            <label
                class="tm-search"
                for="teacher-search"
            >

                <svg
                    class="tm-search-icon"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <circle
                        cx="11"
                        cy="11"
                        r="7"
                    ></circle>

                    <path
                        d="m20 20-4-4"
                    ></path>
                </svg>


                <input
                    type="search"
                    id="teacher-search"
                    placeholder="Cari Guru, kelas, atau email..."
                    autocomplete="off"
                >

            </label>


            <span
                class="tm-count"
                id="teacher-count"
            >
                <?= count($teachers) ?> Guru
            </span>

        </div>


        <!-- Table -->

        <div class="tm-table-scroll">

            <table
                class="tm-table"
                id="teacher-monitor-table"
            >

                <thead>

                    <tr>

                        <th>Guru</th>

                        <th>
                            Kelas &amp; Siswa
                        </th>

                        <th>
                            Laporan Hari Ini
                        </th>

                        <th>
                            7 Hari
                        </th>

                        <th>
                            Terakhir Aktif
                        </th>

                        <th>Status</th>

                        <th>Aksi</th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($teachers as $teacher): ?>

                    <?php

                    $classes = trim(
                        (string) (
                            $teacher['classes']
                            ?? ''
                        )
                    );

                    if ($classes === '') {
                        $classes = 'Belum ada kelas';
                    }


                    $emailOrUsername =
                        trim(
                            (string) (
                                $teacher['email']
                                ?? ''
                            )
                        );

                    if ($emailOrUsername === '') {
                        $emailOrUsername =
                            '@' . (
                                $teacher['username']
                                ?? '-'
                            );
                    }


                    $teacherId =
                        (int) $teacher['id'];

                    $students =
                        (int) $teacher['students'];

                    $todayReports =
                        (int) $teacher['today_reports'];

                    $weekReports =
                        (int) $teacher['week_reports'];

                    $todayPercentage =
                        (int) $teacher['today_percentage'];

                    $weekPercentage =
                        (int) $teacher['week_percentage'];

                    $isActive =
                        (bool) $teacher['is_active'];


                    $searchData = strtolower(
                        implode(
                            ' ',
                            [
                                $teacher['full_name'],
                                $teacher['username'],
                                $teacher['email'],
                                $classes,
                            ]
                        )
                    );

                    ?>


                    <!-- MAIN ROW -->

                    <tr
                        class="tm-teacher-row"
                        data-search="<?= e($searchData) ?>"
                    >


                        <!-- GURU -->

                        <td>

                            <strong class="tm-person-name">

                                <?= e(
                                    $teacher['full_name']
                                ) ?>

                            </strong>

                            <span class="tm-person-contact">

                                <?= e(
                                    $emailOrUsername
                                ) ?>

                            </span>

                        </td>


                        <!-- CLASS -->

                        <td>

                            <strong class="tm-class-name">

                                <?= e($classes) ?>

                            </strong>

                            <span class="tm-class-meta">

                                <?= $students ?>
                                siswa

                            </span>

                        </td>


                        <!-- TODAY -->

                        <td>

                            <strong class="tm-report-number">

                                <?= $todayReports ?>
                                /
                                <?= $students ?>

                            </strong>

                            <div
                                class="tm-progress"
                                title="<?= $todayPercentage ?>%"
                            >

                                <span
                                    style="
                                        width:
                                        <?= $todayPercentage ?>%
                                    "
                                ></span>

                            </div>

                        </td>


                        <!-- WEEK -->

                        <td>

                            <strong class="tm-week-percent">

                                <?= $weekPercentage ?>%

                            </strong>

                            <span class="tm-week-meta">

                                <?= $weekReports ?>
                                laporan

                            </span>

                        </td>


                        <!-- LAST ACTIVITY -->

                        <td>

                            <strong
                                class="tm-activity-primary"
                            >

                                <?= e(
                                    $teacher[
                                        'last_activity_text'
                                    ]
                                ) ?>

                            </strong>

                            <span
                                class="tm-activity-login"
                            >

                                Login:
                                <?= e(
                                    $teacher[
                                        'last_login_text'
                                    ]
                                ) ?>

                            </span>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <span
                                class="
                                    tm-status
                                    <?= $isActive
                                        ? 'active'
                                        : 'inactive'
                                    ?>
                                "
                            >

                                <?= $isActive
                                    ? 'Aktif'
                                    : 'Nonaktif'
                                ?>

                            </span>

                        </td>


                        <!-- ACTION -->

                        <td>

                            <div class="tm-actions">


                                <!-- Detail button -->

                                <button
                                    class="tm-action-more"
                                    type="button"
                                    data-teacher-detail="
                                        <?= $teacherId ?>
                                    "
                                    aria-expanded="false"
                                    aria-label="
                                        Detail
                                        <?= e(
                                            $teacher[
                                                'full_name'
                                            ]
                                        ) ?>
                                    "
                                >

                                    <svg
                                        width="16"
                                        height="16"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path
                                            d="m8 10 4 4 4-4"
                                        ></path>
                                    </svg>

                                </button>


                                <!-- Active toggle -->

                                <form
                                    action="<?=
                                        e(
                                            url(
                                                'api/users/index.php'
                                            )
                                        )
                                    ?>"
                                    method="post"
                                    data-ajax
                                >

                                    <?= csrf_field() ?>


                                    <input
                                        type="hidden"
                                        name="action"
                                        value="toggle_active"
                                    >


                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?= $teacherId ?>"
                                    >


                                    <input
                                        type="hidden"
                                        name="active"
                                        value="<?=
                                            $isActive
                                                ? '0'
                                                : '1'
                                        ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="
                                            tm-action-toggle
                                            <?= $isActive
                                                ? 'disable'
                                                : 'enable'
                                            ?>
                                        "
                                    >

                                        <?php if ($isActive): ?>

                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <circle
                                                    cx="12"
                                                    cy="12"
                                                    r="8"
                                                ></circle>

                                                <circle
                                                    cx="12"
                                                    cy="12"
                                                    r="2"
                                                ></circle>
                                            </svg>

                                            Nonaktifkan

                                        <?php else: ?>

                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    d="
                                                        M5
                                                        12
                                                        l4
                                                        4
                                                        10
                                                        -10
                                                    "
                                                ></path>
                                            </svg>

                                            Aktifkan

                                        <?php endif; ?>

                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>


                    <!-- DETAIL ROW -->

                    <tr
                        class="tm-detail-row"
                        id="teacher-detail-<?= $teacherId ?>"
                    >

                        <td colspan="7">

                            <div class="tm-detail-content">

                                <div class="tm-detail-item">

                                    <span
                                        class="tm-detail-label"
                                    >
                                        Username
                                    </span>

                                    <strong
                                        class="tm-detail-value"
                                    >

                                        @<?= e(
                                            $teacher[
                                                'username'
                                            ]
                                        ) ?>

                                    </strong>

                                </div>


                                <div class="tm-detail-item">

                                    <span
                                        class="tm-detail-label"
                                    >
                                        Email
                                    </span>

                                    <strong
                                        class="tm-detail-value"
                                    >

                                        <?= e(
                                            $teacher['email']
                                                ?: '-'
                                        ) ?>

                                    </strong>

                                </div>


                                <div class="tm-detail-item">

                                    <span
                                        class="tm-detail-label"
                                    >
                                        Laporan Hari Ini
                                    </span>

                                    <strong
                                        class="tm-detail-value"
                                    >

                                        <?= $todayReports ?>
                                        dari
                                        <?= $students ?>
                                        siswa

                                    </strong>

                                </div>


                                <div class="tm-detail-item">

                                    <span
                                        class="tm-detail-label"
                                    >
                                        Laporan 7 Hari
                                    </span>

                                    <strong
                                        class="tm-detail-value"
                                    >

                                        <?= $weekReports ?>
                                        laporan
                                        ·
                                        <?= $weekPercentage ?>%

                                    </strong>

                                </div>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>


        <div
            class="tm-search-empty"
            id="teacher-search-empty"
        >
            Guru yang dicari tidak ditemukan.
        </div>

    </section>

</div>


<script>
(() => {

    /*
    |--------------------------------------------------------------------------
    | Search Guru
    |--------------------------------------------------------------------------
    */

    const searchInput =
        document.querySelector('#teacher-search');

    const rows =
        Array.from(
            document.querySelectorAll(
                '.tm-teacher-row'
            )
        );

    const count =
        document.querySelector('#teacher-count');

    const emptyState =
        document.querySelector(
            '#teacher-search-empty'
        );


    if (searchInput) {

        searchInput.addEventListener(
            'input',
            () => {

                const keyword =
                    searchInput
                        .value
                        .trim()
                        .toLowerCase();

                let visible = 0;


                rows.forEach(row => {

                    const haystack =
                        (
                            row.dataset.search
                            || ''
                        ).toLowerCase();

                    const matched =
                        !keyword
                        || haystack.includes(
                            keyword
                        );


                    row.style.display =
                        matched
                            ? ''
                            : 'none';


                    /*
                     * Detail row ikut disembunyikan
                     * bila parent row tidak match.
                     */
                    const button =
                        row.querySelector(
                            '[data-teacher-detail]'
                        );

                    if (button) {

                        const id =
                            button.dataset
                                .teacherDetail;

                        const detail =
                            document.querySelector(
                                '#teacher-detail-'
                                + id
                            );

                        if (
                            detail
                            && !matched
                        ) {
                            detail.classList
                                .remove('open');

                            button.setAttribute(
                                'aria-expanded',
                                'false'
                            );
                        }

                    }


                    if (matched) {
                        visible++;
                    }

                });


                if (count) {

                    count.textContent =
                        visible
                        + ' Guru';

                }


                if (emptyState) {

                    emptyState.style.display =
                        visible === 0
                            ? 'block'
                            : 'none';

                }

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Expand detail Guru
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'click',
        event => {

            const button =
                event.target.closest(
                    '[data-teacher-detail]'
                );

            if (!button) {
                return;
            }


            const teacherId =
                button.dataset.teacherDetail;

            const detailRow =
                document.querySelector(
                    '#teacher-detail-'
                    + teacherId
                );

            if (!detailRow) {
                return;
            }


            const isOpen =
                detailRow.classList
                    .toggle('open');


            button.setAttribute(
                'aria-expanded',
                isOpen
                    ? 'true'
                    : 'false'
            );

        }
    );

})();
</script>


<?php

require ROOT_PATH . '/includes/footer.php';

?>