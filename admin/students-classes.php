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

function admin_sc_level_short(int $level): string
{
    return match ($level) {
        7 => 'M-1',
        8 => 'M-2',
        9 => 'M-3',
        default => 'Level ' . max(1, min(6, $level)),
    };
}


function admin_sc_score(mixed $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    $number = (float) $value;

    if ((float) (int) $number === $number) {
        return (string) (int) $number;
    }

    return rtrim(
        rtrim(
            number_format($number, 1, ',', '.'),
            '0'
        ),
        ','
    );
}


function admin_sc_url(array $params = []): string
{
    foreach ($params as $key => $value) {
        if (
            $value === ''
            || $value === null
            || $value === 0
            || $value === '0'
        ) {
            unset($params[$key]);
        }
    }

    $query = http_build_query($params);

    return url(
        'admin/students-classes.php'
        . ($query !== '' ? '?' . $query : '')
    );
}


/*
|--------------------------------------------------------------------------
| Filter
|--------------------------------------------------------------------------
*/

$selectedClass = normalize_class_name(
    (string) ($_GET['kelas'] ?? '1A')
);

if (
    !in_array(
        $selectedClass,
        all_class_names(),
        true
    )
) {
    $selectedClass = '1A';
}


$selectedYear = trim(
    (string) (
        $_GET['tahun_ajaran']
        ?? active_academic_year()
    )
);

if (!valid_academic_year($selectedYear)) {
    $selectedYear = active_academic_year();
}


$teacherFilter = trim(
    (string) ($_GET['guru'] ?? '')
);


$levelFilter = (int) (
    $_GET['level']
    ?? 0
);

if (
    $levelFilter < 0
    || $levelFilter > 9
) {
    $levelFilter = 0;
}


$activeTab = (string) (
    $_GET['tab']
    ?? 'scores'
);

if (
    !in_array(
        $activeTab,
        [
            'scores',
            'attendance',
        ],
        true
    )
) {
    $activeTab = 'scores';
}


/*
|--------------------------------------------------------------------------
| Daftar tahun ajaran
|--------------------------------------------------------------------------
*/

$yearRows = $pdo
    ->query(
        "
            SELECT tahun_ajaran
            FROM classes

            UNION

            SELECT tahun_ajaran
            FROM surah_curriculum

            ORDER BY tahun_ajaran DESC
        "
    )
    ->fetchAll(PDO::FETCH_COLUMN);


$academicYears = array_values(
    array_unique(
        array_filter(
            array_map(
                'strval',
                array_merge(
                    [
                        active_academic_year(),
                        $selectedYear,
                    ],
                    $yearRows
                )
            ),
            'valid_academic_year'
        )
    )
);

rsort($academicYears);


/*
|--------------------------------------------------------------------------
| Daftar Guru
|--------------------------------------------------------------------------
*/

$teachers = $pdo
    ->query(
        "
            SELECT
                id,
                full_name

            FROM users

            WHERE role = 'guru'
              AND is_active = 1
              AND approval_status = 'approved'

            ORDER BY full_name ASC
        "
    )
    ->fetchAll(PDO::FETCH_ASSOC);


$teacherIds = array_column(
    $teachers,
    'id'
);

if (
    $teacherFilter !== ''
    && !in_array(
        $teacherFilter,
        $teacherIds,
        true
    )
) {
    $teacherFilter = '';
}


/*
|--------------------------------------------------------------------------
| Master kelas 1A - 6B
|--------------------------------------------------------------------------
*/

$classStatement = $pdo->prepare(
    "
        SELECT
            c.id,
            c.nama_kelas,
            c.tingkat,
            c.rombel,
            c.teacher_id,
            c.wali_kelas,
            c.tahun_ajaran,
            c.aktif,

            u.full_name AS teacher_name,

            COUNT(
                DISTINCT CASE
                    WHEN s.status = 'aktif'
                    THEN s.id
                END
            ) AS total_students,

            SUM(
                CASE
                    WHEN s.status = 'aktif'
                     AND s.level = 1
                    THEN 1
                    ELSE 0
                END
            ) AS level_1,

            SUM(
                CASE
                    WHEN s.status = 'aktif'
                     AND s.level = 2
                    THEN 1
                    ELSE 0
                END
            ) AS level_2,

            SUM(
                CASE
                    WHEN s.status = 'aktif'
                     AND s.level = 3
                    THEN 1
                    ELSE 0
                END
            ) AS level_3,

            SUM(
                CASE
                    WHEN s.status = 'aktif'
                     AND s.level = 4
                    THEN 1
                    ELSE 0
                END
            ) AS level_4,

            SUM(
                CASE
                    WHEN s.status = 'aktif'
                     AND s.level = 5
                    THEN 1
                    ELSE 0
                END
            ) AS level_5,

            SUM(
                CASE
                    WHEN s.status = 'aktif'
                     AND s.level = 6
                    THEN 1
                    ELSE 0
                END
            ) AS level_6,

            SUM(
                CASE
                    WHEN s.status = 'aktif'
                     AND s.level = 7
                    THEN 1
                    ELSE 0
                END
            ) AS level_7,

            SUM(
                CASE
                    WHEN s.status = 'aktif'
                     AND s.level = 8
                    THEN 1
                    ELSE 0
                END
            ) AS level_8,

            SUM(
                CASE
                    WHEN s.status = 'aktif'
                     AND s.level = 9
                    THEN 1
                    ELSE 0
                END
            ) AS level_9

        FROM classes c

        LEFT JOIN users u
            ON u.id = c.teacher_id

        LEFT JOIN students s
            ON s.kelas = c.nama_kelas

        WHERE c.tahun_ajaran = ?

        GROUP BY
            c.id,
            c.nama_kelas,
            c.tingkat,
            c.rombel,
            c.teacher_id,
            c.wali_kelas,
            c.tahun_ajaran,
            c.aktif,
            u.full_name

        ORDER BY
            c.tingkat ASC,
            c.rombel ASC
    "
);

$classStatement->execute([
    $selectedYear
]);

$classRows = $classStatement
    ->fetchAll(PDO::FETCH_ASSOC);


$classMap = [];

foreach ($classRows as $row) {
    $classMap[
        (string) $row['nama_kelas']
    ] = $row;
}


/*
|--------------------------------------------------------------------------
| Total siswa sesuai filter global
|--------------------------------------------------------------------------
*/

$totalSql = "
    SELECT COUNT(*)
    FROM students
    WHERE status = 'aktif'
";

$totalParams = [];


if ($teacherFilter !== '') {
    $totalSql .= "
        AND teacher_id = ?
    ";

    $totalParams[] = $teacherFilter;
}


if ($levelFilter > 0) {
    $totalSql .= "
        AND level = ?
    ";

    $totalParams[] = $levelFilter;
}


$totalStatement = $pdo->prepare(
    $totalSql
);

$totalStatement->execute(
    $totalParams
);

$totalStudentsFiltered = (int) (
    $totalStatement->fetchColumn()
);


/*
|--------------------------------------------------------------------------
| Data kelas yang sedang dipilih
|--------------------------------------------------------------------------
*/

$selectedClassData =
    $classMap[$selectedClass]
    ?? null;


$currentTeacherId = (string) (
    $selectedClassData['teacher_id']
    ?? ''
);

$currentTeacherName = trim(
    (string) (
        $selectedClassData['teacher_name']
        ?? ''
    )
);

