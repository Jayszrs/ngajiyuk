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

function parent_time_ago(?string $datetime): string
{
    if (!$datetime) {
        return 'Belum pernah login';
    }

    $timestamp = strtotime($datetime);

    if (!$timestamp) {
        return 'Belum pernah login';
    }

    $diff = max(0, time() - $timestamp);

    if ($diff < 60) {
        return 'Baru saja';
    }

    if ($diff < 3600) {
        return max(
            1,
            (int) floor($diff / 60)
        ) . ' menit lalu';
    }

    if ($diff < 86400) {
        return max(
            1,
            (int) floor($diff / 3600)
        ) . ' jam lalu';
    }

    return max(
        1,
        (int) floor($diff / 86400)
    ) . ' hari lalu';
}


function parent_profile_completion(array $parent): int
{
    /*
     * 4 komponen utama biodata:
     *
     * Nama      = 25%
     * Telepon   = 25%
     * Alamat    = 25%
     * Foto      = 25%
     *
     * Email tidak dihitung karena merupakan
     * bagian akun/login, bukan biodata utama.
     */

    $fields = [
        trim((string) ($parent['full_name'] ?? '')),
        trim((string) ($parent['phone'] ?? '')),
        trim((string) ($parent['address'] ?? '')),
        trim((string) ($parent['photo_url'] ?? '')),
    ];

    $filled = 0;

    foreach ($fields as $field) {
        if ($field !== '') {
            $filled++;
        }
    }

    return $filled * 25;
}


/*
|--------------------------------------------------------------------------
| HANDLE LINK / UNLINK
|--------------------------------------------------------------------------
|
| Dibuat langsung di parents.php supaya tidak perlu
| mengubah source API lain pada tahap ini.
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = trim(
        (string) ($_POST['action'] ?? '')
    );

    $parentId = trim(
        (string) ($_POST['parent_id'] ?? '')
    );

    /*
     * Pastikan target merupakan akun Orang Tua.
     */
    $parentCheck = $pdo->prepare(
        "
            SELECT
                id,
                full_name,
                username
            FROM users
            WHERE id = ?
              AND role = 'orang_tua'
            LIMIT 1
        "
    );

    $parentCheck->execute([
        $parentId
    ]);

    $targetParent = $parentCheck->fetch();

    if (!$targetParent) {
        flash(
            'error',
            'Akun Orang Tua tidak ditemukan.'
        );

        redirect('admin/parents.php');
    }


    /*
     * HUBUNGKAN / PINDAHKAN ANAK
     */
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
                'admin/parents.php#parent-' . $parentId
            );
        }


        $studentStmt = $pdo->prepare(
            "
                SELECT
                    id,
                    nama_lengkap,
                    nis,
                    kelas
                FROM students
                WHERE nis = ?
                  AND status = 'aktif'
                LIMIT 1
            "
        );

        $studentStmt->execute([
            $nis
        ]);

        $student = $studentStmt->fetch();

        if (!$student) {
            flash(
                'error',
                'Siswa aktif dengan NIS tersebut tidak ditemukan.'
            );

            redirect(
                'admin/parents.php#parent-' . $parentId
            );
        }


        /*
         * Jangan izinkan seorang siswa aktif
         * dimiliki dua akun Orang Tua.
         */
        $linkedCheck = $pdo->prepare(
            "
                SELECT
                    psl.parent_id,
                    u.full_name
                FROM parent_student_links psl

                INNER JOIN users u
                    ON u.id = psl.parent_id

                WHERE psl.student_id = ?
                  AND psl.status = 'active'
                  AND psl.parent_id <> ?

                LIMIT 1
            "
        );

        $linkedCheck->execute([
            $student['id'],
            $parentId,
        ]);

        $existingLink = $linkedCheck->fetch();

        if ($existingLink) {
            flash(
                'error',
                'Siswa tersebut sudah terhubung dengan Orang Tua '
                . $existingLink['full_name']
                . '.'
            );

            redirect(
                'admin/parents.php#parent-' . $parentId
            );
        }


        try {

            /*
             * parent_student_links memakai parent_id
             * sebagai PRIMARY KEY.
             *
             * Jadi satu parent memiliki satu anak aktif
             * dalam struktur database sekarang.
             */
            $linkStmt = $pdo->prepare(
                "
                    INSERT INTO parent_student_links (
                        parent_id,
                        student_id,
                        status
                    )
                    VALUES (?, ?, 'active')

                    ON DUPLICATE KEY UPDATE
                        student_id = VALUES(student_id),
                        status = 'active',
                        updated_at = NOW()
                "
            );

            $linkStmt->execute([
                $parentId,
                $student['id'],
            ]);


            audit_event(
                'parent_student_linked',
                'success',
                $parentId,
                [
                    'parent' =>
                        $targetParent['username'],

                    'student_id' =>
                        $student['id'],

                    'student' =>
                        $student['nama_lengkap'],

                    'nis' =>
                        $student['nis'],
                ]
            );


            flash(
                'success',
                'Anak berhasil dihubungkan ke akun Orang Tua.'
            );

        } catch (PDOException $error) {

            error_log(
                'NgajiYuk parent link gagal: '
                . $error->getMessage()
            );

            flash(
                'error',
                'Hubungan Orang Tua dan siswa gagal disimpan.'
            );
        }


        redirect(
            'admin/parents.php#parent-' . $parentId
        );
    }


    /*
     * PUTUSKAN HUBUNGAN
     */
    if ($action === 'unlink_child') {

        $unlinkStmt = $pdo->prepare(
            "
                UPDATE parent_student_links
                SET
                    status = 'inactive',
                    updated_at = NOW()

                WHERE parent_id = ?
                  AND status = 'active'
            "
        );

        $unlinkStmt->execute([
            $parentId
        ]);


        audit_event(
            'parent_student_unlinked',
            'success',
            $parentId,
            [
                'parent' =>
                    $targetParent['username'],
            ]
        );


        flash(
            'success',
            'Hubungan Orang Tua dan anak berhasil diputuskan.'
        );


        redirect(
            'admin/parents.php#parent-' . $parentId
        );
    }


    flash(
        'error',
        'Aksi tidak dikenali.'
    );

    redirect('admin/parents.php');
}


