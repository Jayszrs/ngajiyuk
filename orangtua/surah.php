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

function parent_surah_level_title(int $level): string
{
    return match ($level) {
        7 => 'Mustawa Muttawasit 1',
        8 => 'Mustawa Muttawasit 2',
        9 => 'Mustawa Muttawasit 3',
        default => 'Level ' . $level,
    };
}


function parent_surah_jenjang(int $level): string
{
    return match ($level) {
        7 => 'Mustawa 1',
        8 => 'Mustawa 2',
        9 => 'Mustawa 3',
        default => 'Jenjang ' . $level,
    };
}


/*
|--------------------------------------------------------------------------
| TAHUN AJARAN
|--------------------------------------------------------------------------
*/

$selectedYear = trim(
    (string) (
        $_GET['tahun_ajaran']
        ?? active_academic_year()
    )
);


if (!valid_academic_year($selectedYear)) {
    $selectedYear = active_academic_year();
}


/*
|--------------------------------------------------------------------------
| DAFTAR TAHUN AJARAN
|--------------------------------------------------------------------------
*/

$yearStmt = $pdo->query(
    "
        SELECT DISTINCT
            tahun_ajaran

        FROM surah_curriculum

        WHERE tahun_ajaran IS NOT NULL
          AND tahun_ajaran <> ''

        ORDER BY
            tahun_ajaran DESC
    "
);


$yearRows = $yearStmt->fetchAll(
    PDO::FETCH_COLUMN
);


$academicYears = array_values(
    array_unique(
        array_filter(
            array_merge(
                [
                    active_academic_year(),
                    $selectedYear,
                ],
                array_map(
                    'strval',
                    $yearRows
                )
            ),
            static fn ($year) =>
                valid_academic_year(
                    (string) $year
                )
        )
    )
);


rsort($academicYears);


/*
|--------------------------------------------------------------------------
| DATA KURIKULUM
|--------------------------------------------------------------------------
*/

$curriculumStmt = $pdo->prepare(
    "
        SELECT
            id,
            level,
            nama_surah,
            urutan,
            tahun_ajaran

        FROM surah_curriculum

        WHERE tahun_ajaran = ?

        ORDER BY
            level ASC,
            urutan ASC,
            id ASC
    "
);


$curriculumStmt->execute([
    $selectedYear
]);


$curriculumRows = $curriculumStmt->fetchAll(
    PDO::FETCH_ASSOC
);


/*
|--------------------------------------------------------------------------
| KELOMPOKKAN SURAT BERDASARKAN LEVEL
|--------------------------------------------------------------------------
*/

$levels = [];

for ($level = 1; $level <= 9; $level++) {

    $levels[$level] = [];
}


foreach ($curriculumRows as $row) {

    $level = (int) (
        $row['level']
        ?? 0
    );


    if (
        $level < 1
        || $level > 9
    ) {
        continue;
    }


    $surahName = trim(
        (string) (
            $row['nama_surah']
            ?? ''
        )
    );


    if ($surahName === '') {
        continue;
    }


    $levels[$level][] = [
        'name' => $surahName,
        'order' => (int) (
            $row['urutan']
            ?? 0
        ),
    ];
}


/*
|--------------------------------------------------------------------------
| FALLBACK KURIKULUM
|--------------------------------------------------------------------------
|
| Digunakan hanya bila tahun ajaran tersebut belum mempunyai
| data kurikulum di database.
|--------------------------------------------------------------------------
*/

$hasCurriculum = false;

foreach ($levels as $items) {

    if ($items) {
        $hasCurriculum = true;
        break;
    }
}


