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

function completeness_percent(
    int $completed,
    int $target
): int {
    if ($target <= 0) {
        return 0;
    }

    return min(
        100,
        (int) round(
            ($completed / $target) * 100
        )
    );
}


function completeness_status(
    int $students,
    int $reportsToday
): array {

    /*
     * Tidak punya siswa tetap perlu dicek,
     * karena berarti pembagian kelas/siswa
     * belum lengkap.
     */
    if ($students <= 0) {
        return [
            'label' => 'Perlu Dicek',
            'class' => 'danger',
        ];
    }


    if ($reportsToday >= $students) {
        return [
            'label' => 'Lengkap',
            'class' => 'success',
        ];
    }


    if ($reportsToday > 0) {
        return [
            'label' => 'Belum Lengkap',
            'class' => 'warning',
        ];
    }


    return [
        'label' => 'Perlu Dicek',
        'class' => 'danger',
    ];
}


/*
|--------------------------------------------------------------------------
| KPI GLOBAL
|--------------------------------------------------------------------------
*/

$todayReports = (int) $pdo
    ->query(
        "
            SELECT COUNT(*)
            FROM daily_student_reports
            WHERE tanggal = CURDATE()
        "
    )
    ->fetchColumn();


$weekReports = (int) $pdo
    ->query(
        "
            SELECT COUNT(*)
            FROM daily_student_reports
            WHERE tanggal >= DATE_SUB(
                CURDATE(),
                INTERVAL 6 DAY
            )
              AND tanggal <= CURDATE()
        "
    )
    ->fetchColumn();


$totalLevelExams = (int) $pdo
    ->query(
        "
            SELECT COUNT(*)
            FROM level_promotion_exams
        "
    )
    ->fetchColumn();


$totalMunaqosyah = (int) $pdo
    ->query(
        "
            SELECT COUNT(*)
            FROM munaqosyah_exams
        "
    )
    ->fetchColumn();


/*
|--------------------------------------------------------------------------
| DATA PER GURU
|--------------------------------------------------------------------------
|
| Masing-masing tabel di-aggregate dulu.
| Ini mencegah jumlah laporan/ujian berlipat akibat JOIN.
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    "
        SELECT

            u.id,
            u.full_name,
            u.username,

            COALESCE(
                c.classes,
                ''
            ) AS classes,

            COALESCE(
                s.students,
                0
            ) AS students,

            COALESCE(
                d.reports_today,
                0
            ) AS reports_today,

            COALESCE(
                d.reports_week,
                0
            ) AS reports_week,

            COALESCE(
                e.exams,
                0
            ) AS exams,

            COALESCE(
                m.munaqosyah,
                0
            ) AS munaqosyah

        FROM users u


        LEFT JOIN (

            SELECT

                teacher_id,

                GROUP_CONCAT(
                    DISTINCT nama_kelas
                    ORDER BY tingkat, rombel
                    SEPARATOR ', '
                ) AS classes

            FROM classes

            WHERE aktif = 1

            GROUP BY teacher_id

        ) c
            ON c.teacher_id = u.id


        LEFT JOIN (

            SELECT

                teacher_id,

                COUNT(*) AS students

            FROM students

            WHERE status = 'aktif'

            GROUP BY teacher_id

        ) s
            ON s.teacher_id = u.id


        LEFT JOIN (

            SELECT

                teacher_id,

                COUNT(
                    CASE
                        WHEN tanggal = CURDATE()
                        THEN 1
                    END
                ) AS reports_today,

                COUNT(
                    CASE
                        WHEN tanggal >= DATE_SUB(
                            CURDATE(),
                            INTERVAL 6 DAY
                        )
                        AND tanggal <= CURDATE()
                        THEN 1
                    END
                ) AS reports_week

            FROM daily_student_reports

            GROUP BY teacher_id

        ) d
            ON d.teacher_id = u.id


        LEFT JOIN (

            SELECT

                teacher_id,

                COUNT(*) AS exams

            FROM level_promotion_exams

            GROUP BY teacher_id

        ) e
            ON e.teacher_id = u.id


        LEFT JOIN (

            SELECT

                teacher_id,

                COUNT(*) AS munaqosyah

            FROM munaqosyah_exams

            GROUP BY teacher_id

        ) m
            ON m.teacher_id = u.id


        WHERE u.role = 'guru'
          AND u.is_active = 1
          AND u.approval_status = 'approved'

        ORDER BY
            u.full_name ASC
    "
);

$rows = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);


/*
|--------------------------------------------------------------------------
| HITUNG DATA TURUNAN
|--------------------------------------------------------------------------
*/