/*
|--------------------------------------------------------------------------
| Filter
|--------------------------------------------------------------------------
*/

$selectedClass = normalize_class_name(
    (string) ($_GET['kelas'] ?? '')
);

$filterStatus = trim(
    (string) ($_GET['status'] ?? '')
);

if (
    $selectedClass !== ''
    && !in_array(
        $selectedClass,
        all_class_names(),
        true
    )
) {
    $selectedClass = '';
}

if (
    !in_array(
        $filterStatus,
        [
            '',
            'unlinked'
        ],
        true
    )
) {
    $filterStatus = '';
}


/*
|--------------------------------------------------------------------------
| Statistik kelas
|--------------------------------------------------------------------------
*/

$classNames = all_class_names();

$classStats = [];

$classStatsStmt = $pdo->prepare(
    "
        SELECT

            COUNT(
                DISTINCT CASE
                    WHEN s.status = 'aktif'
                    THEN s.id
                END
            ) AS total_students,

            COUNT(
                DISTINCT CASE
                    WHEN
                        s.status = 'aktif'
                        AND psl.status = 'active'
                    THEN s.id
                END
            ) AS linked_students

        FROM students s

        LEFT JOIN parent_student_links psl
            ON psl.student_id = s.id
           AND psl.status = 'active'

        WHERE s.kelas = ?
    "
);


foreach ($classNames as $className) {

    $classStatsStmt->execute([
        $className
    ]);

    $row = $classStatsStmt->fetch();

    $total = (int) (
        $row['total_students']
        ?? 0
    );

    $linked = (int) (
        $row['linked_students']
        ?? 0
    );

    $classStats[] = [
        'class' => $className,
        'total' => $total,
        'linked' => $linked,
        'unlinked' => max(
            0,
            $total - $linked
        ),
    ];
}


/*
|--------------------------------------------------------------------------
| Total akun Orang Tua
|--------------------------------------------------------------------------
*/

$totalParents = (int) $pdo
    ->query(
        "
            SELECT COUNT(*)
            FROM users
            WHERE role = 'orang_tua'
        "
    )
    ->fetchColumn();


$totalUnlinkedParents = (int) $pdo
    ->query(
        "
            SELECT COUNT(*)

            FROM users u

            LEFT JOIN parent_student_links psl
                ON psl.parent_id = u.id
               AND psl.status = 'active'

            WHERE u.role = 'orang_tua'
              AND psl.student_id IS NULL
        "
    )
    ->fetchColumn();


/*
|--------------------------------------------------------------------------
| Daftar Orang Tua
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        u.id,
        u.username,
        u.email,
        u.full_name,
        u.phone,
        u.address,
        u.bio,
        u.photo_url,
        u.is_active,
        u.last_login_at,

        s.id AS student_id,
        s.nama_lengkap AS child_name,
        s.nis,
        s.kelas,
        s.level

    FROM users u

    LEFT JOIN parent_student_links psl
        ON psl.parent_id = u.id
       AND psl.status = 'active'

    LEFT JOIN students s
        ON s.id = psl.student_id

    WHERE u.role = 'orang_tua'
";

$params = [];


if ($selectedClass !== '') {

    $sql .= "
        AND s.kelas = ?
    ";

    $params[] = $selectedClass;
}


if ($filterStatus === 'unlinked') {

    $sql .= "
        AND psl.student_id IS NULL
    ";
}


$sql .= "
    ORDER BY
        u.full_name ASC,
        u.username ASC
";


$parentStmt = $pdo->prepare($sql);

$parentStmt->execute($params);

$parents = $parentStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Data turunan
|--------------------------------------------------------------------------
*/

foreach ($parents as &$parent) {

    $parent['completion'] =
        parent_profile_completion($parent);

    $parent['login_text'] =
        parent_time_ago(
            $parent['last_login_at']
                ?: null
        );
}

