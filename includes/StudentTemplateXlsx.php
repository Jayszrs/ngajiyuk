<?php

declare(strict_types=1);

/**
 * Pembuat template impor siswa tanpa dependency Composer.
 *
 * File XLSX dibangun dari komponen OpenXML agar template dapat memuat
 * kop sekolah, dua logo, gaya tabel, filter, freeze pane, dan validasi data.
 */
final class StudentTemplateXlsx
{
    public static function download(
        string $filename = 'template-data-siswa-ngajiyuk.xlsx'
    ): never {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException(
                'Ekstensi PHP Zip belum aktif. Aktifkan extension=zip pada php.ini XAMPP.'
            );
        }

        $temporaryPath = tempnam(
            sys_get_temp_dir(),
            'ngajiyuk-template-'
        );

        if ($temporaryPath === false) {
            throw new RuntimeException('File sementara template tidak dapat dibuat.');
        }

        try {
            self::build($temporaryPath);

            header(
                'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
            header(
                'Content-Disposition: attachment; filename="' . $filename . '"'
            );
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

    private static function build(string $path): void
    {
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Template XLSX tidak dapat dibuat.');
        }

        $logoSchool = ROOT_PATH . '/assets/images/logo.png';
        $logoTahsin = ROOT_PATH . '/assets/images/logo-tahsin.png';

        if (!is_file($logoSchool) || !is_file($logoTahsin)) {
            $zip->close();
            throw new RuntimeException('Dua logo template tidak ditemukan.');
        }

        try {
            $zip->addFromString('[Content_Types].xml', self::contentTypes());
            $zip->addFromString('_rels/.rels', self::rootRelationships());
            $zip->addFromString('docProps/app.xml', self::appProperties());
            $zip->addFromString('docProps/core.xml', self::coreProperties());
            $zip->addFromString('xl/workbook.xml', self::workbook());
            $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelationships());
            $zip->addFromString('xl/styles.xml', self::styles());
            $zip->addFromString('xl/worksheets/sheet1.xml', self::worksheet());
            $zip->addFromString(
                'xl/worksheets/_rels/sheet1.xml.rels',
                self::worksheetRelationships()
            );
            $zip->addFromString('xl/drawings/drawing1.xml', self::drawing());
            $zip->addFromString(
                'xl/drawings/_rels/drawing1.xml.rels',
                self::drawingRelationships()
            );
            $zip->addFile($logoSchool, 'xl/media/logo-sekolah.png');
            $zip->addFile($logoTahsin, 'xl/media/logo-tahsin.png');
        } finally {
            $zip->close();
        }
    }

