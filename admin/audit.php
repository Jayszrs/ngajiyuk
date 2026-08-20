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

function audit_activity_label(string $type): string
{
    return match ($type) {

        'login'
            => 'Login akun',

        'logout'
            => 'Logout akun',

        'password_changed'
            => 'Password berhasil diganti',

        'password_reset_requested'
            => 'Permintaan reset password',

        'daily_report_saved'
            => 'Laporan harian disimpan',

        'daily_report_updated'
            => 'Laporan harian diperbarui',

        'tahsin_score_saved'
            => 'Nilai harian disimpan',

        'level_exam_saved'
            => 'Hasil ujian level disimpan',

        'munaqosyah_saved'
            => 'Hasil Munaqosyah disimpan',

        'account_created'
            => 'Akun dibuat',

        'account_deleted'
            => 'Akun dihapus',

        'account_approved'
            => 'Akun disetujui',

        'account_rejected'
            => 'Akun ditolak',

        'role_changed'
            => 'Role akun diubah',

        'student_created'
            => 'Siswa ditambahkan',

        'student_updated'
            => 'Data siswa diperbarui',

        'student_deleted'
            => 'Siswa dihapus',

        'students_imported'
            => 'Data siswa diimpor',

        'parent_student_linked'
            => 'Orang Tua dihubungkan ke anak',

        'parent_student_unlinked'
            => 'Hubungan Orang Tua diputus',

        'class_teacher_assigned'
            => 'Wali kelas mengaji diperbarui',

        'curriculum_saved'
            => 'Kurikulum surat diperbarui',

        default
            => ucwords(
                str_replace(
                    '_',
                    ' ',
                    $type
                )
            ),
    };
}


function audit_status_label(
    string $status
): string {
    return match ($status) {

        'success'
            => 'Berhasil',

        'blocked'
            => 'Diblokir',

        'failed'
            => 'Gagal',

        default
            => ucfirst($status),
    };
}


function audit_status_class(
    string $status
): string {
    return match ($status) {

        'success'
            => 'success',

        'blocked'
            => 'blocked',

        default
            => 'failed',
    };
}


function audit_format_date(
    ?string $datetime
): string {

    if (!$datetime) {
        return '-';
    }

    $timestamp = strtotime(
        $datetime
    );

    if (!$timestamp) {
        return '-';
    }


    $months = [
        1 => 'Jan',
        'Feb',
        'Mar',
        'Apr',
        'Mei',
        'Jun',
        'Jul',
        'Agu',
        'Sep',
        'Okt',
        'Nov',
        'Des',
    ];


    $day = (int) date(
        'j',
        $timestamp
    );

    $month = $months[
        (int) date(
            'n',
            $timestamp
        )
    ];

    $year = date(
        'Y',
        $timestamp
    );

    $time = date(
        'H.i',
        $timestamp
    );


    return
        $day
        . ' '
        . $month
        . ' '
        . $year
        . ', '
        . $time;
}


function audit_detail_value(
    array $details,
    array $keys
): mixed {

    foreach ($keys as $key) {

        if (
            array_key_exists(
                $key,
                $details
            )
            && $details[$key] !== ''
            && $details[$key] !== null
        ) {
            return $details[$key];
        }
    }

    return null;
}