unset($parent);


$pageTitle = 'Monitoring Orang Tua';

require ROOT_PATH . '/includes/header.php';

?>


<style>

/* =========================================================
   MONITORING ORANG TUA
   ========================================================= */

.parent-monitor-page {
    --pm-green: #174f3d;
    --pm-green-dark: #123f31;
    --pm-green-bright: #009b68;

    --pm-text: #0f172a;
    --pm-muted: #68768a;

    --pm-line: #e8ecea;

    width: 100%;
}


/* =========================================================
   PAGE HEADER
   ========================================================= */

.pm-page-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;

    gap: 22px;

    margin-bottom: 25px;
}

.pm-eyebrow {
    margin: 0 0 8px;

    color: #009367;

    font-size: 10px;
    font-weight: 900;

    letter-spacing: .21em;
    text-transform: uppercase;
}

.pm-title {
    margin: 0;

    color: #060c17;

    font-size: clamp(
        30px,
        2.5vw,
        39px
    );

    font-weight: 900;

    line-height: 1.05;

    letter-spacing: -.045em;
}

.pm-description {
    margin: 9px 0 0;

    color: #627086;

    font-size: 12px;

    line-height: 1.6;
}

.pm-update {
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

    box-shadow:
        0 2px 5px
        rgba(16, 24, 40, .05);
}

.pm-update svg {
    color: #00a875;
}


/* =========================================================
   CLASS MONITOR
   ========================================================= */

.pm-class-panel {
    margin-bottom: 22px;

    overflow: hidden;

    border: 1px solid #bce8d5;
    border-radius: 21px;

    background: #fff;

    box-shadow:
        0 2px 5px
        rgba(16, 24, 40, .035);
}

.pm-class-head {
    min-height: 115px;

    padding: 24px 24px 22px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 25px;

    border-bottom: 1px solid #e4eeea;

    background:
        linear-gradient(
            100deg,
            #edfff6 0%,
            #ffffff 67%
        );
}

.pm-class-eyebrow {
    margin: 0 0 7px;

    color: #009969;

    font-size: 10px;
    font-weight: 900;

    letter-spacing: .18em;

    text-transform: uppercase;
}

.pm-class-title {
    margin: 0;

    color: #0c1727;

    font-size: 22px;
    font-weight: 900;

    letter-spacing: -.025em;
}

.pm-class-description {
    margin: 8px 0 0;

    color: #718096;

    font-size: 10.5px;

    line-height: 1.5;
}

.pm-all-parent {
    flex: 0 0 auto;

    min-height: 42px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 9px 16px;

    border-radius: 11px;

    background: var(--pm-green);

    color: #fff;

    font-size: 10px;
    font-weight: 900;

    text-decoration: none;

    box-shadow:
        0 5px 13px
        rgba(23, 79, 61, .12);
}

.pm-all-parent:hover {
    background: var(--pm-green-dark);

    color: #fff;
}


/* =========================================================
   CLASS TABLE
   ========================================================= */

.pm-class-table-wrap {
    padding: 18px 18px 20px;

    overflow-x: auto;
}

.pm-class-table {
    width: 100%;

    min-width: 850px;

    overflow: hidden;

    border-collapse: separate;
    border-spacing: 0;

    border-radius: 14px;
}

.pm-class-table thead th {
    height: 43px;

    padding: 11px 16px;

    background: var(--pm-green);

    color: #fff;

    font-size: 9px;
    font-weight: 900;

    text-align: left;

    text-transform: uppercase;
}

.pm-class-table thead th:first-child {
    border-radius: 12px 0 0 0;
}

.pm-class-table thead th:last-child {
    border-radius: 0 12px 0 0;

    text-align: right;
}

.pm-class-table tbody td {
    height: 57px;

    padding: 11px 16px;

    border-bottom: 1px solid #edf0ee;

    color: #142033;

    font-size: 11px;
    font-weight: 800;
}

.pm-class-table tbody tr:nth-child(odd) td {
    background: #fbfcfb;
}

.pm-class-table tbody tr:hover td {
    background: #f6fbf8;
}

.pm-class-table tbody tr:last-child td {
    border-bottom: 0;
}

.pm-class-table td:last-child {
    text-align: right;
}


/* class badge */

.pm-class-badge {
    min-width: 52px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 8px 12px;

    border-radius: 11px;

    background: #f3f5f6;

    color: #101828;

    font-size: 11px;
    font-weight: 900;
}


/* linked number */

.pm-linked-number {
    color: #008859;

    font-size: 13px;
    font-weight: 900;
}


/* unlinked badge */

.pm-unlinked-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 4px 8px;

    border: 1px solid #89e5ba;
    border-radius: 999px;

    background: #effff6;

    color: #008454;

    font-size: 9px;
    font-weight: 900;
}

.pm-unlinked-badge.warning {
    border-color: #ffd36f;

    background: #fff9e8;

    color: #d56a00;
}


