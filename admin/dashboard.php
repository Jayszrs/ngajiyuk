<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

$user = require_role('admin');
$pdo = db();

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function dashboard_count(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function dashboard_time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }

    $timestamp = strtotime($datetime);

    if (!$timestamp) {
        return '-';
    }

    $diff = max(0, time() - $timestamp);

    if ($diff < 60) {
        return 'Baru saja';
    }

    if ($diff < 3600) {
        $minutes = (int) floor($diff / 60);

        return $minutes . ' menit lalu';
    }

    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);

        return $hours . ' jam lalu';
    }

    $days = (int) floor($diff / 86400);

    return $days . ' hari lalu';
}


/*
|--------------------------------------------------------------------------
| Statistik utama
|--------------------------------------------------------------------------
*/

$totalTeachers = dashboard_count(
    $pdo,
    "
        SELECT COUNT(*)
        FROM users
        WHERE role = 'guru'
          AND is_active = 1
          AND approval_status = 'approved'
    "
);

$totalParents = dashboard_count(
    $pdo,
    "
        SELECT COUNT(*)
        FROM users
        WHERE role = 'orang_tua'
          AND is_active = 1
          AND approval_status = 'approved'
    "
);

$totalStudents = dashboard_count(
    $pdo,
    "
        SELECT COUNT(*)
        FROM students
        WHERE status = 'aktif'
    "
);

$totalClasses = dashboard_count(
    $pdo,
    "
        SELECT COUNT(*)
        FROM classes
        WHERE aktif = 1
    "
);

$todayReports = dashboard_count(
    $pdo,
    "
        SELECT COUNT(*)
        FROM daily_student_reports
        WHERE tanggal = CURDATE()
    "
);

$totalLevelExams = dashboard_count(
    $pdo,
    "
        SELECT COUNT(*)
        FROM level_promotion_exams
    "
);

$totalMunaqosyah = dashboard_count(
    $pdo,
    "
        SELECT COUNT(*)
        FROM munaqosyah_exams
    "
);


/*
|--------------------------------------------------------------------------
| Peringatan sistem
|--------------------------------------------------------------------------
*/

$pendingAccounts = dashboard_count(
    $pdo,
    "
        SELECT COUNT(*)
        FROM users
        WHERE approval_status = 'pending'
    "
);

/*
 * Menghitung SISWA aktif yang belum memiliki relasi
 * Orang Tua aktif.
 */
$unlinkedStudents = dashboard_count(
    $pdo,
    "
        SELECT COUNT(*)
        FROM students s
        LEFT JOIN parent_student_links psl
            ON psl.student_id = s.id
           AND psl.status = 'active'
        WHERE s.status = 'aktif'
          AND psl.parent_id IS NULL
    "
);

$classesWithoutTeacher = dashboard_count(
    $pdo,
    "
        SELECT COUNT(*)
        FROM classes
        WHERE aktif = 1
          AND teacher_id IS NULL
    "
);

/*
 * Angka pada card "Perlu Perhatian".
 * Laporan guru ditampilkan sebagai warning tersendiri
 * supaya tidak membuat angka siswa/data menjadi misleading.
 */
$attentionCount =
    $pendingAccounts
    + $unlinkedStudents
    + $classesWithoutTeacher;


/*
|--------------------------------------------------------------------------
| Kelengkapan laporan per guru
|--------------------------------------------------------------------------
|
| Total siswa diambil berdasarkan teacher_id siswa.
| Laporan dihitung berdasarkan laporan hari ini.
|--------------------------------------------------------------------------
*/