function audit_target_text(
    array $event,
    array $details
): string {

    /*
     * Nama target dari akun.
     */
    $targetName = trim(
        (string) (
            $event[
                'target_name'
            ]
            ?? ''
        )
    );


    /*
     * Nama siswa dari JSON audit.
     */
    $studentName = trim(
        (string) (
            audit_detail_value(
                $details,
                [
                    'student',
                    'student_name',
                    'nama_siswa',
                ]
            )
            ?? ''
        )
    );


    $type = (string) (
        $event['event_type']
        ?? ''
    );


    if (
        $type === 'level_exam_saved'
        && $studentName !== ''
    ) {
        return
            'Ujian level '
            . $studentName;
    }


    if (
        $type === 'munaqosyah_saved'
        && $studentName !== ''
    ) {
        return
            'Munaqosyah '
            . $studentName;
    }


    if (
        in_array(
            $type,
            [
                'daily_report_saved',
                'daily_report_updated',
            ],
            true
        )
        && $studentName !== ''
    ) {

        $date = trim(
            (string) (
                audit_detail_value(
                    $details,
                    [
                        'date',
                        'tanggal',
                    ]
                )
                ?? ''
            )
        );


        if ($date !== '') {
            return
                'Laporan '
                . $studentName
                . ' · '
                . audit_format_short_date(
                    $date
                );
        }


        return
            'Laporan '
            . $studentName;
    }


    if (
        $type === 'tahsin_score_saved'
        && $studentName !== ''
    ) {

        $surah = trim(
            (string) (
                audit_detail_value(
                    $details,
                    [
                        'surah',
                        'nama_surah',
                    ]
                )
                ?? ''
            )
        );


        if ($surah !== '') {
            return
                'Nilai '
                . $surah
                . ' · '
                . $studentName;
        }


        return
            'Nilai '
            . $studentName;
    }


    if ($targetName !== '') {
        return $targetName;
    }


    if ($studentName !== '') {
        return $studentName;
    }


    $target = trim(
        (string) (
            audit_detail_value(
                $details,
                [
                    'target',
                    'target_name',
                    'username',
                    'class',
                    'kelas',
                ]
            )
            ?? ''
        )
    );


    return $target !== ''
        ? $target
        : '-';
}


function audit_format_short_date(
    string $date
): string {

    $timestamp = strtotime(
        $date
    );

    if (!$timestamp) {
        return $date;
    }


    $months = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember',
    ];


    return
        (int) date(
            'j',
            $timestamp
        )
        . ' '
        . $months[
            (int) date(
                'n',
                $timestamp
            )
        ]
        . ' '
        . date(
            'Y',
            $timestamp
        );
}