if ($currentTeacherName === '') {
    $currentTeacherName =
        'Belum ditentukan';
}


/*
|--------------------------------------------------------------------------
| Siswa kelas terpilih
|--------------------------------------------------------------------------
*/

$studentSql = "
    SELECT
        s.*,
        u.full_name AS teacher_name

    FROM students s

    LEFT JOIN users u
        ON u.id = s.teacher_id

    WHERE s.kelas = ?
      AND s.status = 'aktif'
";

$studentParams = [
    $selectedClass
];


if ($teacherFilter !== '') {
    $studentSql .= "
        AND s.teacher_id = ?
    ";

    $studentParams[] =
        $teacherFilter;
}


if ($levelFilter > 0) {
    $studentSql .= "
        AND s.level = ?
    ";

    $studentParams[] =
        $levelFilter;
}


$studentSql .= "
    ORDER BY
        s.nama_lengkap ASC
";


$studentStatement = $pdo->prepare(
    $studentSql
);

$studentStatement->execute(
    $studentParams
);

$students = $studentStatement
    ->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Total siswa kelas sebelum filter
|--------------------------------------------------------------------------
*/

$classTotalStatement = $pdo->prepare(
    "
        SELECT COUNT(*)
        FROM students
        WHERE kelas = ?
          AND status = 'aktif'
    "
);

$classTotalStatement->execute([
    $selectedClass
]);

$selectedClassTotal = (int) (
    $classTotalStatement
        ->fetchColumn()
);


/*
|--------------------------------------------------------------------------
| Level yang perlu dimunculkan pada tabel surat
|--------------------------------------------------------------------------
*/

if ($levelFilter > 0) {

    $curriculumLevels = [
        $levelFilter
    ];

} else {

    $curriculumLevels = [];

    foreach ($students as $student) {
        $level = (int) $student['level'];

        if (
            $level >= 1
            && $level <= 9
        ) {
            $curriculumLevels[] =
                $level;
        }
    }

    $curriculumLevels = array_values(
        array_unique(
            $curriculumLevels
        )
    );

    sort($curriculumLevels);

    if (!$curriculumLevels) {
        $curriculumLevels = [1];
    }
}


/*
|--------------------------------------------------------------------------
| Kurikulum surat sesuai level siswa
|--------------------------------------------------------------------------
*/

$levelPlaceholders = implode(
    ',',
    array_fill(
        0,
        count($curriculumLevels),
        '?'
    )
);

$curriculumSql = "
    SELECT
        level,
        nama_surah,
        urutan

    FROM surah_curriculum

    WHERE tahun_ajaran = ?
      AND level IN ($levelPlaceholders)

    ORDER BY
        level ASC,
        urutan ASC
";

$curriculumStatement = $pdo->prepare(
    $curriculumSql
);

$curriculumStatement->execute(
    array_merge(
        [
            $selectedYear
        ],
        $curriculumLevels
    )
);

$surahs = $curriculumStatement
    ->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Nilai Tahsin/Tahfidz
|--------------------------------------------------------------------------
|
| Semua catatan diambil berurutan lama -> baru.
| Kalau surat yang sama punya beberapa catatan,
| data paling akhir akan menjadi nilai yang ditampilkan.
|--------------------------------------------------------------------------
*/

$scoreSql = "
    SELECT
        t.*,
        s.level

    FROM laporan_tahsin_tahfidz t

    INNER JOIN students s
        ON s.id = t.student_id

    WHERE s.kelas = ?
      AND s.status = 'aktif'
      AND t.tahun_ajaran = ?
";

$scoreParams = [
    $selectedClass,
    $selectedYear,
];


if ($teacherFilter !== '') {
    $scoreSql .= "
        AND s.teacher_id = ?
    ";

    $scoreParams[] =
        $teacherFilter;
}


if ($levelFilter > 0) {
    $scoreSql .= "
        AND s.level = ?
    ";

    $scoreParams[] =
        $levelFilter;
}


$scoreSql .= "
    ORDER BY
        t.tanggal ASC,
        t.created_at ASC
";


$scoreStatement = $pdo->prepare(
    $scoreSql
);

$scoreStatement->execute(
    $scoreParams
);

$scoreRows = $scoreStatement
    ->fetchAll(PDO::FETCH_ASSOC);


$scoreMap = [];

foreach ($scoreRows as $score) {

    $studentId =
        (string) $score['student_id'];

    $surahName =
        (string) $score['nama_surah'];

    $scoreMap[$studentId][$surahName]
        = $score;
}


/*
|--------------------------------------------------------------------------
| Rentang tahun ajaran untuk laporan harian
|--------------------------------------------------------------------------
*/

$academicStartYear = (int) substr(
    $selectedYear,
    0,
    4
);

$academicStart =
    sprintf(
        '%04d-07-01',
        $academicStartYear
    );

$academicEnd =
    sprintf(
        '%04d-06-30',
        $academicStartYear + 1
    );


/*
|--------------------------------------------------------------------------
| Presensi terbaru per siswa
|--------------------------------------------------------------------------
*/

$dailySql = "
    SELECT
        d.*

    FROM daily_student_reports d

    INNER JOIN students s
        ON s.id = d.student_id

    WHERE s.kelas = ?
      AND s.status = 'aktif'
      AND d.tanggal BETWEEN ? AND ?
";

$dailyParams = [
    $selectedClass,
    $academicStart,
    $academicEnd,
];


if ($teacherFilter !== '') {
    $dailySql .= "
        AND s.teacher_id = ?
    ";

    $dailyParams[] =
        $teacherFilter;
}


if ($levelFilter > 0) {
    $dailySql .= "
        AND s.level = ?
    ";

    $dailyParams[] =
        $levelFilter;
}


$dailySql .= "
    ORDER BY
        d.tanggal DESC,
        d.created_at DESC
";


$dailyStatement = $pdo->prepare(
    $dailySql
);

$dailyStatement->execute(
    $dailyParams
);

$dailyRows = $dailyStatement
    ->fetchAll(PDO::FETCH_ASSOC);


$dailyMap = [];

foreach ($dailyRows as $daily) {

    $studentId =
        (string) $daily['student_id'];

    if (
        !isset(
            $dailyMap[$studentId]
        )
    ) {
        $dailyMap[$studentId] =
            $daily;
    }
}


/*
|--------------------------------------------------------------------------
| Tadarus terbaru per siswa
|--------------------------------------------------------------------------
*/

$tadarusSql = "
    SELECT
        t.*

    FROM laporan_tadarus_pagi t

    INNER JOIN students s
        ON s.id = t.student_id

    WHERE s.kelas = ?
      AND s.status = 'aktif'
      AND t.tanggal BETWEEN ? AND ?
";

$tadarusParams = [
    $selectedClass,
    $academicStart,
    $academicEnd,
];


if ($teacherFilter !== '') {
    $tadarusSql .= "
        AND s.teacher_id = ?
    ";

    $tadarusParams[] =
        $teacherFilter;
}


if ($levelFilter > 0) {
    $tadarusSql .= "
        AND s.level = ?
    ";

    $tadarusParams[] =
        $levelFilter;
}


$tadarusSql .= "
    ORDER BY
        t.tanggal DESC,
        t.created_at DESC
";


$tadarusStatement = $pdo->prepare(
    $tadarusSql
);