foreach ($rows as &$row) {

    $students =
        (int) $row['students'];

    $reportsToday =
        (int) $row['reports_today'];

    $reportsWeek =
        (int) $row['reports_week'];


    /*
     * Hari ini:
     * 1 laporan per siswa.
     */
    $row['today_percent'] =
        completeness_percent(
            $reportsToday,
            $students
        );


    /*
     * Target 7 hari:
     * jumlah siswa × 7.
     *
     * Contoh:
     * 1 siswa,
     * 1 laporan dalam 7 hari
     * = 14%.
     */
    $weekTarget =
        $students * 7;

    $row['week_percent'] =
        completeness_percent(
            $reportsWeek,
            $weekTarget
        );


    $row['status_data'] =
        completeness_status(
            $students,
            $reportsToday
        );
}

unset($row);


$pageTitle =
    'Kelengkapan Laporan';

require ROOT_PATH
    . '/includes/header.php';

?>


<style>

/* =========================================================
   KELENGKAPAN LAPORAN
   admin/completeness.php
   ========================================================= */

.completeness-page {
    --cl-green: #174f3d;
    --cl-green-dark: #113f30;
    --cl-emerald: #008c61;

    --cl-text: #101828;
    --cl-muted: #68768a;

    --cl-border: #e5e9e7;

    width: 100%;
}


/* =========================================================
   PAGE HEADER
   ========================================================= */

.cl-page-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;

    gap: 24px;

    margin-bottom: 25px;
}


.cl-eyebrow {
    margin: 0 0 8px;

    color: #009367;

    font-size: 10px;
    font-weight: 900;

    letter-spacing: .21em;

    text-transform: uppercase;
}


.cl-title {
    margin: 0;

    color: #07101c;

    font-size: clamp(
        30px,
        2.5vw,
        39px
    );

    font-weight: 900;

    line-height: 1.05;

    letter-spacing: -.045em;
}


.cl-description {
    margin: 10px 0 0;

    color: #627086;

    font-size: 12px;

    line-height: 1.55;
}


/* refresh */

.cl-refresh {
    flex: 0 0 auto;

    min-height: 37px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 7px;

    padding: 8px 14px;

    border: 1px solid #dce3df;
    border-radius: 11px;

    background: #fff;

    color: #667085;

    font-size: 10px;
    font-weight: 800;

    text-decoration: none;

    box-shadow:
        0 2px 5px
        rgba(16, 24, 40, .05);
}


.cl-refresh svg {
    color: #00a875;
}


.cl-refresh:hover {
    border-color: #bed6ca;

    color: var(--cl-green);

    background: #fff;
}


/* =========================================================
   MAIN PANEL
   ========================================================= */

.cl-panel {
    overflow: hidden;

    border: 1px solid #e2e7e4;
    border-radius: 21px;

    background: #fff;

    box-shadow:
        0 2px 6px
        rgba(16, 24, 40, .04);
}


/* =========================================================
   KPI
   ========================================================= */

.cl-stats {
    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 12px;

    padding: 17px 18px 18px;
}


.cl-stat {
    position: relative;

    min-height: 112px;

    padding: 19px 20px;

    overflow: hidden;

    border: 1px solid #e4e9e6;
    border-radius: 16px;

    background: #fff;

    box-shadow:
        0 2px 4px
        rgba(16, 24, 40, .035);
}


.cl-stat:hover {
    border-color: #d3ddd7;

    box-shadow:
        0 5px 13px
        rgba(16, 24, 40, .055);
}


.cl-stat-label {
    display: block;

    margin-bottom: 9px;

    color: #98a2b3;

    font-size: 9px;
    font-weight: 900;

    letter-spacing: .03em;

    text-transform: uppercase;
}


.cl-stat-value {
    display: block;

    color: #0b1524;

    font-size: 27px;
    font-weight: 900;

    line-height: 1;

    letter-spacing: -.04em;
}


.cl-stat-description {
    display: block;

    margin-top: 11px;

    color: #627188;

    font-size: 9.5px;
}