function audit_detail_text(
    array $event,
    array $details
): string {

    $type = (string) (
        $event['event_type']
        ?? ''
    );


    /*
     * =====================================================
     * UJIAN LEVEL
     * =====================================================
     */

    if (
        $type === 'level_exam_saved'
    ) {

        $from = audit_detail_value(
            $details,
            [
                'level_asal',
                'from_level',
                'level_from',
            ]
        );


        $to = audit_detail_value(
            $details,
            [
                'level_tujuan',
                'to_level',
                'level_to',
            ]
        );


        $average = audit_detail_value(
            $details,
            [
                'average',
                'nilai_rata_rata',
                'score',
                'nilai',
            ]
        );


        $result = audit_detail_value(
            $details,
            [
                'result',
                'status',
                'hasil',
            ]
        );


        $parts = [
            'Guru menyimpan ujian',
        ];


        if (
            $from !== null
            && $to !== null
        ) {
            $parts[] =
                'Level '
                . $from
                . ' → Level '
                . $to;
        }


        if ($average !== null) {
            $parts[] =
                'dengan nilai rata-rata '
                . $average;
        }


        if ($result !== null) {
            $parts[] =
                'dan hasil '
                . $result;
        }


        return
            implode(
                ' ',
                $parts
            )
            . '.';
    }


    /*
     * =====================================================
     * LAPORAN HARIAN
     * =====================================================
     */

    if (
        in_array(
            $type,
            [
                'daily_report_saved',
                'daily_report_updated',
            ],
            true
        )
    ) {

        $date = audit_detail_value(
            $details,
            [
                'date',
                'tanggal',
            ]
        );


        $presence = audit_detail_value(
            $details,
            [
                'status_presensi',
                'presensi',
                'attendance',
            ]
        );


        $activity = audit_detail_value(
            $details,
            [
                'kegiatan',
                'activity',
            ]
        );


        $parts = [];


        $parts[] =
            $type === 'daily_report_updated'
                ? 'Guru berhasil memperbarui laporan'
                : 'Guru menyimpan laporan harian';


        if ($date) {
            $parts[] =
                'tanggal '
                . audit_format_short_date(
                    (string) $date
                );
        }


        if ($presence) {
            $parts[] =
                'dengan presensi '
                . $presence;
        }


        if ($activity) {
            $parts[] =
                'dan kegiatan “'
                . $activity
                . '”';
        }


        return
            implode(
                ' ',
                $parts
            )
            . '.';
    }


    /*
     * =====================================================
     * NILAI
     * =====================================================
     */

    if (
        $type === 'tahsin_score_saved'
    ) {

        $surah = audit_detail_value(
            $details,
            [
                'surah',
                'nama_surah',
            ]
        );


        $score = audit_detail_value(
            $details,
            [
                'nilai',
                'score',
                'nilai_rata_rata',
            ]
        );


        $date = audit_detail_value(
            $details,
            [
                'tanggal',
                'date',
            ]
        );


        $note = audit_detail_value(
            $details,
            [
                'keterangan',
                'note',
            ]
        );


        $text =
            'Guru menyimpan nilai';


        if ($surah) {
            $text .=
                ' '
                . $surah;
        }


        if ($score !== null) {
            $text .=
                ' sebesar '
                . $score;
        }


        if ($date) {
            $text .=
                ' pada '
                . audit_format_short_date(
                    (string) $date
                );
        }


        if ($note) {
            $text .=
                ' dengan keterangan “'
                . $note
                . '”';
        }


        return $text . '.';
    }


    /*
     * =====================================================
     * MUNAQOSYAH
     * =====================================================
     */

    if (
        $type === 'munaqosyah_saved'
    ) {

        $juz = audit_detail_value(
            $details,
            [
                'juz',
                'level',
                'jenjang',
            ]
        );


        $average = audit_detail_value(
            $details,
            [
                'average',
                'nilai_rata_rata',
                'score',
                'nilai',
            ]
        );


        $predicate = audit_detail_value(
            $details,
            [
                'predicate',
                'predikat',
                'result',
            ]
        );


        $text =
            'Guru menyimpan hasil Munaqosyah';


        if ($juz) {
            $text .=
                ' '
                . $juz;
        }


        if ($average !== null) {
            $text .=
                ', nilai rata-rata '
                . $average;
        }


        if ($predicate) {
            $text .=
                ', predikat '
                . $predicate;
        }


        return $text . '.';
    }


    /*
     * =====================================================
     * GENERIC FALLBACK
     * =====================================================
     */

    if (!$details) {
        return
            'Tidak ada rincian tambahan.';
    }


    $ignoredKeys = [
        'password',
        'password_hash',
        'token',
        'token_hash',
        'csrf',
        'csrf_token',
    ];


    $parts = [];


    foreach (
        $details
        as $key => $value
    ) {

        if (
            in_array(
                strtolower(
                    (string) $key
                ),
                $ignoredKeys,
                true
            )
        ) {
            continue;
        }


        if (
            !is_scalar($value)
            || $value === ''
            || $value === null
        ) {
            continue;
        }


        $label = ucwords(
            str_replace(
                '_',
                ' ',
                (string) $key
            )
        );


        $parts[] =
            $label
            . ': '
            . $value;


        if (
            count($parts) >= 4
        ) {
            break;
        }
    }


    return $parts
        ? implode(
            ' · ',
            $parts
        )
        : 'Tidak ada rincian tambahan.';
}


/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

$status = trim(
    (string) (
        $_GET['status']
        ?? ''
    )
);


$date = trim(
    (string) (
        $_GET['date']
        ?? ''
    )
);


$search = trim(
    (string) (
        $_GET['q']
        ?? ''
    )
);


$allowedStatuses = [
    '',
    'success',
    'failed',
    'blocked',
];


if (
    !in_array(
        $status,
        $allowedStatuses,
        true
    )
) {
    $status = '';
}


/*
|--------------------------------------------------------------------------
| QUERY
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        e.*,

        a.username
            AS actor_username,

        a.full_name
            AS actor_name,

        t.username
            AS target_username,

        t.full_name
            AS target_name

    FROM account_security_events e

    LEFT JOIN users a
        ON a.id = e.actor_user_id

    LEFT JOIN users t
        ON t.id = e.target_user_id

    WHERE 1 = 1
";


$params = [];


/*
 * Status
 */