    private static function worksheet(): string
    {
        $headers = [
            'Nama Peserta Didik',
            'L/P',
            'NIS',
            'NIK',
            'Tempat/Tanggal Lahir',
            'Ayah',
            'Ibu',
            'Wali Murid',
            'Alamat',
            'Nomor Telepon',
            'Kelas',
            'Level',
        ];

        $example = [
            'Contoh Nama Siswa',
            'L',
            '262701001',
            '3275010101010001',
            'Bekasi, 1 Januari 2020',
            'Nama Ayah',
            'Nama Ibu',
            'Nama Wali',
            'Alamat lengkap siswa',
            '081234567890',
            '1A',
            '1',
        ];

        $headerCells = '';
        $exampleCells = '';

        foreach ($headers as $index => $value) {
            $column = self::columnName($index + 1);
            $headerCells .= self::inlineCell($column . '6', $value, 4);
            $exampleCells .= self::inlineCell(
                $column . '7',
                $example[$index],
                in_array($index, [2, 3, 9], true) ? 7 : 5
            );
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetPr><tabColor rgb="FF0B6948"/></sheetPr>'
            . '<dimension ref="A1:L1000"/>'
            . '<sheetViews><sheetView workbookViewId="0" showGridLines="0">'
            . '<pane ySplit="6" topLeftCell="A7" activePane="bottomLeft" state="frozen"/>'
            . '<selection pane="bottomLeft" activeCell="A7" sqref="A7"/>'
            . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="21"/>'
            . '<cols>'
            . '<col min="1" max="1" width="28" customWidth="1"/>'
            . '<col min="2" max="2" width="9" customWidth="1"/>'
            . '<col min="3" max="4" width="19" customWidth="1" style="7"/>'
            . '<col min="5" max="8" width="25" customWidth="1"/>'
            . '<col min="9" max="9" width="34" customWidth="1"/>'
            . '<col min="10" max="10" width="19" customWidth="1" style="7"/>'
            . '<col min="11" max="12" width="12" customWidth="1"/>'
            . '</cols>'
            . '<sheetData>'
            . '<row r="1" ht="29" customHeight="1">'
            . self::inlineCell('B1', 'YAYASAN BANI SALEH', 1)
            . '</row>'
            . '<row r="2" ht="29" customHeight="1">'
            . self::inlineCell('B2', 'SEKOLAH DASAR ISLAM LABSCHOOL BANI SALEH', 2)
            . '</row>'
            . '<row r="3" ht="27" customHeight="1">'
            . self::inlineCell('B3', 'TEMPLATE IMPORT DATA SISWA NGAJIYUK', 2)
            . '</row>'
            . '<row r="4" ht="24" customHeight="1">'
            . self::inlineCell(
                'B4',
                'Isi satu siswa per baris. Jangan mengubah nama kolom pada baris 6.',
                3
            )
            . '</row>'
            . '<row r="5" ht="9" customHeight="1"/>'
            . '<row r="6" ht="32" customHeight="1">' . $headerCells . '</row>'
            . '<row r="7" ht="28" customHeight="1">' . $exampleCells . '</row>'
            . '</sheetData>'
            . '<autoFilter ref="A6:L1000"/>'
            . '<mergeCells count="4">'
            . '<mergeCell ref="B1:K1"/><mergeCell ref="B2:K2"/>'
            . '<mergeCell ref="B3:K3"/><mergeCell ref="B4:K4"/>'
            . '</mergeCells>'
            . '<dataValidations count="3">'
            . '<dataValidation type="list" allowBlank="1" showErrorMessage="1" '
            . 'errorTitle="Jenis kelamin tidak valid" error="Gunakan L atau P." sqref="B7:B1000">'
            . '<formula1>"L,P"</formula1></dataValidation>'
            . '<dataValidation type="list" allowBlank="0" showErrorMessage="1" '
            . 'errorTitle="Kelas tidak valid" error="Pilih kelas 1A sampai 6B." sqref="K7:K1000">'
            . '<formula1>"1A,1B,2A,2B,3A,3B,4A,4B,5A,5B,6A,6B"</formula1></dataValidation>'
            . '<dataValidation type="whole" operator="between" allowBlank="0" '
            . 'showErrorMessage="1" errorTitle="Level tidak valid" '
            . 'error="Level harus 1 sampai 9." sqref="L7:L1000">'
            . '<formula1>1</formula1><formula2>9</formula2></dataValidation>'
            . '</dataValidations>'
            . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            . '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            . '<drawing r:id="rId1"/>'
            . '</worksheet>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="6">'
            . '<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><color rgb="FFFFFFFF"/><sz val="16"/><name val="Calibri"/></font>'
            . '<font><b/><color rgb="FF143D2F"/><sz val="14"/><name val="Calibri"/></font>'
            . '<font><i/><color rgb="FF5F6F68"/><sz val="10"/><name val="Calibri"/></font>'
            . '<font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Calibri"/></font>'
            . '<font><color rgb="FF24352F"/><sz val="10"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="5">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF144C38"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE9F8F0"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFF6D9"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFD7E1DC"/></left>'
            . '<right style="thin"><color rgb="FFD7E1DC"/></right>'
            . '<top style="thin"><color rgb="FFD7E1DC"/></top>'
            . '<bottom style="thin"><color rgb="FFD7E1DC"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="8">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="4" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="5" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="5" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="49" fontId="5" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private static function drawing(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" '
            . 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . self::imageAnchor(0, 0, 'Logo Sekolah', 'rId1', 780000, 780000)
            . self::imageAnchor(11, 0, 'Logo Tahsin', 'rId2', 960000, 720000)
            . '</xdr:wsDr>';
    }

    private static function imageAnchor(
        int $column,
        int $row,
        string $name,
        string $relationship,
        int $width,
        int $height
    ): string {
        return '<xdr:oneCellAnchor><xdr:from><xdr:col>' . $column . '</xdr:col>'
            . '<xdr:colOff>45000</xdr:colOff><xdr:row>' . $row . '</xdr:row>'
            . '<xdr:rowOff>35000</xdr:rowOff></xdr:from>'
            . '<xdr:ext cx="' . $width . '" cy="' . $height . '"/>'
            . '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="' . ($column + 2) . '" name="'
            . self::xml($name) . '"/><xdr:cNvPicPr/></xdr:nvPicPr>'
            . '<xdr:blipFill><a:blip r:embed="' . $relationship . '"/>'
            . '<a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
            . '<xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>'
            . '</xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
    }

    private static function inlineCell(string $reference, string $value, int $style): string
    {
        return '<c r="' . $reference . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">'
            . self::xml($value) . '</t></is></c>';
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
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
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

    private static function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView xWindow="0" yWindow="0" windowWidth="24000" windowHeight="14000"/></bookViews>'
            . '<sheets><sheet name="Data Siswa" sheetId="1" r:id="rId1"/></sheets>'
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
            . '<Application>NgajiYuk</Application><AppVersion>1.0</AppVersion>'
            . '</Properties>';
    }

    private static function coreProperties(): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>NgajiYuk</dc:creator><dc:title>Template Data Siswa</dc:title>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '</cp:coreProperties>';
    }
}
