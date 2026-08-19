<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require ROOT_PATH . '/includes/XlsxReader.php';


$user = require_api_user(
    'guru',
    'admin'
);


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(
        false,
        'Metode tidak diizinkan.',
        null,
        405
    );
}


verify_csrf();


/**
 * Validasi upload.
 */
if (
    !isset($_FILES['file']) ||
    !is_array($_FILES['file'])
) {
    throw new RuntimeException(
        'File Excel atau CSV belum dipilih.'
    );
}


$file = $_FILES['file'];

$uploadError =
    (int) (
        $file['error']
        ?? UPLOAD_ERR_NO_FILE
    );


if ($uploadError !== UPLOAD_ERR_OK) {
    $message = match ($uploadError) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE =>
            'Ukuran file terlalu besar.',

        UPLOAD_ERR_PARTIAL =>
            'File hanya terunggah sebagian. Silakan coba lagi.',

        UPLOAD_ERR_NO_FILE =>
            'File belum dipilih.',

        UPLOAD_ERR_NO_TMP_DIR =>
            'Folder temporary PHP tidak tersedia.',

        UPLOAD_ERR_CANT_WRITE =>
            'Server tidak dapat menyimpan file sementara.',

        UPLOAD_ERR_EXTENSION =>
            'Upload file dihentikan oleh ekstensi PHP.',

        default =>
            'File gagal diunggah.',
    };

    throw new RuntimeException(
        $message
    );
}


$tmpPath =
    (string) (
        $file['tmp_name']
        ?? ''
    );


if (
    $tmpPath === '' ||
    !is_uploaded_file($tmpPath)
) {
    throw new RuntimeException(
        'File upload temporary tidak valid.'
    );
}


$fileSize =
    (int) (
        $file['size']
        ?? 0
    );


if (
    $fileSize <= 0
) {
    throw new RuntimeException(
        'File tidak berisi data.'
    );
}


if (
    $fileSize >
    8 * 1024 * 1024
) {
    throw new RuntimeException(
        'Ukuran file maksimal 8 MB.'
    );
}


$originalName =
    (string) (
        $file['name']
        ?? ''
    );


$extension = strtolower(
    pathinfo(
        $originalName,
        PATHINFO_EXTENSION
    )
);


if (
    !in_array(
        $extension,
        [
            'xlsx',
            'csv'
        ],
        true
    )
) {
    throw new RuntimeException(
        'Gunakan file XLSX atau CSV.'
    );
}


/**
 * PENTING:
 *
 * Extension asli dikirim ke reader.
 *
 * Jangan hanya gunakan:
 * XlsxReader::rows($tmpPath)
 *
 * karena $tmpPath biasanya seperti:
 * C:\xampp\tmp\phpXXXX.tmp
 */
$rows =
    XlsxReader::rows(
        $tmpPath,
        $extension
    );


if (!$rows) {
    throw new RuntimeException(
        'File tidak berisi data.'
    );
}


/**
 * Normalisasi nama kolom.
 */
$normalize =
    static function (
        string $value
    ): string {
        $value =
            strtoupper(
                trim($value)
            );

        return trim(
            preg_replace(
                '/[^A-Z0-9]+/',
                ' ',
                $value
            ) ?? $value
        );
    };


/**
 * Cari baris header.
 */
$headerIndex = null;
$headers = [];


foreach (
    $rows
    as $index => $row
) {
    $candidate =
        array_map(
            fn($value) =>
                $normalize(
                    (string) $value
                ),
            $row
        );

    $joined =
        ' ' .
        implode(
            ' ',
            $candidate
        );

    if (
        str_contains(
            $joined,
            'NIS'
        ) &&
        (
            str_contains(
                $joined,
                'NAMA'
            ) ||
            str_contains(
                $joined,
                'PESERTA DIDIK'
            )
        )
    ) {
        $headerIndex = $index;
        $headers = $candidate;

        break;
    }
}


if ($headerIndex === null) {
    throw new RuntimeException(
        'Kolom NIS dan Nama Peserta Didik tidak ditemukan.'
    );
}


/**
 * Alias kolom yang didukung.
 */