if ($status !== '') {

    $sql .= "
        AND e.status = ?
    ";

    $params[] = $status;
}


/*
 * Tanggal
 */
if ($date !== '') {

    $sql .= "
        AND DATE(
            e.created_at
        ) = ?
    ";

    $params[] = $date;
}


/*
 * Search
 */
if ($search !== '') {

    $needle =
        '%'
        . $search
        . '%';


    $sql .= "
        AND (
            e.event_type LIKE ?

            OR a.username LIKE ?

            OR a.full_name LIKE ?

            OR t.username LIKE ?

            OR t.full_name LIKE ?

            OR e.details LIKE ?
        )
    ";


    array_push(
        $params,
        $needle,
        $needle,
        $needle,
        $needle,
        $needle,
        $needle
    );
}


$sql .= "
    ORDER BY
        e.created_at DESC,
        e.id DESC

    LIMIT 500
";


$stmt = $pdo->prepare(
    $sql
);

$stmt->execute(
    $params
);


$events = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);


$pageTitle =
    'Audit Aktivitas';


require ROOT_PATH
    . '/includes/header.php';

?>


<style>

/* =========================================================
   AUDIT AKTIVITAS
   admin/audit.php
   ========================================================= */

.audit-page {
    --au-green: #174f3d;
    --au-green-dark: #103e30;
    --au-green-text: #00754f;

    --au-text: #101828;
    --au-muted: #667085;

    --au-line: #e7ebe9;
    --au-soft: #f8f9fa;

    width: 100%;
}


/* =========================================================
   PAGE HEADER
   ========================================================= */

.au-page-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;

    gap: 24px;

    margin-bottom: 25px;
}


.au-eyebrow {
    margin: 0 0 8px;

    color: #009367;

    font-size: 10px;
    font-weight: 900;

    letter-spacing: .21em;

    text-transform: uppercase;
}


.au-title {
    margin: 0;

    color: #07101d;

    font-size: clamp(
        30px,
        2.5vw,
        39px
    );

    font-weight: 900;

    line-height: 1.05;

    letter-spacing: -.045em;
}


.au-description {
    margin: 10px 0 0;

    color: #627086;

    font-size: 12px;

    line-height: 1.55;
}


/* update */