/* view class */

.pm-view-class {
    min-height: 34px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 7px 13px;

    border-radius: 10px;

    background: #eafaf2;

    color: #00754e;

    font-size: 9px;
    font-weight: 900;

    text-decoration: none;
}

.pm-view-class:hover {
    background: #dff6eb;

    color: #005e3f;
}


/* warning footer */

.pm-unlinked-row td {
    background: #fffcf4 !important;

    border-top: 1px solid #f5e5b5;
}

.pm-unlinked-row td:first-child {
    color: #8c3600;
}

.pm-connect-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 34px;

    padding: 7px 13px;

    border: 1px solid #ece4d2;
    border-radius: 10px;

    background: #fff;

    color: #aa4300;

    font-size: 9px;
    font-weight: 900;

    text-decoration: none;

    box-shadow:
        0 2px 4px
        rgba(16, 24, 40, .04);
}


/* =========================================================
   PARENT LIST PANEL
   ========================================================= */

.pm-parent-panel {
    overflow: hidden;

    border: 1px solid #e2e7e4;
    border-radius: 20px;

    background: #fff;

    box-shadow:
        0 2px 5px
        rgba(16, 24, 40, .035);
}

.pm-toolbar {
    min-height: 78px;

    padding: 17px 19px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 18px;

    border-bottom: 1px solid var(--pm-line);
}

.pm-search {
    position: relative;

    width: min(
        420px,
        100%
    );
}

.pm-search svg {
    position: absolute;

    top: 50%;
    left: 14px;

    width: 17px;
    height: 17px;

    color: #98a2b3;

    pointer-events: none;

    transform: translateY(-50%);
}

.pm-search input {
    width: 100%;
    height: 42px;

    padding:
        9px
        14px
        9px
        42px;

    border: 1px solid #dce2df;
    border-radius: 11px;

    outline: none;

    background: #fff;

    color: #344054;

    font-family: inherit;

    font-size: 11px;
    font-weight: 600;
}

.pm-search input:focus {
    border-color: #53a488;

    box-shadow:
        0 0 0 3px
        rgba(0, 143, 98, .09);
}

.pm-parent-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 4px 9px;

    border: 1px solid #bad8ff;
    border-radius: 999px;

    background: #f1f6ff;

    color: #175cd3;

    font-size: 9px;
    font-weight: 900;

    white-space: nowrap;
}


/* =========================================================
   PARENT TABLE
   ========================================================= */

.pm-parent-table-wrap {
    overflow-x: auto;
}

.pm-parent-table {
    width: 100%;

    min-width: 1080px;

    border-collapse: collapse;

    table-layout: fixed;
}

.pm-parent-table thead th {
    height: 45px;

    padding: 12px 18px;

    background: #f8f9fa;

    border-bottom: 1px solid var(--pm-line);

    color: #586579;

    font-size: 9px;
    font-weight: 900;

    text-align: left;

    text-transform: uppercase;
}


/* widths */

.pm-parent-table th:nth-child(1),
.pm-parent-table td:nth-child(1) {
    width: 16%;
}

.pm-parent-table th:nth-child(2),
.pm-parent-table td:nth-child(2) {
    width: 15%;
}

.pm-parent-table th:nth-child(3),
.pm-parent-table td:nth-child(3) {
    width: 20%;
}

.pm-parent-table th:nth-child(4),
.pm-parent-table td:nth-child(4) {
    width: 14%;
}

.pm-parent-table th:nth-child(5),
.pm-parent-table td:nth-child(5) {
    width: 13%;
}

.pm-parent-table th:nth-child(6),
.pm-parent-table td:nth-child(6) {
    width: 8%;
}

.pm-parent-table th:nth-child(7),
.pm-parent-table td:nth-child(7) {
    width: 14%;
}

.pm-parent-row td {
    min-height: 70px;

    padding: 15px 18px;

    border-bottom: 1px solid var(--pm-line);

    vertical-align: middle;

    color: #344054;

    font-size: 10.5px;
}

.pm-parent-row:hover td {
    background: #fbfcfb;
}


/* identity */

.pm-parent-name {
    display: block;

    color: #101828;

    font-size: 12px;
    font-weight: 900;
}

.pm-parent-email {
    display: block;

    max-width: 200px;

    margin-top: 5px;

    overflow: hidden;

    color: #718096;

    font-size: 9px;

    text-overflow: ellipsis;
    white-space: nowrap;
}


/* child */

.pm-child-name {
    display: block;

    color: #172033;

    font-size: 11px;
    font-weight: 900;
}

.pm-child-meta {
    display: block;

    margin-top: 4px;

    color: #8994a5;

    font-size: 9px;
}

.pm-child-empty {
    display: inline-flex;

    padding: 5px 9px;

    border: 1px solid #ffd16a;
    border-radius: 999px;

    background: #fff9e9;

    color: #cf6100;

    font-size: 9px;
    font-weight: 900;
}


/* class */