$aliases = [
    'nama_lengkap' => [
        'NAMA LENGKAP',
        'NAMA PESERTA DIDIK',
        'NAMA SISWA',
        'NAMA'
    ],

    'nis' => [
        'NIS',
        'NOMOR INDUK SISWA'
    ],

    'kelas' => [
        'KELAS',
        'ROMBEL'
    ],

    'jenis_kelamin' => [
        'JENIS KELAMIN',
        'L P',
        'JK'
    ],

    'nik' => [
        'NIK'
    ],

    'tempat_tanggal_lahir' => [
        'TEMPAT TANGGAL LAHIR',
        'TEMPAT DAN TANGGAL LAHIR',
        'TTL'
    ],

    'nama_ayah' => [
        'NAMA AYAH',
        'AYAH'
    ],

    'nama_ibu' => [
        'NAMA IBU',
        'IBU'
    ],

    'wali_murid' => [
        'WALI MURID',
        'NAMA WALI'
    ],

    'alamat' => [
        'ALAMAT'
    ],

    'no_telp' => [
        'NO TELP',
        'NO TELEPON',
        'NOMOR TELEPON',
        'HP'
    ],
];


$map = [];


foreach (
    $aliases
    as $key => $names
) {
    foreach (
        $headers
        as $index => $header
    ) {
        if (
            in_array(
                $header,
                $names,
                true
            )
        ) {
            $map[$key] = $index;

            break;
        }
    }
}


if (
    !isset(
        $map['nama_lengkap'],
        $map['nis']
    )
) {
    throw new RuntimeException(
        'Kolom NIS atau Nama tidak dapat dipetakan.'
    );
}


/**
 * Default kelas dan level.
 */
$defaultClass =
    normalize_class_name(
        (string) (
            $_POST['kelas']
            ?? '1A'
        )
    );


$defaultLevel =
    (int) (
        $_POST['level']
        ?? 1
    );


if (
    !in_array(
        $defaultClass,
        all_class_names(),
        true
    ) ||
    $defaultLevel < 1 ||
    $defaultLevel > 9
) {
    throw new RuntimeException(
        'Kelas atau level bawaan tidak valid.'
    );
}


$pdo = db();


/**
 * Tentukan Guru pemilik data.
 *
 * Guru:
 * selalu menggunakan akun sendiri.
 *
 * Admin:
 * boleh memilih Guru atau membiarkan null.
 */
if ($user['role'] === 'guru') {
    $teacherId =
        $user['id'];

} else {
    $teacherId =
        trim(
            (string) (
                $_POST['teacher_id']
                ?? ''
            )
        );

    $teacherId =
        $teacherId !== ''
            ? $teacherId
            : null;


    if ($teacherId !== null) {
        $teacherCheck =
            $pdo->prepare(
                "SELECT id
                 FROM users
                 WHERE
                    id = ?
                    AND role = 'guru'
                    AND is_active = 1
                    AND approval_status = 'approved'
                 LIMIT 1"
            );

        $teacherCheck->execute([
            $teacherId
        ]);


        if (
            !$teacherCheck->fetchColumn()
        ) {
            throw new RuntimeException(
                'Guru tujuan tidak ditemukan atau tidak aktif.'
            );
        }
    }
}


/**
 * Insert/update siswa berdasarkan NIS.
 */