$teacherQuery = $pdo->query(
    "
        SELECT
            u.id,
            u.full_name,
            u.username,

            GROUP_CONCAT(
                DISTINCT c.nama_kelas
                ORDER BY c.tingkat, c.rombel
                SEPARATOR ', '
            ) AS classes,

            COUNT(DISTINCT CASE
                WHEN s.status = 'aktif'
                THEN s.id
            END) AS total_students,

            COUNT(DISTINCT CASE
                WHEN dsr.tanggal = CURDATE()
                THEN dsr.student_id
            END) AS completed_reports

        FROM users u

        LEFT JOIN classes c
            ON c.teacher_id = u.id
           AND c.aktif = 1

        LEFT JOIN students s
            ON s.teacher_id = u.id
           AND s.status = 'aktif'

        LEFT JOIN daily_student_reports dsr
            ON dsr.teacher_id = u.id
           AND dsr.student_id = s.id
           AND dsr.tanggal = CURDATE()

        WHERE u.role = 'guru'
          AND u.is_active = 1
          AND u.approval_status = 'approved'

        GROUP BY
            u.id,
            u.full_name,
            u.username

        ORDER BY
            u.full_name ASC
    "
);

$teachers = $teacherQuery->fetchAll(PDO::FETCH_ASSOC);

$teachersNeedAttention = 0;

foreach ($teachers as &$teacher) {
    $total = (int) $teacher['total_students'];
    $completed = (int) $teacher['completed_reports'];

    $teacher['percentage'] = $total > 0
        ? min(
            100,
            (int) round(($completed / $total) * 100)
        )
        : 0;

    /*
     * Guru yang punya siswa tetapi laporan belum lengkap.
     */
    $teacher['needs_attention'] =
        $total > 0
        && $completed < $total;

    if ($teacher['needs_attention']) {
        $teachersNeedAttention++;
    }
}

unset($teacher);


/*
|--------------------------------------------------------------------------
| Aktivitas terbaru
|--------------------------------------------------------------------------
|
| Menggabungkan aktivitas akademik yang memang relevan
| untuk dashboard admin.
|--------------------------------------------------------------------------
*/

$activityStmt = $pdo->query(
    "
        SELECT *
        FROM (

            SELECT
                'level_exam' AS activity_type,
                lpe.created_at,
                u.username,
                u.full_name,
                s.nama_lengkap AS student_name

            FROM level_promotion_exams lpe

            INNER JOIN users u
                ON u.id = lpe.teacher_id

            INNER JOIN students s
                ON s.id = lpe.student_id


            UNION ALL


            SELECT
                'munaqosyah' AS activity_type,
                me.created_at,
                u.username,
                u.full_name,
                s.nama_lengkap AS student_name

            FROM munaqosyah_exams me

            INNER JOIN users u
                ON u.id = me.teacher_id

            INNER JOIN students s
                ON s.id = me.student_id


            UNION ALL


            SELECT
                'daily_report' AS activity_type,
                dsr.created_at,
                u.username,
                u.full_name,
                s.nama_lengkap AS student_name

            FROM daily_student_reports dsr

            INNER JOIN users u
                ON u.id = dsr.teacher_id

            INNER JOIN students s
                ON s.id = dsr.student_id

        ) activity

        ORDER BY created_at DESC

        LIMIT 8
    "
);

$activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| KPI
|--------------------------------------------------------------------------
*/

$stats = [
    [
        'label' => 'Guru',
        'value' => $totalTeachers,
        'description' => 'Akun Guru aktif di sistem',
        'icon' => 'teacher',
        'tone' => 'green',
    ],
    [
        'label' => 'Orang Tua',
        'value' => $totalParents,
        'description' => 'Akun wali terdaftar',
        'icon' => 'parent',
        'tone' => 'blue',
    ],
    [
        'label' => 'Siswa',
        'value' => $totalStudents,
        'description' => 'Siswa aktif seluruh kelas',
        'icon' => 'students',
        'tone' => 'green',
    ],
    [
        'label' => 'Kelas Aktif',
        'value' => $totalClasses,
        'description' => 'Seluruh tahun ajaran',
        'icon' => 'school',
        'tone' => 'blue',
    ],
    [
        'label' => 'Laporan Hari Ini',
        'value' => $todayReports,
        'description' => 'Laporan siswa hari ini',
        'icon' => 'clipboard',
        'tone' => 'green',
    ],
    [
        'label' => 'Ujian Level',
        'value' => $totalLevelExams,
        'description' => 'Total hasil ujian tersimpan',
        'icon' => 'graduate',
        'tone' => 'orange',
    ],
    [
        'label' => 'Munaqosyah',
        'value' => $totalMunaqosyah,
        'description' => 'Total hasil Munaqosyah',
        'icon' => 'book',
        'tone' => 'blue',
    ],
    [
        'label' => 'Perlu Perhatian',
        'value' => $attentionCount,
        'description' => 'Akun atau data perlu dicek',
        'icon' => 'warning',
        'tone' => 'red',
    ],
];