.pm-child-class {
    display: inline-flex;
    align-items: center;

    gap: 7px;

    min-height: 33px;

    padding: 7px 11px;

    border-radius: 10px;

    background: #eef9f3;

    color: #08734f;

    font-size: 9.5px;
    font-weight: 900;

    text-decoration: none;
}

.pm-child-class.empty {
    background: #f3f5f6;

    color: #99a2b1;
}


/* biodata */

.pm-completion-number {
    display: block;

    color: #172033;

    font-size: 11px;
    font-weight: 900;
}

.pm-completion-track {
    width: 104px;
    height: 7px;

    margin-top: 8px;

    overflow: hidden;

    border-radius: 999px;

    background: #f0f2f4;
}

.pm-completion-track span {
    display: block;

    height: 100%;

    border-radius: inherit;

    background: #23b979;
}

.pm-completion-track.low span {
    background: #ff626d;
}


/* login */

.pm-last-login {
    color: #344054;

    font-size: 10.5px;
    font-weight: 800;
}


/* status */

.pm-status {
    display: inline-flex;

    padding: 4px 9px;

    border-radius: 999px;

    font-size: 9px;
    font-weight: 900;
}

.pm-status.active {
    border: 1px solid #a0eac3;

    background: #effff6;

    color: #008852;
}

.pm-status.inactive {
    border: 1px solid #f2b7b7;

    background: #fff2f2;

    color: #d62b2b;
}


/* manage */

.pm-manage-wrap {
    position: relative;

    display: inline-block;
}

.pm-manage-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 7px;

    min-height: 35px;

    padding: 7px 12px;

    border: 0;
    border-radius: 10px;

    background: #eef5ff;

    color: #175cd3;

    cursor: pointer;

    font-family: inherit;

    font-size: 9.5px;
    font-weight: 900;
}

.pm-manage-button:hover {
    background: #e2edff;
}


/* =========================================================
   MANAGE ROW
   ========================================================= */

.pm-manage-row {
    display: none;
}

.pm-manage-row.open {
    display: table-row;
}

.pm-manage-row td {
    padding: 0 !important;

    background: #f8faf9 !important;
}

.pm-manage-content {
    padding: 17px 20px 19px;

    display: flex;
    align-items: flex-end;

    gap: 12px;
}

.pm-manage-field {
    width: min(
        320px,
        100%
    );
}

.pm-manage-field label {
    display: block;

    margin-bottom: 7px;

    color: #647168;

    font-size: 8.5px;
    font-weight: 900;

    letter-spacing: .05em;

    text-transform: uppercase;
}

.pm-manage-field input {
    width: 100%;
    height: 39px;

    padding: 8px 11px;

    border: 1px solid #d9e0dc;
    border-radius: 9px;

    outline: 0;

    background: #fff;

    font-family: inherit;

    font-size: 10.5px;
}

.pm-manage-field input:focus {
    border-color: #56a78a;

    box-shadow:
        0 0 0 3px
        rgba(0, 143, 98, .08);
}

.pm-manage-submit,
.pm-manage-unlink {
    min-height: 39px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 8px 13px;

    border: 0;
    border-radius: 9px;

    cursor: pointer;

    font-family: inherit;

    font-size: 9.5px;
    font-weight: 900;
}

.pm-manage-submit {
    background: var(--pm-green);

    color: #fff;
}

.pm-manage-submit:hover {
    background: var(--pm-green-dark);
}

.pm-manage-unlink {
    background: #fff0f0;

    color: #d92d20;
}


/* empty */

.pm-empty {
    padding: 48px 20px;

    color: #7d8993;

    text-align: center;
}

.pm-empty strong {
    display: block;

    margin-bottom: 5px;

    color: #344054;

    font-size: 14px;
}

.pm-search-empty {
    display: none;

    padding: 45px 20px;

    color: #7d8993;

    font-size: 11px;

    text-align: center;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 820px) {

    .pm-page-head,
    .pm-class-head {
        align-items: flex-start;

        flex-direction: column;
    }

    .pm-toolbar {
        align-items: stretch;

        flex-direction: column;
    }

    .pm-search {
        width: 100%;
    }

    .pm-parent-count {
        align-self: flex-start;
    }

    .pm-manage-content {
        align-items: stretch;

        flex-direction: column;
    }

    .pm-manage-field {
        width: 100%;
    }

}


@media (max-width: 520px) {

    .pm-title {
        font-size: 28px;
    }

    .pm-class-title {
        font-size: 19px;
    }

}

</style>


