<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

$user = require_role('orang_tua');
$pdo  = db();


/* =========================================================
   HELPERS
   ========================================================= */

function rpt_pick(
    array $row,
    array $keys,
    mixed $default = null
): mixed {
    foreach ($keys as $key) {
        if (
            array_key_exists($key, $row)
            && $row[$key] !== null
            && $row[$key] !== ''
        ) {
            return $row[$key];
        }
    }

    return $default;
}


function rpt_level(int $level): string
{
    return match ($level) {
        7 => 'Mustawa Muttawasit 1',
        8 => 'Mustawa Muttawasit 2',
        9 => 'Mustawa Muttawasit 3',
        default => 'Level ' . max(1, min(6, $level)),
    };
}


function rpt_date(mixed $value): string
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime((string) $value);

    if (!$timestamp) {
        return '-';
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
        (int) date('j', $timestamp)
        . ' '
        . $months[(int) date('n', $timestamp)]
        . ' '
        . date('Y', $timestamp);
}


function rpt_date_key(array $row): string
{
    $value = rpt_pick(
        $row,
        [
            'tanggal',
            'tanggal_ujian',
            'created_at',
        ],
        ''
    );

    if (!$value) {
        return '';
    }

    $timestamp = strtotime((string) $value);

    return $timestamp
        ? date('Y-m-d', $timestamp)
        : '';
}


function rpt_score(mixed $value): string
{
    if (
        $value === null
        || $value === ''
    ) {
        return '-';
    }

    if (!is_numeric($value)) {
        return (string) $value;
    }

    $number = (float) $value;

    if (floor($number) === $number) {
        return (string) (int) $number;
    }

    return rtrim(
        rtrim(
            number_format(
                $number,
                2,
                ',',
                '.'
            ),
            '0'
        ),
        ','
    );
}


function rpt_average(array $values): ?float
{
    $numbers = [];

    foreach ($values as $value) {
        if (is_numeric($value)) {
            $numbers[] = (float) $value;
        }
    }

    if (!$numbers) {
        return null;
    }

    return array_sum($numbers) / count($numbers);
}


function rpt_predicate(mixed $score): string
{
    if (!is_numeric($score)) {
        return '-';
    }

    $score = (float) $score;

    if ($score >= 90) {
        return 'Mumtaz';
    }

    if ($score >= 80) {
        return 'Jayyid Jiddan';
    }

    if ($score >= 65) {
        return 'Jayyid';
    }

    if ($score >= 50) {
        return 'Maqbul';
    }

    if ($score >= 35) {
        return 'Dhaif';
    }

    return 'Dhaif Jiddan';
}


/*
|--------------------------------------------------------------------------
| Cari logo tanpa bikin halaman error kalau nama file beda.
|--------------------------------------------------------------------------
*/

function rpt_asset(array $paths): string
{
    foreach ($paths as $path) {
        $absolute =
            ROOT_PATH
            . '/'
            . ltrim($path, '/');

        if (is_file($absolute)) {
            return url($path);
        }
    }

    return '';
}


$schoolLogo = rpt_asset([
    'assets/images/logo.png',
    'assets/images/logo-sekolah.png',
    'assets/images/logo_labschool.png',
    'assets/images/logo-labschool.png',
    'assets/img/logo.png',
    'assets/img/logo-sekolah.png',
]);


$foundationLogo = rpt_asset([
    'assets/images/logo-yayasan.png',
    'assets/images/logo_yayasan.png',
    'assets/images/logo-bani-saleh.png',
    'assets/images/logo-banisaleh.png',
    'assets/images/logo2.png',
    'assets/img/logo-yayasan.png',
    'assets/img/logo2.png',
]);


/*
 * Kalau logo kanan tidak ketemu,
 * jangan pakai logo kiri dua kali.
 */
if ($foundationLogo === $schoolLogo) {
    $foundationLogo = '';
}


/* =========================================================
   HUBUNGKAN ANAK
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = trim(
        (string) ($_POST['action'] ?? '')
    );


    if ($action === 'link_child') {

        $nis = trim(
            (string) ($_POST['nis'] ?? '')
        );


        if ($nis === '') {

            flash(
                'error',
                'NIS anak wajib diisi.'
            );

            redirect(
                'orangtua/dashboard.php'
            );
        }


        $studentStmt = $pdo->prepare(
            "
                SELECT
                    id,
                    nis,
                    nama_lengkap,
                    kelas,
                    level

                FROM students

                WHERE nis = ?
                  AND status = 'aktif'

                LIMIT 1
            "
        );

        $studentStmt->execute([
            $nis
        ]);

        $student = $studentStmt->fetch(
            PDO::FETCH_ASSOC
        );


        if (!$student) {

            flash(
                'error',
                'NIS siswa tidak ditemukan.'
            );

            redirect(
                'orangtua/dashboard.php'
            );
        }


        $claimedStmt = $pdo->prepare(
            "
                SELECT parent_id

                FROM parent_student_links

                WHERE student_id = ?
                  AND status = 'active'
                  AND parent_id <> ?

                LIMIT 1
            "
        );

        $claimedStmt->execute([
            $student['id'],
            $user['id'],
        ]);


        if ($claimedStmt->fetch()) {

            flash(
                'error',
                'Siswa sudah terhubung dengan akun Orang Tua lain.'
            );

            redirect(
                'orangtua/dashboard.php'
            );
        }


        try {

            $pdo->beginTransaction();


            $disableStmt = $pdo->prepare(
                "
                    UPDATE parent_student_links

                    SET status = 'inactive'

                    WHERE parent_id = ?
                "
            );

            $disableStmt->execute([
                $user['id']
            ]);


            $existingStmt = $pdo->prepare(
                "
                    SELECT id

                    FROM parent_student_links

                    WHERE parent_id = ?
                      AND student_id = ?

                    LIMIT 1
                "
            );

            $existingStmt->execute([
                $user['id'],
                $student['id'],
            ]);

            $linkId =
                $existingStmt->fetchColumn();


            if ($linkId) {

                $activateStmt = $pdo->prepare(
                    "
                        UPDATE parent_student_links

                        SET status = 'active'

                        WHERE id = ?
                    "
                );

                $activateStmt->execute([
                    $linkId
                ]);

            } else {

                $insertStmt = $pdo->prepare(
                    "
                        INSERT INTO parent_student_links (
                            parent_id,
                            student_id,
                            status
                        )

                        VALUES (
                            ?,
                            ?,
                            'active'
                        )
                    "
                );

                $insertStmt->execute([
                    $user['id'],
                    $student['id'],
                ]);
            }


            $pdo->commit();


            flash(
                'success',
                'Akun orang tua berhasil dihubungkan ke satu anak.'
            );


        } catch (Throwable $error) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                '[NGAJIYUK!] Link child: '
                . $error->getMessage()
            );

            flash(
                'error',
                'Gagal menghubungkan anak.'
            );
        }


        redirect(
            'orangtua/dashboard.php'
        );
    }
}


/* =========================================================
   ANAK TERHUBUNG
   ========================================================= */

$stmt = $pdo->prepare(
    "
        SELECT
            s.id,
            s.nis,
            s.nama_lengkap,
            s.kelas,
            s.level,
            s.teacher_id,
            u.full_name AS teacher_name

        FROM parent_student_links psl

        INNER JOIN students s
            ON s.id = psl.student_id

        LEFT JOIN users u
            ON u.id = s.teacher_id

        WHERE psl.parent_id = ?
          AND psl.status = 'active'
          AND s.status = 'aktif'

        LIMIT 1
    "
);