$pageTitle = 'Dashboard Monitoring';

require ROOT_PATH . '/includes/header.php';

?>


<style>
/* =========================================================
   ADMIN MONITORING DASHBOARD
   Page-specific UI
   ========================================================= */

.monitor-dashboard {
    --monitor-green: #087f5b;
    --monitor-green-dark: #0d4d3b;
    --monitor-text: #101828;
    --monitor-muted: #667085;
    --monitor-border: #e7ebe9;
    --monitor-bg-soft: #f8faf9;

    width: 100%;
}


/* ---------------------------------------------------------
   HEADER
   --------------------------------------------------------- */

.monitor-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;

    gap: 20px;

    margin-bottom: 24px;
}

.monitor-eyebrow {
    margin: 0 0 7px;

    color: #00996a;

    font-size: 10px;
    font-weight: 900;

    letter-spacing: .2em;
    text-transform: uppercase;
}

.monitor-title {
    margin: 0;

    color: #08111f;

    font-size: clamp(28px, 2.5vw, 39px);
    font-weight: 900;

    letter-spacing: -.045em;
    line-height: 1.08;
}

.monitor-description {
    margin: 9px 0 0;

    color: #617087;

    font-size: 12px;

    line-height: 1.6;
}

.monitor-update {
    flex: 0 0 auto;

    display: inline-flex;
    align-items: center;

    gap: 7px;

    padding: 9px 14px;

    border: 1px solid #dfe5e2;
    border-radius: 11px;

    background: #fff;

    color: #687588;

    font-size: 10px;
    font-weight: 800;

    box-shadow:
        0 2px 5px rgba(16, 24, 40, .05);
}

.monitor-update svg {
    color: #04a878;
}


/* ---------------------------------------------------------
   KPI
   --------------------------------------------------------- */

.monitor-stats {
    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 14px;

    margin-bottom: 20px;
}

.monitor-stat {
    position: relative;

    min-height: 111px;

    padding: 19px 18px;

    overflow: hidden;

    border: 1px solid #e3e8e5;
    border-radius: 16px;

    background: #fff;

    box-shadow:
        0 2px 4px rgba(16, 24, 40, .04);
}

.monitor-stat-label {
    display: block;

    margin-bottom: 8px;

    color: #98a2b3;

    font-size: 9px;
    font-weight: 900;

    letter-spacing: .03em;

    text-transform: uppercase;
}

.monitor-stat-number {
    display: block;

    color: #0b1524;

    font-size: 27px;
    font-weight: 900;

    letter-spacing: -.035em;

    line-height: 1;
}

.monitor-stat-description {
    display: block;

    margin-top: 10px;

    color: #65758a;

    font-size: 9.5px;
}

.monitor-stat-icon {
    position: absolute;

    top: 18px;
    right: 17px;

    width: 42px;
    height: 42px;

    display: grid;
    place-items: center;

    border-radius: 12px;
}

.monitor-stat-icon svg {
    width: 20px;
    height: 20px;
}

.monitor-stat-icon.green {
    background: #eafbf3;
    color: #008a62;
}

.monitor-stat-icon.blue {
    background: #eef5ff;
    color: #175cd3;
}

.monitor-stat-icon.orange {
    background: #fff8e8;
    color: #e46b13;
}