<div class="parent-monitor-page">


    <!-- =====================================================
         PAGE HEADER
         ===================================================== -->

    <header class="pm-page-head">

        <div>

            <p class="pm-eyebrow">
                Akun Wali
            </p>

            <h1 class="pm-title">
                Monitoring Orang Tua
            </h1>

            <p class="pm-description">
                Periksa relasi Orang Tua–anak, aktivitas login,
                dan kelengkapan biodata.
            </p>

        </div>


        <div class="pm-update">

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

        </div>

    </header>



    <!-- =====================================================
         MONITORING PER KELAS
         ===================================================== -->

    <section class="pm-class-panel">

        <header class="pm-class-head">

            <div>

                <p class="pm-class-eyebrow">
                    Monitoring Per Kelas
                </p>

                <h2 class="pm-class-title">
                    Data Orang Tua Kelas 1A–6B
                </h2>

                <p class="pm-class-description">
                    Pilih kelas untuk melihat akun Orang Tua,
                    anak terhubung, dan siswa yang belum memiliki
                    akun wali.
                </p>

            </div>


            <a
                class="pm-all-parent"
                href="<?= e(
                    url(
                        'admin/parents.php#parent-list'
                    )
                ) ?>"
            >
                Semua Orang Tua
                (<?= $totalParents ?>)
            </a>

        </header>


        <div class="pm-class-table-wrap">

            <table class="pm-class-table">

                <thead>

                    <tr>

                        <th>Kelas</th>

                        <th>
                            Jumlah Siswa
                        </th>

                        <th>
                            Orang Tua Terhubung
                        </th>

                        <th>
                            Siswa Belum Terhubung
                        </th>

                        <th>Aksi</th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($classStats as $stat): ?>

                    <tr>

                        <td>

                            <span class="pm-class-badge">

                                <?= e(
                                    $stat['class']
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?= $stat['total'] ?>

                        </td>


                        <td>

                            <span class="pm-linked-number">

                                <?= $stat['linked'] ?>

                            </span>

                        </td>


                        <td>

                            <span
                                class="
                                    pm-unlinked-badge
                                    <?=
                                        $stat['unlinked'] > 0
                                            ? 'warning'
                                            : ''
                                    ?>
                                "
                            >

                                <?= $stat['unlinked'] ?>
                                siswa

                            </span>

                        </td>


                        <td>

                            <a
                                class="pm-view-class"
                                href="<?= e(
                                    url(
                                        'admin/parents.php?kelas='
                                        . rawurlencode(
                                            $stat['class']
                                        )
                                        . '#parent-list'
                                    )
                                ) ?>"
                            >
                                Lihat Kelas
                                <?= e(
                                    $stat['class']
                                ) ?>
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>


                <?php if ($totalUnlinkedParents > 0): ?>

                    <tr class="pm-unlinked-row">

                        <td colspan="3">

                            Akun Orang Tua belum
                            terhubung ke anak

                        </td>


                        <td>

                            <span
                                class="
                                    pm-unlinked-badge
                                    warning
                                "
                            >

                                <?= $totalUnlinkedParents ?>
                                akun

                            </span>

                        </td>


                        <td>

                            <a
                                class="pm-connect-button"
                                href="<?= e(
                                    url(
                                        'admin/parents.php'
                                        . '?status=unlinked'
                                        . '#parent-list'
                                    )
                                ) ?>"
                            >
                                Hubungkan Akun
                            </a>

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>



    <!-- =====================================================
         DAFTAR ORANG TUA
         ===================================================== -->

    <section
        class="pm-parent-panel"
        id="parent-list"
    >

        <div class="pm-toolbar">

            <label
                class="pm-search"
                for="parent-search"
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
                    id="parent-search"
                    placeholder="Cari Orang Tua, anak, atau NIS..."
                    autocomplete="off"
                >

            </label>


            <span
                class="pm-parent-count"
                id="parent-count"
            >

                <?= count($parents) ?>
                Orang Tua

            </span>

        </div>


        <?php if ($parents): ?>

            <div class="pm-parent-table-wrap">

                <table
                    class="pm-parent-table"
                    id="parent-table"
                >

                    <thead>

                        <tr>

                            <th>
                                Orang Tua
                            </th>

                            <th>
                                Anak Terhubung
                            </th>

                            <th>
                                Kelas Anak
                            </th>

                            <th>
                                Biodata
                            </th>

                            <th>
                                Login Terakhir
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Aksi
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($parents as $parent): ?>

                        <?php

                        $parentId = (string) $parent['id'];

                        $isLinked =
                            !empty(
                                $parent['student_id']
                            );

                        $completion =
                            (int) $parent['completion'];

                        $searchText = strtolower(
                            implode(
                                ' ',
                                [
                                    $parent['full_name'],
                                    $parent['username'],
                                    $parent['email'],
                                    $parent['child_name'],
                                    $parent['nis'],
                                    $parent['kelas'],
                                ]
                            )
                        );

                        ?>


                        <tr
                            class="pm-parent-row"
                            id="parent-<?= e($parentId) ?>"
                            data-search="<?= e($searchText) ?>"
                        >


                            <!-- Orang Tua -->

                            <td>

                                <strong class="pm-parent-name">

                                    <?= e(
                                        $parent[
                                            'full_name'
                                        ]
                                    ) ?>

                                </strong>

                                <span class="pm-parent-email">

                                    <?php if (
                                        !empty(
                                            $parent['email']
                                        )
                                    ): ?>

                                        <?= e(
                                            $parent['email']
                                        ) ?>

                                    <?php else: ?>

                                        @<?= e(
                                            $parent[
                                                'username'
                                            ]
                                        ) ?>

                                    <?php endif; ?>

                                </span>

                            </td>


                            <!-- Anak -->

                            <td>

                                <?php if ($isLinked): ?>

                                    <strong class="pm-child-name">

                                        <?= e(
                                            $parent[
                                                'child_name'
                                            ]
                                        ) ?>

                                    </strong>

                                    <span class="pm-child-meta">

                                        NIS
                                        <?= e(
                                            $parent['nis']
                                        ) ?>

                                    </span>

                                <?php else: ?>

                                    <span class="pm-child-empty">

                                        Belum terhubung

                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Kelas -->

                            <td>

                                <?php if (
                                    $isLinked
                                    && !empty(
                                        $parent['kelas']
                                    )
                                ): ?>

                                    <a
                                        class="pm-child-class"
                                        href="<?= e(
                                            url(
                                                'admin/students-classes.php'
                                                . '?kelas='
                                                . rawurlencode(
                                                    $parent[
                                                        'kelas'
                                                    ]
                                                )
                                            )
                                        ) ?>"
                                    >

                                        <svg
                                            width="14"
                                            height="14"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path
                                                d="
                                                    M4 21V8
                                                    l8-5
                                                    8 5
                                                    v13
                                                "
                                            ></path>

                                            <path
                                                d="
                                                    M8 21v-6h8v6
                                                "
                                            ></path>
                                        </svg>

                                        Kelas
                                        <?= e(
                                            $parent[
                                                'kelas'
                                            ]
                                        ) ?>

                                    </a>

                                <?php else: ?>

                                    <span
                                        class="
                                            pm-child-class
                                            empty
                                        "
                                    >

                                        <svg
                                            width="14"
                                            height="14"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path
                                                d="
                                                    M4 21V8
                                                    l8-5
                                                    8 5
                                                    v13
                                                "
                                            ></path>
                                        </svg>

                                        Kelas belum tersedia

                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Biodata -->

                            <td>

                                <strong
                                    class="
                                        pm-completion-number
                                    "
                                >

                                    <?= $completion ?>%

                                </strong>

                                <div
                                    class="
                                        pm-completion-track
                                        <?=
                                            $completion < 50
                                                ? 'low'
                                                : ''
                                        ?>
                                    "
                                >

                                    <span
                                        style="
                                            width:
                                            <?= $completion ?>%
                                        "
                                    ></span>

                                </div>

                            </td>


                            <!-- Login -->

                            <td>

                                <span class="pm-last-login">

                                    <?= e(
                                        $parent[
                                            'login_text'
                                        ]
                                    ) ?>

                                </span>

                            </td>


                            <!-- Status -->

                            <td>

                                <span
                                    class="
                                        pm-status
                                        <?=
                                            $parent[
                                                'is_active'
                                            ]
                                                ? 'active'
                                                : 'inactive'
                                        ?>
                                    "
                                >

                                    <?=
                                        $parent['is_active']
                                            ? 'Aktif'
                                            : 'Nonaktif'
                                    ?>

                                </span>

                            </td>


                            <!-- Action -->

                            <td>

                                <button
                                    type="button"
                                    class="pm-manage-button"
                                    data-parent-manage="<?= e(
                                        $parentId
                                    ) ?>"
                                    aria-expanded="false"
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
                                                M10 13a5 5 0 0 0
                                                7.07.07l2-2
                                                a5 5 0 0 0
                                                -7.07-7.07
                                                l-1.15 1.15
                                            "
                                        ></path>

                                        <path
                                            d="
                                                M14 11
                                                a5 5 0 0 0
                                                -7.07-.07
                                                l-2 2
                                                a5 5 0 0 0
                                                7.07 7.07
                                                l1.15-1.15
                                            "
                                        ></path>
                                    </svg>

                                    Kelola Anak

                                </button>

                            </td>

                        </tr>


                        <!-- Manage form -->

                        <tr
                            class="pm-manage-row"
                            id="manage-parent-<?= e(
                                $parentId
                            ) ?>"
                        >

                            <td colspan="7">

                                <div class="pm-manage-content">


                                    <form
                                        method="post"
                                        action="<?= e(
                                            url(
                                                'admin/parents.php'
                                            )
                                        ) ?>"
                                        class="pm-manage-content"
                                        style="
                                            padding:0;
                                            flex:1;
                                        "
                                    >

                                        <?= csrf_field() ?>


                                        <input
                                            type="hidden"
                                            name="action"
                                            value="link_child"
                                        >


                                        <input
                                            type="hidden"
                                            name="parent_id"
                                            value="<?= e(
                                                $parentId
                                            ) ?>"
                                        >


                                        <div
                                            class="
                                                pm-manage-field
                                            "
                                        >

                                            <label>
                                                NIS Anak
                                            </label>

                                            <input
                                                type="text"
                                                name="nis"
                                                value="<?= e(
                                                    (string) (
                                                        $parent[
                                                            'nis'
                                                        ]
                                                        ?? ''
                                                    )
                                                ) ?>"
                                                placeholder="
                                                    Masukkan NIS siswa
                                                "
                                                required
                                            >

                                        </div>


                                        <button
                                            type="submit"
                                            class="
                                                pm-manage-submit
                                            "
                                        >

                                            <?= $isLinked
                                                ? 'Pindahkan Anak'
                                                : 'Hubungkan Anak'
                                            ?>

                                        </button>

                                    </form>


                                    <?php if ($isLinked): ?>

                                        <form
                                            method="post"
                                            action="<?= e(
                                                url(
                                                    'admin/parents.php'
                                                )
                                            ) ?>"
                                            onsubmit="
                                                return confirm(
                                                    'Putuskan hubungan Orang Tua dan anak ini?'
                                                );
                                            "
                                        >

                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="unlink_child"
                                            >

                                            <input
                                                type="hidden"
                                                name="parent_id"
                                                value="<?= e(
                                                    $parentId
                                                ) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="
                                                    pm-manage-unlink
                                                "
                                            >
                                                Putuskan
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


            <div
                class="pm-search-empty"
                id="parent-search-empty"
            >
                Orang Tua yang dicari tidak ditemukan.
            </div>


        <?php else: ?>

            <div class="pm-empty">

                <strong>
                    Belum ada Orang Tua
                </strong>

                Tidak ada akun Orang Tua pada
                filter yang dipilih.

            </div>

        <?php endif; ?>

    </section>

