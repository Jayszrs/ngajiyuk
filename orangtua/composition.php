<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

$user = require_role('orang_tua');
$pdo = db();


/*
|--------------------------------------------------------------------------
| TAHUN AJARAN
|--------------------------------------------------------------------------
*/

$academicYear = active_academic_year();


/*
|--------------------------------------------------------------------------
| TARGET SURAT PER LEVEL
|--------------------------------------------------------------------------
|
| Ambil langsung dari kurikulum database.
|--------------------------------------------------------------------------
*/

$curriculumStmt = $pdo->prepare(
    "
        SELECT
            level,
            nama_surah,
            urutan

        FROM surah_curriculum

        WHERE tahun_ajaran = ?

        ORDER BY
            level ASC,
            urutan ASC
    "
);

$curriculumStmt->execute([
    $academicYear
]);

$curriculumRows = $curriculumStmt->fetchAll(
    PDO::FETCH_ASSOC
);


/*
|--------------------------------------------------------------------------
| GROUP PER LEVEL
|--------------------------------------------------------------------------
*/

$levelTargets = [];

for ($level = 1; $level <= 9; $level++) {
    $levelTargets[$level] = [];
}


foreach ($curriculumRows as $row) {

    $level = (int) (
        $row['level']
        ?? 0
    );

    if (
        $level >= 1
        && $level <= 9
    ) {

        $surah = trim(
            (string) (
                $row['nama_surah']
                ?? ''
            )
        );

        if ($surah !== '') {
            $levelTargets[$level][] = $surah;
        }
    }
}


/*
|--------------------------------------------------------------------------
| FALLBACK
|--------------------------------------------------------------------------
|
| Kalau kurikulum tahun ajaran belum diisi,
| tampilkan standar awal seperti desain referensi.
|--------------------------------------------------------------------------
*/

$fallbackTargets = [

    1 => [
        'An-Nas',
        'Al-Falaq',
        'Al-Ikhlas',
        'Al-Lahab',
        'An-Nasr',
        'Al-Kafirun',
        'Al-Kautsar',
        'Al-Ma\'un',
    ],

    2 => [
        'Quraisy',
        'Al-Fil',
        'Al-Humazah',
        'Al-Asr',
        'At-Takasur',
        'Al-Qari\'ah',
        'Al-Adiyat',
        'Az-Zalzalah',
    ],

    3 => [
        'Al-Bayyinah',
        'Al-Qadr',
        'Al-Alaq',
        'At-Tin',
        'Asy-Syarh',
        'Ad-Dhuha',
        'Al-Lail',
        'Asy-Syams',
        'Al-Balad',
    ],

    4 => [
        'Al-Fajr',
        'Al-Ghasyiyah',
        'Al-A\'la',
        'At-Tariq',
        'Al-Buruj',
    ],

    5 => [
        'Al-Insyiqaq',
        'Al-Muthaffifin',
        'Al-Infitar',
        'At-Takwir',
    ],

    6 => [
        'Abasa',
        'An-Naziat',
        'An-Naba',
        'Muroja\'ah Juz 30',
        'Surah Pilihan',
    ],

    7 => [
        'Al-Mulk',
        'Al-Qalam',
        'Al-Haqqah',
        'Al-Ma\'arij',
        'Nuh',
    ],

    8 => [
        'Al-Jinn',
        'Al-Muzammil',
        'Al-Muddasir',
    ],

    9 => [
        'Al-Qiyamah',
        'Al-Insan',
        'Al-Mursalat',
    ],
];


foreach ($levelTargets as $level => $targets) {

    if (!$targets) {
        $levelTargets[$level] =
            $fallbackTargets[$level];
    }
}


/*
|--------------------------------------------------------------------------
| LABEL LEVEL
|--------------------------------------------------------------------------
*/

function parent_composition_level_name(
    int $level
): string {

    return match ($level) {

        7 => 'Mustawa Muttawasit 1',

        8 => 'Mustawa Muttawasit 2',

        9 => 'Mustawa Muttawasit 3',

        default => 'Level ' . $level,
    };
}


/*
|--------------------------------------------------------------------------
| KOMPOSISI NILAI
|--------------------------------------------------------------------------
*/

