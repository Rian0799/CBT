<?php
// app/WordQuestionService.php - Template Soal Word (.docx) Format Tabel Modern & Parser
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/QuestionService.php';

class WordQuestionService {

    /**
     * Download Template Soal Microsoft Word (.docx modern / .doc legacy)
     */
    public static function downloadTemplate(string $format = 'docx'): void {
        if ($format === 'xlsx') {
            self::generateXlsxTemplate();
        } elseif ($format === 'docx' && class_exists('ZipArchive')) {
            self::generateDocxTemplate();
        } else {
            self::generateDocTemplate();
        }
        exit;
    }

    /**
     * Generate Template Excel (.xlsx) Modern - Office Open XML 2007+
     * Format tabel yang rapi & kompatibel dengan Excel terbaru
     */
    public static function generateXlsxTemplate(): void {
        if (!class_exists('ZipArchive')) {
            // Fallback: SpreadsheetML (can be opened by modern Excel)
            self::generateXlsHtmlTemplate();
            return;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="template_soal_cbt.xlsx"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive();
        $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

        // _rels/.rels
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

        // xl/_rels/workbook.xml.rels
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

        // xl/workbook.xml
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Template Soal CBT" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>');

        // xl/styles.xml - Define styles: bold header, colors, borders
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="6">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/><color rgb="FF1E1B4B"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>
    <font><sz val="11"/><name val="Calibri"/><color rgb="FF4338CA"/><b/></font>
    <font><sz val="11"/><name val="Calibri"/><color rgb="FF059669"/><b/></font>
    <font><sz val="11"/><name val="Calibri"/><color rgb="FFDC2626"/><b/></font>
  </fonts>
  <fills count="6">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF4338CA"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFE0E7FF"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF0FDF4"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFEF9C3"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border><left style="thin"><color rgb="FFCBD5E1"/></left><right style="thin"><color rgb="FFCBD5E1"/></right><top style="thin"><color rgb="FFCBD5E1"/></top><bottom style="thin"><color rgb="FFCBD5E1"/></bottom><diagonal/></border>
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="10">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment wrapText="1" vertical="center" horizontal="center"/></xf>
    <xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment wrapText="1" vertical="top"/></xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment wrapText="1" vertical="top"/></xf>
    <xf numFmtId="0" fontId="4" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment wrapText="1" vertical="top"/></xf>
    <xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0" applyFill="1" applyBorder="1"><alignment wrapText="1" vertical="top"/></xf>
    <xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment wrapText="1" vertical="top" horizontal="center"/></xf>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment wrapText="1" vertical="center" horizontal="center"/></xf>
    <xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment wrapText="1" vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"><alignment wrapText="1" vertical="top" horizontal="center"/></xf>
  </cellXfs>
</styleSheet>');

        // Build sharedStrings & worksheet data
        $strings = [];
        $strIndex = [];
        $rows = []; // array of [colA, colB, colC, colD, colE, colF, colG, colH, style_row_type]

        $si = function(string $s) use (&$strings, &$strIndex): int {
            if (!isset($strIndex[$s])) {
                $strIndex[$s] = count($strings);
                $strings[] = $s;
            }
            return $strIndex[$s];
        };

        // Header row
        $hdr = ['No.', 'Tipe Soal', 'Teks Soal / Pertanyaan', 'Opsi Jawaban (A/B/C/D/E)', 'Pasangan / Pernyataan (Jodohkan & B/S)', 'Kunci Jawaban', 'Pembahasan / Rubrik', 'Catatan'];
        $rows[] = ['type' => 'header', 'data' => $hdr];

        // Info row
        $rows[] = ['type' => 'info', 'data' => [
            '', 'PANDUAN',
            'Isi kolom TIPE SOAL dengan salah satu: pilgan / kompleks / jodohkan / benarsalah / isian / uraian',
            'Untuk Pilgan/Kompleks: format "A) Jawaban A | B) Jawaban B | C) Jawaban C"',
            'Untuk Jodohkan: "[PREMIS 1] Teks => [TARGET 1] Teks". Untuk B/S: "[P1] Teks => BENAR/SALAH"',
            'Pilgan: A/B/C/D/E. Kompleks: A,C. Jodohkan: 1-1,2-2. B/S: dalam kolom E. Isian: jawaban1,alternatif',
            'Penjelasan / Rubrik penilaian',
            'Keterangan tambahan (opsional)'
        ]];

        // Sample data rows
        $samples = [
            [1, 'pilgan',
                'Protokol standar untuk mentransfer dokumen web secara terenkripsi dan aman adalah...',
                'A) FTP (File Transfer Protocol) | B) HTTP | C) HTTPS (Hypertext Transfer Protocol Secure) | D) SMTP | E) SSH',
                '', 'C',
                'HTTPS menggunakan enkripsi SSL/TLS untuk mengamankan transmisi data antara browser dan server.',
                ''
            ],
            [2, 'kompleks',
                'Manakah teknologi berikut yang dieksekusi di sisi peramban klien (client-side)? (Jawaban bisa lebih dari satu)',
                'A) JavaScript | B) PHP Backend | C) HTML5 & CSS3 | D) Python Django Server',
                '', 'A,C',
                'JavaScript, HTML, dan CSS dieksekusi langsung di browser. PHP dan Python berjalan di server.',
                ''
            ],
            [3, 'jodohkan',
                'Pasangkan teknologi web di kolom kiri dengan fungsi utamanya yang tepat!',
                '',
                '[PREMIS 1] HTML5 => [TARGET 1] Menyusun struktur & semantik halaman web' . "\n" .
                '[PREMIS 2] CSS3 => [TARGET 2] Mengatur tampilan, warna & tata letak visual' . "\n" .
                '[PREMIS 3] MySQL => [TARGET 3] Menyimpan dan mengelola basis data relasional',
                '1-1, 2-2, 3-3',
                'HTML untuk struktur, CSS untuk style, MySQL untuk basis data.',
                ''
            ],
            [4, 'benarsalah',
                'Tentukan apakah setiap pernyataan berikut Benar atau Salah!',
                '',
                '[P1] Apache adalah web server yang melayani permintaan HTTP => BENAR' . "\n" .
                '[P2] Kode PHP dapat dilihat pengguna melalui Inspect Element => SALAH' . "\n" .
                '[P3] Database MySQL dapat dikelola visual dengan phpMyAdmin => BENAR',
                '(lihat kolom E)',
                'PHP dieksekusi di server sehingga tidak terlihat di browser.',
                ''
            ],
            [5, 'isian',
                'Tag HTML yang digunakan untuk membuat hyperlink atau tautan ke halaman lain adalah... (tuliskan nama tagnya)',
                '',
                '', 'a, tag a, <a>',
                'Tag <a> (anchor) digunakan untuk membuat hyperlink dengan atribut href.',
                'Jawaban berupa alternatif, dipisah koma'
            ],
            [6, 'uraian',
                'Jelaskan secara komprehensif apa yang dimaksud dengan pola arsitektur MVC (Model-View-Controller) dan fungsi masing-masing komponennya!',
                '',
                '', 'MVC memisahkan logika: Model (data bisnis), View (tampilan antarmuka), Controller (pengendali alur).',
                'Rubrik: Konsep MVC (5 poin), Peran Model (5 poin), Peran View (5 poin), Peran Controller (5 poin)',
                'Dinilai manual oleh guru'
            ],
        ];

        foreach ($samples as $s) {
            $rows[] = ['type' => 'data_' . $s[1], 'data' => $s];
        }

        // Build sharedStrings.xml
        $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="0" uniqueCount="0">';

        // We'll collect all unique strings first
        $allStrings = [];
        foreach ($rows as $row) {
            foreach ($row['data'] as $cell) {
                $cell = (string)$cell;
                if ($cell !== '' && !isset($strIndex[$cell])) {
                    $strIndex[$cell] = count($allStrings);
                    $allStrings[] = $cell;
                }
            }
        }

        $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($allStrings) . '" uniqueCount="' . count($allStrings) . '">';
        foreach ($allStrings as $s) {
            $ssXml .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1, 'UTF-8') . '</t></si>';
        }
        $ssXml .= '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $ssXml);

        // Build worksheet
        $wsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetViews><sheetView workbookViewId="0" tabSelected="1"><pane ySplit="2" topLeftCell="A3" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>
  <sheetFormatPr defaultRowHeight="30" customHeight="1"/>
  <cols>
    <col min="1" max="1" width="6" customWidth="1"/>
    <col min="2" max="2" width="14" customWidth="1"/>
    <col min="3" max="3" width="45" customWidth="1"/>
    <col min="4" max="4" width="40" customWidth="1"/>
    <col min="5" max="5" width="40" customWidth="1"/>
    <col min="6" max="6" width="20" customWidth="1"/>
    <col min="7" max="7" width="35" customWidth="1"/>
    <col min="8" max="8" width="20" customWidth="1"/>
  </cols>
  <sheetData>';

        $cols = ['A','B','C','D','E','F','G','H'];
        $rowNum = 0;

        foreach ($rows as $row) {
            $rowNum++;
            $ht = ($row['type'] === 'header') ? ' ht="25" customHeight="1"' : ' ht="80" customHeight="1"';
            $wsXml .= '<row r="' . $rowNum . '"' . $ht . '>';

            foreach ($row['data'] as $ci => $cellVal) {
                $cellVal = (string)$cellVal;
                $colLetter = $cols[$ci] ?? 'Z';
                $cellRef = $colLetter . $rowNum;

                // Determine cell style
                $styleId = 3; // default data cell
                if ($row['type'] === 'header') $styleId = 1;
                elseif ($row['type'] === 'info') $styleId = 2;
                elseif ($colLetter === 'F') $styleId = 4; // answer - green
                elseif ($colLetter === 'B') $styleId = 6; // type - centered indigo
                elseif ($colLetter === 'A') $styleId = 9; // No. centered

                if ($cellVal === '') {
                    $wsXml .= '<c r="' . $cellRef . '" s="' . $styleId . '"><v></v></c>';
                } elseif (is_numeric($cellVal) && $colLetter === 'A') {
                    $wsXml .= '<c r="' . $cellRef . '" s="' . $styleId . '" t="n"><v>' . $cellVal . '</v></c>';
                } else {
                    $sIdx = $strIndex[$cellVal] ?? 0;
                    $wsXml .= '<c r="' . $cellRef . '" s="' . $styleId . '" t="s"><v>' . $sIdx . '</v></c>';
                }
            }
            $wsXml .= '</row>';
        }

        $wsXml .= '</sheetData>
  <autoFilter ref="A2:H2"/>
  <pageSetup paperSize="9" orientation="landscape"/>
</worksheet>';

        $zip->addFromString('xl/worksheets/sheet1.xml', $wsXml);
        $zip->close();

        readfile($tempFile);
        @unlink($tempFile);
    }