$stmt->execute([
    $user['id']
]);

$child = $stmt->fetch(
    PDO::FETCH_ASSOC
);


/* =========================================================
   DATA RAPOR
   ========================================================= */

$dailyReports = [];
$levelReports = [];
$munaqReports = [];

$latestLevel = null;
$latestMunaq = null;

$dates = [];
$selectedDate = '';

$dailySelected = [];


if ($child) {

    /* -----------------------------------------------------
       HAFALAN HARIAN
       ----------------------------------------------------- */

    try {

        $stmt = $pdo->prepare(
            "
                SELECT *

                FROM laporan_tahsin_tahfidz

                WHERE student_id = ?

                ORDER BY
                    tanggal DESC,
                    created_at DESC
            "
        );

        $stmt->execute([
            $child['id']
        ]);

        $dailyReports = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    } catch (Throwable $error) {

        error_log(
            '[NGAJIYUK!] Daily report query: '
            . $error->getMessage()
        );

        $dailyReports = [];
    }


    /* -----------------------------------------------------
       UJIAN LEVEL
       ----------------------------------------------------- */

    try {

        $stmt = $pdo->prepare(
            "
                SELECT *

                FROM level_promotion_exams

                WHERE student_id = ?

                ORDER BY created_at DESC
            "
        );

        $stmt->execute([
            $child['id']
        ]);

        $levelReports = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        $latestLevel =
            $levelReports[0]
            ?? null;

    } catch (Throwable $error) {

        error_log(
            '[NGAJIYUK!] Level query: '
            . $error->getMessage()
        );

        $levelReports = [];
    }


    /* -----------------------------------------------------
       MUNAQOSYAH
       ----------------------------------------------------- */

    try {

        $stmt = $pdo->prepare(
            "
                SELECT *

                FROM munaqosyah_exams

                WHERE student_id = ?

                ORDER BY created_at DESC
            "
        );

        $stmt->execute([
            $child['id']
        ]);

        $munaqReports = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        $latestMunaq =
            $munaqReports[0]
            ?? null;

    } catch (Throwable $error) {

        error_log(
            '[NGAJIYUK!] Munaqosyah query: '
            . $error->getMessage()
        );

        $munaqReports = [];
    }


    /* -----------------------------------------------------
       TANGGAL HARIAN
       ----------------------------------------------------- */

    foreach ($dailyReports as $row) {

        $date =
            rpt_date_key($row);

        if ($date !== '') {
            $dates[] = $date;
        }
    }


    $dates = array_values(
        array_unique($dates)
    );

    rsort($dates);


    $requestedDate = trim(
        (string) ($_GET['tanggal'] ?? '')
    );


    if (
        $requestedDate !== ''
        && in_array(
            $requestedDate,
            $dates,
            true
        )
    ) {

        $selectedDate =
            $requestedDate;

    } else {

        $selectedDate =
            $dates[0]
            ?? '';
    }


    if ($selectedDate !== '') {

        foreach ($dailyReports as $row) {

            if (
                rpt_date_key($row)
                === $selectedDate
            ) {
                $dailySelected[] =
                    $row;
            }
        }
    }
}


/* =========================================================
   SUMMARY HARIAN
   ========================================================= */

$uniqueSurahs = [];

foreach ($dailyReports as $row) {

    $name = trim(
        (string) rpt_pick(
            $row,
            [
                'nama_surah',
                'surah',
                'surat',
            ],
            ''
        )
    );

    if ($name !== '') {
        $uniqueSurahs[] = $name;
    }
}

$uniqueSurahs =
    array_values(
        array_unique(
            $uniqueSurahs
        )
    );


/* =========================================================
   DATA UJIAN LEVEL
   ========================================================= */

$levelDate =
    $latestLevel
        ? rpt_pick(
            $latestLevel,
            [
                'tanggal_ujian',
                'tanggal',
                'created_at',
            ]
        )
        : null;


$levelFrom =
    $latestLevel
        ? rpt_pick(
            $latestLevel,
            [
                'level_asal',
                'from_level',
                'level_from',
                'level_awal',
            ]
        )
        : null;


$levelTo =
    $latestLevel
        ? rpt_pick(
            $latestLevel,
            [
                'level_tujuan',
                'to_level',
                'level_to',
                'level_baru',
            ]
        )
        : null;


$levelSurah =
    $latestLevel
        ? rpt_pick(
            $latestLevel,
            [
                'nama_surah',
                'surah',
                'surat_ujian',
                'materi',
            ],
            '-'
        )
        : '-';


$levelYear =
    $latestLevel
        ? rpt_pick(
            $latestLevel,
            [
                'tahun_ajaran',
                'academic_year',
            ],
            active_academic_year()
        )
        : active_academic_year();


$levelResult =
    $latestLevel
        ? rpt_pick(
            $latestLevel,
            [
                'hasil',
                'result',
                'status_kelulusan',
                'status',
            ],
            '-'
        )
        : '-';


$levelNote =
    $latestLevel
        ? rpt_pick(
            $latestLevel,
            [
                'catatan_guru',
                'catatan',
                'keterangan',
                'notes',
            ],
            'Belum ada catatan guru.'
        )
        : 'Belum ada catatan guru.';


$levelCriteria = [];


if ($latestLevel) {

    $levelMap = [

        'Kelancaran' => [
            'nilai_kelancaran',
            'kelancaran',
        ],

        'Makhrojul Huruf' => [
            'nilai_makhraj',
            'nilai_makhroj',
            'makhraj',
            'makhroj',
        ],

        'Hukum Tajwid' => [
            'nilai_tajwid',
            'tajwid',
        ],

        'Sambung Ayat / Hafalan' => [
            'nilai_hafalan',
            'hafalan',
            'nilai_sambung_ayat',
            'sambung_ayat',
        ],
    ];


    foreach (
        $levelMap
        as $label => $keys
    ) {

        $value =
            rpt_pick(
                $latestLevel,
                $keys
            );


        if (
            $value !== null
            && $value !== ''
        ) {
            $levelCriteria[$label] =
                $value;
        }
    }
}


$levelAverage =
    $latestLevel
        ? rpt_pick(
            $latestLevel,
            [
                'nilai_rata_rata',
                'rata_rata',
                'average',
            ]
        )
        : null;


if (
    $levelAverage === null
    && $levelCriteria
) {

    $levelAverage =
        rpt_average(
            array_values(
                $levelCriteria
            )
        );
}


/* =========================================================
   DATA MUNAQOSYAH
   ========================================================= */

$munaqDate =
    $latestMunaq
        ? rpt_pick(
            $latestMunaq,
            [
                'tanggal_ujian',
                'tanggal',
                'created_at',
            ]
        )
        : null;


$munaqYear =
    $latestMunaq
        ? rpt_pick(
            $latestMunaq,
            [
                'tahun_ajaran',
                'academic_year',
            ],
            active_academic_year()
        )
        : active_academic_year();


$munaqMaterial =
    $latestMunaq
        ? rpt_pick(
            $latestMunaq,
            [
                'juz',
                'materi',
                'jenjang',
                'nama_surah',
            ],
            '-'
        )
        : '-';