$gradingScale = [

    [
        'category' => 'Mumtaz',
        'meaning' => 'Istimewa',
        'scale' => '90 - 100',
        'grade' => 'A',
        'tone' => 'green',
    ],

    [
        'category' => 'Jayyid Jiddan',
        'meaning' => 'Sangat Bagus',
        'scale' => '80 - 89,99',
        'grade' => 'A-',
        'tone' => 'green',
    ],

    [
        'category' => 'Jayyid',
        'meaning' => 'Bagus',
        'scale' => '65 - 79,99',
        'grade' => 'B',
        'tone' => 'blue',
    ],

    [
        'category' => 'Maqbul',
        'meaning' => 'Diterima/Lulus',
        'scale' => '50 - 64,99',
        'grade' => 'C',
        'tone' => 'yellow',
    ],

    [
        'category' => 'Dhaif',
        'meaning' => 'Lemah',
        'scale' => '35 - 49,99',
        'grade' => 'D',
        'tone' => 'orange',
    ],

    [
        'category' => 'Dhaif Jiddan',
        'meaning' => 'Sangat Lemah',
        'scale' => '0 - 34,99',
        'grade' => 'E',
        'tone' => 'red',
    ],
];


/*
|--------------------------------------------------------------------------
| PREDIKAT KELULUSAN
|--------------------------------------------------------------------------
*/

$graduationCategories = [

    [
        'number' => 1,
        'title' => "Mustawa Ibtida'i",
        'subtitle' => 'Tingkat Pemula',
        'description' => 'Juz 30',
    ],

    [
        'number' => 2,
        'title' => 'Mustawa Mutawassit',
        'subtitle' => 'Tingkat Menengah',
        'description' =>
            'Juz 30, Ayat Kursi, Ar-Rahman, Al-Mulk, '
            . 'Al-Waqiah, dan surah pilihan',
    ],

    [
        'number' => 3,
        'title' => 'Mustawa Mutaqoddim',
        'subtitle' => 'Tingkat Lanjutan',
        'description' =>
            'Juz 30, surah pilihan, dan hadits pilihan',
    ],
];


$pageTitle = 'Komposisi Nilai';

require ROOT_PATH
    . '/includes/header.php';

?>


<style>

/* =========================================================
   ORANG TUA - KOMPOSISI NILAI
   ========================================================= */

.parent-composition-page {
    --cp-green: #174f3d;
    --cp-green-dark: #103e30;
    --cp-emerald: #00a36d;

    --cp-text: #101828;
    --cp-muted: #68768a;

    --cp-border: #e1e6e3;
    --cp-soft: #f8faf9;

    width: 100%;
}


/* =========================================================
   PAGE HEADER
   ========================================================= */

.cp-page-head {
    margin-bottom: 24px;
}