.monitor-stat-icon.red {
    background: #fff0f0;
    color: #e12727;
}


/* ---------------------------------------------------------
   MAIN DASHBOARD GRID
   --------------------------------------------------------- */

.monitor-content {
    display: grid;

    grid-template-columns:
        minmax(0, 2.08fr)
        minmax(300px, 1fr);

    gap: 20px;

    align-items: start;
}

.monitor-panel {
    overflow: hidden;

    border: 1px solid #e5e9e7;
    border-radius: 19px;

    background: #fff;

    box-shadow:
        0 2px 5px rgba(16, 24, 40, .035);
}


/* ---------------------------------------------------------
   COMPLETENESS
   --------------------------------------------------------- */

.monitor-panel-header {
    min-height: 70px;

    padding: 18px 22px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 15px;

    border-bottom: 1px solid #edf0ee;
}

.monitor-panel-title {
    margin: 0;

    color: #111827;

    font-size: 16px;
    font-weight: 900;

    letter-spacing: -.02em;
}

.monitor-panel-description {
    margin: 6px 0 0;

    color: #718096;

    font-size: 10.5px;
}

.monitor-panel-link {
    flex: 0 0 auto;

    color: #00865d;

    font-size: 10.5px;
    font-weight: 900;

    text-decoration: none;
}

.monitor-panel-link:hover {
    color: #075b45;
}


/* Teacher report row */

.teacher-monitor-row {
    min-height: 67px;

    display: grid;

    grid-template-columns:
        minmax(180px, 1.4fr)
        72px
        minmax(160px, 1.4fr)
        82px;

    align-items: center;

    gap: 18px;

    padding: 13px 22px;

    border-bottom: 1px solid #edf0ee;
}

.teacher-monitor-row:last-child {
    border-bottom: 0;
}

.teacher-monitor-row:hover {
    background: #fbfcfb;
}

.teacher-name {
    display: block;

    color: #111827;

    font-size: 12.5px;
    font-weight: 900;
}

