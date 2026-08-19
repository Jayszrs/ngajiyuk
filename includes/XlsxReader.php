<?php
declare(strict_types=1);

final class XlsxReader
{
    public static function rows(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'csv') return self::csvRows($path);
        if ($extension !== 'xlsx') throw new RuntimeException('Format yang didukung adalah XLSX atau CSV.');
        if (!class_exists('ZipArchive')) throw new RuntimeException('Ekstensi PHP zip belum aktif.');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('File XLSX tidak dapat dibuka.');
        $shared=[];$sharedXml=$zip->getFromName('xl/sharedStrings.xml');
        if($sharedXml!==false){$xml=simplexml_load_string($sharedXml);if($xml){foreach($xml->si as $si){$parts=[];if(isset($si->t))$parts[]=(string)$si->t;foreach($si->r as $run)$parts[]=(string)$run->t;$shared[]=implode('',$parts);}}}
        $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');$zip->close();if($sheet===false)throw new RuntimeException('Worksheet pertama tidak ditemukan.');$xml=simplexml_load_string($sheet);if(!$xml)throw new RuntimeException('Worksheet tidak valid.');$rows=[];
        foreach($xml->sheetData->row as $row){$values=[];foreach($row->c as $cell){$ref=(string)$cell['r'];preg_match('/^[A-Z]+/',$ref,$m);$index=self::columnIndex($m[0]??'A');$type=(string)$cell['t'];$value=(string)$cell->v;if($type==='s')$value=$shared[(int)$value]??'';elseif($type==='inlineStr')$value=(string)$cell->is->t;$values[$index]=trim($value);}if($values){$max=max(array_keys($values));$rows[]=array_map(static fn($i)=>$values[$i]??'',range(0,$max));}}
        return $rows;
    }
    private static function csvRows(string $path): array
    {
        $handle=fopen($path,'rb');if(!$handle)throw new RuntimeException('CSV tidak dapat dibuka.');$rows=[];while(($row=fgetcsv($handle,0,','))!==false){if(count($row)===1&&str_contains($row[0],';'))$row=str_getcsv($row[0],';');$rows[]=$row;}fclose($handle);return $rows;
    }
    private static function columnIndex(string $letters): int
    {
        $result=0;foreach(str_split($letters) as $letter)$result=$result*26+(ord($letter)-64);return $result-1;
    }
}