.au-update {
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


.au-update svg {
    color: #00a875;
}


.au-update:hover {
    border-color: #bcd6c8;

    color: var(--au-green);
}


/* =========================================================
   PANEL
   ========================================================= */

.au-panel {
    overflow: hidden;

    border: 1px solid #e2e7e4;
    border-radius: 20px;

    background: #fff;

    box-shadow:
        0 2px 6px
        rgba(16, 24, 40, .04);
}


/* =========================================================
   FILTER
   ========================================================= */

.au-filter {
    min-height: 78px;

    padding: 17px 18px;

    display: grid;

    grid-template-columns:
        minmax(280px, 1fr)
        165px
        165px;

    gap: 12px;

    align-items: center;

    border-bottom:
        1px solid var(--au-line);
}


/* search */

.au-search {
    position: relative;
}


.au-search svg {
    position: absolute;

    top: 50%;
    left: 14px;

    width: 17px;
    height: 17px;

    color: #98a2b3;

    pointer-events: none;

    transform:
        translateY(-50%);
}


.au-search input {
    width: 100%;
    height: 42px;

    padding:
        9px
        13px
        9px
        42px;

    border: 1px solid #dce2df;
    border-radius: 11px;

    outline: 0;

    background: #fff;

    color: #344054;

    font-family: inherit;

    font-size: 11px;
    font-weight: 600;
}


.au-search input::placeholder {
    color: #8491a0;
}


.au-search input:hover {
    border-color: #c8d0cc;
}


.au-search input:focus {
    border-color: #54a486;

    box-shadow:
        0 0 0 3px
        rgba(0, 143, 98, .08);
}


/* status/date */

.au-filter-select,
.au-filter-date {
    width: 100%;
    height: 42px;

    padding: 8px 12px;

    border: 1px solid #dce2df;
    border-radius: 11px;

    outline: 0;

    background: #fff;

    color: #101828;

    font-family: inherit;

    font-size: 10.5px;
    font-weight: 800;
}


.au-filter-select:hover,
.au-filter-date:hover {
    border-color: #c7d0cb;
}


.au-filter-select:focus,
.au-filter-date:focus {
    border-color: #54a486;

    box-shadow:
        0 0 0 3px
        rgba(0, 143, 98, .08);
}


/* =========================================================
   TABLE
   ========================================================= */

.au-table-scroll {
    width: 100%;

    overflow-x: auto;
}


.au-table {
    width: 100%;

    min-width: 1250px;

    border-collapse: collapse;

    table-layout: fixed;
}


/* widths */

.au-table th:nth-child(1),
.au-table td:nth-child(1) {
    width: 12%;
}


.au-table th:nth-child(2),
.au-table td:nth-child(2) {
    width: 17%;
}


.au-table th:nth-child(3),
.au-table td:nth-child(3) {
    width: 12%;
}


.au-table th:nth-child(4),
.au-table td:nth-child(4) {
    width: 23%;
}


.au-table th:nth-child(5),
.au-table td:nth-child(5) {
    width: 26%;
}


.au-table th:nth-child(6),
.au-table td:nth-child(6) {
    width: 10%;
}


/* table header */

.au-table thead th {
    height: 45px;

    padding: 11px 17px;

    border-bottom:
        1px solid var(--au-line);

    background: #f8f9fa;

    color: #536176;

    font-size: 8.5px;
    font-weight: 900;

    text-align: left;

    text-transform: uppercase;

    white-space: nowrap;
}


/* rows */

.au-table tbody td {
    min-height: 66px;

    padding: 14px 17px;

    border-bottom:
        1px solid #e8ecea;

    vertical-align: middle;

    color: #344054;

    font-size: 9.5px;

    line-height: 1.55;
}


.au-table tbody tr:last-child td {
    border-bottom: 0;
}


.au-table tbody tr:hover td {
    background: #fbfcfb;
}


/* =========================================================
   WAKTU
   ========================================================= */

.au-time {
    color: #43536a;

    font-size: 10px;
    font-weight: 750;

    white-space: nowrap;
}


/* =========================================================
   ACTIVITY
   ========================================================= */

.au-activity-name {
    display: block;

    color: #101828;

    font-size: 10.5px;
    font-weight: 900;

    line-height: 1.4;
}


/* =========================================================
   ACTOR
   ========================================================= */

.au-actor {
    display: block;

    color: #00754f;

    font-size: 10.5px;
    font-weight: 900;

    text-decoration: none;
}


.au-actor-name {
    display: block;

    margin-top: 4px;

    color: #8995a5;

    font-size: 8.5px;
}


/* =========================================================
   TARGET
   ========================================================= */

.au-target {
    display: block;

    color: #25344b;

    font-size: 10px;
    font-weight: 750;

    line-height: 1.45;
}


/* =========================================================
   DETAIL
   ========================================================= */

.au-detail {
    display: block;

    color: #415069;

    font-size: 9.5px;

    line-height: 1.65;
}


/* =========================================================
   STATUS
   ========================================================= */

.au-status {
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


.au-status.success {
    border: 1px solid #98e7ba;

    background: #effff6;

    color: #00864f;
}


.au-status.failed {
    border: 1px solid #ffb7bb;

    background: #fff1f1;

    color: #d92d20;
}


.au-status.blocked {
    border: 1px solid #ffd36e;

    background: #fff9e8;

    color: #bd5d00;
}


/* =========================================================
   EMPTY
   ========================================================= */

.au-empty {
    padding: 55px 20px;

    text-align: center;

    color: #7e8996;
}


.au-empty-icon {
    width: 43px;
    height: 43px;

    margin:
        0 auto
        12px;

    display: grid;
    place-items: center;

    border-radius: 13px;

    background: #f2f5f3;

    color: #6c7a72;
}


.au-empty strong {
    display: block;

    margin-bottom: 6px;

    color: #344054;

    font-size: 13px;
}


/* =========================================================
   RESULTS FOOTER
   ========================================================= */

.au-result-footer {
    min-height: 48px;

    padding: 11px 17px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 15px;

    border-top: 1px solid var(--au-line);

    background: #fafbfa;

    color: #818d99;

    font-size: 9px;
}


.au-result-count {
    color: #516157;

    font-weight: 800;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 900px) {

    .au-filter {
        grid-template-columns:
            1fr 1fr;
    }


    .au-search {
        grid-column:
            1 / -1;
    }

}


@media (max-width: 820px) {

    .au-page-head {
        align-items: flex-start;

        flex-direction: column;
    }

}


@media (max-width: 560px) {

    .au-title {
        font-size: 28px;
    }


    .au-filter {
        grid-template-columns: 1fr;
    }


    .au-search {
        grid-column: auto;
    }

}

</style>



<div class="audit-page">


    <!-- =====================================================
         HEADER
         ===================================================== -->

    <header class="au-page-head">

        <div>

            <p class="au-eyebrow">
                Keamanan Sistem
            </p>

            <h1 class="au-title">
                Audit Aktivitas
            </h1>

            <p class="au-description">
                Telusuri perubahan akun, role, password,
                relasi siswa, kurikulum, dan data akademik.
            </p>

        </div>


        <a
            class="au-update"
            href="<?= e(
                url(
                    'admin/audit.php'
                )
            ) ?>"
        >

            <svg
                width="16"
                height="16"
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

    <section class="au-panel">


        <!-- =================================================
             FILTER
             ================================================= -->

        <form
            method="get"
            action="<?= e(
                url(
                    'admin/audit.php'
                )
            ) ?>"
            class="au-filter"
            id="audit-filter-form"
        >


            <!-- SEARCH -->

            <label
                class="au-search"
                for="audit-search"
            >

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
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
                    id="audit-search"
                    name="q"
                    value="<?= e($search) ?>"
                    placeholder="Cari aktivitas atau pengguna..."
                    autocomplete="off"
                >

            </label>



            <!-- STATUS -->

            <select
                class="au-filter-select"
                name="status"
                id="audit-status"
            >

                <option value="">
                    Semua Status
                </option>


                <option
                    value="success"
                    <?= $status === 'success'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Berhasil
                </option>


                <option
                    value="failed"
                    <?= $status === 'failed'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Gagal
                </option>


                <option
                    value="blocked"
                    <?= $status === 'blocked'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Diblokir
                </option>

            </select>



            <!-- DATE -->

            <input
                class="au-filter-date"
                type="date"
                name="date"
                id="audit-date"
                value="<?= e($date) ?>"
            >

        </form>



        <!-- =================================================
             TABLE
             ================================================= -->

        <div class="au-table-scroll">

            <table class="au-table">

                <thead>

                    <tr>

                        <th>Waktu</th>

                        <th>Aktivitas</th>

                        <th>
                            Pelaku (Username)
                        </th>

                        <th>
                            Target Aktivitas
                        </th>

                        <th>Rincian</th>

                        <th>Status</th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach (
                    $events
                    as $event
                ): ?>

                    <?php

                    $details = json_decode(
                        (string) (
                            $event['details']
                            ?? '{}'
                        ),
                        true
                    );


                    if (
                        !is_array($details)
                    ) {
                        $details = [];
                    }


                    $actorUsername = trim(
                        (string) (
                            $event[
                                'actor_username'
                            ]
                            ?? ''
                        )
                    );


                    $actorName = trim(
                        (string) (
                            $event[
                                'actor_name'
                            ]
                            ?? ''
                        )
                    );


                    $eventStatus =
                        (string) (
                            $event['status']
                            ?? 'failed'
                        );

                    ?>


                    <tr>


                        <!-- WAKTU -->

                        <td>

                            <span class="au-time">

                                <?= e(
                                    audit_format_date(
                                        $event[
                                            'created_at'
                                        ]
                                        ?? null
                                    )
                                ) ?>

                            </span>

                        </td>



                        <!-- AKTIVITAS -->

                        <td>

                            <strong
                                class="
                                    au-activity-name
                                "
                            >

                                <?= e(
                                    audit_activity_label(
                                        (string) (
                                            $event[
                                                'event_type'
                                            ]
                                            ?? ''
                                        )
                                    )
                                ) ?>

                            </strong>

                        </td>



                        <!-- PELAKU -->

                        <td>

                            <?php if (
                                $actorUsername !== ''
                            ): ?>

                                <span
                                    class="au-actor"
                                >
                                    @<?= e(
                                        $actorUsername
                                    ) ?>
                                </span>


                                <?php if (
                                    $actorName !== ''
                                    && $actorName
                                        !== $actorUsername
                                ): ?>

                                    <span
                                        class="
                                            au-actor-name
                                        "
                                    >
                                        <?= e(
                                            $actorName
                                        ) ?>
                                    </span>

                                <?php endif; ?>


                            <?php else: ?>

                                <span
                                    class="au-actor"
                                    style="
                                        color:#7c8795;
                                    "
                                >
                                    Sistem/Tamu
                                </span>

                            <?php endif; ?>

                        </td>



                        <!-- TARGET -->

                        <td>

                            <span class="au-target">

                                <?= e(
                                    audit_target_text(
                                        $event,
                                        $details
                                    )
                                ) ?>

                            </span>

                        </td>



                        <!-- DETAIL -->

                        <td>

                            <span class="au-detail">

                                <?= e(
                                    audit_detail_text(
                                        $event,
                                        $details
                                    )
                                ) ?>

                            </span>

                        </td>



                        <!-- STATUS -->

                        <td>

                            <span
                                class="
                                    au-status
                                    <?= e(
                                        audit_status_class(
                                            $eventStatus
                                        )
                                    ) ?>
                                "
                            >

                                <?= e(
                                    audit_status_label(
                                        $eventStatus
                                    )
                                ) ?>

                            </span>

                        </td>

                    </tr>

                <?php endforeach; ?>



                <?php if (!$events): ?>

                    <tr>

                        <td colspan="6">

                            <div class="au-empty">

                                <div
                                    class="
                                        au-empty-icon
                                    "
                                >

                                    <svg
                                        width="21"
                                        height="21"
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

                                        <path
                                            d="
                                                M12 7v5
                                                l3 2
                                            "
                                        ></path>
                                    </svg>

                                </div>


                                <strong>
                                    Tidak ada aktivitas
                                </strong>

                                Coba ubah pencarian,
                                tanggal, atau status.

                            </div>

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>



        <!-- =================================================
             FOOTER
             ================================================= -->

        <?php if ($events): ?>

            <footer
                class="
                    au-result-footer
                "
            >

                <span>

                    Menampilkan maksimal
                    500 aktivitas terbaru.

                </span>


                <span
                    class="
                        au-result-count
                    "
                >

                    <?= count($events) ?>
                    aktivitas

                </span>

            </footer>

        <?php endif; ?>

    </section>