if (!$hasCurriculum) {

    $fallback = [

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
            'Al-\'Adiyat',
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


    foreach ($fallback as $level => $surahs) {

        foreach ($surahs as $index => $surah) {

            $levels[$level][] = [
                'name' => $surah,
                'order' => $index + 1,
            ];
        }
    }
}


$pageTitle = 'Data Surat';

require ROOT_PATH . '/includes/header.php';

?>


<style>

/* =========================================================
   ORANG TUA - DATA SURAT
   ========================================================= */

.parent-surah-page {
    --ds-green: #174f3d;
    --ds-green-dark: #103e30;
    --ds-green-soft: #eafbf3;

    --ds-text: #101828;
    --ds-muted: #69778a;

    --ds-line: #e4e9e6;

    width: 100%;
}


/* =========================================================
   PAGE HEADER
   ========================================================= */

.ds-page-head {
    margin-bottom: 28px;
}


.ds-eyebrow {
    margin: 0 0 8px;

    color: #009367;

    font-size: 10px;
    font-weight: 900;

    letter-spacing: .22em;
    text-transform: uppercase;
}


.ds-title {
    margin: 0;

    color: #08111f;

    font-size: clamp(
        30px,
        2.5vw,
        39px
    );

    font-weight: 900;

    line-height: 1.05;

    letter-spacing: -.045em;
}


.ds-description {
    max-width: 850px;

    margin: 10px 0 0;

    color: #627086;

    font-size: 12px;

    line-height: 1.6;
}


/* =========================================================
   YEAR FILTER
   ========================================================= */

.ds-year-panel {
    min-height: 81px;

    margin-bottom: 25px;

    padding: 16px 20px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 24px;

    border: 1px solid #e2e7e4;
    border-radius: 18px;

    background: #fff;

    box-shadow:
        0 2px 5px
        rgba(16, 24, 40, .04);
}


.ds-year-title {
    margin: 0;

    color: #101828;

    font-size: 12px;
    font-weight: 900;
}


.ds-year-description {
    margin: 5px 0 0;

    color: #768398;

    font-size: 9px;
}


.ds-year-form {
    flex: 0 0 190px;
}


.ds-year-select {
    width: 100%;
    height: 44px;

    padding: 8px 39px 8px 15px;

    border: 1px solid #dbe2de;
    border-radius: 11px;

    outline: 0;

    background: #fff;

    color: #154331;

    cursor: pointer;

    font-family: inherit;

    font-size: 13px;
    font-weight: 900;
}


.ds-year-select:hover {
    border-color: #bdd0c6;
}


.ds-year-select:focus {
    border-color: #4fa17f;

    box-shadow:
        0 0 0 3px
        rgba(0, 143, 98, .08);
}


/* =========================================================
   LEVEL GRID
   ========================================================= */

.ds-level-grid {
    display: grid;

    grid-template-columns:
        repeat(
            3,
            minmax(0, 1fr)
        );

    gap: 18px;

    align-items: start;
}


/* =========================================================
   LEVEL CARD
   ========================================================= */

.ds-level-card {
    overflow: hidden;

    border: 1px solid #dfe5e2;
    border-radius: 16px;

    background: #fff;

    box-shadow:
        0 2px 5px
        rgba(16, 24, 40, .035);
}


.ds-level-card:hover {
    border-color: #cbd9d1;

    box-shadow:
        0 6px 16px
        rgba(16, 24, 40, .055);
}


/* =========================================================
   CARD HEADER
   ========================================================= */

.ds-level-header {
    min-height: 74px;

    padding: 15px 18px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 15px;

    background:
        linear-gradient(
            135deg,
            #174f3d 0%,
            #184c3a 100%
        );

    color: #fff;
}


.ds-level-eyebrow {
    display: block;

    margin-bottom: 5px;

    color: #99f2cc;

    font-size: 8.5px;
    font-weight: 900;

    letter-spacing: .13em;

    text-transform: uppercase;
}


.ds-level-title {
    margin: 0;

    color: #fff;

    font-size: 15px;
    font-weight: 900;

    letter-spacing: -.015em;
}


.ds-level-count {
    flex: 0 0 auto;

    min-width: 54px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 5px 9px;

    border-radius: 999px;

    background:
        rgba(
            255,
            255,
            255,
            .14
        );

    color: #fff;

    font-size: 8.5px;
    font-weight: 900;

    white-space: nowrap;
}


/* =========================================================
   SURAH LIST
   ========================================================= */

.ds-surah-list {
    margin: 0;
    padding: 0;

    list-style: none;
}


.ds-surah-item {
    min-height: 52px;

    padding: 10px 17px;

    display: flex;
    align-items: center;

    gap: 12px;

    border-bottom:
        1px solid #e9edeb;
}


.ds-surah-item:last-child {
    border-bottom: 0;
}


.ds-surah-item:hover {
    background: #fbfdfc;
}


.ds-surah-number {
    width: 27px;
    height: 27px;

    flex: 0 0 27px;

    display: grid;
    place-items: center;

    border-radius: 8px;

    background: #e9fbf2;

    color: #00865a;

    font-size: 9px;
    font-weight: 900;
}


.ds-surah-name {
    color: #101828;

    font-size: 11px;
    font-weight: 900;

    line-height: 1.35;
}


/* =========================================================
   EMPTY LEVEL
   ========================================================= */

.ds-level-empty {
    min-height: 110px;

    padding: 24px 18px;

    display: flex;
    align-items: center;
    justify-content: center;

    color: #8793a1;

    font-size: 9.5px;

    text-align: center;

    line-height: 1.6;
}


/* =========================================================
   FOOT NOTE
   ========================================================= */

.ds-note {
    margin-top: 20px;

    padding: 15px 17px;

    display: flex;
    align-items: flex-start;

    gap: 10px;

    border: 1px solid #d8e7df;
    border-radius: 13px;

    background: #f5fbf8;

    color: #53685d;

    font-size: 9.5px;

    line-height: 1.6;
}


.ds-note svg {
    width: 17px;
    height: 17px;

    flex: 0 0 17px;

    margin-top: 1px;

    color: #00865a;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 1150px) {

    .ds-level-grid {
        grid-template-columns:
            repeat(
                2,
                minmax(0, 1fr)
            );
    }

}


@media (max-width: 760px) {

    .ds-year-panel {
        align-items: stretch;

        flex-direction: column;
    }


    .ds-year-form {
        flex: none;

        width: 100%;
    }


    .ds-level-grid {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 520px) {

    .ds-title {
        font-size: 28px;
    }


    .ds-level-header {
        min-height: 69px;

        padding:
            14px
            16px;
    }


    .ds-surah-item {
        padding:
            10px
            15px;
    }

}

</style>



<div class="parent-surah-page">


    <!-- =====================================================
         PAGE HEADER
         ===================================================== -->

    <header class="ds-page-head">

        <p class="ds-eyebrow">
            Kurikulum Tahsin &amp; Tahfidz
        </p>


        <h1 class="ds-title">
            Data Surat per Jenjang
        </h1>


        <p class="ds-description">
            Daftar surat dipisahkan per tahun ajaran agar
            perubahan kurikulum tidak mengubah riwayat
            tahun sebelumnya.
        </p>

    </header>



    <!-- =====================================================
         TAHUN AJARAN
         ===================================================== -->

    <section class="ds-year-panel">


        <div>

            <h2 class="ds-year-title">
                Tahun Ajaran
            </h2>

            <p class="ds-year-description">
                Pilih tahun untuk melihat susunan surat
                yang berlaku.
            </p>

        </div>


        <form
            method="get"
            action="<?= e(
                url(
                    'orangtua/surahs.php'
                )
            ) ?>"
            class="ds-year-form"
            id="surah-year-form"
        >

            <select
                name="tahun_ajaran"
                class="ds-year-select"
                id="surah-year-select"
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

        </form>

    </section>



    <!-- =====================================================
         JENJANG
         ===================================================== -->

    <section class="ds-level-grid">


        <?php for (
            $level = 1;
            $level <= 9;
            $level++
        ): ?>

            <?php

            $surahs =
                $levels[$level];

            ?>


            <article class="ds-level-card">


                <!-- HEADER -->

                <header class="ds-level-header">

                    <div>

                        <span class="ds-level-eyebrow">

                            <?= e(
                                parent_surah_jenjang(
                                    $level
                                )
                            ) ?>

                        </span>


                        <h2 class="ds-level-title">

                            <?= e(
                                parent_surah_level_title(
                                    $level
                                )
                            ) ?>

                        </h2>

                    </div>


                    <span class="ds-level-count">

                        <?= count($surahs) ?>
                        surat

                    </span>

                </header>



                <!-- LIST -->

                <?php if ($surahs): ?>

                    <ol class="ds-surah-list">

                        <?php foreach (
                            $surahs
                            as $index => $surah
                        ): ?>

                            <li class="ds-surah-item">

                                <span class="ds-surah-number">

                                    <?= $index + 1 ?>

                                </span>


                                <span class="ds-surah-name">

                                    Surah
                                    <?= e(
                                        $surah['name']
                                    ) ?>

                                </span>

                            </li>

                        <?php endforeach; ?>

                    </ol>


                <?php else: ?>

                    <div class="ds-level-empty">

                        Belum ada data surat
                        untuk jenjang ini pada
                        tahun ajaran
                        <?= e($selectedYear) ?>.

                    </div>

                <?php endif; ?>

            </article>

        <?php endfor; ?>

    </section>



    <!-- =====================================================
         INFORMATION
         ===================================================== -->

    <div class="ds-note">

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


        <span>
            Data surat mengikuti kurikulum pada tahun
            ajaran yang dipilih. Orang Tua hanya dapat
            melihat susunan kurikulum dan tidak dapat
            mengubah data surat.
        </span>

    </div>

</div>



<script>
(() => {

    const select =
        document.querySelector(
            '#surah-year-select'
        );


    const form =
        document.querySelector(
            '#surah-year-form'
        );


    if (
        select
        && form
    ) {

        select.addEventListener(
            'change',
            () => {

                form.submit();

            }
        );

    }

})();
</script>


<?php

require ROOT_PATH
    . '/includes/footer.php';

?>