$statement =
    $pdo->prepare(
        "INSERT INTO students
        (
            id,
            teacher_id,
            nama_lengkap,
            nis,
            kelas,
            level,
            jenis_kelamin,
            nik,
            tempat_tanggal_lahir,
            nama_ayah,
            nama_ibu,
            wali_murid,
            alamat,
            no_telp
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
        ON DUPLICATE KEY UPDATE
            teacher_id =
                COALESCE(
                    VALUES(teacher_id),
                    teacher_id
                ),
            nama_lengkap =
                VALUES(nama_lengkap),
            kelas =
                VALUES(kelas),
            level =
                VALUES(level),
            jenis_kelamin =
                COALESCE(
                    VALUES(jenis_kelamin),
                    jenis_kelamin
                ),
            nik =
                COALESCE(
                    VALUES(nik),
                    nik
                ),
            tempat_tanggal_lahir =
                COALESCE(
                    NULLIF(
                        VALUES(tempat_tanggal_lahir),
                        ''
                    ),
                    tempat_tanggal_lahir
                ),
            nama_ayah =
                COALESCE(
                    NULLIF(
                        VALUES(nama_ayah),
                        ''
                    ),
                    nama_ayah
                ),
            nama_ibu =
                COALESCE(
                    NULLIF(
                        VALUES(nama_ibu),
                        ''
                    ),
                    nama_ibu
                ),
            wali_murid =
                COALESCE(
                    NULLIF(
                        VALUES(wali_murid),
                        ''
                    ),
                    wali_murid
                ),
            alamat =
                COALESCE(
                    NULLIF(
                        VALUES(alamat),
                        ''
                    ),
                    alamat
                ),
            no_telp =
                COALESCE(
                    NULLIF(
                        VALUES(no_telp),
                        ''
                    ),
                    no_telp
                ),
            updated_at = NOW()"
    );


$saved = 0;
$skipped = [];


$pdo->beginTransaction();


try {
    foreach (
        array_slice(
            $rows,
            $headerIndex + 1
        )
        as $offset => $row
    ) {
        $get =
            static function (
                string $key
            ) use (
                $row,
                $map
            ): string {
                $index =
                    $map[$key]
                    ?? -1;

                return trim(
                    (string) (
                        $row[$index]
                        ?? ''
                    )
                );
            };


        $name =
            $get('nama_lengkap');


        /**
         * Excel kadang membaca angka sebagai:
         * 12345.0
         */
        $nisRaw =
            $get('nis');

        $nis =
            preg_replace(
                '/\.0$/',
                '',
                $nisRaw
            ) ?? $nisRaw;


        if (
            $name === '' &&
            $nis === ''
        ) {
            continue;
        }


        $rowNumber =
            $headerIndex +
            $offset +
            2;


        if (
            $name === '' ||
            $nis === ''
        ) {
            $skipped[] =
                'Baris ' .
                $rowNumber .
                ' tidak memiliki nama atau NIS.';

            continue;
        }


        if (
            strlen($nis) > 80
        ) {
            $skipped[] =
                'Baris ' .
                $rowNumber .
                ' memiliki NIS terlalu panjang.';

            continue;
        }


        $className =
            normalize_class_name(
                $get('kelas')
                ?: $defaultClass
            );


        if (
            !in_array(
                $className,
                all_class_names(),
                true
            )
        ) {
            $className =
                $defaultClass;
        }


        $genderRaw =
            strtoupper(
                $get('jenis_kelamin')
            );


        $gender =
            str_starts_with(
                $genderRaw,
                'L'
            )
                ? 'L'
                : (
                    str_starts_with(
                        $genderRaw,
                        'P'
                    )
                        ? 'P'
                        : null
                );


        $nik =
            preg_replace(
                '/\D/',
                '',
                $get('nik')
            ) ?: null;


        if (
            $nik !== null &&
            strlen($nik) !== 16
        ) {
            $nik = null;
        }


        $statement->execute([
            uuidv4(),
            $teacherId,
            $name,
            $nis,
            $className,
            $defaultLevel,
            $gender,
            $nik,
            $get(
                'tempat_tanggal_lahir'
            ) ?: null,
            $get(
                'nama_ayah'
            ) ?: null,
            $get(
                'nama_ibu'
            ) ?: null,
            $get(
                'wali_murid'
            ) ?: null,
            $get(
                'alamat'
            ) ?: null,
            $get(
                'no_telp'
            ) ?: null,
        ]);


        $saved++;
    }


    if ($saved === 0) {
        throw new RuntimeException(
            'Tidak ada data siswa yang valid untuk diimpor.'
        );
    }


    $pdo->commit();


    audit_event(
        'students_imported',
        'success',
        $teacherId,
        [
            'file' =>
                $originalName,

            'saved' =>
                $saved,

            'skipped' =>
                count($skipped),

            'class' =>
                $defaultClass,
        ]
    );


    json_response(
        true,
        "Impor selesai: {$saved} siswa disimpan.",
        [
            'saved' =>
                $saved,

            'skipped' =>
                $skipped,
        ]
    );

} catch (Throwable $error) {
    if (
        $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    error_log(
        'NgajiYuk import siswa gagal: ' .
        $error->getMessage()
    );

    throw $error;
}