.cp-page-title {
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


.cp-page-description {
    margin: 10px 0 0;

    color: #637187;

    font-size: 12px;

    line-height: 1.6;
}


/* =========================================================
   TOP GRID
   ========================================================= */

.cp-top-grid {
    display: grid;

    grid-template-columns:
        minmax(0, .82fr)
        minmax(0, 1.18fr);

    gap: 18px;

    align-items: start;
}


/* =========================================================
   CARD
   ========================================================= */

.cp-card {
    overflow: hidden;

    border: 1px solid var(--cp-border);
    border-radius: 20px;

    background: #fff;

    box-shadow:
        0 2px 6px
        rgba(16, 24, 40, .04);
}


.cp-score-card {
    border-top:
        3px solid var(--cp-green);
}


.cp-target-card {
    border-top:
        3px solid #20be54;
}


.cp-card-inner {
    padding: 21px;
}


/* =========================================================
   CARD HEADER
   ========================================================= */

.cp-card-header {
    display: flex;
    align-items: center;

    gap: 12px;

    margin-bottom: 18px;
}


.cp-card-icon {
    width: 38px;
    height: 38px;

    flex: 0 0 38px;

    display: grid;
    place-items: center;

    border-radius: 11px;

    background: #edf2ef;

    color: var(--cp-green);
}


.cp-card-icon svg {
    width: 19px;
    height: 19px;
}


.cp-card-title {
    margin: 0;

    color: #101828;

    font-size: 17px;
    font-weight: 900;

    letter-spacing: -.02em;
}


.cp-card-subtitle {
    margin: 4px 0 0;

    color: #7b8797;

    font-size: 9px;

    line-height: 1.45;
}


/* =========================================================
   GRADING TABLE
   ========================================================= */

.cp-grade-table-wrap {
    overflow: hidden;

    border: 1px solid #dde3e0;
    border-radius: 13px;
}


.cp-grade-table {
    width: 100%;

    border-collapse: collapse;
}


.cp-grade-table thead th {
    height: 38px;

    padding: 9px 14px;

    border-bottom: 1px solid #dfe4e2;

    background: #f7f8f9;

    color: #617083;

    font-size: 8px;
    font-weight: 900;

    text-align: left;

    text-transform: uppercase;
}


.cp-grade-table thead th:first-child {
    width: 42px;

    text-align: center;
}


.cp-grade-table thead th:last-child {
    width: 70px;

    text-align: center;
}


.cp-grade-table tbody td {
    height: 47px;

    padding: 9px 14px;

    border-bottom:
        1px solid #e8ecea;

    color: #35445a;

    font-size: 10.5px;

    vertical-align: middle;
}


.cp-grade-table tbody tr:last-child td {
    border-bottom: 0;
}


.cp-grade-table tbody tr:hover td {
    background: #fbfcfb;
}


.cp-grade-number {
    color: #8995a5 !important;

    font-size: 10px !important;
    font-weight: 850;

    text-align: center;
}


.cp-grade-category {
    color: #101828 !important;

    font-weight: 900;
}


.cp-grade-scale {
    color: #101828 !important;

    font-weight: 900;
}


/* grade badge */

.cp-grade-badge {
    min-width: 29px;
    min-height: 29px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    font-size: 11px;
    font-weight: 900;
}


.cp-grade-badge.green {
    border: 1px solid #9be6bb;

    background: #edfff5;

    color: #00884f;
}


.cp-grade-badge.blue {
    border: 1px solid #b7d4ff;

    background: #eef5ff;

    color: #155bd8;
}


.cp-grade-badge.yellow {
    border: 1px solid #ffd463;

    background: #fff9e7;

    color: #a86100;
}


.cp-grade-badge.orange {
    border: 1px solid #ffc598;

    background: #fff4eb;

    color: #dc5f00;
}


.cp-grade-badge.red {
    border: 1px solid #ffc0c2;

    background: #fff2f2;

    color: #d92d20;
}


/* =========================================================
   INFORMATION
   ========================================================= */

.cp-guide-box {
    margin-top: 14px;

    padding: 16px;

    display: grid;

    grid-template-columns:
        20px 1fr;

    gap: 10px;

    border: 1px solid #c8dcff;
    border-radius: 13px;

    background: #edf5ff;
}


.cp-guide-icon {
    color: #2774ed;
}


.cp-guide-icon svg {
    width: 17px;
    height: 17px;
}


.cp-guide-title {
    margin: 0;

    color: #17469d;

    font-size: 12px;
    font-weight: 900;
}


.cp-guide-text {
    margin: 7px 0 0;

    color: #1850ae;

    font-size: 9px;

    line-height: 1.6;
}


/* =========================================================
   TARGET LEVEL
   ========================================================= */

.cp-level-grid {
    display: grid;

    grid-template-columns:
        repeat(
            3,
            minmax(0, 1fr)
        );

    gap: 10px;
}


.cp-level-card {
    min-height: 114px;

    padding: 13px;

    border: 1px solid #dde3e0;
    border-radius: 13px;

    background: #fbfcfb;
}


.cp-level-card:hover {
    border-color: #c8d7cf;

    background: #f8fcfa;
}


.cp-level-head {
    display: flex;
    align-items: center;

    gap: 8px;

    margin-bottom: 9px;
}


.cp-level-number {
    width: 27px;
    height: 27px;

    flex: 0 0 27px;

    display: grid;
    place-items: center;

    border-radius: 7px;

    background: var(--cp-green);

    color: #fff;

    font-size: 9px;
    font-weight: 900;
}


.cp-level-name {
    color: #101828;

    font-size: 11px;
    font-weight: 900;
}


.cp-level-target {
    margin: 0;

    color: #47566c;

    font-size: 9px;

    line-height: 1.65;
}


/* =========================================================
   GRADUATION SECTION
   ========================================================= */

.cp-graduation {
    margin-top: 18px;

    padding: 22px;

    border: 1px solid var(--cp-border);
    border-radius: 20px;

    background: #fff;

    box-shadow:
        0 2px 6px
        rgba(16, 24, 40, .04);
}


.cp-graduation-head {
    display: flex;
    align-items: center;

    gap: 12px;

    margin-bottom: 20px;
}


.cp-graduation-grid {
    display: grid;

    grid-template-columns:
        repeat(
            3,
            minmax(0, 1fr)
        );

    gap: 14px;
}


.cp-graduation-card {
    min-height: 120px;

    padding: 16px;

    display: flex;
    align-items: flex-start;

    gap: 11px;

    border: 1px solid #dfe4e2;
    border-radius: 13px;

    background: #fff;
}


.cp-graduation-number {
    width: 35px;
    height: 35px;

    flex: 0 0 35px;

    display: grid;
    place-items: center;

    border-radius: 10px;

    background: #faf2ff;

    color: #7d20db;

    font-size: 10px;
    font-weight: 900;
}


.cp-graduation-title {
    margin: 1px 0 0;

    color: #101828;

    font-size: 12px;
    font-weight: 900;
}


.cp-graduation-subtitle {
    display: block;

    margin-top: 5px;

    color: #69768a;

    font-size: 9px;
}


.cp-graduation-description {
    margin: 10px 0 0;

    color: #3f4e63;

    font-size: 9.5px;

    line-height: 1.6;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 1150px) {

    .cp-top-grid {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 800px) {

    .cp-level-grid {
        grid-template-columns:
            repeat(
                2,
                minmax(0, 1fr)
            );
    }


    .cp-graduation-grid {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 520px) {

    .cp-page-title {
        font-size: 28px;
    }


    .cp-level-grid {
        grid-template-columns: 1fr;
    }


    .cp-card-inner,
    .cp-graduation {
        padding: 17px;
    }


    .cp-grade-table {
        min-width: 540px;
    }


    .cp-grade-table-wrap {
        overflow-x: auto;
    }

}

</style>



<div class="parent-composition-page">


    <!-- =====================================================
         PAGE HEADER
         ===================================================== -->

    <header class="cp-page-head">

        <h1 class="cp-page-title">
            Komposisi Nilai
        </h1>

        <p class="cp-page-description">
            Panduan skala penilaian harian Tahsin &amp;
            Tahfidz sesuai standar sekolah.
        </p>

    </header>



    <!-- =====================================================
         TOP CONTENT
         ===================================================== -->

    <div class="cp-top-grid">


        <!-- =================================================
             KOMPOSISI NILAI
             ================================================= -->

        <section class="cp-card cp-score-card">

            <div class="cp-card-inner">


                <header class="cp-card-header">

                    <div class="cp-card-icon">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <circle
                                cx="12"
                                cy="8"
                                r="4"
                            ></circle>

                            <path
                                d="
                                    m9 12
                                    -1 9
                                    4-3
                                    4 3
                                    -1-9
                                "
                            ></path>
                        </svg>

                    </div>


                    <div>

                        <h2 class="cp-card-title">
                            Tabel Komposisi Nilai
                        </h2>

                        <p class="cp-card-subtitle">
                            Skala nilai dan predikat yang
                            digunakan sekolah.
                        </p>

                    </div>

                </header>



                <!-- TABLE -->

                <div class="cp-grade-table-wrap">

                    <table class="cp-grade-table">

                        <thead>

                            <tr>

                                <th>No</th>

                                <th>Kategori</th>

                                <th>Arti</th>

                                <th>Skala</th>

                                <th>Huruf</th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach (
                            $gradingScale
                            as $index => $grade
                        ): ?>

                            <tr>

                                <td class="cp-grade-number">

                                    <?= $index + 1 ?>

                                </td>


                                <td class="cp-grade-category">

                                    <?= e(
                                        $grade[
                                            'category'
                                        ]
                                    ) ?>

                                </td>


                                <td>

                                    <?= e(
                                        $grade[
                                            'meaning'
                                        ]
                                    ) ?>

                                </td>


                                <td class="cp-grade-scale">

                                    <?= e(
                                        $grade[
                                            'scale'
                                        ]
                                    ) ?>

                                </td>


                                <td style="text-align:center">

                                    <span
                                        class="
                                            cp-grade-badge
                                            <?= e(
                                                $grade[
                                                    'tone'
                                                ]
                                            ) ?>
                                        "
                                    >

                                        <?= e(
                                            $grade[
                                                'grade'
                                            ]
                                        ) ?>

                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>



                <!-- GUIDE -->

                <div class="cp-guide-box">

                    <div class="cp-guide-icon">

                        <svg
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

                    </div>


                    <div>

                        <h3 class="cp-guide-title">
                            Panduan Penggunaan
                        </h3>

                        <p class="cp-guide-text">
                            Gunakan skala ini pada Presensi &amp;
                            Harian, Ujian Kenaikan Level, dan
                            Form Munaqosyah sesuai kualitas
                            bacaan serta hafalan siswa.
                        </p>

                    </div>

                </div>

            </div>

        </section>



        <!-- =================================================
             TARGET HAFALAN
             ================================================= -->

        <section class="cp-card cp-target-card">

            <div class="cp-card-inner">


                <header class="cp-card-header">

                    <div class="cp-card-icon">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <circle
                                cx="12"
                                cy="12"
                                r="8"
                            ></circle>

                            <circle
                                cx="12"
                                cy="12"
                                r="5"
                            ></circle>

                            <circle
                                cx="12"
                                cy="12"
                                r="2"
                            ></circle>
                        </svg>

                    </div>


                    <div>

                        <h2 class="cp-card-title">
                            Target Hafalan per Level
                        </h2>

                        <p class="cp-card-subtitle">
                            Ringkasan surat yang perlu dikuasai
                            pada setiap jenjang.
                        </p>

                    </div>

                </header>



                <div class="cp-level-grid">

                    <?php for (
                        $level = 1;
                        $level <= 9;
                        $level++
                    ): ?>

                        <article class="cp-level-card">


                            <header class="cp-level-head">

                                <span class="cp-level-number">
                                    <?= $level ?>
                                </span>

                                <strong class="cp-level-name">

                                    <?= e(
                                        parent_composition_level_name(
                                            $level
                                        )
                                    ) ?>

                                </strong>

                            </header>


                            <p class="cp-level-target">

                                <?= e(
                                    implode(
                                        ', ',
                                        $levelTargets[
                                            $level
                                        ]
                                    )
                                ) ?>

                            </p>

                        </article>

                    <?php endfor; ?>

                </div>

            </div>

        </section>

    </div>



    <!-- =====================================================
         KATEGORI KELULUSAN
         ===================================================== -->

    <section class="cp-graduation">


        <header class="cp-graduation-head">

            <div class="cp-card-icon">

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
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

            </div>


            <div>

                <h2 class="cp-card-title">
                    Kategori Predikat Kelulusan
                </h2>

                <p class="cp-card-subtitle">
                    Target umum berdasarkan tingkat
                    kemampuan siswa.
                </p>

            </div>

        </header>



        <div class="cp-graduation-grid">

            <?php foreach (
                $graduationCategories
                as $category
            ): ?>

                <article class="cp-graduation-card">


                    <span class="cp-graduation-number">

                        <?= $category[
                            'number'
                        ] ?>

                    </span>


                    <div>

                        <h3 class="cp-graduation-title">

                            <?= e(
                                $category[
                                    'title'
                                ]
                            ) ?>

                        </h3>


                        <span class="cp-graduation-subtitle">

                            <?= e(
                                $category[
                                    'subtitle'
                                ]
                            ) ?>

                        </span>


                        <p class="cp-graduation-description">

                            <?= e(
                                $category[
                                    'description'
                                ]
                            ) ?>

                        </p>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    </section>

</div>


<?php

require ROOT_PATH
    . '/includes/footer.php';

?>