<?php

declare(strict_types=1);

final class XlsxReader
{
    /**
     * Membaca file XLSX atau CSV.
     *
     * Parameter extension sengaja bisa dikirim dari luar karena
     * file upload PHP menggunakan nama temporary seperti:
     *
     * C:\xampp\tmp\php1234.tmp
     *
     * Jadi ekstensi tidak boleh hanya diambil dari $path.
     */
    public static function rows(
        string $path,
        ?string $extension = null
    ): array {
        if (
            $path === '' ||
            !is_file($path) ||
            !is_readable($path)
        ) {
            throw new RuntimeException(
                'File impor tidak dapat dibaca.'
            );
        }

        $extension = strtolower(
            trim(
                $extension ??
                pathinfo($path, PATHINFO_EXTENSION)
            )
        );

        if ($extension === 'csv') {
            return self::csvRows($path);
        }

        if ($extension !== 'xlsx') {
            throw new RuntimeException(
                'Format yang didukung adalah XLSX atau CSV.'
            );
        }

        return self::xlsxRows($path);
    }


    /**
     * Membaca workbook XLSX.
     */
    private static function xlsxRows(
        string $path
    ): array {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException(
                'Ekstensi PHP Zip belum aktif. Aktifkan Zip pada konfigurasi PHP XAMPP.'
            );
        }

        if (
            !function_exists(
                'simplexml_load_string'
            )
        ) {
            throw new RuntimeException(
                'Ekstensi PHP SimpleXML belum aktif.'
            );
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException(
                'File XLSX tidak dapat dibuka.'
            );
        }

        try {
            $sharedStrings = [];

            /**
             * XLSX menyimpan sebagian teks pada
             * xl/sharedStrings.xml.
             */
            $sharedXml = $zip->getFromName(
                'xl/sharedStrings.xml'
            );

            if ($sharedXml !== false) {
                $xml = self::loadXml(
                    $sharedXml,
                    'Data shared strings XLSX tidak valid.'
                );

                $namespace =
                    self::mainNamespace($xml);

                $root =
                    self::mainChildren(
                        $xml,
                        $namespace
                    );

                foreach ($root->si as $stringItem) {
                    $sharedStrings[] =
                        self::nodeText(
                            $stringItem,
                            $namespace
                        );
                }
            }


            /**
             * Saat ini project membaca worksheet pertama.
             */
            $sheetXml = $zip->getFromName(
                'xl/worksheets/sheet1.xml'
            );

            if ($sheetXml === false) {
                throw new RuntimeException(
                    'Worksheet pertama tidak ditemukan.'
                );
            }

        } finally {
            $zip->close();
        }


        $xml = self::loadXml(
            $sheetXml,
            'Worksheet XLSX tidak valid.'
        );

        $namespace =
            self::mainNamespace($xml);

        $root =
            self::mainChildren(
                $xml,
                $namespace
            );

        if (!isset($root->sheetData)) {
            throw new RuntimeException(
                'Data worksheet tidak ditemukan.'
            );
        }

        $rows = [];

        foreach ($root->sheetData->row as $row) {
            $values = [];

            foreach ($row->c as $cell) {
                /*
                 * SimpleXML tidak selalu mengekspos atribut elemen yang
                 * berada di default namespace melalui $cell['r']. Ambil
                 * atribut secara eksplisit agar file XLSX buatan Excel,
                 * LibreOffice, maupun template internal terbaca konsisten.
                 */
                $attributes =
                    $cell->attributes();

                $reference =
                    strtoupper(
                        (string) (
                            $attributes['r']
                            ?? ''
                        )
                    );

                if (
                    !preg_match(
                        '/^([A-Z]+)/',
                        $reference,
                        $matches
                    )
                ) {
                    continue;
                }

                $columnIndex =
                    self::columnIndex(
                        $matches[1]
                    );

                $type =
                    (string) (
                        $attributes['t']
                        ?? ''
                    );

                $cellChildren =
                    self::mainChildren(
                        $cell,
                        $namespace
                    );

                $value = '';

                /**
                 * Shared string.
                 */
                if ($type === 's') {
                    $sharedIndex =
                        (int) (
                            $cellChildren->v ?? 0
                        );

                    $value =
                        $sharedStrings[
                            $sharedIndex
                        ] ?? '';

                /**
                 * Inline string.
                 */
                } elseif ($type === 'inlineStr') {
                    if (
                        isset(
                            $cellChildren->is
                        )
                    ) {
                        $value =
                            self::nodeText(
                                $cellChildren->is,
                                $namespace
                            );
                    }

                /**
                 * Boolean.
                 */
                } elseif ($type === 'b') {
                    $raw =
                        (string) (
                            $cellChildren->v ?? ''
                        );

                    $value =
                        $raw === '1'
                            ? 'TRUE'
                            : 'FALSE';

                /**
                 * Angka, formula cached value,
                 * atau string biasa.
                 */
                } else {
                    $value =
                        (string) (
                            $cellChildren->v ?? ''
                        );
                }

                $values[$columnIndex] =
                    trim($value);
            }

            if (!$values) {
                continue;
            }

            $maxIndex =
                max(
                    array_keys($values)
                );

            $rows[] =
                array_map(
                    static fn(int $index): string =>
                        $values[$index] ?? '',
                    range(
                        0,
                        $maxIndex
                    )
                );
        }

