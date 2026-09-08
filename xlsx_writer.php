<?php
/**
 * ==========================================
 * PENULIS .XLSX MINIMAL (TANPA LIBRARY EKSTERNAL)
 * ==========================================
 * Proyek ini sengaja tanpa Composer/vendor (lihat CLAUDE.md), jadi daripada
 * menambah dependency pihak ketiga untuk generate Excel asli, file ini
 * menulis langsung struktur OOXML minimal (satu sheet) memakai ZipArchive
 * bawaan PHP. Dipakai oleh export_*.php sebagai pengganti CSV ber-BOM,
 * supaya angka benar-benar bertipe number (bisa di-SUM), header bisa bold,
 * dan tidak tergantung setelan pemisah desimal/daftar regional pengguna
 * seperti halnya CSV.
 *
 * Pemakaian singkat:
 *   $xlsx = new SimpleXLSXWriter('Nama Sheet');
 *   $xlsx->setColumnWidths([20, 30, 14, ...]);
 *   $xlsx->addTitleRow('JUDUL LAPORAN', $jumlahKolom);
 *   $xlsx->addRow(['Periode: ...']);
 *   $xlsx->addHeaderRow(['Kolom A', 'Kolom B', ...]);
 *   $xlsx->addRow([$teks, ['value' => 12345, 'style' => 'currency'], ...]);
 *   $xlsx->addRow([['value' => 'TOTAL', 'style' => 'bold'], ['value' => 12345, 'style' => 'currency_bold']]);
 *   $xlsx->freezeHeaderAt($rowIndex);
 *   $xlsx->output('Nama_File.xlsx');
 */

class SimpleXLSXWriter
{
    private string $sheetName;
    /** @var array<int, array> setiap elemen: array cell (lihat writeCellXml) */
    private array $rows = [];
    private array $colWidths = [];
    private ?int $freezeRow = null;
    private int $autoFilterCols = 0;
    private ?int $autoFilterHeaderRow = null;

    public function __construct(string $sheetName = 'Sheet1')
    {
        $this->sheetName = $this->sanitizeSheetName($sheetName);
    }

    private function sanitizeSheetName(string $name): string
    {
        $name = preg_replace('/[\[\]\*\/\\\\\?:]/', ' ', $name);
        return mb_substr(trim($name) ?: 'Sheet1', 0, 31);
    }

    public function setColumnWidths(array $widths): void
    {
        $this->colWidths = $widths;
    }

    /** Baris judul besar & bold, digabung (merge) sepanjang $span kolom. */
    public function addTitleRow(string $title, int $span = 1): void
    {
        $cells = [['value' => $title, 'style' => 'title']];
        for ($i = 1; $i < $span; $i++) {
            $cells[] = ['value' => '', 'style' => 'title'];
        }
        $this->rows[] = ['cells' => $cells, 'merge_span' => $span];
    }

    /** Baris header kolom - bold + fill, dan dicatat untuk freeze pane + autofilter. */
    public function addHeaderRow(array $labels): void
    {
        $cells = array_map(fn($v) => ['value' => $v, 'style' => 'header'], $labels);
        $this->rows[] = ['cells' => $cells];
        $this->freezeRow = count($this->rows);
        $this->autoFilterHeaderRow = count($this->rows);
        $this->autoFilterCols = count($labels);
    }

    /**
     * Baris data biasa. Setiap elemen $cells boleh berupa scalar (auto-detect
     * angka vs teks) atau array ['value' => ..., 'style' => 'normal'|'bold'|'currency'|'currency_bold'].
     */
    public function addRow(array $cells): void
    {
        $normalized = [];
        foreach ($cells as $cell) {
            if (is_array($cell)) {
                $normalized[] = $cell + ['style' => 'normal'];
            } else {
                $normalized[] = ['value' => $cell, 'style' => 'normal'];
            }
        }
        $this->rows[] = ['cells' => $normalized];
    }