$munaqResult =
    $latestMunaq
        ? rpt_pick(
            $latestMunaq,
            [
                'hasil',
                'result',
                'status_kelulusan',
                'status',
            ],
            '-'
        )
        : '-';


$munaqNote =
    $latestMunaq
        ? rpt_pick(
            $latestMunaq,
            [
                'catatan_guru',
                'catatan',
                'keterangan',
                'notes',
            ],
            'Belum ada catatan guru.'
        )
        : 'Belum ada catatan guru.';


$munaqCriteria = [];


if ($latestMunaq) {

    $munaqMap = [

        'Kelancaran' => [
            'nilai_kelancaran',
            'kelancaran',
        ],

        'Makhrojul Huruf' => [
            'nilai_makhraj',
            'nilai_makhroj',
            'makhraj',
            'makhroj',
        ],

        'Hukum Tajwid' => [
            'nilai_tajwid',
            'tajwid',
        ],

        'Hafalan' => [
            'nilai_hafalan',
            'hafalan',
        ],
    ];


    foreach (
        $munaqMap
        as $label => $keys
    ) {

        $value =
            rpt_pick(
                $latestMunaq,
                $keys
            );


        if (
            $value !== null
            && $value !== ''
        ) {
            $munaqCriteria[$label] =
                $value;
        }
    }
}


$munaqAverage =
    $latestMunaq
        ? rpt_pick(
            $latestMunaq,
            [
                'nilai_rata_rata',
                'rata_rata',
                'average',
            ]
        )
        : null;


if (
    $munaqAverage === null
    && $munaqCriteria
) {

    $munaqAverage =
        rpt_average(
            array_values(
                $munaqCriteria
            )
        );
}


$munaqPredicate =
    $latestMunaq
        ? rpt_pick(
            $latestMunaq,
            [
                'predikat',
                'predicate',
            ],
            rpt_predicate(
                $munaqAverage
            )
        )
        : '-';


/* =========================================================
   DATA SEKOLAH / TANDA TANGAN
   ========================================================= */

$principal =
    'WIDI NURMARA, S.Pd.I';

$coordinator =
    'ULFA DWI HASTUTI, S.Li';


$pageTitle = 'Dashboard Anak';

require ROOT_PATH
    . '/includes/header.php';

?>


<style>

/* =========================================================
   DASHBOARD ORANG TUA
   ========================================================= */

.parent-dashboard {
    width: 100%;
}


.parent-dashboard h1 {
    margin: 0;

    color: #101828;

    font-size:
        clamp(
            30px,
            2.5vw,
            39px
        );

    font-weight: 900;

    line-height: 1.05;

    letter-spacing: -.045em;
}


.page-description {
    margin:
        10px 0
        27px;

    color: #667085;

    font-size: 12px;

    line-height: 1.6;
}


/* =========================================================
   HUBUNGKAN ANAK
   ========================================================= */

.link-child-wrap {
    display: flex;
    justify-content: center;

    padding-top: 20px;
}


.link-child-card {
    width: min(550px, 100%);

    padding: 32px;

    border: 1px solid #e1e6e3;
    border-radius: 20px;

    background: #fff;

    text-align: center;

    box-shadow:
        0 3px 8px
        rgba(16, 24, 40, .045);
}


.link-child-icon {
    width: 64px;
    height: 64px;

    margin:
        0 auto
        20px;

    display: grid;
    place-items: center;

    border-radius: 50%;

    background: #d9f9e9;

    color: #008b61;
}


.link-child-card h2 {
    margin: 0;

    color: #101828;

    font-size: 21px;
    font-weight: 900;
}


.link-child-card p {
    max-width: 400px;

    margin:
        10px auto
        22px;

    color: #667085;

    font-size: 11px;

    line-height: 1.6;
}


.link-child-card input {
    width: 100%;
    height: 58px;

    padding: 10px;

    border: 1px solid #dce2df;
    border-radius: 11px;

    outline: 0;

    font-family: inherit;

    font-size: 13px;
    font-weight: 800;

    text-align: center;
}


.link-child-card input:focus {
    border-color: #45a27e;

    box-shadow:
        0 0 0 3px
        rgba(0, 143, 98, .09);
}


.link-child-card button {
    width: 100%;
    height: 52px;

    margin-top: 13px;

    border: 0;
    border-radius: 11px;

    background: #00a36d;

    color: #fff;

    cursor: pointer;

    font-family: inherit;

    font-weight: 900;
}


/* =========================================================
   CHILD HERO
   ========================================================= */

.child-hero {
    min-height: 120px;

    padding: 22px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 20px;

    border-radius: 20px;

    background:
        linear-gradient(
            135deg,
            #08714e,
            #066746
        );

    color: #fff;

    box-shadow:
        0 8px 20px
        rgba(6, 103, 70, .12);
}


.child-profile {
    display: flex;
    align-items: center;

    gap: 17px;
}


.child-avatar {
    width: 78px;
    height: 78px;

    flex: 0 0 78px;

    display: grid;
    place-items: center;

    border-radius: 15px;

    background:
        linear-gradient(
            135deg,
            #18764f,
            #25ba5a
        );

    color: #fff;

    font-size: 22px;
    font-weight: 900;
}


.child-label {
    display: block;

    color: #a7f6cf;

    font-size: 9px;
    font-weight: 900;

    letter-spacing: .12em;

    text-transform: uppercase;
}


.child-name {
    margin: 5px 0 0;

    color: #fff;

    font-size: 21px;
    font-weight: 900;
}


.child-meta {
    margin: 7px 0 0;

    color: #fff;

    font-size: 11px;
    font-weight: 700;
}


.child-actions {
    display: flex;

    gap: 9px;
}


.child-action {
    min-height: 43px;

    padding:
        9px
        15px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 7px;

    border: 0;
    border-radius: 10px;

    background: #fff;

    color: #076344;

    cursor: pointer;

    font-family: inherit;

    font-size: 10px;
    font-weight: 900;
}


.child-action.secondary {
    background:
        rgba(
            255,
            255,
            255,
            .14
        );

    color: #fff;
}


/* =========================================================
   3 BUTTON RAPOR
   ========================================================= */

.report-tabs {
    display: grid;

    grid-template-columns:
        repeat(
            3,
            minmax(0, 1fr)
        );

    gap: 16px;

    margin-top: 20px;
}


.report-tab {
    position: relative;

    width: 100%;
    min-height: 130px;

    padding: 20px;

    border: 1px solid #dfe5e2;
    border-radius: 16px;

    outline: 0;

    background: #fff;

    cursor: pointer;

    font-family: inherit;

    text-align: left;

    transition:
        transform .18s ease,
        border-color .18s ease,
        box-shadow .18s ease,
        background .18s ease;
}


.report-tab:hover {
    transform:
        translateY(-2px);

    border-color: #a9cbbb;

    box-shadow:
        0 8px 18px
        rgba(16, 24, 40, .06);
}


.report-tab.active {
    border-color: #00a36d;

    background:
        linear-gradient(
            135deg,
            #00a36d,
            #07996a
        );

    box-shadow:
        0 8px 20px
        rgba(0, 163, 109, .14);
}


.report-tab-icon {
    height: 31px;

    margin-bottom: 19px;

    display: flex;
    align-items: center;

    color: #00a36d;
}


.report-tab-icon svg {
    width: 22px;
    height: 22px;
}


.report-tab.active
.report-tab-icon {
    color: #fff;
}