$tadarusStatement->execute(
    $tadarusParams
);

$tadarusRows = $tadarusStatement
    ->fetchAll(PDO::FETCH_ASSOC);


$tadarusMap = [];

foreach ($tadarusRows as $tadarus) {

    $studentId =
        (string) $tadarus['student_id'];

    if (
        !isset(
            $tadarusMap[$studentId]
        )
    ) {
        $tadarusMap[$studentId] =
            $tadarus;
    }
}


$pageTitle =
    'Siswa & Kelas';

require ROOT_PATH
    . '/includes/header.php';

?>


<style>

/* =========================================================
   SISWA & KELAS - ADMIN
   ========================================================= */

.school-data-page {
    --sk-green: #174f3d;
    --sk-green-dark: #123e31;
    --sk-emerald: #00865d;
    --sk-emerald-2: #009b6b;
    --sk-mint: #eafbf3;

    --sk-text: #0d1726;
    --sk-text-soft: #536176;
    --sk-muted: #7e8a9c;

    --sk-line: #e6ebe8;
    --sk-bg: #ffffff;

    width: 100%;
}


/* =========================================================
   PAGE HEAD
   ========================================================= */

.sk-page-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;

    gap: 24px;

    margin-bottom: 25px;
}

.sk-page-eyebrow {
    margin: 0 0 8px;

    color: #009367;

    font-size: 10px;
    font-weight: 900;

    letter-spacing: .21em;
    text-transform: uppercase;
}

.sk-page-title {
    margin: 0;

    color: #070d17;

    font-size: clamp(
        30px,
        2.5vw,
        39px
    );

    font-weight: 900;

    line-height: 1.05;

    letter-spacing: -.045em;
}

.sk-page-description {
    margin: 10px 0 0;

    color: #627086;

    font-size: 12px;

    line-height: 1.6;
}

.sk-update-pill {
    flex: 0 0 auto;

    display: inline-flex;
    align-items: center;

    gap: 7px;

    min-height: 37px;

    padding: 8px 14px;

    border: 1px solid #dde4e0;
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

.sk-update-pill svg {
    color: #00a875;
}

.sk-update-pill:hover {
    border-color: #bad8ca;

    color: var(--sk-green);
}


/* =========================================================
   MAIN HUB
   ========================================================= */

.sk-hub {
    overflow: hidden;

    border: 1px solid #bde8d5;
    border-radius: 21px;

    background: #ffffff;

    box-shadow:
        0 2px 6px
        rgba(16, 24, 40, .04);
}


/* =========================================================
   HUB HEADER
   ========================================================= */

.sk-hub-head {
    padding: 24px 24px 20px;

    display: flex;
    align-items: flex-end;
    justify-content: space-between;

    gap: 24px;

    background:
        linear-gradient(
            100deg,
            #ecfff6 0%,
            #ffffff 70%
        );
}

.sk-hub-eyebrow {
    margin: 0 0 7px;

    color: #00966a;

    font-size: 10px;
    font-weight: 900;

    letter-spacing: .18em;
    text-transform: uppercase;
}

.sk-hub-title {
    margin: 0;

    color: #0c1727;

    font-size: 22px;
    font-weight: 900;

    letter-spacing: -.025em;
}

.sk-hub-description {
    max-width: 650px;

    margin: 8px 0 0;

    color: #718096;

    font-size: 10.5px;

    line-height: 1.55;
}

.sk-refresh-button {
    min-height: 42px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 8px;

    padding: 9px 15px;

    border: 1px solid #8de2bd;
    border-radius: 11px;

    background: #fff;

    color: #00754f;

    font-size: 10px;
    font-weight: 900;

    text-decoration: none;
}

.sk-refresh-button:hover {
    background: #effbf5;
}


/* =========================================================
   FILTERS
   ========================================================= */

.sk-filter-area {
    padding:
        0 24px 20px;

    background:
        linear-gradient(
            100deg,
            #ecfff6 0%,
            #ffffff 70%
        );

    border-bottom: 1px solid #e4ece8;
}

.sk-filter-grid {
    display: grid;

    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    gap: 14px;
}

.sk-field label {
    display: block;

    margin-bottom: 7px;

    color: #576477;

    font-size: 8.5px;
    font-weight: 900;

    letter-spacing: .04em;

    text-transform: uppercase;
}

.sk-field select {
    width: 100%;
    height: 42px;

    padding: 8px 13px;

    border: 1px solid #d9e0dc;
    border-radius: 11px;

    outline: 0;

    background: #fff;

    color: #152033;

    font-family: inherit;

    font-size: 11px;
    font-weight: 800;
}

.sk-field select:hover {
    border-color: #c4d0ca;
}

.sk-field select:focus {
    border-color: #51a387;

    box-shadow:
        0 0 0 3px
        rgba(0, 143, 98, .08);
}


/* =========================================================
   MASTER KELAS
   ========================================================= */

.sk-master-section {
    padding: 18px 18px 20px;

    border-bottom: 1px solid var(--sk-line);
}

.sk-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 18px;

    margin-bottom: 12px;
}

.sk-section-title {
    margin: 0;

    color: #101828;

    font-size: 13px;
    font-weight: 900;
}

.sk-section-subtitle {
    margin: 5px 0 0;

    color: #7c8998;

    font-size: 9px;
}

.sk-total-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 25px;

    padding: 5px 10px;

    border-radius: 999px;

    background: #eafaf2;

    color: #008452;

    font-size: 9px;
    font-weight: 900;
}


/* master table */

.sk-master-scroll {
    overflow-x: auto;

    border: 1px solid #e5e9e7;
    border-radius: 14px;
}

.sk-master-table {
    width: 100%;

    min-width: 1050px;

    border-collapse: separate;
    border-spacing: 0;

    table-layout: fixed;
}

.sk-master-table thead th {
    height: 42px;

    padding: 10px 12px;

    border-right:
        1px solid rgba(
            255,
            255,
            255,
            .08
        );

    background: var(--sk-green);

    color: #fff;

    font-size: 8.5px;
    font-weight: 900;

    text-align: center;

    white-space: nowrap;
}

.sk-master-table thead th:first-child {
    width: 105px;

    text-align: left;
}

.sk-master-table thead th:last-child {
    width: 235px;

    text-align: left;
}

.sk-master-table tbody td {
    height: 52px;

    padding: 10px 12px;

    border-right: 1px solid #edf0ee;
    border-bottom: 1px solid #edf0ee;

    color: #27364a;

    font-size: 9.5px;
    font-weight: 750;

    text-align: center;
}

.sk-master-table tbody td:first-child,
.sk-master-table tbody td:last-child {
    text-align: left;
}

.sk-master-table tbody tr:last-child td {
    border-bottom: 0;
}

.sk-master-table tbody td:last-child {
    border-right: 0;
}

.sk-master-table tbody tr:hover td {
    background: #fbfdfc;
}

.sk-master-table
tbody
tr.selected td {
    background: #eafbf3;
}

.sk-master-class {
    min-width: 52px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 8px 12px;

    border-radius: 11px;

    background: #f1f3f4;

    color: #111827;

    font-size: 10px;
    font-weight: 900;

    text-decoration: none;
}

.sk-master-class.active {
    background: #00a36d;

    color: #fff;
}

.sk-master-level-value {
    color: #23334a;

    font-weight: 850;
}

.sk-master-total {
    color: #00764f;

    font-size: 13px;
    font-weight: 900;
}