</div>


<script>
(() => {

    /*
    |--------------------------------------------------------------------------
    | Search Orang Tua
    |--------------------------------------------------------------------------
    */

    const search =
        document.querySelector(
            '#parent-search'
        );

    const parentRows =
        Array.from(
            document.querySelectorAll(
                '.pm-parent-row'
            )
        );

    const count =
        document.querySelector(
            '#parent-count'
        );

    const empty =
        document.querySelector(
            '#parent-search-empty'
        );


    if (search) {

        search.addEventListener(
            'input',
            () => {

                const keyword =
                    search.value
                        .trim()
                        .toLowerCase();

                let visible = 0;


                parentRows.forEach(row => {

                    const matched =
                        keyword === ''
                        || (
                            row.dataset.search
                            || ''
                        )
                        .toLowerCase()
                        .includes(keyword);


                    row.style.display =
                        matched
                            ? ''
                            : 'none';


                    const parentId =
                        row.id.replace(
                            'parent-',
                            ''
                        );


                    const manageRow =
                        document.querySelector(
                            '#manage-parent-'
                            + CSS.escape(
                                parentId
                            )
                        );


                    if (
                        manageRow
                        && !matched
                    ) {

                        manageRow
                            .classList
                            .remove('open');
                    }


                    if (matched) {
                        visible++;
                    }

                });


                if (count) {
                    count.textContent =
                        visible
                        + ' Orang Tua';
                }


                if (empty) {
                    empty.style.display =
                        visible === 0
                            ? 'block'
                            : 'none';
                }

            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Kelola Anak
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'click',
        event => {

            const button =
                event.target.closest(
                    '[data-parent-manage]'
                );

            if (!button) {
                return;
            }


            const id =
                button.dataset
                    .parentManage;


            const target =
                document.querySelector(
                    '#manage-parent-'
                    + CSS.escape(id)
                );


            if (!target) {
                return;
            }


            /*
             * Tutup row lain dulu.
             */
            document
                .querySelectorAll(
                    '.pm-manage-row.open'
                )
                .forEach(row => {

                    if (row !== target) {
                        row.classList
                            .remove('open');
                    }

                });


            document
                .querySelectorAll(
                    '[data-parent-manage]'
                )
                .forEach(otherButton => {

                    if (
                        otherButton
                        !== button
                    ) {
                        otherButton
                            .setAttribute(
                                'aria-expanded',
                                'false'
                            );
                    }

                });


            const isOpen =
                target.classList
                    .toggle('open');


            button.setAttribute(
                'aria-expanded',
                isOpen
                    ? 'true'
                    : 'false'
            );


            if (isOpen) {

                const input =
                    target.querySelector(
                        'input[name="nis"]'
                    );

                if (input) {
                    setTimeout(
                        () => input.focus(),
                        50
                    );
                }
            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Kalau URL punya #parent-xxx,
    | otomatis buka row kelolanya setelah redirect POST.
    |--------------------------------------------------------------------------
    */

    if (
        window.location.hash
        && window.location.hash
            .startsWith('#parent-')
    ) {

        const id =
            window.location.hash
                .replace(
                    '#parent-',
                    ''
                );


        const button =
            document.querySelector(
                '[data-parent-manage="'
                + CSS.escape(id)
                + '"]'
            );


        if (button) {

            setTimeout(
                () => {
                    button.click();
                },
                120
            );
        }
    }

})();
</script>


<?php

require ROOT_PATH . '/includes/footer.php';

?>