</div>



<script>
(() => {

    const form =
        document.querySelector(
            '#audit-filter-form'
        );


    const search =
        document.querySelector(
            '#audit-search'
        );


    const status =
        document.querySelector(
            '#audit-status'
        );


    const date =
        document.querySelector(
            '#audit-date'
        );


    /*
    |--------------------------------------------------------------------------
    | Status langsung filter
    |--------------------------------------------------------------------------
    */

    if (status && form) {

        status.addEventListener(
            'change',
            () => {
                form.submit();
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Tanggal langsung filter
    |--------------------------------------------------------------------------
    */

    if (date && form) {

        date.addEventListener(
            'change',
            () => {
                form.submit();
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Search debounce
    |--------------------------------------------------------------------------
    */

    if (search && form) {

        let timer = null;


        search.addEventListener(
            'input',
            () => {

                clearTimeout(
                    timer
                );


                timer = setTimeout(
                    () => {
                        form.submit();
                    },
                    550
                );

            }
        );


        /*
         * Enter tetap langsung.
         */
        search.addEventListener(
            'keydown',
            event => {

                if (
                    event.key === 'Enter'
                ) {

                    clearTimeout(
                        timer
                    );

                }

            }
        );
    }

})();
</script>


<?php

require ROOT_PATH
    . '/includes/footer.php';

?>