.report-tab-title {
    display: block;

    color: #101828;

    font-size: 13px;
    font-weight: 900;
}


.report-tab.active
.report-tab-title {
    color: #fff;
}


.report-tab-text {
    display: block;

    margin-top: 7px;

    color: #667085;

    font-size: 10px;

    line-height: 1.45;
}


.report-tab.active
.report-tab-text {
    color:
        rgba(
            255,
            255,
            255,
            .95
        );
}


/* =========================================================
   REPORT PANEL
   ========================================================= */

.report-panel {
    margin-top: 30px;

    animation:
        reportEnter
        .2s ease;
}


.report-panel[hidden] {
    display: none !important;
}


@keyframes reportEnter {

    from {
        opacity: 0;

        transform:
            translateY(6px);
    }

    to {
        opacity: 1;

        transform: none;
    }

}


.report-toolbar {
    margin-bottom: 18px;

    display: flex;
    align-items: flex-end;
    justify-content: space-between;

    gap: 15px;
}


.report-eyebrow {
    margin: 0 0 6px;

    color: #009367;

    font-size: 9px;
    font-weight: 900;

    letter-spacing: .13em;

    text-transform: uppercase;
}


.report-heading {
    margin: 0;

    color: #101828;

    font-size: 20px;
    font-weight: 900;
}


.report-actions {
    display: flex;
    align-items: center;

    gap: 8px;
}


.report-actions select,
.report-actions button {
    min-height: 44px;

    padding:
        8px
        13px;

    border: 1px solid #dce3df;
    border-radius: 10px;

    background: #fff;

    color: #536176;

    font-family: inherit;

    font-size: 9.5px;
    font-weight: 900;
}


.report-actions button {
    cursor: pointer;
}


.report-actions .download-excel {
    border-color: #7acfb1;

    background: #7acfb1;

    color: #fff;
}


.report-actions button:disabled {
    opacity: .4;

    cursor: not-allowed;
}


/* =========================================================
   TEMPLATE RAPOR LAMA
   ========================================================= */

.legacy-report {
    position: relative;

    width: 630px;
    min-height: 891px;

    margin:
        0 auto;

    padding:
        30px
        38px
        34px;

    overflow: hidden;

    background: #fff;

    color: #111;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    box-shadow:
        0 8px 25px
        rgba(16, 24, 40, .11);
}


/* =========================================================
   WATERMARK
   ========================================================= */

.legacy-watermark {
    position: absolute;

    z-index: 0;

    top: 310px;
    left: 50%;

    width: 225px;
    height: 225px;

    transform:
        translateX(-50%);

    object-fit: contain;

    opacity: .055;

    pointer-events: none;
}


/* =========================================================
   KOP RAPOR
   ========================================================= */

.legacy-header {
    position: relative;

    z-index: 2;

    min-height: 128px;

    padding:
        0
        70px
        14px;

    text-align: center;
}


.legacy-logo {
    position: absolute;

    top: 25px;

    width: 52px;
    height: 52px;

    object-fit: contain;
}


.legacy-logo.left {
    left: 7px;
}


.legacy-logo.right {
    right: 7px;
}


.legacy-foundation {
    display: block;

    color: #111;

    font-size: 8px;
    font-weight: 700;
}


.legacy-school {
    display: block;

    margin-top: 3px;

    color: #174f3d;

    font-size: 15px;
    font-weight: 900;

    line-height: 1.15;
}


.legacy-report-name {
    display: block;

    margin-top: 8px;

    color: #111;

    font-size: 12px;
    font-weight: 900;

    letter-spacing: .07em;
}


.legacy-school-meta {
    margin-top: 5px;

    display: flex;
    justify-content: center;

    gap: 27px;

    color: #111;

    font-size: 6.5px;
    font-weight: 900;
}


.legacy-address {
    max-width: 400px;

    margin:
        5px auto
        0;

    color: #111;

    font-size: 5.5px;
    font-weight: 600;

    line-height: 1.4;
}


.legacy-double-line {
    position: absolute;

    right: 0;
    bottom: 0;
    left: 0;

    height: 5px;

    border-top:
        2px solid #111;

    border-bottom:
        1px solid #111;
}


/* =========================================================
   IDENTITAS SISWA
   ========================================================= */

.legacy-identity {
    position: relative;

    z-index: 2;

    margin-top: 18px;

    display: grid;

    grid-template-columns:
        repeat(
            2,
            minmax(0, 1fr)
        );

    column-gap: 34px;
    row-gap: 7px;

    font-size: 7px;
}


.legacy-ident-row {
    display: grid;

    grid-template-columns:
        105px
        8px
        minmax(0, 1fr);

    align-items: start;
}


.legacy-ident-label {
    font-weight: 700;
}


.legacy-ident-value {
    font-weight: 800;

    text-transform: uppercase;
}


/* =========================================================
   TABLE RAPOR
   ========================================================= */

.legacy-table {
    position: relative;

    z-index: 2;

    width: 100%;

    margin-top: 21px;

    border-collapse: collapse;

    color: #111;

    font-size: 7px;
}


.legacy-table th,
.legacy-table td {
    padding:
        7px
        8px;

    border:
        1px solid #111;

    vertical-align: middle;
}


.legacy-table th {
    background: #f1f2f3;

    font-weight: 900;
}


.legacy-table.center th,
.legacy-table.center td {
    text-align: center;
}


.legacy-table-info th {
    width: 26%;

    text-align: left;
}


.legacy-empty {
    height: 71px !important;

    color: #708090;

    font-weight: 700;

    text-align: center !important;
}


.legacy-average {
    font-weight: 900;

    text-align: center !important;
}


/* =========================================================
   CATATAN GURU
   ========================================================= */

.legacy-note {
    position: relative;

    z-index: 2;

    margin-top: 17px;

    border:
        1px solid #111;
}


.legacy-note-title {
    padding: 7px;

    border-bottom:
        1px solid #111;

    background: #f1f2f3;

    color: #111;

    font-size: 7px;
    font-weight: 900;

    letter-spacing: .03em;

    text-align: center;
}


.legacy-note-body {
    min-height: 75px;

    padding: 11px;

    color: #111;

    font-size: 6.8px;

    line-height: 1.55;
}


/* =========================================================
   TANDA TANGAN
   ========================================================= */

.legacy-signatures {
    position: relative;

    z-index: 2;

    margin-top: 28px;

    display: grid;

    grid-template-columns:
        repeat(
            3,
            minmax(0, 1fr)
        );

    gap: 15px;

    color: #111;

    font-size: 6.8px;

    text-align: center;
}


.legacy-sign-heading {
    min-height: 18px;

    font-weight: 700;
}


.legacy-sign-space {
    height: 57px;
}


.legacy-sign-name {
    font-weight: 900;

    text-decoration: underline;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 900px) {

    .child-hero,
    .report-toolbar {
        align-items: flex-start;

        flex-direction: column;
    }


    .child-actions {
        flex-wrap: wrap;
    }


    .report-tabs {
        grid-template-columns: 1fr;
    }


    .report-actions {
        width: 100%;

        flex-wrap: wrap;
    }

}


@media (max-width: 700px) {

    .legacy-report-wrap {
        overflow-x: auto;

        padding-bottom: 15px;
    }

}


/* =========================================================
   PRINT / SAVE PDF
   ========================================================= */