.sk-master-teacher {
    display: block;

    overflow: hidden;

    color: #415069;

    text-overflow: ellipsis;
    white-space: nowrap;
}


/* =========================================================
   WALI KELAS
   ========================================================= */

.sk-teacher-section {
    padding: 20px 22px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 25px;

    border-bottom: 1px solid var(--sk-line);

    background: #fbfcfc;
}

.sk-mini-eyebrow {
    margin: 0 0 6px;

    color: #00885d;

    font-size: 9px;
    font-weight: 900;

    letter-spacing: .15em;

    text-transform: uppercase;
}

.sk-teacher-title {
    margin: 0;

    color: #101828;

    font-size: 18px;
    font-weight: 900;

    letter-spacing: -.02em;
}

.sk-teacher-description {
    margin: 7px 0 0;

    color: #718096;

    font-size: 10.5px;
}

.sk-teacher-form {
    display: flex;
    align-items: flex-end;

    gap: 12px;
}

.sk-teacher-select {
    min-width: 280px;
}

.sk-teacher-select label {
    display: block;

    margin-bottom: 6px;

    color: #5b6676;

    font-size: 8.5px;
    font-weight: 900;

    text-transform: uppercase;
}

.sk-teacher-select select {
    width: 100%;
    height: 42px;

    padding: 8px 12px;

    border: 1px solid #dae1dd;
    border-radius: 10px;

    outline: 0;

    background: #fff;

    color: #172033;

    font-family: inherit;

    font-size: 10.5px;
    font-weight: 800;
}

.sk-save-teacher {
    height: 42px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 8px;

    padding: 8px 15px;

    border: 0;
    border-radius: 10px;

    background: var(--sk-green);

    color: #fff;

    cursor: pointer;

    font-family: inherit;

    font-size: 10px;
    font-weight: 900;

    white-space: nowrap;
}

.sk-save-teacher:hover {
    background: var(--sk-green-dark);
}

.sk-save-teacher:disabled {
    cursor: not-allowed;
    opacity: .55;
}


/* =========================================================
   NILAI + LAPORAN
   ========================================================= */

.sk-report-section {
    background: #fff;
}

.sk-report-head {
    padding: 20px 22px;

    display: flex;
    align-items: flex-end;
    justify-content: space-between;

    gap: 20px;
}

.sk-report-title {
    margin: 0;

    color: #0b1422;

    font-size: 22px;
    font-weight: 900;

    letter-spacing: -.025em;
}

.sk-report-description {
    margin: 7px 0 0;

    color: #7b8798;

    font-size: 10.5px;
}


/* tabs */

.sk-tabs {
    display: flex;
    align-items: center;

    gap: 3px;

    padding: 4px;

    border-radius: 11px;

    background: #f0f2f3;
}

.sk-tab-button {
    min-height: 36px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 7px;

    padding: 7px 12px;

    border: 0;
    border-radius: 8px;

    background: transparent;

    color: #687588;

    cursor: pointer;

    font-family: inherit;

    font-size: 9.5px;
    font-weight: 900;
}

.sk-tab-button.active {
    background: #fff;

    color: #007654;

    box-shadow:
        0 2px 5px
        rgba(16, 24, 40, .08);
}

.sk-tab-panel {
    display: none;
}

.sk-tab-panel.active {
    display: block;
}


/* =========================================================
   SCORE MATRIX
   ========================================================= */

.sk-score-scroll {
    position: relative;

    max-width: 100%;

    overflow: auto;

    border-top: 1px solid #e8ecea;
}

.sk-score-table {
    width: max-content;

    min-width: 100%;

    border-collapse: separate;
    border-spacing: 0;
}

.sk-score-table th,
.sk-score-table td {
    border-right: 1px solid #dfe6e2;
    border-bottom: 1px solid #dfe6e2;
}

.sk-score-table thead th {
    position: sticky;

    top: 0;

    z-index: 5;

    text-align: center;
}


/*
 * Kolom pertama
 */

.sk-score-base {
    background: var(--sk-green) !important;

    color: #fff !important;

    font-size: 8px;
    font-weight: 900;
}

.sk-score-no {
    width: 50px;
    min-width: 50px;
}

.sk-score-name {
    width: 250px;
    min-width: 250px;

    text-align: left !important;
}

.sk-score-level {
    width: 150px;
    min-width: 150px;

    text-align: left !important;
}


/* group surat */

.sk-surah-group {
    min-width: 735px;

    height: 39px;

    padding: 8px 10px;

    background: #008b61;

    color: #fff;

    font-size: 10px;
    font-weight: 900;
}

.sk-surah-sub {
    width: 105px;
    min-width: 105px;

    height: 37px;

    padding: 7px 6px;

    background: #e9faf2;

    color: #315548;

    font-size: 7px;
    font-weight: 900;

    text-transform: uppercase;

    white-space: nowrap;
}


/* cells */

.sk-score-table tbody td {
    height: 58px;

    padding: 9px 9px;

    background: #fff;

    color: #35445b;

    font-size: 9px;

    text-align: center;
}

.sk-score-table tbody tr:nth-child(even) td {
    background: #fbfcfc;
}

.sk-score-table tbody tr:hover td {
    background: #f7fbf9;
}

.sk-student-name {
    display: block;

    color: #111827;

    font-size: 10.5px;
    font-weight: 900;
}

.sk-student-nis {
    display: block;

    margin-top: 4px;

    color: #919cac;

    font-size: 8px;
}

.sk-student-level {
    display: block;

    color: #111827;

    font-size: 9.5px;
    font-weight: 900;
}

.sk-student-teacher {
    display: block;

    max-width: 145px;

    margin-top: 4px;

    overflow: hidden;

    color: #8c98a8;

    font-size: 8px;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.sk-score-value {
    color: #27364a;

    font-weight: 800;
}

.sk-score-average {
    color: #007654;

    font-weight: 900;
}

.sk-score-note {
    max-width: 100px;

    overflow: hidden;

    text-overflow: ellipsis;
    white-space: nowrap;
}


/* sticky first columns */

.sk-score-table
.sk-sticky-no {
    position: sticky;
    left: 0;

    z-index: 6;
}

.sk-score-table
.sk-sticky-name {
    position: sticky;
    left: 50px;

    z-index: 6;
}

.sk-score-table
.sk-sticky-level {
    position: sticky;
    left: 300px;

    z-index: 6;
}

.sk-score-table tbody
.sk-sticky-no,
.sk-score-table tbody
.sk-sticky-name,
.sk-score-table tbody
.sk-sticky-level {
    z-index: 3;

    background: #fff;
}

.sk-score-table tbody
tr:nth-child(even)
.sk-sticky-no,
.sk-score-table tbody
tr:nth-child(even)
.sk-sticky-name,
.sk-score-table tbody
tr:nth-child(even)
.sk-sticky-level {
    background: #fbfcfc;
}


/* =========================================================
   PRESENSI & TADARUS
   ========================================================= */

.sk-attendance-wrap {
    overflow-x: auto;

    border-top: 1px solid var(--sk-line);
}

.sk-attendance-table {
    width: 100%;

    min-width: 1050px;

    border-collapse: collapse;
}

.sk-attendance-table thead th {
    height: 43px;

    padding: 10px 13px;

    background: var(--sk-green);

    color: #fff;

    font-size: 8.5px;
    font-weight: 900;

    text-align: left;

    text-transform: uppercase;
}

.sk-attendance-table tbody td {
    height: 57px;

    padding: 10px 13px;

    border-bottom: 1px solid var(--sk-line);

    color: #415069;

    font-size: 9.5px;

    vertical-align: middle;
}

.sk-attendance-table tbody tr:hover td {
    background: #fbfcfb;
}

.sk-presence-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-width: 55px;

    padding: 5px 8px;

    border-radius: 999px;

    font-size: 8.5px;
    font-weight: 900;
}

