$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$php = 'C:\xampp\php\php.exe'
$studentFile = Join-Path $env:TEMP 'ngajiyuk-student-template-check.xlsx'
$exportFile = Join-Path $env:TEMP 'ngajiyuk-class-export-check.xlsx'

function Write-BinaryPhpOutput {
    param([string]$ScriptPath, [string]$TargetPath)

    $info = New-Object System.Diagnostics.ProcessStartInfo
    $info.FileName = $php
    $info.Arguments = '"' + $ScriptPath + '" --child'
    $info.UseShellExecute = $false
    $info.RedirectStandardOutput = $true
    $info.RedirectStandardError = $true
    $info.CreateNoWindow = $true

    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $info
    [void] $process.Start()
    $stream = [System.IO.File]::Create($TargetPath)
    try {
        $process.StandardOutput.BaseStream.CopyTo($stream)
    } finally {
        $stream.Dispose()
    }
    $errorText = $process.StandardError.ReadToEnd()
    $process.WaitForExit()
    if ($process.ExitCode -ne 0) {
        throw "Generator gagal: $errorText"
    }
}

Write-BinaryPhpOutput (Join-Path $PSScriptRoot 'student-template.php') $studentFile
Write-BinaryPhpOutput (Join-Path $PSScriptRoot 'styled-xlsx.php') $exportFile

$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$excel.DisplayAlerts = $false

try {
    foreach ($file in @($studentFile, $exportFile)) {
        $book = $excel.Workbooks.Open($file, 0, $true)
        try {
            $sheet = $book.Worksheets.Item(1)
            $header = [string] $sheet.Range('A6').Text
            $data = [string] $sheet.Range('A7').Text
            if ($header -eq '') {
                throw "Excel menghapus worksheet saat membuka $file"
            }
            Write-Output "PASS_EXCEL $([System.IO.Path]::GetFileName($file)) HEADER=$header DATA=$data"
            [System.Runtime.InteropServices.Marshal]::ReleaseComObject($sheet) | Out-Null
        } finally {
            $book.Close($false)
            [System.Runtime.InteropServices.Marshal]::ReleaseComObject($book) | Out-Null
        }
    }
} finally {
    $excel.Quit()
    [System.Runtime.InteropServices.Marshal]::ReleaseComObject($excel) | Out-Null
    if (Test-Path -LiteralPath $studentFile) {
        Remove-Item -LiteralPath $studentFile -Force
    }
    if (Test-Path -LiteralPath $exportFile) {
        Remove-Item -LiteralPath $exportFile -Force
    }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}