@media print {

    body.print-report * {
        visibility: hidden !important;
    }


    body.print-report
    .legacy-print-target,
    body.print-report
    .legacy-print-target * {
        visibility: visible !important;
    }


    body.print-report
    .legacy-print-target {
        position: absolute;

        top: 0;
        left: 0;

        width: 210mm;
        min-height: 297mm;

        margin: 0;

        padding:
            13mm
            14mm;

        box-shadow: none;
    }


    @page {
        size: A4 portrait;

        margin: 0;
    }

}

</style>



<div class="parent-dashboard">


    <h1>
        Preview Rapor Anak
    </h1>


    <p class="page-description">
        Akun ini hanya dapat melihat dan memperbarui
        satu anak yang ditautkan.
    </p>



<?php if (!$child): ?>


    <!-- =====================================================
         BELUM TERHUBUNG ANAK
         ===================================================== -->

    <div class="link-child-wrap">

        <form
            class="link-child-card"
            method="post"
            action="<?= e(
                url(
                    'orangtua/dashboard.php'
                )
            ) ?>"
        >

            <?= csrf_field() ?>


            <input
                type="hidden"
                name="action"
                value="link_child"
            >


            <div class="link-child-icon">

                <svg
                    width="28"
                    height="28"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
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

            </div>


            <h2>
                Hubungkan Satu Anak
            </h2>


            <p>
                Masukkan NIS anak. Setelah terhubung,
                rapor akan terisi otomatis berdasarkan
                data yang disimpan Guru.
            </p>


            <input
                type="text"
                name="nis"
                placeholder="NIS anak"
                autocomplete="off"
                required
            >


            <button type="submit">
                Hubungkan Anak
            </button>

        </form>

    </div>


