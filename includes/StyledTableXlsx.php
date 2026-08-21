<?php

declare(strict_types=1);

/**
 * Ekspor tabel XLSX berkop sekolah tanpa dependency Composer.
 *
 * Seluruh nilai ditulis sebagai teks agar NIS, NIK, nomor telepon, dan tanggal
 * tidak diubah Excel menjadi notasi ilmiah atau deretan tanda pagar.
 */
final class StyledTableXlsx
{
    public static function download(
        string $filename,
        string $reportTitle,
        string $reportSubtitle,
        array $headers,
        array $rows,
        string $sheetName = 'Data Kelas'
    ): never {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException(
                'Ekstensi PHP Zip belum aktif. Aktifkan extension=zip pada php.ini XAMPP.'
            );
        }

        if ($headers === []) {
            throw new RuntimeException('Kolom ekspor XLSX tidak tersedia.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'ngajiyuk-export-');
        if ($temporaryPath === false) {
            throw new RuntimeException('File sementara ekspor tidak dapat dibuat.');
        }

        try {
            self::build(
                $temporaryPath,
                $reportTitle,
                $reportSubtitle,
                $headers,
                $rows,
                $sheetName
            );

            header(
                'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . (string) filesize($temporaryPath));
            header('Cache-Control: private, max-age=0, must-revalidate');
            readfile($temporaryPath);
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }

        exit;
    }

    private static function build(
        string $path,
        string $reportTitle,
        string $reportSubtitle,
        array $headers,
        array $rows,
        string $sheetName
    ): void {
        $schoolLogo = ROOT_PATH . '/assets/images/logo.png';
        $tahsinLogo = ROOT_PATH . '/assets/images/logo-tahsin.png';
        if (!is_file($schoolLogo) || !is_file($tahsinLogo)) {
            throw new RuntimeException('Dua logo resmi untuk ekspor tidak ditemukan.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('File ekspor XLSX tidak dapat dibuat.');
        }

        try {
            $columnCount = count($headers);
            $zip->addFromString('[Content_Types].xml', self::contentTypes());
            $zip->addFromString('_rels/.rels', self::rootRelationships());
            $zip->addFromString('docProps/app.xml', self::appProperties());
            $zip->addFromString('docProps/core.xml', self::coreProperties($reportTitle));
            $zip->addFromString('xl/workbook.xml', self::workbook($sheetName));
            $zip->addFromString(
                'xl/_rels/workbook.xml.rels',
                self::workbookRelationships()
            );
            $zip->addFromString('xl/styles.xml', self::styles());
            $zip->addFromString(
                'xl/worksheets/sheet1.xml',
                self::worksheet($reportTitle, $reportSubtitle, $headers, $rows)
            );
            $zip->addFromString(
                'xl/worksheets/_rels/sheet1.xml.rels',
                self::worksheetRelationships()
            );
            $zip->addFromString(
                'xl/drawings/drawing1.xml',
                self::drawing($columnCount)
            );
            $zip->addFromString(
                'xl/drawings/_rels/drawing1.xml.rels',
                self::drawingRelationships()
            );
            $zip->addFile($schoolLogo, 'xl/media/logo-sekolah.png');
            $zip->addFile($tahsinLogo, 'xl/media/logo-tahsin.png');
        } finally {
            $zip->close();
        }
    }

    private static function worksheet(
        string $reportTitle,
        string $reportSubtitle,
        array $headers,
        array $rows
    ): string {
        $columnCount = count($headers);
        $lastColumn = self::columnName($columnCount);
        $lastRow = max(7, 6 + count($rows));
        $mergeStart = $columnCount > 3 ? 'B' : 'A';
        $mergeEnd = $columnCount > 3
            ? self::columnName($columnCount - 1)
            : $lastColumn;

        $widths = self::columnWidths($headers, $rows);
        $columnsXml = '';
        foreach ($widths as $index => $width) {
            $number = $index + 1;
            $columnsXml .= '<col min="' . $number . '" max="' . $number
                . '" width="' . $width . '" customWidth="1"/>';
        }

        $headerCells = '';
        foreach (array_values($headers) as $index => $header) {
            $headerCells .= self::inlineCell(
                self::columnName($index + 1) . '6',
                (string) $header,
                5
            );
        }

        $dataRowsXml = '';
        foreach (array_values($rows) as $rowIndex => $row) {
            $excelRow = $rowIndex + 7;
            $style = $rowIndex % 2 === 0 ? 6 : 7;
            $cells = '';
            for ($columnIndex = 0; $columnIndex < $columnCount; $columnIndex++) {
                $cells .= self::inlineCell(
                    self::columnName($columnIndex + 1) . $excelRow,
                    (string) ($row[$columnIndex] ?? ''),
                    $style
                );
            }
            $dataRowsXml .= '<row r="' . $excelRow . '" ht="24" customHeight="1">'
                . $cells . '</row>';
        }

        if ($rows === []) {
            $dataRowsXml = '<row r="7" ht="28" customHeight="1">'
                . self::inlineCell('A7', 'Belum ada data pada kelas dan periode ini.', 8)
                . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetPr><tabColor rgb="FF0B6948"/><pageSetUpPr fitToPage="1"/></sheetPr>'
            . '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0" showGridLines="0">'
            . '<pane ySplit="6" topLeftCell="A7" activePane="bottomLeft" state="frozen"/>'
            . '<selection pane="bottomLeft" activeCell="A7" sqref="A7"/>'
            . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="21"/>'
            . '<cols>' . $columnsXml . '</cols>'
            . '<sheetData>'
            . '<row r="1" ht="25" customHeight="1">'
            . self::inlineCell($mergeStart . '1', 'YAYASAN BANI SALEH', 1)
            . '</row>'
            . '<row r="2" ht="28" customHeight="1">'
            . self::inlineCell(
                $mergeStart . '2',
                'SEKOLAH DASAR ISLAM LABSCHOOL BANI SALEH',
                2
            )
            . '</row>'
            . '<row r="3" ht="27" customHeight="1">'
            . self::inlineCell($mergeStart . '3', $reportTitle, 3)
            . '</row>'
            . '<row r="4" ht="22" customHeight="1">'
            . self::inlineCell($mergeStart . '4', $reportSubtitle, 4)
            . '</row>'
            . '<row r="5" ht="10" customHeight="1"/>'
            . '<row r="6" ht="38" customHeight="1">' . $headerCells . '</row>'
            . $dataRowsXml
            . '</sheetData>'
            . '<autoFilter ref="A6:' . $lastColumn . $lastRow . '"/>'
            . '<mergeCells count="4">'
            . '<mergeCell ref="' . $mergeStart . '1:' . $mergeEnd . '1"/>'
            . '<mergeCell ref="' . $mergeStart . '2:' . $mergeEnd . '2"/>'
            . '<mergeCell ref="' . $mergeStart . '3:' . $mergeEnd . '3"/>'
            . '<mergeCell ref="' . $mergeStart . '4:' . $mergeEnd . '4"/>'
            . '</mergeCells>'
            . '<printOptions horizontalCentered="1"/>'
            . '<pageMargins left="0.2" right="0.2" top="0.4" bottom="0.4" header="0.2" footer="0.2"/>'
            . '<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/>'
            . '<drawing r:id="rId1"/>'
            . '</worksheet>';
    }

    private static function columnWidths(array $headers, array $rows): array
    {
        $widths = [];
        foreach (array_values($headers) as $index => $header) {
            $maximum = self::textLength((string) $header);
            foreach ($rows as $row) {
                $maximum = max(
                    $maximum,
                    self::textLength((string) ($row[$index] ?? ''))
                );
            }
            $widths[$index] = min(36, max(9, $maximum + 3));
        }
        return $widths;
    }

    private static function textLength(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="7">'
            . '<font><sz val="10"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><color rgb="FF22332D"/><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><color rgb="FF174A38"/><sz val="14"/><name val="Calibri"/></font>'
            . '<font><b/><color rgb="FF142039"/><sz val="13"/><name val="Calibri"/></font>'
            . '<font><i/><color rgb="FF64736D"/><sz val="10"/><name val="Calibri"/></font>'
            . '<font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Calibri"/></font>'
            . '<font><color rgb="FF23352F"/><sz val="10"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="5">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF174D3A"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF0FAF5"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFF8E7"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFD8E2DD"/></left>'
            . '<right style="thin"><color rgb="FFD8E2DD"/></right>'
            . '<top style="thin"><color rgb="FFD8E2DD"/></top>'
            . '<bottom style="thin"><color rgb="FFD8E2DD"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="9">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="5" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="49" fontId="6" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            . '<xf numFmtId="49" fontId="6" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="4" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private static function drawing(int $columnCount): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" '
            . 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . self::imageAnchor(0, 'Logo Sekolah', 'rId1', 720000, 720000)
            . self::imageAnchor(max(1, $columnCount - 1), 'Logo Tahsin', 'rId2', 900000, 670000)
            . '</xdr:wsDr>';
    }

    private static function imageAnchor(
        int $column,
        string $name,
        string $relationship,
        int $width,
        int $height
    ): string {
        return '<xdr:oneCellAnchor><xdr:from><xdr:col>' . $column . '</xdr:col>'
            . '<xdr:colOff>40000</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>30000</xdr:rowOff>'
            . '</xdr:from><xdr:ext cx="' . $width . '" cy="' . $height . '"/>'
            . '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="' . ($column + 2) . '" name="'
            . self::xml($name) . '"/><xdr:cNvPicPr/></xdr:nvPicPr>'
            . '<xdr:blipFill><a:blip r:embed="' . $relationship . '"/>'
            . '<a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
            . '<xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>'
            . '</xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
    }

    private static function inlineCell(string $reference, string $value, int $style): string
    {
        return '<c r="' . $reference . '" t="inlineStr" s="' . $style
            . '"><is><t xml:space="preserve">' . self::xml($value) . '</t></is></c>';
    }

    private static function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }
        return $name;
    }

    private static function xml(string $value): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';
        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="png" ContentType="image/png"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private static function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private static function workbook(string $sheetName): string
    {
        $safeName = mb_substr($sheetName, 0, 31, 'UTF-8');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView xWindow="0" yWindow="0" windowWidth="24000" windowHeight="14000"/></bookViews>'
            . '<sheets><sheet name="' . self::xml($safeName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '<calcPr calcId="191029"/></workbook>';
    }

    private static function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function worksheetRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
            . '</Relationships>';
    }

    private static function drawingRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/logo-sekolah.png"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/logo-tahsin.png"/>'
            . '</Relationships>';
    }

    private static function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>NgajiYuk</Application><AppVersion>1.0</AppVersion></Properties>';
    }

    private static function coreProperties(string $title): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>NgajiYuk</dc:creator><dc:title>' . self::xml($title) . '</dc:title>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z')
            . '</dcterms:created></cp:coreProperties>';
    }
}