.sk-presence-badge.hadir {
    background: #eafbf3;
    color: #008451;
}

.sk-presence-badge.izin {
    background: #fff8df;
    color: #b96600;
}

.sk-presence-badge.sakit {
    background: #eef5ff;
    color: #175cd3;
}

.sk-presence-badge.alpa {
    background: #fff0f0;
    color: #d62d2d;
}

.sk-presence-badge.empty {
    background: #f2f4f5;
    color: #8994a3;
}


/* =========================================================
   EMPTY
   ========================================================= */

.sk-empty {
    min-width: 100%;

    padding: 45px 20px;

    color: #7c8896;

    text-align: center;
}

.sk-empty strong {
    display: block;

    margin-bottom: 6px;

    color: #344054;

    font-size: 13px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 1000px) {

    .sk-filter-grid {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

    .sk-field:last-child {
        grid-column: 1 / -1;
    }

    .sk-teacher-section {
        align-items: flex-start;

        flex-direction: column;
    }

    .sk-teacher-form {
        width: 100%;
    }

    .sk-teacher-select {
        flex: 1;

        min-width: 0;
    }

}


@media (max-width: 820px) {

    .sk-page-head,
    .sk-hub-head,
    .sk-report-head {
        align-items: flex-start;

        flex-direction: column;
    }

    .sk-filter-grid {
        grid-template-columns: 1fr;
    }

    .sk-field:last-child {
        grid-column: auto;
    }

    .sk-teacher-form {
        align-items: stretch;

        flex-direction: column;
    }

    .sk-save-teacher {
        width: 100%;
    }

    .sk-tabs {
        width: 100%;
    }

    .sk-tab-button {
        flex: 1;
    }

}


@media (max-width: 520px) {

    .sk-page-title {
        font-size: 28px;
    }

    .sk-hub-title,
    .sk-report-title {
        font-size: 19px;
    }

}

</style>


<div class="school-data-page">


    <!-- =====================================================
         PAGE HEADER
         ===================================================== -->

    <header class="sk-page-head">

        <div>

            <p class="sk-page-eyebrow">
                Data Sekolah
            </p>

            <h1 class="sk-page-title">
                Manajemen Siswa &amp; Kelas
            </h1>

            <p class="sk-page-description">
                Pantau anggota kelas, tentukan wali kelas mengaji,
                dan kelola data nilai siswa.
            </p>

        </div>


        <a
            class="sk-update-pill"
            href="<?= e(
                admin_sc_url([
                    'kelas' =>
                        $selectedClass,

                    'tahun_ajaran' =>
                        $selectedYear,

                    'guru' =>
                        $teacherFilter,

                    'level' =>
                        $levelFilter,

                    'tab' =>
                        $activeTab,
                ])
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
         DATA HUB
         ===================================================== -->

    <section class="sk-hub">


        <!-- HEADER -->

        <header class="sk-hub-head">

            <div>

                <p class="sk-hub-eyebrow">
                    Versi Administrator
                </p>

                <h2 class="sk-hub-title">
                    Pusat Data Kelas &amp; Nilai
                </h2>

                <p class="sk-hub-description">
                    Tampilan seperti Data Kelas Guru dengan cakupan
                    seluruh Guru, kelas, level, nilai per surat,
                    serta Presensi–Tadarus.
                </p>

            </div>


            <a
                class="sk-refresh-button"
                href="<?= e(
                    admin_sc_url([
                        'kelas' =>
                            $selectedClass,

                        'tahun_ajaran' =>
                            $selectedYear,

                        'guru' =>
                            $teacherFilter,

                        'level' =>
                            $levelFilter,

                        'tab' =>
                            $activeTab,
                    ])
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
                    <path
                        d="
                            M20 6v6h-6
                        "
                    ></path>

                    <path
                        d="
                            M4 18v-6h6
                        "
                    ></path>

                    <path
                        d="
                            M18.5 9
                            A7 7 0 0 0
                            6 6
                            L4 8
                        "
                    ></path>

                    <path
                        d="
                            M5.5 15
                            A7 7 0 0 0
                            18 18
                            l2-2
                        "
                    ></path>
                </svg>

                Perbarui Data

            </a>

        </header>


        <!-- =================================================
             FILTER
             ================================================= -->

        <form
            method="get"
            action="<?= e(
                url(
                    'admin/students-classes.php'
                )
            ) ?>"
            class="sk-filter-area"
            id="school-filter-form"
        >

            <input
                type="hidden"
                name="kelas"
                value="<?= e(
                    $selectedClass
                ) ?>"
            >


            <div class="sk-filter-grid">


                <!-- Tahun ajaran -->

                <div class="sk-field">

                    <label for="filter-year">
                        Tahun Ajaran
                    </label>

                    <select
                        id="filter-year"
                        name="tahun_ajaran"
                        data-auto-submit
                    >

                        <?php foreach (
                            $academicYears
                            as $year
                        ): ?>

                            <option
                                value="<?= e($year) ?>"
                                <?= $year === $selectedYear
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= e($year) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Guru -->

                <div class="sk-field">

                    <label for="filter-teacher">
                        Guru Pengampu
                    </label>

                    <select
                        id="filter-teacher"
                        name="guru"
                        data-auto-submit
                    >

                        <option value="">
                            Semua Guru
                        </option>

                        <?php foreach (
                            $teachers
                            as $teacher
                        ): ?>

                            <option
                                value="<?= e(
                                    $teacher['id']
                                ) ?>"
                                <?= $teacherFilter
                                    === $teacher['id']
                                        ? 'selected'
                                        : ''
                                ?>
                            >
                                <?= e(
                                    $teacher[
                                        'full_name'
                                    ]
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Level -->

                <div class="sk-field">

                    <label for="filter-level">
                        Filter Level
                    </label>

                    <select
                        id="filter-level"
                        name="level"
                        data-auto-submit
                    >

                        <option value="0">
                            Semua Level Tahfidz
                        </option>

                        <?php for (
                            $level = 1;
                            $level <= 9;
                            $level++
                        ): ?>

                            <option
                                value="<?= $level ?>"
                                <?= $levelFilter
                                    === $level
                                        ? 'selected'
                                        : ''
                                ?>
                            >
                                <?= e(
                                    admin_sc_level_short(
                                        $level
                                    )
                                ) ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </div>

            </div>

        </form>



        <!-- =================================================
             MASTER KELAS
             ================================================= -->

        <section class="sk-master-section">

            <header class="sk-section-head">

                <div>

                    <h3 class="sk-section-title">
                        Master Kelas 1A–6B
                    </h3>

                    <p class="sk-section-subtitle">
                        Klik kelas untuk membuka rekap akademiknya.
                    </p>

                </div>


                <span class="sk-total-badge">

                    <?= number_format(
                        $totalStudentsFiltered,
                        0,
                        ',',
                        '.'
                    ) ?>
                    siswa

                </span>

            </header>


            <div class="sk-master-scroll">

                <table class="sk-master-table">

                    <thead>

                        <tr>

                            <th>Kelas</th>

                            <?php for (
                                $level = 1;
                                $level <= 6;
                                $level++
                            ): ?>

                                <th>
                                    Level <?= $level ?>
                                </th>

                            <?php endfor; ?>

                            <th>M-1</th>
                            <th>M-2</th>
                            <th>M-3</th>

                            <th>Total</th>

                            <th>
                                Guru/Wali Kelas
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php
                    $visibleClassCount = 0;
                    ?>


                    <?php foreach (
                        all_class_names()
                        as $className
                    ): ?>

                        <?php

                        $row =
                            $classMap[$className]
                            ?? null;

                        $rowTeacherId =
                            (string) (
                                $row['teacher_id']
                                ?? ''
                            );


                        if (
                            $teacherFilter !== ''
                            && $rowTeacherId
                                !== $teacherFilter
                        ) {
                            continue;
                        }


                        $visibleClassCount++;


                        $classUrl =
                            admin_sc_url([
                                'kelas' =>
                                    $className,

                                'tahun_ajaran' =>
                                    $selectedYear,

                                'guru' =>
                                    $teacherFilter,

                                'level' =>
                                    $levelFilter,

                                'tab' =>
                                    $activeTab,
                            ]);

                        ?>

                        <tr
                            class="<?= $className
                                === $selectedClass
                                    ? 'selected'
                                    : ''
                            ?>"
                        >

                            <td>

                                <a
                                    class="
                                        sk-master-class
                                        <?= $className
                                            === $selectedClass
                                                ? 'active'
                                                : ''
                                        ?>
                                    "
                                    href="<?= e(
                                        $classUrl
                                    ) ?>"
                                >
                                    <?= e(
                                        $className
                                    ) ?>
                                </a>

                            </td>


                            <?php for (
                                $level = 1;
                                $level <= 9;
                                $level++
                            ): ?>

                                <?php

                                $value = (int) (
                                    $row[
                                        'level_' . $level
                                    ]
                                    ?? 0
                                );

                                ?>

                                <td>

                                    <span
                                        class="
                                            sk-master-level-value
                                        "
                                    >

                                        <?= $value > 0
                                            ? $value
                                            : '-'
                                        ?>

                                    </span>

                                </td>

                            <?php endfor; ?>


                            <td>

                                <strong
                                    class="
                                        sk-master-total
                                    "
                                >
                                    <?= (int) (
                                        $row[
                                            'total_students'
                                        ]
                                        ?? 0
                                    ) ?>
                                </strong>

                            </td>


                            <td>

                                <span
                                    class="
                                        sk-master-teacher
                                    "
                                >

                                    <?= e(
                                        (string) (
                                            $row[
                                                'teacher_name'
                                            ]
                                            ?? 'Belum ditentukan'
                                        )
                                    ) ?>

                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>


                    <?php if (
                        $visibleClassCount === 0
                    ): ?>

                        <tr>

                            <td colspan="12">

                                <div class="sk-empty">

                                    <strong>
                                        Tidak ada kelas
                                    </strong>

                                    Tidak ada kelas yang sesuai
                                    dengan Guru yang dipilih.

                                </div>

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>



        <!-- =================================================
             WALI KELAS
             ================================================= -->

        <section class="sk-teacher-section">

            <div>

                <p class="sk-mini-eyebrow">

                    Kelas
                    <?= e($selectedClass) ?>
                    ·
                    <?= e($selectedYear) ?>

                </p>

                <h3 class="sk-teacher-title">
                    Wali Kelas Mengaji
                </h3>

                <p class="sk-teacher-description">
                    Satu Guru penanggung jawab untuk kegiatan
                    mengaji kelas ini.
                </p>

            </div>


            <form
                action="<?= e(
                    url(
                        'api/classes/index.php'
                    )
                ) ?>"
                method="post"
                data-ajax
                class="sk-teacher-form"
            >

                <?= csrf_field() ?>


                <input
                    type="hidden"
                    name="action"
                    value="assign_teacher"
                >


                <input
                    type="hidden"
                    name="kelas"
                    value="<?= e(
                        $selectedClass
                    ) ?>"
                >


                <input
                    type="hidden"
                    name="tahun_ajaran"
                    value="<?= e(
                        $selectedYear
                    ) ?>"
                >


                <div class="sk-teacher-select">

                    <label>
                        Pilih Guru
                    </label>

                    <select
                        name="teacher_id"
                    >

                        <option value="">
                            Belum ada wali mengaji
                        </option>

                        <?php foreach (
                            $teachers
                            as $teacher
                        ): ?>

                            <option
                                value="<?= e(
                                    $teacher['id']
                                ) ?>"
                                <?= $currentTeacherId
                                    === $teacher['id']
                                        ? 'selected'
                                        : ''
                                ?>
                            >

                                <?= e(
                                    $teacher[
                                        'full_name'
                                    ]
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <button
                    type="submit"
                    class="sk-save-teacher"
                    <?= !$selectedClassData
                        ? 'disabled'
                        : ''
                    ?>
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

                    Simpan Wali Kelas

                </button>

            </form>

        </section>



        <!-- =================================================
             NILAI & LAPORAN
             ================================================= -->

        <section class="sk-report-section">

            <header class="sk-report-head">

                <div>

                    <p class="sk-mini-eyebrow">

                        Kelas
                        <?= e(
                            $selectedClass
                        ) ?>

                    </p>

                    <h3 class="sk-report-title">
                        Data Nilai &amp; Laporan Harian
                    </h3>

                    <p class="sk-report-description">

                        <?= count($students) ?>
                        siswa tampil dari

                        <?= $selectedClassTotal ?>
                        siswa.

                    </p>

                </div>


                <div
                    class="sk-tabs"
                    role="tablist"
                >

                    <button
                        type="button"
                        class="sk-tab-button"
                        data-modal-open="admin-class-export"
                    >
                        <?= svg_icon('file', 15) ?>
                        Ekspor Excel
                    </button>

                    <button
                        type="button"
                        class="
                            sk-tab-button
                            <?= $activeTab
                                === 'scores'
                                    ? 'active'
                                    : ''
                            ?>
                        "
                        data-school-tab="scores"
                        role="tab"
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
                                    M4 19.5
                                    A2.5 2.5 0 0 1
                                    6.5 17
                                    H20
                                    V3
                                    H6.5
                                    A2.5 2.5 0 0 0
                                    4 5.5
                                    z
                                "
                            ></path>

                            <path
                                d="M12 3v14"
                            ></path>
                        </svg>

                        Nilai per Surat

                    </button>


                    <button
                        type="button"
                        class="
                            sk-tab-button
                            <?= $activeTab
                                === 'attendance'
                                    ? 'active'
                                    : ''
                            ?>
                        "
                        data-school-tab="attendance"
                        role="tab"
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
                                    M9 5h6
                                    M9 3h6v4H9z
                                "
                            ></path>

                            <rect
                                x="5"
                                y="5"
                                width="14"
                                height="17"
                                rx="2"
                            ></rect>

                            <path
                                d="
                                    m9 14
                                    2 2
                                    4-5
                                "
                            ></path>
                        </svg>

                        Presensi &amp; Tadarus

                    </button>

                </div>

            </header>



            <!-- =============================================
                 TAB NILAI
                 ============================================= -->

            <div
                class="
                    sk-tab-panel
                    <?= $activeTab
                        === 'scores'
                            ? 'active'
                            : ''
                    ?>
                "
                data-school-panel="scores"
            >

                <?php if (
                    $students
                    && $surahs
                ): ?>

                    <div class="sk-score-scroll">

                        <table class="sk-score-table">

                            <thead>

                                <tr>

                                    <th
                                        rowspan="2"
                                        class="
                                            sk-score-base
                                            sk-score-no
                                            sk-sticky-no
                                        "
                                    >
                                        No
                                    </th>

                                    <th
                                        rowspan="2"
                                        class="
                                            sk-score-base
                                            sk-score-name
                                            sk-sticky-name
                                        "
                                    >
                                        Nama Peserta Didik
                                    </th>

                                    <th
                                        rowspan="2"
                                        class="
                                            sk-score-base
                                            sk-score-level
                                            sk-sticky-level
                                        "
                                    >
                                        Guru &amp; Level
                                    </th>


                                    <?php foreach (
                                        $surahs
                                        as $surah
                                    ): ?>

                                        <th
                                            colspan="7"
                                            class="
                                                sk-surah-group
                                            "
                                        >
                                            <?= e(
                                                $surah[
                                                    'nama_surah'
                                                ]
                                            ) ?>
                                        </th>

                                    <?php endforeach; ?>

                                </tr>


                                <tr>

                                    <?php foreach (
                                        $surahs
                                        as $surah
                                    ): ?>

                                        <th class="sk-surah-sub">
                                            Kelancaran
                                        </th>

                                        <th class="sk-surah-sub">
                                            Makhrojul Huruf
                                        </th>

                                        <th class="sk-surah-sub">
                                            Hukum Tajwid
                                        </th>

                                        <th class="sk-surah-sub">
                                            Sambung Ayat
                                        </th>

                                        <th class="sk-surah-sub">
                                            Jumlah
                                        </th>

                                        <th class="sk-surah-sub">
                                            Rata-rata
                                        </th>

                                        <th class="sk-surah-sub">
                                            Ket.
                                        </th>

                                    <?php endforeach; ?>

                                </tr>

                            </thead>


                            <tbody>

                            <?php foreach (
                                $students
                                as $index => $student
                            ): ?>

                                <?php

                                $studentId =
                                    (string) $student['id'];

                                ?>

                                <tr>


                                    <!-- No -->

                                    <td
                                        class="
                                            sk-sticky-no
                                        "
                                    >
                                        <?= $index + 1 ?>
                                    </td>


                                    <!-- Nama -->

                                    <td
                                        class="
                                            sk-sticky-name
                                        "
                                        style="
                                            text-align:left;
                                        "
                                    >

                                        <strong
                                            class="
                                                sk-student-name
                                            "
                                        >
                                            <?= e(
                                                $student[
                                                    'nama_lengkap'
                                                ]
                                            ) ?>
                                        </strong>

                                        <span
                                            class="
                                                sk-student-nis
                                            "
                                        >
                                            NIS
                                            <?= e(
                                                $student[
                                                    'nis'
                                                ]
                                            ) ?>
                                        </span>

                                    </td>


                                    <!-- Guru level -->

                                    <td
                                        class="
                                            sk-sticky-level
                                        "
                                        style="
                                            text-align:left;
                                        "
                                    >

                                        <strong
                                            class="
                                                sk-student-level
                                            "
                                        >

                                            <?= e(
                                                admin_sc_level_short(
                                                    (int) $student[
                                                        'level'
                                                    ]
                                                )
                                            ) ?>

                                        </strong>

                                        <span
                                            class="
                                                sk-student-teacher
                                            "
                                        >

                                            <?= e(
                                                (string) (
                                                    $student[
                                                        'teacher_name'
                                                    ]
                                                    ?? 'Belum ditentukan'
                                                )
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- Surat -->

                                    <?php foreach (
                                        $surahs
                                        as $surah
                                    ): ?>

                                        <?php

                                        $surahName =
                                            (string) $surah[
                                                'nama_surah'
                                            ];

                                        $score =
                                            $scoreMap[
                                                $studentId
                                            ][
                                                $surahName
                                            ]
                                            ?? null;


                                        $scoreValues = [];

                                        if ($score) {

                                            foreach (
                                                [
                                                    'nilai_kelancaran',
                                                    'nilai_makhraj',
                                                    'nilai_tajwid',
                                                    'nilai_hafalan',
                                                ]
                                                as $field
                                            ) {

                                                if (
                                                    $score[$field]
                                                    !== null
                                                    && $score[$field]
                                                    !== ''
                                                ) {
                                                    $scoreValues[] =
                                                        (float) $score[
                                                            $field
                                                        ];
                                                }
                                            }
                                        }


                                        $totalScore =
                                            $scoreValues
                                                ? array_sum(
                                                    $scoreValues
                                                )
                                                : null;


                                        $average =
                                            $score[
                                                'nilai_rata_rata'
                                            ]
                                            ?? (
                                                $scoreValues
                                                    ? (
                                                        array_sum(
                                                            $scoreValues
                                                        )
                                                        / count(
                                                            $scoreValues
                                                        )
                                                    )
                                                    : null
                                            );

                                        ?>


                                        <td class="sk-score-value">

                                            <?= admin_sc_score(
                                                $score[
                                                    'nilai_kelancaran'
                                                ]
                                                ?? null
                                            ) ?>

                                        </td>


                                        <td class="sk-score-value">

                                            <?= admin_sc_score(
                                                $score[
                                                    'nilai_makhraj'
                                                ]
                                                ?? null
                                            ) ?>

                                        </td>


                                        <td class="sk-score-value">

                                            <?= admin_sc_score(
                                                $score[
                                                    'nilai_tajwid'
                                                ]
                                                ?? null
                                            ) ?>

                                        </td>


                                        <td class="sk-score-value">

                                            <?= admin_sc_score(
                                                $score[
                                                    'nilai_hafalan'
                                                ]
                                                ?? null
                                            ) ?>

                                        </td>


                                        <td class="sk-score-value">

                                            <?= admin_sc_score(
                                                $totalScore
                                            ) ?>

                                        </td>


                                        <td class="sk-score-average">

                                            <?= admin_sc_score(
                                                $average
                                            ) ?>

                                        </td>


                                        <td
                                            class="
                                                sk-score-note
                                            "
                                            title="<?= e(
                                                (string) (
                                                    $score[
                                                        'keterangan'
                                                    ]
                                                    ?? ''
                                                )
                                            ) ?>"
                                        >

                                            <?= e(
                                                trim(
                                                    (string) (
                                                        $score[
                                                            'keterangan'
                                                        ]
                                                        ?? ''
                                                    )
                                                )
                                                ?: '-'
                                            ) ?>

                                        </td>

                                    <?php endforeach; ?>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>


                <?php else: ?>

                    <div class="sk-empty">

                        <strong>
                            Belum ada data nilai
                        </strong>

                        <?php if (!$students): ?>

                            Belum ada siswa aktif sesuai
                            filter pada kelas ini.

                        <?php else: ?>

                            Kurikulum surat untuk level
                            dan tahun ajaran ini belum tersedia.

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </div>



            <!-- =============================================
                 TAB PRESENSI & TADARUS
                 ============================================= -->

            <div
                class="
                    sk-tab-panel
                    <?= $activeTab
                        === 'attendance'
                            ? 'active'
                            : ''
                    ?>
                "
                data-school-panel="attendance"
            >

                <?php if ($students): ?>

                    <div class="sk-attendance-wrap">

                        <table
                            class="
                                sk-attendance-table
                            "
                        >

                            <thead>

                                <tr>

                                    <th>No</th>

                                    <th>
                                        Nama Peserta Didik
                                    </th>

                                    <th>
                                        Guru &amp; Level
                                    </th>

                                    <th>
                                        Presensi Terakhir
                                    </th>

                                    <th>
                                        Tanggal
                                    </th>

                                    <th>
                                        Tadarus Terakhir
                                    </th>

                                    <th>
                                        Hal/Ayat
                                    </th>

                                    <th>
                                        Keterangan
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                            <?php foreach (
                                $students
                                as $index => $student
                            ): ?>

                                <?php

                                $studentId =
                                    (string) $student['id'];

                                $daily =
                                    $dailyMap[
                                        $studentId
                                    ]
                                    ?? null;

                                $tadarus =
                                    $tadarusMap[
                                        $studentId
                                    ]
                                    ?? null;


                                $presence =
                                    trim(
                                        (string) (
                                            $daily[
                                                'status_presensi'
                                            ]
                                            ?? ''
                                        )
                                    );


                                $presenceClass =
                                    match (
                                        strtolower(
                                            $presence
                                        )
                                    ) {
                                        'hadir'
                                            => 'hadir',

                                        'izin'
                                            => 'izin',

                                        'sakit'
                                            => 'sakit',

                                        'alpa'
                                            => 'alpa',

                                        default
                                            => 'empty',
                                    };

                                ?>

                                <tr>

                                    <td>
                                        <?= $index + 1 ?>
                                    </td>


                                    <td>

                                        <strong
                                            class="
                                                sk-student-name
                                            "
                                        >
                                            <?= e(
                                                $student[
                                                    'nama_lengkap'
                                                ]
                                            ) ?>
                                        </strong>

                                        <span
                                            class="
                                                sk-student-nis
                                            "
                                        >
                                            NIS
                                            <?= e(
                                                $student[
                                                    'nis'
                                                ]
                                            ) ?>
                                        </span>

                                    </td>


                                    <td>

                                        <strong
                                            class="
                                                sk-student-level
                                            "
                                        >

                                            <?= e(
                                                admin_sc_level_short(
                                                    (int) $student[
                                                        'level'
                                                    ]
                                                )
                                            ) ?>

                                        </strong>

                                        <span
                                            class="
                                                sk-student-teacher
                                            "
                                        >

                                            <?= e(
                                                (string) (
                                                    $student[
                                                        'teacher_name'
                                                    ]
                                                    ?? 'Belum ditentukan'
                                                )
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <span
                                            class="
                                                sk-presence-badge
                                                <?= e(
                                                    $presenceClass
                                                ) ?>
                                            "
                                        >

                                            <?= e(
                                                $presence !== ''
                                                    ? $presence
                                                    : 'Belum ada'
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?= $daily
                                            ? e(
                                                format_date_id(
                                                    $daily[
                                                        'tanggal'
                                                    ]
                                                )
                                            )
                                            : '-'
                                        ?>

                                    </td>


                                    <td>

                                        <?= e(
                                            (string) (
                                                $tadarus[
                                                    'nama_surah'
                                                ]
                                                ?? '-'
                                            )
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= e(
                                            (string) (
                                                $tadarus[
                                                    'hal_ayat'
                                                ]
                                                ?? '-'
                                            )
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= e(
                                            trim(
                                                (string) (
                                                    $tadarus[
                                                        'keterangan'
                                                    ]
                                                    ?? $daily[
                                                        'catatan_guru'
                                                    ]
                                                    ?? ''
                                                )
                                            )
                                            ?: '-'
                                        ) ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>


                <?php else: ?>

                    <div class="sk-empty">

                        <strong>
                            Belum ada siswa
                        </strong>

                        Tidak ada siswa aktif sesuai
                        filter yang dipilih.

                    </div>

                <?php endif; ?>

            </div>

        </section>

        <div class="modal" id="admin-class-export" aria-hidden="true">
            <div class="modal-card">
                <div class="modal-head">
                    <div>
                        <p class="eyebrow">EKSPOR DATA KELAS</p>
                        <h2>Kelas <?= e($selectedClass) ?></h2>
                        <p class="muted">XLSX berisi kop, dua logo, dan kolom yang sudah dirapikan. CSV tetap tersedia sebagai data mentah.</p>
                    </div>
                    <button class="modal-close" type="button" data-modal-close aria-label="Tutup">&times;</button>
                </div>
                <div class="form-grid">
                    <?php foreach ([
                        'daily' => 'Presensi & Tadarus',
                        'surah' => 'Nilai per Surat',
                        'level' => 'Ujian Kenaikan Level',
                        'munaqosyah' => 'Form Munaqosyah',
                    ] as $exportType => $exportLabel): ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 15px;border:1px solid #dfe6e2;border-radius:13px">
                            <strong><?= e($exportLabel) ?></strong>
                            <span style="display:flex;gap:7px">
                                <a class="btn btn-primary btn-sm" href="<?= e(url('api/classes/export.php') . '?' . http_build_query([
                                    'kelas' => $selectedClass,
                                    'tahun_ajaran' => $selectedYear,
                                    'type' => $exportType,
                                    'format' => 'xlsx',
                                ])) ?>"><?= svg_icon('file', 14) ?> XLSX</a>
                                <a class="btn btn-soft btn-sm" href="<?= e(url('api/classes/export.php') . '?' . http_build_query([
                                    'kelas' => $selectedClass,
                                    'tahun_ajaran' => $selectedYear,
                                    'type' => $exportType,
                                    'format' => 'csv',
                                ])) ?>">CSV</a>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </section>