.cl-stat-icon {
    position: absolute;

    top: 18px;
    right: 18px;

    width: 42px;
    height: 42px;

    display: grid;
    place-items: center;

    border-radius: 12px;
}


.cl-stat-icon svg {
    width: 20px;
    height: 20px;
}


/* icon colors */

.cl-stat-icon.green {
    background: #eafbf3;

    color: #008b61;
}


.cl-stat-icon.blue {
    background: #eef5ff;

    color: #2159e8;
}


.cl-stat-icon.orange {
    background: #fff8e8;

    color: #d96d08;
}


.cl-stat-icon.book {
    background: #eef5ff;

    color: #2059e7;
}


/* =========================================================
   TABLE
   ========================================================= */

.cl-table-scroll {
    width: 100%;

    overflow-x: auto;

    border-top: 1px solid var(--cl-border);
}


.cl-table {
    width: 100%;

    min-width: 1060px;

    border-collapse: collapse;

    table-layout: fixed;
}


/* column sizes */

.cl-table th:nth-child(1),
.cl-table td:nth-child(1) {
    width: 30%;
}


.cl-table th:nth-child(2),
.cl-table td:nth-child(2) {
    width: 18%;
}


.cl-table th:nth-child(3),
.cl-table td:nth-child(3) {
    width: 12%;
}


.cl-table th:nth-child(4),
.cl-table td:nth-child(4) {
    width: 13%;
}


.cl-table th:nth-child(5),
.cl-table td:nth-child(5) {
    width: 14%;
}


.cl-table th:nth-child(6),
.cl-table td:nth-child(6) {
    width: 13%;
}


/* header */

.cl-table thead th {
    height: 45px;

    padding: 11px 18px;

    background: var(--cl-green);

    color: #fff;

    font-size: 9px;
    font-weight: 900;

    text-align: left;

    text-transform: uppercase;

    white-space: nowrap;
}


/* body */

.cl-table tbody td {
    height: 63px;

    padding: 12px 18px;

    border-bottom:
        1px solid #e9edeb;

    vertical-align: middle;

    color: #334155;

    font-size: 10.5px;
}


.cl-table tbody tr:last-child td {
    border-bottom: 0;
}


.cl-table tbody tr:hover td {
    background: #fbfcfb;
}


/* =========================================================
   TEACHER
   ========================================================= */

.cl-teacher-name {
    display: block;

    overflow: hidden;

    color: #101828;

    font-size: 11.5px;
    font-weight: 900;

    text-overflow: ellipsis;
    white-space: nowrap;
}


.cl-teacher-class {
    display: block;

    max-width: 410px;

    margin-top: 5px;

    overflow: hidden;

    color: #8290a2;

    font-size: 9px;

    text-overflow: ellipsis;
    white-space: nowrap;
}


/* =========================================================
   TODAY REPORT
   ========================================================= */

.cl-today-value {
    display: block;

    color: #101828;

    font-size: 12px;
    font-weight: 900;
}


.cl-progress {
    width: 120px;
    max-width: 100%;

    height: 7px;

    margin-top: 8px;

    overflow: hidden;

    border-radius: 999px;

    background: #f0f2f4;
}


.cl-progress span {
    display: block;

    height: 100%;

    border-radius: inherit;

    background:
        linear-gradient(
            90deg,
            #0da86f,
            #39c78a
        );
}


/* =========================================================
   WEEK
   ========================================================= */

.cl-week {
    display: block;

    color: #101828;

    font-size: 12px;
    font-weight: 900;
}


.cl-week-meta {
    display: block;

    margin-top: 4px;

    color: #8a96a7;

    font-size: 8.5px;
}


/* =========================================================
   EXAM COUNTS
   ========================================================= */

.cl-count {
    color: #111827;

    font-size: 12px;
    font-weight: 900;
}


/* =========================================================
   STATUS
   ========================================================= */

.cl-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 23px;

    padding: 4px 9px;

    border-radius: 999px;

    font-size: 8.5px;
    font-weight: 900;

    white-space: nowrap;
}


.cl-status.danger {
    border: 1px solid #ffb3b7;

    background: #fff1f1;

    color: #d92d20;
}


.cl-status.warning {
    border: 1px solid #ffd36e;

    background: #fff9e8;

    color: #c85e00;
}