<?php else: ?>


    <?php

    $initial =
        strtoupper(
            mb_substr(
                trim(
                    (string) $child[
                        'nama_lengkap'
                    ]
                ),
                0,
                2
            )
        );

    ?>


    <!-- =====================================================
         DATA ANAK
         ===================================================== -->

    <section class="child-hero">


        <div class="child-profile">

            <div class="child-avatar">
                <?= e($initial) ?>
            </div>


            <div>

                <span class="child-label">
                    Siswa yang ditautkan
                </span>


                <h2 class="child-name">

                    <?= e(
                        $child[
                            'nama_lengkap'
                        ]
                    ) ?>

                </h2>


                <p class="child-meta">

                    NIS
                    <?= e(
                        $child['nis']
                    ) ?>

                    · Kelas
                    <?= e(
                        $child['kelas']
                        ?: '-'
                    ) ?>

                    ·
                    <?= e(
                        rpt_level(
                            (int) $child[
                                'level'
                            ]
                        )
                    ) ?>

                </p>

            </div>

        </div>


        <div class="child-actions">

            <button
                type="button"
                class="child-action"
                onclick="
                    window.appToast?.(
                        'Biodata akademik anak dikelola Guru dan Administrator.'
                    )
                "
            >
                ✎ Edit Biodata Anak
            </button>


            <button
                type="button"
                class="
                    child-action
                    secondary
                "
                data-export-report="daily-report"
                <?= !$dailySelected
                    ? 'disabled'
                    : ''
                ?>
            >
                ↓ Download Tadarus
            </button>

        </div>

    </section>



    <!-- =====================================================
         3 BUTTON PILIH RAPOR
         ===================================================== -->

    <section class="report-tabs">


        <!-- HARIAN -->

        <button
            type="button"
            class="
                report-tab
                active
            "
            data-report-tab="daily"
            aria-selected="true"
        >

            <div class="report-tab-icon">

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path
                        d="
                            M4 5
                            c3-1 5 0 8 2
                            v13
                            c-3-2-5-3-8-2
                            V5Z
                        "
                    ></path>

                    <path
                        d="
                            M20 5
                            c-3-1-5 0-8 2
                            v13
                            c3-2 5-3 8-2
                            V5Z
                        "
                    ></path>
                </svg>

            </div>


            <strong class="report-tab-title">
                Hafalan Harian
            </strong>


            <span class="report-tab-text">

                <?= count(
                    $dailyReports
                ) ?>
                laporan

                ·

                <?= count(
                    $uniqueSurahs
                ) ?>
                surat

            </span>

        </button>



        <!-- LEVEL -->

        <button
            type="button"
            class="report-tab"
            data-report-tab="level"
            aria-selected="false"
        >

            <div class="report-tab-icon">

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
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
                            c3 2 7 2 10 0
                            v-4
                        "
                    ></path>
                </svg>

            </div>


            <strong class="report-tab-title">
                Hafalan Level
            </strong>


            <span class="report-tab-text">

                <?php if ($latestLevel): ?>

                    <?= e(
                        rpt_score(
                            $levelAverage
                        )
                    ) ?>

                    ·

                    <?= e(
                        (string) $levelResult
                    ) ?>

                <?php else: ?>

                    Belum ada ujian

                <?php endif; ?>

            </span>

        </button>



        <!-- MUNAQOSYAH -->

        <button
            type="button"
            class="report-tab"
            data-report-tab="munaqosyah"
            aria-selected="false"
        >

            <div class="report-tab-icon">

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <circle
                        cx="12"
                        cy="8"
                        r="5"
                    ></circle>

                    <path
                        d="
                            m8.5 12
                            -2 9
                            5.5-3
                            5.5 3
                            -2-9
                        "
                    ></path>
                </svg>

            </div>


            <strong class="report-tab-title">
                Munaqosyah
            </strong>


            <span class="report-tab-text">

                <?php if ($latestMunaq): ?>

                    <?= e(
                        rpt_score(
                            $munaqAverage
                        )
                    ) ?>

                    ·

                    <?= e(
                        (string)
                        $munaqPredicate
                    ) ?>

                <?php else: ?>

                    Belum ada ujian

                <?php endif; ?>

            </span>

        </button>

    </section>



    <!-- =====================================================
         RAPOR HAFALAN HARIAN
         ===================================================== -->

    <section
        class="report-panel"
        data-report-panel="daily"
    >

        <header class="report-toolbar">

            <div>

                <p class="report-eyebrow">
                    Template Rapor Resmi
                </p>


                <h2 class="report-heading">
                    Rapor Hafalan Harian
                </h2>

            </div>


            <div class="report-actions">


                <form
                    method="get"
                    action="<?= e(
                        url(
                            'orangtua/dashboard.php'
                        )
                    ) ?>"
                >

                    <select
                        name="tanggal"
                        onchange="
                            this.form.submit()
                        "
                    >

                        <?php if (!$dates): ?>

                            <option value="">
                                Pilih tanggal laporan
                            </option>

                        <?php endif; ?>


                        <?php foreach (
                            $dates
                            as $date
                        ): ?>

                            <option
                                value="<?= e(
                                    $date
                                ) ?>"
                                <?= $date
                                    === $selectedDate
                                        ? 'selected'
                                        : ''
                                ?>
                            >

                                <?= e(
                                    rpt_date(
                                        $date
                                    )
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </form>


                <button
                    type="button"
                    class="download-excel"
                    data-export-report="daily-report"
                    <?= !$dailySelected
                        ? 'disabled'
                        : ''
                    ?>
                >
                    ↓ Download Excel
                </button>


                <button
                    type="button"
                    data-print-report="daily-report"
                    <?= !$dailySelected
                        ? 'disabled'
                        : ''
                    ?>
                >
                    ▣ Cetak / Simpan PDF
                </button>

            </div>

        </header>


        <div class="legacy-report-wrap">

            <div
                class="legacy-report"
                id="daily-report"
                data-report-name="
                    rapor-hafalan-harian
                "
            >


                <?php
                render_legacy_header(
                    'RAPOR HAFALAN HARIAN',
                    $schoolLogo,
                    $foundationLogo
                );
                ?>


                <?php if ($schoolLogo): ?>

                    <img
                        src="<?= e(
                            $schoolLogo
                        ) ?>"
                        class="legacy-watermark"
                        alt=""
                    >

                <?php endif; ?>


                <div class="legacy-identity">

                    <?php

                    render_identity_row(
                        'Nama Peserta Didik',
                        $child['nama_lengkap']
                    );

                    render_identity_row(
                        'Jenjang Tahfidz',
                        rpt_level(
                            (int) $child[
                                'level'
                            ]
                        )
                    );

                    render_identity_row(
                        'NIS',
                        $child['nis']
                    );

                    render_identity_row(
                        'Kelas',
                        $child['kelas']
                        ?: '-'
                    );

                    render_identity_row(
                        'Tanggal Laporan',
                        $selectedDate
                            ? rpt_date(
                                $selectedDate
                            )
                            : '-'
                    );

                    render_identity_row(
                        'Sumber Nilai',
                        'Terisi Otomatis'
                    );

                    ?>

                </div>


                <table
                    class="
                        legacy-table
                        center
                    "
                >

                    <thead>

                        <tr>

                            <th>No</th>

                            <th>Surat</th>

                            <th>Kelancaran</th>

                            <th>Makhraj</th>

                            <th>Tajwid</th>

                            <th>Hafalan</th>

                            <th>Rata-rata</th>

                            <th>Keterangan</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if ($dailySelected): ?>


                        <?php foreach (
                            $dailySelected
                            as $index => $row
                        ): ?>


                            <?php

                            $scores = [

                                rpt_pick(
                                    $row,
                                    [
                                        'nilai_kelancaran',
                                        'kelancaran',
                                    ]
                                ),

                                rpt_pick(
                                    $row,
                                    [
                                        'nilai_makhraj',
                                        'nilai_makhroj',
                                        'makhraj',
                                        'makhroj',
                                    ]
                                ),

                                rpt_pick(
                                    $row,
                                    [
                                        'nilai_tajwid',
                                        'tajwid',
                                    ]
                                ),

                                rpt_pick(
                                    $row,
                                    [
                                        'nilai_hafalan',
                                        'hafalan',
                                    ]
                                ),
                            ];


                            $average =
                                rpt_pick(
                                    $row,
                                    [
                                        'nilai_rata_rata',
                                        'rata_rata',
                                        'average',
                                    ]
                                );


                            if ($average === null) {

                                $average =
                                    rpt_average(
                                        $scores
                                    );
                            }

                            ?>


                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>


                                <td>

                                    <?= e(
                                        (string)
                                        rpt_pick(
                                            $row,
                                            [
                                                'nama_surah',
                                                'surah',
                                                'surat',
                                            ],
                                            '-'
                                        )
                                    ) ?>

                                </td>


                                <?php foreach (
                                    $scores
                                    as $score
                                ): ?>

                                    <td>

                                        <?= e(
                                            rpt_score(
                                                $score
                                            )
                                        ) ?>

                                    </td>

                                <?php endforeach; ?>


                                <td>

                                    <?= e(
                                        rpt_score(
                                            $average
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= e(
                                        (string)
                                        rpt_pick(
                                            $row,
                                            [
                                                'keterangan',
                                                'catatan',
                                                'catatan_guru',
                                            ],
                                            '-'
                                        )
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="8"
                                class="legacy-empty"
                            >
                                Belum ada data hafalan harian.
                            </td>

                        </tr>


                    <?php endif; ?>

                    </tbody>

                </table>


                <?php

                $dailyNote =
                    $dailySelected
                        ? rpt_pick(
                            $dailySelected[0],
                            [
                                'catatan_guru',
                                'catatan',
                                'keterangan',
                            ],
                            'Belum ada catatan guru.'
                        )
                        : 'Belum ada catatan guru.';


                render_legacy_note(
                    (string) $dailyNote
                );


                render_legacy_signatures(
                    $principal,
                    $coordinator,
                    $selectedDate
                        ? rpt_date(
                            $selectedDate
                        )
                        : '-'
                );

                ?>

            </div>

        </div>

    </section>



    <!-- =====================================================
         RAPOR UJIAN KENAIKAN LEVEL
         ===================================================== -->

    <section
        class="report-panel"
        data-report-panel="level"
        hidden
    >

        <header class="report-toolbar">

            <div>

                <p class="report-eyebrow">
                    Template Rapor Resmi
                </p>


                <h2 class="report-heading">
                    Rapor Ujian Kenaikan Level
                </h2>

            </div>


            <div class="report-actions">

                <button
                    type="button"
                    class="download-excel"
                    data-export-report="level-report"
                    <?= !$latestLevel
                        ? 'disabled'
                        : ''
                    ?>
                >
                    ↓ Download Excel
                </button>


                <button
                    type="button"
                    data-print-report="level-report"
                    <?= !$latestLevel
                        ? 'disabled'
                        : ''
                    ?>
                >
                    ▣ Cetak / Simpan PDF
                </button>

            </div>

        </header>


        <div class="legacy-report-wrap">

            <div
                class="legacy-report"
                id="level-report"
                data-report-name="
                    rapor-ujian-kenaikan-level
                "
            >


                <?php
                render_legacy_header(
                    'RAPOR UJIAN KENAIKAN LEVEL',
                    $schoolLogo,
                    $foundationLogo
                );
                ?>


                <?php if ($schoolLogo): ?>

                    <img
                        src="<?= e(
                            $schoolLogo
                        ) ?>"
                        class="legacy-watermark"
                        alt=""
                    >

                <?php endif; ?>


                <!-- IDENTITAS -->

                <div class="legacy-identity">

                    <?php

                    render_identity_row(
                        'Nama Peserta Didik',
                        $child['nama_lengkap']
                    );

                    render_identity_row(
                        'Jenjang Tahfidz',
                        rpt_level(
                            (int) $child[
                                'level'
                            ]
                        )
                    );

                    render_identity_row(
                        'NIS',
                        $child['nis']
                    );

                    render_identity_row(
                        'Periode',
                        '-'
                    );

                    render_identity_row(
                        'Kelas',
                        $child['kelas']
                        ?: '-'
                    );

                    render_identity_row(
                        'Sumber Nilai',
                        'Terisi Otomatis'
                    );

                    ?>

                </div>


                <!-- INFO UJIAN -->

                <table
                    class="
                        legacy-table
                        legacy-table-info
                    "
                >

                    <tbody>

                        <tr>

                            <th>
                                Tanggal Ujian
                            </th>


                            <td>

                                <?= e(
                                    $levelDate
                                        ? rpt_date(
                                            $levelDate
                                        )
                                        : '-'
                                ) ?>

                            </td>


                            <th>
                                Kenaikan Jenjang
                            </th>


                            <td>

                                <?php if (
                                    $levelFrom !== null
                                    && $levelTo !== null
                                ): ?>

                                    Level
                                    <?= e(
                                        (string)
                                        $levelFrom
                                    ) ?>

                                    →

                                    Level
                                    <?= e(
                                        (string)
                                        $levelTo
                                    ) ?>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>

                        </tr>


                        <tr>

                            <th>
                                Surat Ujian
                            </th>

                            <td colspan="3">

                                <?= e(
                                    (string)
                                    $levelSurah
                                ) ?>

                            </td>

                        </tr>


                        <tr>

                            <th>
                                Tahun Ajaran
                            </th>

                            <td>

                                <?= e(
                                    (string)
                                    $levelYear
                                ) ?>

                            </td>


                            <th>
                                Hasil
                            </th>

                            <td>

                                <?= e(
                                    (string)
                                    $levelResult
                                ) ?>

                            </td>

                        </tr>

                    </tbody>

                </table>


                <!-- KRITERIA PENILAIAN -->

                <table
                    class="
                        legacy-table
                        center
                    "
                >

                    <thead>

                        <tr>

                            <th style="width:45px">
                                No
                            </th>

                            <th>
                                Kriteria Penilaian
                            </th>

                            <th style="width:110px">
                                Nilai
                            </th>

                            <th style="width:125px">
                                Keterangan
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (
                        $latestLevel
                        && $levelCriteria
                    ): ?>


                        <?php

                        $number = 1;

                        foreach (
                            $levelCriteria
                            as $label => $score
                        ):

                        ?>


                            <tr>

                                <td>
                                    <?= $number++ ?>
                                </td>


                                <td>
                                    <?= e($label) ?>
                                </td>


                                <td>

                                    <?= e(
                                        rpt_score(
                                            $score
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= e(
                                        rpt_predicate(
                                            $score
                                        )
                                    ) ?>

                                </td>

                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="4"
                                class="legacy-empty"
                            >
                                Belum ada hasil ujian kenaikan level.
                            </td>

                        </tr>


                    <?php endif; ?>


                        <tr>

                            <th
                                colspan="2"
                                class="legacy-average"
                            >
                                RATA-RATA
                            </th>


                            <th>

                                <?= e(
                                    rpt_score(
                                        $levelAverage
                                    )
                                ) ?>

                            </th>


                            <th>

                                <?= e(
                                    rpt_predicate(
                                        $levelAverage
                                    )
                                ) ?>

                            </th>

                        </tr>

                    </tbody>

                </table>


                <?php

                render_legacy_note(
                    (string) $levelNote
                );


                render_legacy_signatures(
                    $principal,
                    $coordinator,
                    $levelDate
                        ? rpt_date(
                            $levelDate
                        )
                        : '-'
                );

                ?>

            </div>

        </div>

    </section>



    <!-- =====================================================
         RAPOR MUNAQOSYAH
         ===================================================== -->

    <section
        class="report-panel"
        data-report-panel="munaqosyah"
        hidden
    >

        <header class="report-toolbar">

            <div>

                <p class="report-eyebrow">
                    Template Rapor Resmi
                </p>


                <h2 class="report-heading">
                    Rapor Munaqosyah
                </h2>

            </div>


            <div class="report-actions">

                <button
                    type="button"
                    class="download-excel"
                    data-export-report="munaqosyah-report"
                    <?= !$latestMunaq
                        ? 'disabled'
                        : ''
                    ?>
                >
                    ↓ Download Excel
                </button>


                <button
                    type="button"
                    data-print-report="munaqosyah-report"
                    <?= !$latestMunaq
                        ? 'disabled'
                        : ''
                    ?>
                >
                    ▣ Cetak / Simpan PDF
                </button>

            </div>

        </header>


        <div class="legacy-report-wrap">

            <div
                class="legacy-report"
                id="munaqosyah-report"
                data-report-name="
                    rapor-munaqosyah
                "
            >


                <?php
                render_legacy_header(
                    'RAPOR MUNAQOSYAH',
                    $schoolLogo,
                    $foundationLogo
                );
                ?>


                <?php if ($schoolLogo): ?>

                    <img
                        src="<?= e(
                            $schoolLogo
                        ) ?>"
                        class="legacy-watermark"
                        alt=""
                    >

                <?php endif; ?>


                <div class="legacy-identity">

                    <?php

                    render_identity_row(
                        'Nama Peserta Didik',
                        $child['nama_lengkap']
                    );

                    render_identity_row(
                        'Jenjang Tahfidz',
                        rpt_level(
                            (int) $child[
                                'level'
                            ]
                        )
                    );

                    render_identity_row(
                        'NIS',
                        $child['nis']
                    );

                    render_identity_row(
                        'Tahun Ajaran',
                        $munaqYear
                    );

                    render_identity_row(
                        'Kelas',
                        $child['kelas']
                        ?: '-'
                    );

                    render_identity_row(
                        'Materi / Juz',
                        $munaqMaterial
                    );

                    ?>

                </div>


                <table
                    class="
                        legacy-table
                        legacy-table-info
                    "
                >

                    <tbody>

                        <tr>

                            <th>
                                Tanggal Ujian
                            </th>

                            <td>

                                <?= e(
                                    $munaqDate
                                        ? rpt_date(
                                            $munaqDate
                                        )
                                        : '-'
                                ) ?>

                            </td>


                            <th>
                                Hasil
                            </th>

                            <td>

                                <?= e(
                                    (string)
                                    $munaqResult
                                ) ?>

                            </td>

                        </tr>

                    </tbody>

                </table>


                <table
                    class="
                        legacy-table
                        center
                    "
                >

                    <thead>

                        <tr>

                            <th style="width:45px">
                                No
                            </th>

                            <th>
                                Kriteria Penilaian
                            </th>

                            <th style="width:110px">
                                Nilai
                            </th>

                            <th style="width:125px">
                                Keterangan
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (
                        $latestMunaq
                        && $munaqCriteria
                    ): ?>


                        <?php

                        $number = 1;

                        foreach (
                            $munaqCriteria
                            as $label => $score
                        ):

                        ?>


                            <tr>

                                <td>
                                    <?= $number++ ?>
                                </td>


                                <td>
                                    <?= e($label) ?>
                                </td>


                                <td>

                                    <?= e(
                                        rpt_score(
                                            $score
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= e(
                                        rpt_predicate(
                                            $score
                                        )
                                    ) ?>

                                </td>

                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="4"
                                class="legacy-empty"
                            >
                                Belum ada hasil Munaqosyah.
                            </td>

                        </tr>


                    <?php endif; ?>


                        <tr>

                            <th
                                colspan="2"
                                class="legacy-average"
                            >
                                RATA-RATA
                            </th>


                            <th>

                                <?= e(
                                    rpt_score(
                                        $munaqAverage
                                    )
                                ) ?>

                            </th>


                            <th>

                                <?= e(
                                    (string)
                                    $munaqPredicate
                                ) ?>

                            </th>

                        </tr>


                        <tr>

                            <th
                                colspan="2"
                                class="legacy-average"
                            >
                                HASIL
                            </th>


                            <td colspan="2">

                                <?= e(
                                    (string)
                                    $munaqResult
                                ) ?>

                            </td>

                        </tr>

                    </tbody>

                </table>


                <?php

                render_legacy_note(
                    (string) $munaqNote
                );


                render_legacy_signatures(
                    $principal,
                    $coordinator,
                    $munaqDate
                        ? rpt_date(
                            $munaqDate
                        )
                        : '-'
                );

                ?>

            </div>

        </div>

    </section>


<?php endif; ?>


</div>



<?php

/* =========================================================
   TEMPLATE FUNCTIONS
   ========================================================= */

function render_legacy_header(
    string $title,
    string $schoolLogo,
    string $foundationLogo
): void {
?>

    <header class="legacy-header">


        <?php if ($schoolLogo !== ''): ?>

            <img
                src="<?= e(
                    $schoolLogo
                ) ?>"
                class="
                    legacy-logo
                    left
                "
                alt=""
            >

        <?php endif; ?>


        <?php if ($foundationLogo !== ''): ?>

            <img
                src="<?= e(
                    $foundationLogo
                ) ?>"
                class="
                    legacy-logo
                    right
                "
                alt=""
            >

        <?php endif; ?>


        <span class="legacy-foundation">
            YAYASAN BANI SALEH
        </span>


        <span class="legacy-school">
            SEKOLAH DASAR ISLAM LABSCHOOL
            <br>
            BANI SALEH
        </span>


        <span class="legacy-report-name">
            <?= e($title) ?>
        </span>


        <div class="legacy-school-meta">

            <span>
                NPSN: 70010942
            </span>

            <span>
                TERAKREDITASI: A
            </span>

        </div>


        <div class="legacy-address">
            Jl. Pangeran RT 001/008 Desa Lubang Buaya,
            Kec. Setu, Kab. Bekasi
        </div>


        <div class="legacy-double-line"></div>

    </header>

<?php
}


function render_identity_row(
    string $label,
    mixed $value
): void {
?>

    <div class="legacy-ident-row">

        <span class="legacy-ident-label">
            <?= e($label) ?>
        </span>


        <span>:</span>


        <span class="legacy-ident-value">

            <?= e(
                (string) $value
            ) ?>

        </span>

    </div>

<?php
}


function render_legacy_note(
    string $text
): void {
?>

    <div class="legacy-note">

        <div class="legacy-note-title">
            CATATAN GURU
        </div>


        <div class="legacy-note-body">

            <?= e(
                $text !== ''
                    ? $text
                    : 'Belum ada catatan guru.'
            ) ?>

        </div>

    </div>

<?php
}


function render_legacy_signatures(
    string $principal,
    string $coordinator,
    string $date
): void {
?>

    <div class="legacy-signatures">


        <div>

            <div class="legacy-sign-heading">
                Orang Tua/Wali
            </div>


            <div class="legacy-sign-space"></div>


            ....................................

        </div>



        <div>

            <div class="legacy-sign-heading">
                Kepala Sekolah
            </div>


            <div class="legacy-sign-space"></div>


            <span class="legacy-sign-name">

                <?= e(
                    $principal
                ) ?>

            </span>

        </div>



        <div>

            <div class="legacy-sign-heading">

                Dikeluarkan di : Bekasi

                <br>

                Tanggal :
                <?= e($date) ?>

                <br><br>

                Koordinator Tahfidz

            </div>


            <div class="legacy-sign-space"></div>


            <span class="legacy-sign-name">

                <?= e(
                    $coordinator
                ) ?>

            </span>

        </div>


    </div>

<?php
}

?>



<script>
(() => {

    /* =====================================================
       SWITCH 3 RAPOR
       ===================================================== */

    const reportTabs =
        document.querySelectorAll(
            '[data-report-tab]'
        );


    const reportPanels =
        document.querySelectorAll(
            '[data-report-panel]'
        );


    function openReport(type) {

        reportTabs.forEach(tab => {

            const active =
                tab.dataset.reportTab
                === type;


            tab.classList.toggle(
                'active',
                active
            );


            tab.setAttribute(
                'aria-selected',
                active
                    ? 'true'
                    : 'false'
            );

        });


        reportPanels.forEach(panel => {

            panel.hidden =
                panel.dataset.reportPanel
                !== type;

        });


        sessionStorage.setItem(
            'ngajiyuk-report-tab',
            type
        );

    }


    reportTabs.forEach(tab => {

        tab.addEventListener(
            'click',
            () => {

                openReport(
                    tab.dataset.reportTab
                );

            }
        );

    });


    const savedTab =
        sessionStorage.getItem(
            'ngajiyuk-report-tab'
        );


    if (
        savedTab
        && document.querySelector(
            `[data-report-tab="${savedTab}"]`
        )
    ) {

        openReport(savedTab);

    } else {

        openReport('daily');

    }


    /* =====================================================
       PRINT / SAVE PDF
       ===================================================== */

    document
        .querySelectorAll(
            '[data-print-report]'
        )
        .forEach(button => {

            button.addEventListener(
                'click',
                () => {

                    if (button.disabled) {
                        return;
                    }


                    const report =
                        document.getElementById(
                            button.dataset
                                .printReport
                        );


                    if (!report) {
                        return;
                    }


                    document.body
                        .classList
                        .add(
                            'print-report'
                        );


                    report.classList
                        .add(
                            'legacy-print-target'
                        );


                    window.print();


                    window.setTimeout(
                        () => {

                            document.body
                                .classList
                                .remove(
                                    'print-report'
                                );


                            report.classList
                                .remove(
                                    'legacy-print-target'
                                );

                        },
                        500
                    );

                }
            );

        });


    /* =====================================================
       DOWNLOAD EXCEL
       ===================================================== */

    document
        .querySelectorAll(
            '[data-export-report]'
        )
        .forEach(button => {

            button.addEventListener(
                'click',
                () => {

                    if (button.disabled) {
                        return;
                    }


                    const report =
                        document.getElementById(
                            button.dataset
                                .exportReport
                        );


                    if (!report) {
                        return;
                    }


                    const fileName =
                        report.dataset
                            .reportName
                        || 'rapor';


                    /*
                     * Clone supaya watermark tidak
                     * mengganggu isi Excel.
                     */
                    const copy =
                        report.cloneNode(true);


                    copy
                        .querySelectorAll(
                            '.legacy-watermark'
                        )
                        .forEach(
                            element =>
                                element.remove()
                        );


                    const html = `
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset="UTF-8">

                            <style>

                                body {
                                    font-family:
                                        Arial,
                                        sans-serif;
                                }

                                table {
                                    width: 100%;
                                    border-collapse: collapse;
                                }

                                th,
                                td {
                                    border:
                                        1px solid #000;

                                    padding: 7px;
                                }

                                th {
                                    background: #eee;
                                    font-weight: bold;
                                }

                                .legacy-header {
                                    text-align: center;
                                }

                                .legacy-logo {
                                    width: 50px;
                                }

                            </style>

                        </head>

                        <body>
                            ${copy.outerHTML}
                        </body>

                        </html>
                    `;


                    const blob =
                        new Blob(
                            [
                                '\ufeff',
                                html
                            ],
                            {
                                type:
                                    'application/vnd.ms-excel;charset=utf-8'
                            }
                        );


                    const blobUrl =
                        URL.createObjectURL(
                            blob
                        );


                    const link =
                        document.createElement(
                            'a'
                        );


                    link.href =
                        blobUrl;


                    link.download =
                        `${fileName}.xls`;


                    document.body
                        .appendChild(
                            link
                        );


                    link.click();

                    link.remove();


                    URL.revokeObjectURL(
                        blobUrl
                    );

                }
            );

        });

})();
</script>


<?php

require ROOT_PATH
    . '/includes/footer.php';

?>