        return $rows;
    }


    /**
     * Membaca CSV.
     *
     * Mendukung delimiter koma dan titik koma.
     * BOM UTF-8 pada kolom pertama juga dibersihkan.
     */
    private static function csvRows(
        string $path
    ): array {
        $handle = fopen(
            $path,
            'rb'
        );

        if ($handle === false) {
            throw new RuntimeException(
                'CSV tidak dapat dibuka.'
            );
        }

        $rows = [];
        $firstRow = true;

        try {
            while (
                (
                    $row = fgetcsv(
                        $handle,
                        0,
                        ','
                    )
                ) !== false
            ) {
                /**
                 * Jika seluruh baris terbaca sebagai
                 * satu kolom dan terdapat ;,
                 * coba delimiter titik koma.
                 */
                if (
                    count($row) === 1 &&
                    str_contains(
                        (string) $row[0],
                        ';'
                    )
                ) {
                    $row = str_getcsv(
                        (string) $row[0],
                        ';'
                    );
                }

                /**
                 * Hapus UTF-8 BOM pada sel pertama.
                 */
                if (
                    $firstRow &&
                    isset($row[0])
                ) {
                    $row[0] = preg_replace(
                        '/^\xEF\xBB\xBF/',
                        '',
                        (string) $row[0]
                    ) ?? (string) $row[0];
                }

                $firstRow = false;

                $rows[] =
                    array_map(
                        static fn($value): string =>
                            trim(
                                (string) $value
                            ),
                        $row
                    );
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }


    /**
     * Parse XML dengan error internal.
     */
    private static function loadXml(
        string $contents,
        string $errorMessage
    ): SimpleXMLElement {
        $previous =
            libxml_use_internal_errors(true);

        try {
            $xml =
                simplexml_load_string(
                    $contents,
                    'SimpleXMLElement',
                    LIBXML_NONET |
                    LIBXML_NOCDATA
                );

            if (
                !$xml instanceof
                SimpleXMLElement
            ) {
                throw new RuntimeException(
                    $errorMessage
                );
            }

            return $xml;
        } finally {
            libxml_clear_errors();

            libxml_use_internal_errors(
                $previous
            );
        }
    }


    /**
     * Mengambil default namespace XML XLSX.
     */
    private static function mainNamespace(
        SimpleXMLElement $xml
    ): string {
        $namespaces =
            $xml->getNamespaces(true);

        return
            $namespaces['']
            ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    }


    /**
     * Mengambil children dari namespace utama.
     */
    private static function mainChildren(
        SimpleXMLElement $xml,
        string $namespace
    ): SimpleXMLElement {
        if ($namespace === '') {
            return $xml;
        }

        return $xml->children(
            $namespace
        );
    }


    /**
     * Menggabungkan teks biasa dan rich text XLSX.
     */
    private static function nodeText(
        SimpleXMLElement $node,
        string $namespace
    ): string {
        $children =
            self::mainChildren(
                $node,
                $namespace
            );

        $parts = [];

        foreach ($children->t as $text) {
            $parts[] =
                (string) $text;
        }

        foreach ($children->r as $run) {
            $runChildren =
                self::mainChildren(
                    $run,
                    $namespace
                );

            foreach (
                $runChildren->t
                as $text
            ) {
                $parts[] =
                    (string) $text;
            }
        }

        return implode(
            '',
            $parts
        );
    }


    /**
     * Konversi A -> 0
     * B -> 1
     * Z -> 25
     * AA -> 26
     */
    private static function columnIndex(
        string $letters
    ): int {
        $result = 0;

        foreach (
            str_split(
                strtoupper($letters)
            )
            as $letter
        ) {
            $result =
                ($result * 26) +
                (
                    ord($letter) -
                    ord('A') +
                    1
                );
        }

        return $result - 1;
    }
}