.cl-status.success {
    border: 1px solid #9ae5ba;

    background: #effff6;

    color: #00864f;
}


/* =========================================================
   EMPTY
   ========================================================= */

.cl-empty {
    padding: 48px 20px;

    text-align: center;

    color: #788592;
}


.cl-empty strong {
    display: block;

    margin-bottom: 6px;

    color: #344054;

    font-size: 14px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 1100px) {

    .cl-stats {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

}


@media (max-width: 820px) {

    .cl-page-head {
        align-items: flex-start;

        flex-direction: column;
    }

}


@media (max-width: 540px) {

    .cl-stats {
        grid-template-columns: 1fr;
    }


    .cl-title {
        font-size: 28px;
    }


    .cl-stat {
        min-height: 105px;
    }

}

</style>



<div class="completeness-page">


    <!-- =====================================================
         HEADER
         ===================================================== -->

    <header class="cl-page-head">

        <div>

            <p class="cl-eyebrow">
                Kontrol Pelaporan
            </p>

            <h1 class="cl-title">
                Kelengkapan Laporan
            </h1>

            <p class="cl-description">
                Bandingkan laporan harian, ujian level,
                dan Munaqosyah setiap Guru.
            </p>

        </div>


        <a
            href="<?= e(
                url(
                    'admin/completeness.php'
                )
            ) ?>"
            class="cl-refresh"
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
                <circle
                    cx="12"
                    cy="12"
                    r="9"
                ></circle>

                <path
                    d="M12 7v5l3 2"
                ></path>
            </svg>

            Perbarui · Baru saja

        </a>

    </header>



    <!-- =====================================================
         PANEL
         ===================================================== -->

    <section class="cl-panel">


        <!-- =================================================
             KPI
             ================================================= -->

        <div class="cl-stats">


            <!-- LAPORAN HARI INI -->

            <article class="cl-stat">

                <span class="cl-stat-label">
                    Laporan Hari Ini
                </span>

                <strong class="cl-stat-value">

                    <?= number_format(
                        $todayReports,
                        0,
                        ',',
                        '.'
                    ) ?>

                </strong>

                <span class="cl-stat-description">
                    Seluruh Guru
                </span>


                <span class="cl-stat-icon green">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.9"
                    >
                        <rect
                            x="5"
                            y="4"
                            width="14"
                            height="17"
                            rx="2"
                        ></rect>

                        <path
                            d="M9 4V2h6v2"
                        ></path>

                        <path
                            d="m9 12 2 2 4-4"
                        ></path>
                    </svg>

                </span>

            </article>



            <!-- 7 HARI -->

            <article class="cl-stat">

                <span class="cl-stat-label">
                    7 Hari
                </span>

                <strong class="cl-stat-value">

                    <?= number_format(
                        $weekReports,
                        0,
                        ',',
                        '.'
                    ) ?>

                </strong>

                <span class="cl-stat-description">
                    Laporan tersimpan
                </span>


                <span class="cl-stat-icon blue">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.9"
                    >
                        <rect
                            x="3"
                            y="5"
                            width="18"
                            height="16"
                            rx="2"
                        ></rect>

                        <path d="M7 3v4"></path>
                        <path d="M17 3v4"></path>

                        <path d="M3 10h18"></path>

                        <path d="M8 14h3"></path>
                        <path d="M13 14h3"></path>
                        <path d="M8 17h3"></path>
                        <path d="M13 17h3"></path>
                    </svg>

                </span>

            </article>



            <!-- LEVEL -->

            <article class="cl-stat">

                <span class="cl-stat-label">
                    Ujian Level
                </span>

                <strong class="cl-stat-value">

                    <?= number_format(
                        $totalLevelExams,
                        0,
                        ',',
                        '.'
                    ) ?>

                </strong>

                <span class="cl-stat-description">
                    Total ujian
                </span>


                <span class="cl-stat-icon orange">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.9"
                    >
                        <path
                            d="
                                m3 9
                                9-5
                                9 5
                                -9 5
                                -9-5Z
                            "
                        ></path>

                        <path
                            d="
                                M7 12v4
                                c3 2
                                7 2
                                10 0
                                v-4
                            "
                        ></path>
                    </svg>

                </span>

            </article>



            <!-- MUNAQOSYAH -->

            <article class="cl-stat">

                <span class="cl-stat-label">
                    Munaqosyah
                </span>

                <strong class="cl-stat-value">

                    <?= number_format(
                        $totalMunaqosyah,
                        0,
                        ',',
                        '.'
                    ) ?>

                </strong>

                <span class="cl-stat-description">
                    Total ujian
                </span>


                <span class="cl-stat-icon book">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.9"
                    >
                        <path
                            d="
                                M4 5
                                c3-1
                                5 0
                                8 2
                                v13
                                c-3-2
                                -5-3
                                -8-2
                                V5Z
                            "
                        ></path>

                        <path
                            d="
                                M20 5
                                c-3-1
                                -5 0
                                -8 2
                                v13
                                c3-2
                                5-3
                                8-2
                                V5Z
                            "
                        ></path>
                    </svg>

                </span>

            </article>

        </div>



        <!-- =================================================
             TABLE
             ================================================= -->

        <div class="cl-table-scroll">

            <table class="cl-table">

                <thead>

                    <tr>

                        <th>Guru</th>

                        <th>
                            Laporan Hari Ini
                        </th>

                        <th>
                            Minggu Ini
                        </th>

                        <th>
                            Ujian Level
                        </th>

                        <th>
                            Munaqosyah
                        </th>

                        <th>
                            Status
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach (
                    $rows
                    as $row
                ): ?>

                    <?php

                    $students =
                        (int) $row['students'];

                    $reportsToday =
                        (int) $row['reports_today'];

                    $reportsWeek =
                        (int) $row['reports_week'];

                    $todayPercent =
                        (int) $row['today_percent'];

                    $weekPercent =
                        (int) $row['week_percent'];

                    $status =
                        $row['status_data'];

                    $classes = trim(
                        (string) $row['classes']
                    );

                    if ($classes === '') {
                        $classes =
                            'Belum ada kelas';
                    }

                    ?>


                    <tr>


                        <!-- GURU -->

                        <td>

                            <strong
                                class="
                                    cl-teacher-name
                                "
                            >

                                <?= e(
                                    $row[
                                        'full_name'
                                    ]
                                ) ?>

                            </strong>


                            <span
                                class="
                                    cl-teacher-class
                                "
                            >

                                <?= e($classes) ?>

                            </span>

                        </td>



                        <!-- HARI INI -->

                        <td>

                            <strong
                                class="
                                    cl-today-value
                                "
                            >

                                <?= $reportsToday ?>
                                /
                                <?= $students ?>

                            </strong>


                            <div
                                class="cl-progress"
                                title="
                                    <?= $todayPercent ?>%
                                "
                            >

                                <span
                                    style="
                                        width:
                                        <?= $todayPercent ?>%;
                                    "
                                ></span>

                            </div>

                        </td>



                        <!-- WEEK -->

                        <td>

                            <strong class="cl-week">

                                <?= $weekPercent ?>%

                            </strong>


                            <?php if (
                                $reportsWeek > 0
                            ): ?>

                                <span
                                    class="
                                        cl-week-meta
                                    "
                                >

                                    <?= $reportsWeek ?>
                                    laporan

                                </span>

                            <?php endif; ?>

                        </td>



                        <!-- EXAM -->

                        <td>

                            <strong class="cl-count">

                                <?= (int) (
                                    $row['exams']
                                ) ?>

                            </strong>

                        </td>



                        <!-- MUNAQOSYAH -->

                        <td>

                            <strong class="cl-count">

                                <?= (int) (
                                    $row[
                                        'munaqosyah'
                                    ]
                                ) ?>

                            </strong>

                        </td>



                        <!-- STATUS -->

                        <td>

                            <span
                                class="
                                    cl-status
                                    <?= e(
                                        $status[
                                            'class'
                                        ]
                                    ) ?>
                                "
                            >

                                <?= e(
                                    $status[
                                        'label'
                                    ]
                                ) ?>

                            </span>

                        </td>

                    </tr>

                <?php endforeach; ?>



                <?php if (!$rows): ?>

                    <tr>

                        <td colspan="6">

                            <div class="cl-empty">

                                <strong>
                                    Belum ada Guru aktif
                                </strong>

                                Data kelengkapan laporan
                                akan tampil setelah akun
                                Guru tersedia.

                            </div>

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</div>


<?php

require ROOT_PATH
    . '/includes/footer.php';

?>