    private static function colLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $index = intdiv($index - 1, 26);
        }
        return $letter;
    }

    private static function xmlEscape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private const STYLE_MAP = [
        'normal'        => 0,
        'header'        => 1,
        'currency'      => 2,
        'currency_bold' => 3,
        'bold'          => 4,
        'title'         => 5,
    ];

    private function isNumericStyle(string $style): bool
    {
        return in_array($style, ['currency', 'currency_bold'], true);
    }

    private function buildSheetXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        // Urutan elemen CT_Worksheet WAJIB persis: sheetViews sebelum cols,
        // cols sebelum sheetData. LibreOffice memaafkan urutan salah (auto-
        // reorder diam-diam); Excel asli memvalidasi ketat dan menolaknya -
        // gejalanya "file perlu diperbaiki" lalu isi sheet dibuang kosong,
        // bukan sekadar diurutkan ulang. Jangan tukar urutan tiga blok ini.
        if ($this->freezeRow !== null) {
            $topLeft = 'A' . ($this->freezeRow + 1);
            $xml .= '<sheetViews><sheetView tabSelected="1" workbookViewId="0">'
                  . '<pane ySplit="' . $this->freezeRow . '" topLeftCell="' . $topLeft . '" activePane="bottomLeft" state="frozen"/>'
                  . '<selection pane="bottomLeft" activeCell="' . $topLeft . '" sqref="' . $topLeft . '"/>'
                  . '</sheetView></sheetViews>';
        }

        if (!empty($this->colWidths)) {
            $xml .= '<cols>';
            foreach ($this->colWidths as $i => $w) {
                $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float)$w . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach ($this->rows as $rowIndex => $row) {
            $rowNum = $rowIndex + 1;
            $xml .= '<row r="' . $rowNum . '">';
            foreach ($row['cells'] as $colIndex => $cell) {
                $ref = self::colLetter($colIndex) . $rowNum;
                $styleId = self::STYLE_MAP[$cell['style']] ?? 0;
                $value = $cell['value'];
                if ($this->isNumericStyle($cell['style']) || is_int($value) || is_float($value)) {
                    $num = is_numeric($value) ? $value : 0;
                    $xml .= '<c r="' . $ref . '" s="' . $styleId . '"><v>' . self::formatNumber($num) . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '" s="' . $styleId . '" t="inlineStr"><is><t xml:space="preserve">' . self::xmlEscape((string)$value) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';

        if ($this->autoFilterHeaderRow !== null && $this->autoFilterCols > 0) {
            $lastRow = count($this->rows);
            $ref = 'A' . $this->autoFilterHeaderRow . ':' . self::colLetter($this->autoFilterCols - 1) . $lastRow;
            $xml .= '<autoFilter ref="' . $ref . '"/>';
        }

        foreach ($this->rows as $rowIndex => $row) {
            if (!empty($row['merge_span']) && $row['merge_span'] > 1) {
                $rowNum = $rowIndex + 1;
                $xml .= '<mergeCells count="1"><mergeCell ref="A' . $rowNum . ':' . self::colLetter($row['merge_span'] - 1) . $rowNum . '"/></mergeCells>';
            }
        }

        $xml .= '</worksheet>';
        return $xml;
    }

    private static function formatNumber($num): string
    {
        if ((float)$num == (int)$num) {
            return (string)(int)$num;
        }
        return rtrim(rtrim(sprintf('%.4F', (float)$num), '0'), '.');
    }

    private function buildStylesXml(): string
    {
        // numFmtId 44 kurang lebih cocok untuk uang, tapi kita definisikan
        // custom format sendiri (id 164) supaya tidak terikat simbol mata uang
        // regional - cukup pemisah ribuan, tanpa desimal (gaji dalam Rupiah bulat).
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0"/></numFmts>'
            . '<fonts count="4">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="14"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE2E8F0"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="6">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' // 0 normal
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>' // 1 header
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>' // 2 currency
            . '<xf numFmtId="164" fontId="2" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>' // 3 currency_bold
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>' // 4 bold
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>' // 5 title
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /** Kirim file .xlsx sebagai response HTTP lalu exit. */
    public function output(string $filename): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive();
        $zip->open($tmpFile, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::xmlEscape($this->sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/styles.xml', $this->buildStylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->buildSheetXml());
        $zip->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmpFile));
        readfile($tmpFile);
        unlink($tmpFile);
        exit();
    }
}