.teacher-class {
    display: block;

    max-width: 340px;

    margin-top: 4px;

    overflow: hidden;

    color: #8190a5;

    font-size: 9.5px;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.teacher-fraction {
    color: #17233a;

    font-size: 12px;
    font-weight: 900;
}

.teacher-progress-wrap {
    min-width: 0;
}

.teacher-progress {
    height: 7px;

    overflow: hidden;

    border-radius: 999px;

    background: #f0f2f4;
}

.teacher-progress span {
    display: block;

    height: 100%;

    border-radius: inherit;

    background: linear-gradient(
        90deg,
        #13a36d,
        #39c98b
    );
}

.teacher-progress-value {
    display: block;

    margin-top: 5px;

    color: #929ba9;

    font-size: 8.5px;

    text-align: right;
}

.teacher-status {
    justify-self: end;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    min-width: 73px;

    padding: 5px 8px;

    border-radius: 999px;

    font-size: 8.5px;
    font-weight: 900;
}

.teacher-status.warning {
    border: 1px solid #ffb6ba;

    background: #fff5f5;

    color: #d92d20;
}

.teacher-status.good {
    border: 1px solid #b7e7cd;

    background: #effbf4;

    color: #087443;
}

.teacher-status.empty {
    border: 1px solid #dfe5e2;

    background: #f8faf9;

    color: #687588;
}


/* ---------------------------------------------------------
   RIGHT COLUMN
   --------------------------------------------------------- */

.monitor-side {
    display: grid;

    gap: 20px;
}


/* System warning */

.system-warning {
    padding: 20px;

    border: 1px solid #efcf64;
    border-radius: 19px;

    background: #fffbed;
}

.system-warning-title {
    display: flex;
    align-items: center;

    gap: 8px;

    margin: 0 0 17px;

    color: #472210;

    font-size: 15px;
    font-weight: 900;
}

.system-warning-title svg {
    color: #71401d;
}

.system-warning-item {
    margin-top: 10px;

    padding: 12px 14px;

    border-radius: 11px;

    background: rgba(255, 255, 255, .74);

    color: #67320c;

    font-size: 10px;
    font-weight: 850;

    line-height: 1.5;
}

.system-warning-item:first-of-type {
    margin-top: 0;
}


/* Activity */

.activity-panel {
    padding: 20px;
}

.activity-title {
    display: flex;
    align-items: center;

    gap: 8px;

    margin: 0 0 18px;

    color: #111827;

    font-size: 15px;
    font-weight: 900;
}

.activity-title svg {
    color: #00a36f;
}

.activity-list {
    display: grid;

    gap: 0;
}

.activity-item {
    position: relative;

    padding: 0 0 16px 16px;

    border-left: 1px solid #bcebd7;
}

.activity-item:last-child {
    padding-bottom: 0;
}

.activity-item::before {
    content: "";

    position: absolute;

    top: 3px;
    left: -3px;

    width: 5px;
    height: 5px;

    border-radius: 50%;

    background: #28b87d;
}

.activity-name {
    display: block;

    color: #172033;

    font-size: 10.5px;
    font-weight: 900;

    line-height: 1.45;
}

.activity-meta {
    display: block;

    margin-top: 3px;

    color: #718096;

    font-size: 9px;

    line-height: 1.45;
}

.activity-empty {
    padding: 18px;

    border-radius: 12px;

    background: #f8faf9;

    color: #7d8a84;

    font-size: 10px;

    text-align: center;
}


/* ---------------------------------------------------------
   RESPONSIVE
   --------------------------------------------------------- */

@media (max-width: 1180px) {

    .monitor-stats {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

    .monitor-content {
        grid-template-columns: 1fr;
    }

    .monitor-side {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

}


@media (max-width: 760px) {

    .monitor-head {
        align-items: flex-start;

        flex-direction: column;
    }

    .monitor-update {
        align-self: flex-start;
    }

    .monitor-stats {
        grid-template-columns: 1fr;
    }

    .monitor-side {
        grid-template-columns: 1fr;
    }

    .teacher-monitor-row {
        grid-template-columns:
            1fr auto;

        gap: 10px;

        padding: 15px 17px;
    }

    .teacher-progress-wrap {
        grid-column: 1 / -1;
    }

    .teacher-status {
        grid-column: 2;
        grid-row: 1;
    }

    .teacher-fraction {
        grid-column: 1;
    }

    .monitor-panel-header {
        padding: 17px;

        align-items: flex-start;
    }

}


@media (max-width: 480px) {

    .monitor-panel-header {
        flex-direction: column;
    }

}
</style>


<div class="monitor-dashboard">

    <!-- =====================================================
         PAGE HEADER
         ===================================================== -->

    <header class="monitor-head">

        <div>

            <p class="monitor-eyebrow">
                Pusat Kendali
            </p>

            <h1 class="monitor-title">
                Dashboard Monitoring
            </h1>

            <p class="monitor-description">
                Ringkasan kondisi akun, siswa, kelas, laporan,
                dan peringatan sistem hari ini.
            </p>

        </div>

        <div class="monitor-update">

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
                <circle cx="12" cy="12" r="9"></circle>
                <path d="M12 7v5l3 2"></path>
            </svg>

            <span>
                Perbarui · Baru saja
            </span>

        </div>

    </header>


    <!-- =====================================================
         KPI CARDS
         ===================================================== -->

    <section class="monitor-stats">

        <?php foreach ($stats as $stat): ?>

            <article class="monitor-stat">

                <span class="monitor-stat-label">
                    <?= e($stat['label']) ?>
                </span>

                <strong class="monitor-stat-number">
                    <?= number_format((int) $stat['value'], 0, ',', '.') ?>
                </strong>

                <span class="monitor-stat-description">
                    <?= e($stat['description']) ?>
                </span>


                <span
                    class="
                        monitor-stat-icon
                        <?= e($stat['tone']) ?>
                    "
                >

                    <?php if ($stat['icon'] === 'teacher'): ?>

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path d="m3 8 9-4 9 4-9 4-9-4Z"/>
                            <path d="M7 10v5c3 2 7 2 10 0v-5"/>
                        </svg>


                    <?php elseif ($stat['icon'] === 'parent'): ?>

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <circle cx="10" cy="8" r="4"/>
                            <path d="M3 20c0-4 3-7 7-7"/>
                            <path d="m16 17 2 2 4-5"/>
                        </svg>


                    <?php elseif ($stat['icon'] === 'students'): ?>

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <circle cx="9" cy="8" r="3"/>
                            <circle cx="17" cy="8" r="2.5"/>
                            <path d="M3 20v-2c0-3 2-5 6-5s6 2 6 5v2"/>
                            <path d="M16 13c3 0 5 2 5 5v2"/>
                        </svg>


                    <?php elseif ($stat['icon'] === 'school'): ?>

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path d="M4 21V8l8-5 8 5v13"/>
                            <path d="M8 21v-6h8v6"/>
                            <path d="M8 10h2M14 10h2"/>
                        </svg>


                    <?php elseif ($stat['icon'] === 'clipboard'): ?>

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <rect x="5" y="4" width="14" height="17" rx="2"/>
                            <path d="M9 4V2h6v2"/>
                            <path d="m9 12 2 2 4-4"/>
                        </svg>


                    <?php elseif ($stat['icon'] === 'graduate'): ?>

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path d="m3 9 9-5 9 5-9 5-9-5Z"/>
                            <path d="M7 12v4c3 2 7 2 10 0v-4"/>
                        </svg>


                    <?php elseif ($stat['icon'] === 'book'): ?>

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path d="M4 5c3-1 5 0 8 2v13c-3-2-5-3-8-2V5Z"/>
                            <path d="M20 5c-3-1-5 0-8 2v13c3-2 5-3 8-2V5Z"/>
                        </svg>


                    <?php else: ?>

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path d="M12 3 5 6v5c0 5 3 8 7 10 4-2 7-5 7-10V6l-7-3Z"/>
                            <path d="M12 8v5"/>
                            <path d="M12 17h.01"/>
                        </svg>

                    <?php endif; ?>

                </span>

            </article>

        <?php endforeach; ?>

    </section>


    <!-- =====================================================
         MAIN CONTENT
         ===================================================== -->

    <div class="monitor-content">


        <!-- =================================================
             LEFT: COMPLETENESS
             ================================================= -->

        <section class="monitor-panel">

            <header class="monitor-panel-header">

                <div>

                    <h2 class="monitor-panel-title">
                        Kelengkapan Hari Ini
                    </h2>

                    <p class="monitor-panel-description">
                        Laporan Guru dibandingkan jumlah siswa
                        yang ditangani.
                    </p>

                </div>

                <a
                    href="<?= e(url('admin/completeness.php')) ?>"
                    class="monitor-panel-link"
                >
                    Lihat semua
                </a>

            </header>


            <div>

                <?php if ($teachers): ?>

                    <?php foreach ($teachers as $teacher): ?>

                        <?php
                        $total = (int) $teacher['total_students'];
                        $completed = (int) $teacher['completed_reports'];
                        $percentage = (int) $teacher['percentage'];

                        $classes = trim(
                            (string) ($teacher['classes'] ?? '')
                        );

                        if ($classes === '') {
                            $classes = 'Belum ada kelas';
                        }
                        ?>

                        <article class="teacher-monitor-row">

                            <div>

                                <strong class="teacher-name">
                                    <?= e($teacher['full_name']) ?>
                                </strong>

                                <span class="teacher-class">
                                    <?= e($classes) ?>
                                </span>

                            </div>


                            <div class="teacher-fraction">

                                <?= $completed ?>/<?= $total ?>

                            </div>


                            <div class="teacher-progress-wrap">

                                <div class="teacher-progress">

                                    <span
                                        style="
                                            width:
                                            <?= $percentage ?>%;
                                        "
                                    ></span>

                                </div>

                                <span class="teacher-progress-value">
                                    <?= $percentage ?>%
                                </span>

                            </div>


                            <?php if ($total === 0): ?>

                                <span class="teacher-status empty">
                                    Belum Ada Siswa
                                </span>

                            <?php elseif ($teacher['needs_attention']): ?>

                                <span class="teacher-status warning">
                                    Perlu Dicek
                                </span>

                            <?php else: ?>

                                <span class="teacher-status good">
                                    Lengkap
                                </span>

                            <?php endif; ?>

                        </article>

                    <?php endforeach; ?>


                <?php else: ?>

                    <div class="activity-empty">
                        Belum ada akun Guru aktif.
                    </div>

                <?php endif; ?>

            </div>

        </section>


        <!-- =================================================
             RIGHT
             ================================================= -->

        <aside class="monitor-side">


            <!-- SYSTEM WARNING -->

            <section class="system-warning">

                <h2 class="system-warning-title">

                    <svg
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M12 3 2.5 20h19L12 3Z"/>
                        <path d="M12 9v5"/>
                        <path d="M12 17h.01"/>
                    </svg>

                    Peringatan Sistem

                </h2>


                <?php if ($unlinkedStudents > 0): ?>

                    <div class="system-warning-item">

                        <?= $unlinkedStudents ?>
                        siswa belum terhubung ke Orang Tua.

                    </div>

                <?php endif; ?>


                <?php if ($teachersNeedAttention > 0): ?>

                    <div class="system-warning-item">

                        <?= $teachersNeedAttention ?>
                        Guru perlu dicek laporan hari ini.

                    </div>

                <?php endif; ?>


                <?php if ($pendingAccounts > 0): ?>

                    <div class="system-warning-item">

                        <?= $pendingAccounts ?>
                        akun masih menunggu persetujuan.

                    </div>

                <?php endif; ?>


                <?php if ($classesWithoutTeacher > 0): ?>

                    <div class="system-warning-item">

                        <?= $classesWithoutTeacher ?>
                        kelas aktif belum mempunyai Guru.

                    </div>

                <?php endif; ?>


                <?php if (
                    $unlinkedStudents === 0
                    && $teachersNeedAttention === 0
                    && $pendingAccounts === 0
                    && $classesWithoutTeacher === 0
                ): ?>

                    <div class="system-warning-item">

                        Tidak ada peringatan sistem saat ini.

                    </div>

                <?php endif; ?>

            </section>


            <!-- ACTIVITY -->

            <section class="monitor-panel activity-panel">

                <h2 class="activity-title">

                    <svg
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            d="
                                M3 12h4
                                l2-7
                                4 14
                                2-7
                                h6
                            "
                        />
                    </svg>

                    Aktivitas Terbaru

                </h2>


                <?php if ($activities): ?>

                    <div class="activity-list">

                        <?php foreach ($activities as $activity): ?>

                            <?php

                            $activityTitle = match (
                                $activity['activity_type']
                            ) {
                                'level_exam'
                                    => 'Hasil ujian level disimpan',

                                'munaqosyah'
                                    => 'Hasil Munaqosyah disimpan',

                                default
                                    => 'Laporan harian Guru disimpan',
                            };

                            ?>

                            <article class="activity-item">

                                <strong class="activity-name">
                                    <?= e($activityTitle) ?>
                                </strong>

                                <span class="activity-meta">

                                    @<?= e($activity['username']) ?>

                                    ·

                                    <?= e(
                                        dashboard_time_ago(
                                            $activity['created_at']
                                        )
                                    ) ?>

                                </span>

                            </article>

                        <?php endforeach; ?>

                    </div>


                <?php else: ?>

                    <div class="activity-empty">

                        Belum ada aktivitas terbaru.

                    </div>

                <?php endif; ?>

            </section>

        </aside>

    </div>

</div>


<?php

require ROOT_PATH . '/includes/footer.php';

?>