</div>



<script>
(() => {

    /*
    |--------------------------------------------------------------------------
    | Auto submit filter
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll(
            '[data-auto-submit]'
        )
        .forEach(select => {

            select.addEventListener(
                'change',
                () => {

                    const form =
                        select.closest('form');

                    if (form) {
                        form.submit();
                    }

                }
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Tabs Nilai / Presensi
    |--------------------------------------------------------------------------
    */

    const buttons =
        document.querySelectorAll(
            '[data-school-tab]'
        );

    const panels =
        document.querySelectorAll(
            '[data-school-panel]'
        );


    buttons.forEach(button => {

        button.addEventListener(
            'click',
            () => {

                const selected =
                    button.dataset
                        .schoolTab;


                buttons.forEach(
                    item => {

                        item.classList
                            .toggle(
                                'active',
                                item === button
                            );

                    }
                );


                panels.forEach(
                    panel => {

                        panel.classList
                            .toggle(
                                'active',
                                panel.dataset
                                    .schoolPanel
                                    === selected
                            );

                    }
                );


                /*
                 * Simpan tab ke URL supaya kalau
                 * refresh tetap kembali ke tab yang sama.
                 */
                const url =
                    new URL(
                        window.location.href
                    );

                url.searchParams.set(
                    'tab',
                    selected
                );

                window.history.replaceState(
                    {},
                    '',
                    url
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