    /**
     * Generate Template Word .DOCX - Format TABEL Modern (Office 2007+)
     * Menggunakan tabel OOXML dengan kolom yang rapi dan mudah dibaca guru
     */
    public static function generateDocxTemplate(): void {
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="template_soal_cbt_tabel.docx"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $zip = new ZipArchive();
        $tempFile = tempnam(sys_get_temp_dir(), 'docx_');
        $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>');

        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
</Relationships>');

        $zip->addFromString('word/settings.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:defaultTabStop w:val="720"/>
  <w:compat><w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/></w:compat>
</w:settings>');

        // Helper functions untuk XML
        $p = function(string $text, bool $bold = false, string $color = '', string $size = '20', string $align = '') {
            $rPr = '<w:rPr>';
            if ($bold) $rPr .= '<w:b/>';
            if ($color) $rPr .= '<w:color w:val="' . $color . '"/>';
            $rPr .= '<w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/>';
            $rPr .= '</w:rPr>';
            $pPr = '';
            if ($align) $pPr = '<w:pPr><w:jc w:val="' . $align . '"/></w:pPr>';
            return '<w:p>' . $pPr . '<w:r>' . $rPr . '<w:t xml:space="preserve">' . htmlspecialchars($text, ENT_XML1, 'UTF-8') . '</w:t></w:r></w:p>';
        };

        // Build document.xml with table structure
        $ns = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"';
        $doc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document ' . $ns . '>
<w:body>';

        // Title
        $doc .= '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="120"/></w:pPr>
<w:r><w:rPr><w:b/><w:sz w:val="32"/><w:szCs w:val="32"/><w:color w:val="1E1B4B"/></w:rPr>
<w:t>TEMPLATE SOAL UJIAN BERBASIS KOMPUTER (CBT)</w:t></w:r></w:p>';

        $doc .= '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="80"/></w:pPr>
<w:r><w:rPr><w:sz w:val="20"/><w:color w:val="64748B"/></w:rPr>
<w:t>Format Tabel Modern - 6 Jenis Soal: Pilgan, Kompleks, Menjodohkan, Benar/Salah, Isian, Uraian</w:t></w:r></w:p>';

        // Instructions box (shaded paragraph)
        $doc .= '<w:p><w:pPr><w:shd w:val="clear" w:color="auto" w:fill="EEF2FF"/><w:spacing w:before="80" w:after="80"/></w:pPr>
<w:r><w:rPr><w:b/><w:sz w:val="18"/><w:color w:val="4338CA"/></w:rPr><w:t xml:space="preserve">PANDUAN PENGISIAN: </w:t></w:r>
<w:r><w:rPr><w:sz w:val="18"/><w:color w:val="1E293B"/></w:rPr><w:t xml:space="preserve">Isi kolom TIPE dengan: pilgan / kompleks / jodohkan / benarsalah / isian / uraian. Kolom bertanda [TAG] wajib diisi sesuai format tipe soal. Jangan ubah nama tag dalam kurung siku.</w:t></w:r></w:p>';

        $doc .= '<w:p><w:pPr><w:spacing w:after="120"/></w:pPr></w:p>';

        // ============ MAIN TABLE ============
        // Table properties
        $tblWidth = 9360; // total width in twips (landscape-ish)
        // col widths (in twips): No, Tipe, Soal, Opsi A-E, Pasangan/Pernyataan, Kunci, Pembahasan
        $colWidths = [600, 1100, 2400, 2000, 1800, 900, 1860];

        $tbl = '<w:tbl>';
        $tbl .= '<w:tblPr>
<w:tblStyle w:val="TableGrid"/>
<w:tblW w:w="' . $tblWidth . '" w:type="dxa"/>
<w:tblBorders>
  <w:top w:val="single" w:sz="4" w:color="CBD5E1"/>
  <w:left w:val="single" w:sz="4" w:color="CBD5E1"/>
  <w:bottom w:val="single" w:sz="4" w:color="CBD5E1"/>
  <w:right w:val="single" w:sz="4" w:color="CBD5E1"/>
  <w:insideH w:val="single" w:sz="4" w:color="CBD5E1"/>
  <w:insideV w:val="single" w:sz="4" w:color="CBD5E1"/>
</w:tblBorders>
<w:tblCellMar>
  <w:top w:w="80" w:type="dxa"/>
  <w:left w:w="100" w:type="dxa"/>
  <w:bottom w:w="80" w:type="dxa"/>
  <w:right w:w="100" w:type="dxa"/>
</w:tblCellMar>
</w:tblPr>';

        // Helper: make table cell
        $tc = function(string $content, int $width, string $fill = '', bool $bold = false, string $color = '') use ($colWidths) {
            $shd = $fill ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/>' : '';
            $cell = '<w:tc>';
            $cell .= '<w:tcPr><w:tcW w:w="' . $width . '" w:type="dxa"/>' . $shd . '</w:tcPr>';
            $cell .= '<w:p><w:pPr>' . ($fill && $fill !== 'F8FAFC' ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/>' : '') . '</w:pPr>';
            $rPr = '<w:rPr>';
            if ($bold) $rPr .= '<w:b/>';
            if ($color) $rPr .= '<w:color w:val="' . $color . '"/>';
            $rPr .= '<w:sz w:val="18"/><w:szCs w:val="18"/>';
            $rPr .= '</w:rPr>';
            $cell .= '<w:r>' . $rPr . '<w:t xml:space="preserve">' . htmlspecialchars($content, ENT_XML1, 'UTF-8') . '</w:t></w:r>';
            $cell .= '</w:p></w:tc>';
            return $cell;
        };

        // Header Row
        $tbl .= '<w:tr>';
        $tbl .= '<w:trPr><w:trHeight w:val="500"/><w:tblHeader/></w:trPr>';
        $headers = ['No.', 'Tipe Soal', 'Teks Soal / Pertanyaan', 'Opsi A) B) C) D) E)', 'Pasangan / Pernyataan\n(Jodohkan & B/S)', 'Kunci Jawaban', 'Pembahasan / Rubrik'];
        foreach ($headers as $hi => $h) {
            $tbl .= '<w:tc>';
            $tbl .= '<w:tcPr><w:tcW w:w="' . $colWidths[$hi] . '" w:type="dxa"><w:shd w:val="clear" w:color="auto" w:fill="4338CA"/></w:tcPr>';
            $tbl .= '<w:p><w:pPr><w:jc w:val="center"/><w:shd w:val="clear" w:color="auto" w:fill="4338CA"/></w:pPr>';
            $tbl .= '<w:r><w:rPr><w:b/><w:color w:val="FFFFFF"/><w:sz w:val="18"/><w:szCs w:val="18"/></w:rPr>';
            $tbl .= '<w:t xml:space="preserve">' . htmlspecialchars(str_replace('\n', ' / ', $h), ENT_XML1, 'UTF-8') . '</w:t></w:r></w:p></w:tc>';
        }
        $tbl .= '</w:tr>';

        // Data helper function for a row
        $addRow = function(string $no, string $type, string $soal, string $opsi, string $pasangan, string $kunci, string $pembahasan, string $typeFill = 'EEF2FF', string $kunciFill = 'F0FDF4') use (&$tbl, $colWidths) {
            $tbl .= '<w:tr><w:trPr><w:trHeight w:val="800" w:hRule="atLeast"/></w:trPr>';

            // No.
            $tbl .= '<w:tc><w:tcPr><w:tcW w:w="' . $colWidths[0] . '" w:type="dxa"/></w:tcPr>
<w:p><w:pPr><w:jc w:val="center"/></w:pPr>
<w:r><w:rPr><w:b/><w:sz w:val="18"/></w:rPr><w:t>' . htmlspecialchars($no, ENT_XML1) . '</w:t></w:r></w:p></w:tc>';

            // Tipe
            $tbl .= '<w:tc><w:tcPr><w:tcW w:w="' . $colWidths[1] . '" w:type="dxa"><w:shd w:val="clear" w:color="auto" w:fill="' . $typeFill . '"/></w:tcPr>
<w:p><w:pPr><w:jc w:val="center"/><w:shd w:val="clear" w:color="auto" w:fill="' . $typeFill . '"/></w:pPr>
<w:r><w:rPr><w:b/><w:color w:val="4338CA"/><w:sz w:val="18"/></w:rPr><w:t>' . htmlspecialchars($type, ENT_XML1) . '</w:t></w:r></w:p></w:tc>';

            $cellData = [$soal, $opsi, $pasangan];
            for ($i = 0; $i < 3; $i++) {
                $text = $cellData[$i];
                $tbl .= '<w:tc><w:tcPr><w:tcW w:w="' . $colWidths[2 + $i] . '" w:type="dxa"/></w:tcPr>';
                // Split by | for multiple options display
                $lines = explode(' | ', $text);
                foreach ($lines as $li => $line) {
                    $tbl .= '<w:p>';
                    if ($li === 0) $tbl .= '<w:pPr></w:pPr>';
                    $tbl .= '<w:r><w:rPr><w:sz w:val="18"/></w:rPr><w:t xml:space="preserve">' . htmlspecialchars(trim($line), ENT_XML1, 'UTF-8') . '</w:t></w:r></w:p>';
                }
                if (empty($lines)) $tbl .= '<w:p><w:r><w:t></w:t></w:r></w:p>';
                $tbl .= '</w:tc>';
            }

            // Kunci
            $tbl .= '<w:tc><w:tcPr><w:tcW w:w="' . $colWidths[5] . '" w:type="dxa"><w:shd w:val="clear" w:color="auto" w:fill="' . $kunciFill . '"/></w:tcPr>
<w:p><w:pPr><w:jc w:val="center"/><w:shd w:val="clear" w:color="auto" w:fill="' . $kunciFill . '"/></w:pPr>
<w:r><w:rPr><w:b/><w:color w:val="059669"/><w:sz w:val="18"/></w:rPr><w:t xml:space="preserve">' . htmlspecialchars($kunci, ENT_XML1, 'UTF-8') . '</w:t></w:r></w:p></w:tc>';

            // Pembahasan
            $tbl .= '<w:tc><w:tcPr><w:tcW w:w="' . $colWidths[6] . '" w:type="dxa"/></w:tcPr>
<w:p><w:r><w:rPr><w:sz w:val="18"/></w:rPr><w:t xml:space="preserve">' . htmlspecialchars($pembahasan, ENT_XML1, 'UTF-8') . '</w:t></w:r></w:p></w:tc>';

            $tbl .= '</w:tr>';
        };

        // ROW 1: Contoh Pilgan
        $addRow('1', '[PILGAN]',
            '[SOAL] Protokol standar untuk mentransfer dokumen web secara terenkripsi dan aman adalah...',
            '[A] FTP (File Transfer Protocol) | [B] HTTP | [C] HTTPS (Hypertext Transfer Protocol Secure) | [D] SMTP (Simple Mail Transfer Protocol) | [E] SSH (Secure Shell)',
            '', '[KUNCI] C',
            '[PEMBAHASAN] HTTPS menggunakan enkripsi SSL/TLS untuk mengamankan transmisi data antara browser dan server.'
        );

        // ROW 2: Contoh Kompleks
        $addRow('2', '[KOMPLEKS]',
            '[SOAL] Manakah teknologi berikut yang dieksekusi di sisi peramban klien? (Jawaban bisa lebih dari satu)',
            '[A] JavaScript | [B] PHP Backend | [C] HTML5 & CSS3 | [D] Python Django Server',
            '', '[KUNCI] A, C',
            '[PEMBAHASAN] JavaScript, HTML, dan CSS dieksekusi langsung di browser. PHP dan Python berjalan di server.', 'E0E7FF'
        );

        // ROW 3: Contoh Jodohkan
        $addRow('3', '[JODOHKAN]',
            '[SOAL] Pasangkan teknologi web di kolom kiri dengan fungsi utamanya yang tepat!',
            '',
            '[PREMIS 1] HTML5 => [TARGET 1] Menyusun struktur & semantik halaman web' . "\n" .
            '[PREMIS 2] CSS3 => [TARGET 2] Mengatur tampilan, warna & tata letak visual' . "\n" .
            '[PREMIS 3] MySQL => [TARGET 3] Menyimpan dan mengelola basis data relasional',
            '[KUNCI] 1-1, 2-2, 3-3',
            '[PEMBAHASAN] HTML untuk struktur, CSS untuk style, MySQL untuk basis data relasional.', 'FEF9C3'
        );

        // ROW 4: Contoh Benar/Salah
        $addRow('4', '[BENARSALAH]',
            '[SOAL] Tentukan apakah setiap pernyataan berikut Benar atau Salah!',
            '',
            '[PERNYATAAN 1] Apache adalah web server yang melayani permintaan HTTP => [KUNCI 1] BENAR' . "\n" .
            '[PERNYATAAN 2] Kode PHP dapat dilihat pengguna melalui Inspect Element => [KUNCI 2] SALAH' . "\n" .
            '[PERNYATAAN 3] phpMyAdmin digunakan untuk mengelola MySQL secara visual => [KUNCI 3] BENAR',
            '(lihat kolom F)',
            '[PEMBAHASAN] PHP dieksekusi di server, tidak terlihat di browser. Apache adalah web server HTTP.', 'F0FDF4'
        );

        // ROW 5: Contoh Isian
        $addRow('5', '[ISIAN]',
            '[SOAL] Tag HTML yang digunakan untuk membuat hyperlink atau tautan ke halaman lain adalah... (tuliskan nama tagnya)',
            '',
            '', '[KUNCI] a, tag a, <a>',
            '[PEMBAHASAN] Tag <a> (anchor) digunakan untuk membuat hyperlink dengan atribut href.', 'FDF4FF'
        );

        // ROW 6: Contoh Uraian
        $addRow('6', '[URAIAN]',
            '[SOAL] Jelaskan secara komprehensif apa yang dimaksud dengan pola arsitektur MVC dan fungsi masing-masing komponennya!',
            '',
            '', '[KUNCI] MVC memisahkan Model (data), View (tampilan), Controller (pengendali alur).',
            '[RUBRIK] Konsep MVC (5 poin), Peran Model (5 poin), Peran View (5 poin), Peran Controller (5 poin)' . "\n" . '[PEMBAHASAN] Soal uraian dikoreksi manual oleh guru.', 'FFF1F2'
        );

        $tbl .= '</w:tbl>';
        $doc .= $tbl;

        // Footer note
        $doc .= '<w:p><w:pPr><w:spacing w:before="200"/></w:pPr></w:p>';
        $doc .= '<w:p><w:pPr><w:shd w:val="clear" w:color="auto" w:fill="F1F5F9"/></w:pPr>
<w:r><w:rPr><w:b/><w:sz w:val="16"/><w:color w:val="475569"/></w:rPr>
<w:t xml:space="preserve">CATATAN PENTING: </w:t></w:r>
<w:r><w:rPr><w:sz w:val="16"/><w:color w:val="475569"/></w:rPr>
<w:t xml:space="preserve">Tag dalam kurung siku seperti [SOAL], [A], [KUNCI], [PREMIS 1], [TARGET 1], [PERNYATAAN 1], [RUBRIK], [PEMBAHASAN] WAJIB ditulis persis tanpa perubahan huruf. Kolom Tipe Soal diisi: pilgan / kompleks / jodohkan / benarsalah / isian / uraian (huruf kecil, tanpa spasi). Untuk menambah soal, duplikasi baris yang sesuai tipenya.</w:t></w:r></w:p>';

        $doc .= '<w:sectPr>
<w:pgSz w:w="15840" w:h="12240" w:orient="landscape"/>
<w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720"/>
</w:sectPr>';

        $doc .= '</w:body></w:document>';

        $zip->addFromString('word/document.xml', $doc);

        // Minimal styles.xml
        $zip->addFromString('word/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docDefaults>
    <w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="20"/><w:szCs w:val="20"/></w:rPr></w:rPrDefault>
  </w:docDefaults>
  <w:style w:type="table" w:styleId="TableGrid">
    <w:name w:val="Table Grid"/>
    <w:tblPr><w:tblBorders>
      <w:top w:val="single" w:sz="4" w:color="auto"/>
      <w:left w:val="single" w:sz="4" w:color="auto"/>
      <w:bottom w:val="single" w:sz="4" w:color="auto"/>
      <w:right w:val="single" w:sz="4" w:color="auto"/>
      <w:insideH w:val="single" w:sz="4" w:color="auto"/>
      <w:insideV w:val="single" w:sz="4" w:color="auto"/>
    </w:tblBorders></w:tblPr>
  </w:style>
</w:styles>');

        $zip->close();
        readfile($tempFile);
        @unlink($tempFile);
    }

    /**
     * Generate format .DOC (Word HTML legacy - dibuka di semua versi Word & WPS)
     */
    public static function generateDocTemplate(): void {
        header('Content-Type: application/msword; charset=utf-8');
        header('Content-Disposition: attachment; filename="template_soal_cbt_tabel.doc"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta charset="utf-8"><title>Template Soal CBT</title>';
        echo '<style>
body { font-family: Calibri, Arial, sans-serif; font-size: 10pt; }
h1 { font-size: 14pt; color: #1e1b4b; text-align: center; margin-bottom: 2px; }
h2 { font-size: 11pt; color: #4338ca; margin: 0; }
table { border-collapse: collapse; width: 100%; }
th { background: #4338ca; color: white; font-weight: bold; padding: 7px 6px; border: 1px solid #cbd5e1; font-size: 9pt; text-align: center; }
td { padding: 6px; border: 1px solid #cbd5e1; vertical-align: top; font-size: 9pt; }
.no { text-align: center; font-weight: bold; width: 30px; }
.tipe { text-align: center; font-weight: bold; color: #4338ca; background: #eef2ff; width: 90px; }
.kunci { text-align: center; font-weight: bold; color: #059669; background: #f0fdf4; width: 80px; }
.soal { width: 220px; }
.opsi { width: 180px; }
.pasangan { width: 170px; }
.pembahasan { width: 160px; }
.info { background: #f1f5f9; color: #475569; font-size: 8.5pt; padding: 6px 10px; }
.catatan { background: #fef9c3; font-size: 8.5pt; padding: 8px 10px; margin-top: 10px; }
</style></head><body>';

        echo '<h1>TEMPLATE SOAL UJIAN BERBASIS KOMPUTER (CBT)</h1>';
        echo '<p style="text-align:center; color:#64748b; font-size:9pt; margin-top:0;">Format Tabel Modern - 6 Jenis Soal Asesmen &bull; SD/MI, SMP/MTs, SMA/SMK/MA</p>';

        echo '<div class="catatan"><strong>PANDUAN:</strong> Isi kolom TIPE dengan: <code>pilgan / kompleks / jodohkan / benarsalah / isian / uraian</code>. Tag dalam kurung siku seperti [SOAL], [A], [KUNCI], [PREMIS 1], [TARGET 1], [PERNYATAAN 1], [RUBRIK], [PEMBAHASAN] wajib ditulis persis. Duplikasi baris untuk menambah soal baru.</div><br>';

        echo '<table>
<thead><tr>
  <th class="no">No.</th><th class="tipe">Tipe Soal</th>
  <th class="soal">Teks Soal / Pertanyaan</th>
  <th class="opsi">Opsi Jawaban [A] [B] [C] [D] [E]</th>
  <th class="pasangan">Pasangan / Pernyataan<br>(Jodohkan &amp; B/S)</th>
  <th class="kunci">Kunci Jawaban</th>
  <th class="pembahasan">Pembahasan / Rubrik</th>
</tr></thead>
<tbody>';

        $rows = [
            [1, '[PILGAN]',
                '[SOAL] Protokol standar untuk mentransfer dokumen web secara terenkripsi dan aman adalah...',
                '[A] FTP (File Transfer Protocol)<br>[B] HTTP<br>[C] HTTPS (Hypertext Transfer Protocol Secure)<br>[D] SMTP<br>[E] SSH',
                '', '[KUNCI] C',
                '[PEMBAHASAN] HTTPS menggunakan enkripsi SSL/TLS untuk mengamankan transmisi data.'],
            [2, '[KOMPLEKS]',
                '[SOAL] Manakah teknologi berikut yang dieksekusi di sisi peramban klien? (Jawaban bisa lebih dari satu)',
                '[A] JavaScript<br>[B] PHP Backend<br>[C] HTML5 &amp; CSS3<br>[D] Python Django',
                '', '[KUNCI] A, C',
                '[PEMBAHASAN] JavaScript, HTML, CSS dieksekusi di browser. PHP &amp; Python berjalan di server.'],
            [3, '[JODOHKAN]',
                '[SOAL] Pasangkan teknologi web dengan fungsi utamanya!',
                '',
                '[PREMIS 1] HTML5 =&gt; [TARGET 1] Menyusun struktur &amp; semantik<br>[PREMIS 2] CSS3 =&gt; [TARGET 2] Mengatur tampilan visual<br>[PREMIS 3] MySQL =&gt; [TARGET 3] Menyimpan basis data relasional',
                '[KUNCI] 1-1, 2-2, 3-3',
                '[PEMBAHASAN] HTML untuk struktur, CSS untuk style, MySQL untuk basis data.'],
            [4, '[BENARSALAH]',
                '[SOAL] Tentukan Benar atau Salah setiap pernyataan berikut!',
                '',
                '[PERNYATAAN 1] Apache adalah web server =&gt; [KUNCI 1] BENAR<br>[PERNYATAAN 2] PHP terlihat di browser =&gt; [KUNCI 2] SALAH<br>[PERNYATAAN 3] phpMyAdmin untuk kelola MySQL =&gt; [KUNCI 3] BENAR',
                '(lihat kolom F)',
                '[PEMBAHASAN] PHP berjalan di server, tidak terlihat di browser.'],
            [5, '[ISIAN]',
                '[SOAL] Tag HTML untuk membuat hyperlink ke halaman lain adalah... (tuliskan nama tagnya)',
                '', '',
                '[KUNCI] a, tag a, &lt;a&gt;',
                '[PEMBAHASAN] Tag &lt;a&gt; (anchor) digunakan untuk hyperlink dengan atribut href.'],
            [6, '[URAIAN]',
                '[SOAL] Jelaskan pola arsitektur MVC dan fungsi masing-masing komponennya!',
                '', '',
                '[KUNCI] MVC = Model (data), View (tampilan), Controller (pengendali alur).',
                '[RUBRIK] Konsep MVC (5), Model (5), View (5), Controller (5)<br>[PEMBAHASAN] Dinilai manual oleh guru.'],
        ];

        foreach ($rows as $r) {
            echo '<tr>
<td class="no">' . $r[0] . '</td>
<td class="tipe">' . $r[1] . '</td>
<td class="soal">' . $r[2] . '</td>
<td class="opsi">' . ($r[3] ?? '') . '</td>
<td class="pasangan">' . ($r[4] ?? '') . '</td>
<td class="kunci">' . $r[5] . '</td>
<td class="pembahasan">' . $r[6] . '</td>
</tr>';
        }

        echo '</tbody></table>';
        echo '<div class="catatan" style="margin-top:12px;"><strong>CARA MENAMBAH SOAL:</strong> Duplikasi baris yang sesuai dengan tipe soal yang diinginkan, lalu ubah isinya. Untuk soal <strong>jodohkan</strong>, format kolom F adalah: <code>[PREMIS N] teks =&gt; [TARGET N] teks</code>. Untuk soal <strong>benarsalah</strong>, format kolom F adalah: <code>[PERNYATAAN N] teks =&gt; [KUNCI N] BENAR/SALAH</code>.</div>';
        echo '</body></html>';
    }

    /**
     * Fallback: SpreadsheetML (Excel HTML format, dibuka Excel 2003+)
     */
    private static function generateXlsHtmlTemplate(): void {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="template_soal_cbt.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');
        // ... redirect to doc template as final fallback
        self::generateDocTemplate();
    }

    /**
     * Ekstraksi teks dari berkas Word (.docx atau .doc)
     */
    public static function extractTextFromWord(string $filePath, string $ext): string {
        if ($ext === 'docx') {
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($filePath) === true) {
                    if (($index = $zip->locateName('word/document.xml')) !== false) {
                        $xml = $zip->getFromIndex($index);
                        $zip->close();

                        $xml = preg_replace('/<\/w:p>/i', "\n", $xml);
                        $xml = preg_replace('/<w:br[^>]*\/>/i', "\n", $xml);
                        $xml = preg_replace('/<\/w:tr>/i', "\n", $xml);
                        $xml = preg_replace('/<\/w:tc>/i', " \t ", $xml);

                        return trim(strip_tags(html_entity_decode($xml, ENT_QUOTES, 'UTF-8')));
                    }
                    $zip->close();
                }
            }
        }

        $content = file_get_contents($filePath);
        if (strpos($content, '<html') !== false || strpos($content, '<body') !== false || strpos($content, '<table') !== false) {
            $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
            $content = preg_replace('/<\/(p|div|tr|h[1-6]|td|th)>/i', "\n", $content);
            return trim(strip_tags(html_entity_decode($content, ENT_QUOTES, 'UTF-8')));
        }

        return trim($content);
    }

    /**
     * Parsing teks berkas Word menjadi butir-butir soal CBT (6 Jenis Soal)
     */
    public static function parseQuestionsFromText(string $rawText, array $configuredWeights = []): array {
        $lines = preg_split("/\r\n|\n|\r/", $rawText);
        $questions = [];
        $currentQuestion = null;
        $currentSection = 'pilgan';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') continue;

            // Deteksi penanda tipe soal dari kolom TIPE (format tabel: [PILGAN], [KOMPLEKS], dst)
            if (preg_match('/^\[(PILGAN|KOMPLEKS|JODOHKAN|BENARSALAH|ISIAN|URAIAN)\]$/i', $trimmed, $mType)) {
                $currentSection = strtolower($mType[1]);
                continue;
            }

            // Deteksi penanda seksi (Section header dari template text lama)
            if (preg_match('/===\s*SOAL\s*(PILGAN|KOMPLEKS|JODOHKAN|BENARSALAH|ISIAN|URAIAN)\s*===/i', $trimmed, $mSec)) {
                $currentSection = strtolower($mSec[1]);
                continue;
            }

            // Deteksi tag [SOAL] baru
            if (preg_match('/^\[SOAL\]\s*(.*)$/i', $trimmed, $mSoal)) {
                if ($currentQuestion !== null && !empty($currentQuestion['question_text'])) {
                    $questions[] = self::finalizeQuestion($currentQuestion, $configuredWeights);
                }

                $soalText = trim($mSoal[1]);
                $currentQuestion = [
                    'type' => $currentSection,
                    'question_text' => $soalText,
                    'options' => [],
                    'correct' => '',
                    'explanation' => '',
                    'rubric' => '',
                    'premises' => [],
                    'targets' => [],
                    'statements' => []
                ];
                continue;
            }

            if ($currentQuestion === null) continue;

            // Opsi A-E
            if (preg_match('/^\[([A-E])\]\s*(.*)$/i', $trimmed, $mOpt)) {
                $currentQuestion['options'][strtoupper($mOpt[1])] = trim($mOpt[2]);
                continue;
            }

            // Kunci Jawaban [KUNCI]
            if (preg_match('/^\[KUNCI\]\s*(.*)$/i', $trimmed, $mKey)) {
                $currentQuestion['correct'] = trim($mKey[1]);
                continue;
            }

            // Pembahasan [PEMBAHASAN]
            if (preg_match('/^\[PEMBAHASAN\]\s*(.*)$/i', $trimmed, $mExp)) {
                $currentQuestion['explanation'] = trim($mExp[1]);
                continue;
            }

            // Rubrik [RUBRIK] (Uraian)
            if (preg_match('/^\[RUBRIK\]\s*(.*)$/i', $trimmed, $mRub)) {
                $currentQuestion['rubric'] = trim($mRub[1]);
                continue;
            }

            // Menjodohkan: [PREMIS N] teks => [TARGET N] teks
            if (preg_match('/^\[PREMIS\s*(\d+)\]\s*(.*?)\s*=>\s*\[TARGET\s*(\d+)\]\s*(.*)$/i', $trimmed, $mMatch)) {
                $currentQuestion['type'] = 'jodohkan';
                $currentQuestion['premises'][] = ['id' => 'p' . $mMatch[1], 'text' => trim($mMatch[2])];
                $currentQuestion['targets'][] = ['id' => 't' . $mMatch[3], 'text' => trim($mMatch[4])];
                continue;
            }

            // Benar / Salah: [PERNYATAAN N] teks => [KUNCI N] BENAR/SALAH
            if (preg_match('/^\[PERNYATAAN\s*(\d+)\]\s*(.*?)\s*=>\s*\[KUNCI\s*\d+\]\s*(BENAR|SALAH|B|S)/i', $trimmed, $mBS)) {
                $currentQuestion['type'] = 'benarsalah';
                $stValNorm = (stripos($mBS[3], 'B') !== false) ? 'B' : 'S';
                $currentQuestion['statements'][] = [
                    'id' => 's' . $mBS[1],
                    'text' => trim($mBS[2]),
                    'val' => $stValNorm
                ];
                continue;
            }

            // Tambah baris ke teks soal jika belum ada opsi/kunci
            if (empty($currentQuestion['options']) && empty($currentQuestion['correct'])) {
                $currentQuestion['question_text'] .= '<br>' . $trimmed;
            }
        }

        if ($currentQuestion !== null && !empty($currentQuestion['question_text'])) {
            $questions[] = self::finalizeQuestion($currentQuestion, $configuredWeights);
        }

        return $questions;
    }

    /**
     * Normalisasi satu butir soal ke dalam bentuk skema database questions
     */
    private static function finalizeQuestion(array $q, array $configuredWeights): array {
        $type = $q['type'];
        $optionsData = null;
        $correctAnswer = null;
        $weight = isset($configuredWeights[$type]) ? (float)$configuredWeights[$type] : 10.0;

        if ($type === 'pilgan') {
            $optionsData = json_encode($q['options'], JSON_UNESCAPED_UNICODE);
            $correctAnswer = json_encode(strtoupper(trim($q['correct'] ?: 'A')));
        } elseif ($type === 'kompleks') {
            $opts = [];
            foreach ($q['options'] as $letter => $txt) {
                $opts[] = ['key' => $letter, 'text' => $txt];
            }
            $optionsData = json_encode($opts, JSON_UNESCAPED_UNICODE);
            $keys = array_map('trim', explode(',', $q['correct']));
            $correctAnswer = json_encode($keys, JSON_UNESCAPED_UNICODE);
        } elseif ($type === 'jodohkan') {
            $optionsData = json_encode(['premises' => $q['premises'], 'targets' => $q['targets']], JSON_UNESCAPED_UNICODE);
            $pairs = [];
            foreach (explode(',', $q['correct']) as $pr) {
                $part = explode('-', trim($pr));
                if (count($part) === 2) {
                    $pairs['p' . trim($part[0])] = 't' . trim($part[1]);
                }
            }
            if (empty($pairs)) {
                foreach ($q['premises'] as $idx => $p) {
                    $pairs[$p['id']] = $q['targets'][$idx]['id'] ?? 't1';
                }
            }
            $correctAnswer = json_encode($pairs, JSON_UNESCAPED_UNICODE);
        } elseif ($type === 'benarsalah') {
            $stList = [];
            $answers = [];
            foreach ($q['statements'] as $st) {
                $stList[] = ['id' => $st['id'], 'text' => $st['text']];
                $answers[$st['id']] = $st['val'];
            }
            $optionsData = json_encode($stList, JSON_UNESCAPED_UNICODE);
            $correctAnswer = json_encode($answers, JSON_UNESCAPED_UNICODE);
        } elseif ($type === 'isian') {
            $optionsData = json_encode(['case_sensitive' => false, 'placeholder' => 'Ketik jawaban singkat disini...']);
            $keys = array_map('trim', explode(',', $q['correct']));
            $correctAnswer = json_encode($keys, JSON_UNESCAPED_UNICODE);
        } elseif ($type === 'uraian') {
            $optionsData = json_encode(['rubric' => $q['rubric'] ?: 'Koreksi manual oleh guru pengajar.', 'max_score' => $weight], JSON_UNESCAPED_UNICODE);
            $correctAnswer = json_encode($q['correct']);
        }

        return [
            'type' => $type,
            'question_text' => '<p>' . $q['question_text'] . '</p>',
            'options_data' => $optionsData,
            'correct_answer' => $correctAnswer,
            'score_weight' => $weight,
            'explanation' => $q['explanation']
        ];
    }
}
