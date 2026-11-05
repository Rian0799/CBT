<?php
// admin/student_import.php - Import Data Siswa dari Excel / CSV
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';

Auth::requireRole('admin');
$pdo = Database::getConnection();

// ================= DOWNLOAD TEMPLATE EXCEL / CSV =================
if (isset($_GET['download_template'])) {
    $format = strtolower($_GET['download_template']);

    // Sample data siswa multi-jenjang (SD/MI, SMP/MTs, SMA/SMK/MA)
    $sampleStudents = [
        ['0151234501', 'UJN-2026-SD01', 'Aditia Pratama', '5 SD Negeri 1', 'L', 'siswa123'],
        ['0141234502', 'UJN-2026-MI02', 'Fatimah Az-Zahra', '6 MI Al-Hidayah', 'P', 'siswa123'],
        ['0111234503', 'UJN-2026-SMP01', 'Bagas Saputra', '7 SMP Negeri 2', 'L', 'siswa123'],
        ['0101234504', 'UJN-2026-MTS02', 'Nur Aisyah', '9 MTs Negeri 1', 'P', 'siswa123'],
        ['0071234505', 'UJN-2026-SMA01', 'Rian Hidayat', 'X RPL 1', 'L', 'siswa123'],
        ['0061234506', 'UJN-2026-SMK02', 'Zahra Amelia', 'XII MIPA 2', 'P', 'siswa123']
    ];

    // FORMAT 1: EXCEL (.XLS - Microsoft Excel SpreadsheetML XML Format)
    // Format ini terbuka langsung di Microsoft Excel dengan teks rapi tanpa menghilangkan angka nol (006...)
    if ($format === 'xls') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="template_import_siswa_cbt.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
        ?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11"/>
  </Style>
  <Style ss:ID="Header">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#FFFFFF" ss:Bold="1"/>
   <Interior ss:Color="#059669" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="TextCell">
   <NumberFormat ss:Format="@"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
   </Borders>
  </Style>
  <Style ss:ID="CenterCell">
   <Alignment ss:Horizontal="Center"/>
   <NumberFormat ss:Format="@"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
   </Borders>
  </Style>
 </Styles>
 <Worksheet ss:Name="Data Siswa">
  <Table>
   <Column ss:Width="120"/>
   <Column ss:Width="130"/>
   <Column ss:Width="180"/>
   <Column ss:Width="110"/>
   <Column ss:Width="90"/>
   <Column ss:Width="110"/>
   <Row ss:Height="26">
    <Cell ss:StyleID="Header"><Data ss:Type="String">NISN</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">NO_PESERTA</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">NAMA_LENGKAP</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">KELAS</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">JENIS_KELAMIN</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">PASSWORD</Data></Cell>
   </Row>
   <?php foreach ($sampleStudents as $s): ?>
   <Row ss:Height="20">
    <Cell ss:StyleID="CenterCell"><Data ss:Type="String"><?= $s[0] ?></Data></Cell>
    <Cell ss:StyleID="CenterCell"><Data ss:Type="String"><?= $s[1] ?></Data></Cell>
    <Cell ss:StyleID="TextCell"><Data ss:Type="String"><?= htmlspecialchars($s[2]) ?></Data></Cell>
    <Cell ss:StyleID="CenterCell"><Data ss:Type="String"><?= $s[3] ?></Data></Cell>
    <Cell ss:StyleID="CenterCell"><Data ss:Type="String"><?= $s[4] ?></Data></Cell>
    <Cell ss:StyleID="CenterCell"><Data ss:Type="String"><?= $s[5] ?></Data></Cell>
   </Row>
   <?php endforeach; ?>
  </Table>
 </Worksheet>
</Workbook>
        <?php
        exit;
    }

    // FORMAT 2: EXCEL CSV (.CSV dengan UTF-8 BOM)
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="template_import_siswa_cbt.csv"');
        $output = fopen('php://output', 'w');
        // UTF-8 BOM agar Excel menampilkan aksen dan tanda baca dengan benar
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, ['NISN', 'NO_PESERTA', 'NAMA_LENGKAP', 'KELAS', 'JENIS_KELAMIN', 'PASSWORD']);
        foreach ($sampleStudents as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
}

// ================= PROSES UPLOAD DAN IMPORT =================
$importStats = null;
$importErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_siswa'])) {
    $onDuplicate = $_POST['on_duplicate'] ?? 'update'; // 'update' atau 'skip'
    $defaultPass = trim($_POST['default_password'] ?? 'siswa123');
    if (empty($defaultPass)) $defaultPass = 'siswa123';

    $file = $_FILES['file_siswa'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'Gagal mengunggah berkas. Kode kesalahan: ' . $file['error']);
        redirect(BASE_URL . '/admin/student_import.php');
    }

    $fileName = $file['name'];
    $fileTmp  = $file['tmp_name'];
    $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $rows = [];

    // 1. Parsing jika file CSV / TXT
    if ($ext === 'csv' || $ext === 'txt') {
        $content = file_get_contents($fileTmp);
        // Hilangkan BOM jika ada
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }

        // Deteksi delimiter (koma atau titik koma atau tab)
        $firstLine = strtok($content, "\r\n");
        $delimiter = ',';
        if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $delimiter = "\t";
        }

        $lines = preg_split("/\r\n|\n|\r/", $content);
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $data = str_getcsv($line, $delimiter);
            $rows[] = $data;
        }
    }
    // 2. Parsing jika file XLS (SpreadsheetML atau HTML table format)
    elseif ($ext === 'xls' || $ext === 'xlsx' || $ext === 'xml') {
        $content = file_get_contents($fileTmp);

        // Jika merupakan format XML SpreadsheetML
        if (strpos($content, '<Workbook') !== false && strpos($content, '<Table') !== false) {
            $xml = @simplexml_load_string($content);
            if ($xml) {
                $ns = $xml->getNamespaces(true);
                $xml->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
                $xmlRows = $xml->xpath('//ss:Row');
                foreach ($xmlRows as $xRow) {
                    $rowCells = [];
                    foreach ($xRow->Cell as $cell) {
                        $rowCells[] = (string)$cell->Data;
                    }
                    if (!empty($rowCells)) {
                        $rows[] = $rowCells;
                    }
                }
            }
        }
        // Fallback: Jika berformat HTML Table yang disimpan sebagai .xls
        if (empty($rows)) {
            if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $content, $trMatches)) {
                foreach ($trMatches[1] as $trHtml) {
                    if (preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $trHtml, $tdMatches)) {
                        $rowCells = array_map(function($td) {
                            return trim(strip_tags(html_entity_decode($td, ENT_QUOTES, 'UTF-8')));
                        }, $tdMatches[1]);
                        if (!empty($rowCells)) {
                            $rows[] = $rowCells;
                        }
                    }
                }
            }
        }

        if (empty($rows)) {
            // Coba baca baris per baris sebagai CSV jika berkas berekstensi .xls tetapi isinya teks csv
            $lines = preg_split("/\r\n|\n|\r/", $content);
            foreach ($lines as $line) {
                if (trim($line) === '') continue;
                $rows[] = str_getcsv($line, ',');
            }
        }
    }

    if (empty($rows)) {
        set_flash('error', 'Berkas kosong atau format tidak dapat dibaca.');
        redirect(BASE_URL . '/admin/student_import.php');
    }

    // Cache daftar kelas saat ini
    $classMap = []; // nama_kelas_lower => id
    $cStmt = $pdo->query("SELECT id, name FROM classes");
    while ($c = $cStmt->fetch()) {
        $classMap[strtolower(trim($c['name']))] = (int)$c['id'];
    }

    $stmtCreateClass = $pdo->prepare("INSERT INTO classes (name, level, major) VALUES (?, ?, ?)");
    $stmtCheckStudent = $pdo->prepare("SELECT id FROM students WHERE nisn = ? OR exam_number = ? LIMIT 1");
    $stmtInsertStudent = $pdo->prepare("INSERT INTO students (nisn, exam_number, full_name, class_id, gender, password) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtUpdateStudent = $pdo->prepare("UPDATE students SET full_name = ?, class_id = ?, gender = ?, password = ? WHERE id = ?");

    $defaultHash = password_hash($defaultPass, PASSWORD_BCRYPT);

    $insertedCount = 0;
    $updatedCount = 0;
    $skippedCount = 0;
    $rowIndex = 0;

    foreach ($rows as $cols) {
        $rowIndex++;
        // Bersihkan whitespace dari tiap kolom
        $cols = array_map('trim', $cols);

        // Abaikan baris kosong
        if (empty($cols) || count(array_filter($cols)) === 0) continue;

        $nisn       = $cols[0] ?? '';
        $examNumber = $cols[1] ?? '';
        $fullName   = $cols[2] ?? '';
        $className  = $cols[3] ?? '';
        $genderRaw  = strtoupper($cols[4] ?? 'L');
        $passRaw    = $cols[5] ?? '';

        // Lewati baris header jika kolom pertama adalah kata "NISN"
        if (strtoupper($nisn) === 'NISN' || strtoupper($examNumber) === 'NO_PESERTA' || strtoupper($fullName) === 'NAMA_LENGKAP') {
            continue;
        }

        // Validasi kolom esensial
        if (empty($nisn) || empty($fullName)) {
            $importErrors[] = "Baris $rowIndex: NISN atau Nama Siswa kosong, dilewati.";
            $skippedCount++;
            continue;
        }

        // Jika nomor ujian kosong, generate otomatis dari NISN
        if (empty($examNumber)) {
            $examNumber = 'UJN-' . date('Y') . '-' . substr($nisn, -4);
        }

        // Format Gender (L / P)
        $gender = (strpos($genderRaw, 'P') !== false || strpos($genderRaw, 'WANITA') !== false || strpos($genderRaw, 'PEREMPUAN') !== false) ? 'P' : 'L';

        // Format Kelas: Jika belum ada di database, otomatis dibuatkan
        if (empty($className)) {
            $className = 'X Umum';
        }
        $classKey = strtolower($className);
        if (!isset($classMap[$classKey])) {
            // Ekstrak perkiraan level dari nama kelas (Mendukung SD/MI 1-6, SMP/MTs 7-9, SMA/SMK/MA 10-13)
            $level = '10';
            if (preg_match('/\b(XIII|13)\b/i', $className)) {
                $level = '13';
            } elseif (preg_match('/\b(XII|12)\b/i', $className)) {
                $level = '12';
            } elseif (preg_match('/\b(XI|11)\b/i', $className)) {
                $level = '11';
            } elseif (preg_match('/\b(X|10)\b/i', $className)) {
                $level = '10';
            } elseif (preg_match('/\b(IX|9)\b/i', $className)) {
                $level = '9';
            } elseif (preg_match('/\b(VIII|8)\b/i', $className)) {
                $level = '8';
            } elseif (preg_match('/\b(VII|7)\b/i', $className)) {
                $level = '7';
            } elseif (preg_match('/\b(VI|6)\b/i', $className)) {
                $level = '6';
            } elseif (preg_match('/\b(V|5)\b/i', $className)) {
                $level = '5';
            } elseif (preg_match('/\b(IV|4)\b/i', $className)) {
                $level = '4';
            } elseif (preg_match('/\b(III|3)\b/i', $className)) {
                $level = '3';
            } elseif (preg_match('/\b(II|2)\b/i', $className)) {
                $level = '2';
            } elseif (preg_match('/\b(I|1)\b/i', $className)) {
                $level = '1';
            }

            // Tebak jurusan/bidang berdasarkan jenjang & nama kelas
            $major = 'Umum';
            if ((int)$level <= 6) {
                $major = 'Tematik / Umum';
            } elseif (preg_match('/(RPL|TKJ|AKL|OTKP|TBSM|TKRO|DKV|BOGA|BUSANA)/i', $className, $mMajor)) {
                $major = strtoupper($mMajor[1]);
            } elseif (preg_match('/(MIPA|IPA|IPS|BAHASA|AGAMA)/i', $className, $mMajor)) {
                $major = strtoupper($mMajor[1]);
            }

            $stmtCreateClass->execute([$className, $level, $major]);
            $newClassId = (int)$pdo->lastInsertId();
            $classMap[$classKey] = $newClassId;
        }
        $targetClassId = $classMap[$classKey];

        // Password hash
        $finalHash = !empty($passRaw) ? password_hash($passRaw, PASSWORD_BCRYPT) : $defaultHash;

        // Cek duplikasi siswa
        $stmtCheckStudent->execute([$nisn, $examNumber]);
        $existingId = $stmtCheckStudent->fetchColumn();

        if ($existingId) {
            if ($onDuplicate === 'update') {
                $stmtUpdateStudent->execute([$fullName, $targetClassId, $gender, $finalHash, $existingId]);
                $updatedCount++;
            } else {
                $skippedCount++;
            }
        } else {
            try {
                $stmtInsertStudent->execute([$nisn, $examNumber, $fullName, $targetClassId, $gender, $finalHash]);
                $insertedCount++;
            } catch (Exception $e) {
                $importErrors[] = "Baris $rowIndex (NISN: $nisn): Gagal simpan - " . $e->getMessage();
                $skippedCount++;
            }
        }
    }

    $importStats = [
        'total' => $insertedCount + $updatedCount + $skippedCount,
        'inserted' => $insertedCount,
        'updated' => $updatedCount,
        'skipped' => $skippedCount,
        'errors' => $importErrors
    ];

    if ($insertedCount > 0 || $updatedCount > 0) {
        set_flash('success', "Proses import selesai: <strong>$insertedCount siswa baru</strong> ditambahkan, <strong>$updatedCount siswa</strong> diperbarui.");
    } else {
        set_flash('warning', "Tidak ada data baru yang ditambahkan. Silakan periksa kembali berkas Anda.");
    }
}

$pageTitle = 'Import Data Siswa dari Excel';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">
                <a href="<?= BASE_URL ?>/admin/students.php" class="hover:underline flex items-center">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Data Siswa
                </a>
                <span>&bull;</span>
                <span>Import Massal</span>
            </div>
            <h1 class="text-2xl font-black text-slate-800">Import Data Siswa dari Excel</h1>
            <p class="text-sm text-slate-500">Unggah berkas Excel (.xls) atau CSV untuk memasukkan ratusan akun siswa sekaligus dalam hitungan detik.</p>
        </div>

        <div class="flex items-center space-x-2">
            <a href="<?= BASE_URL ?>/admin/students.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition">
                <i class="fa-solid fa-users mr-1.5"></i> Lihat Data Siswa
            </a>
        </div>
    </div>

    <!-- Download Templates Banner -->
    <div class="bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 rounded-3xl p-6 sm:p-8 text-white shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-6 relative overflow-hidden">
        <div class="relative z-10 max-w-xl">
            <span class="inline-flex items-center px-3 py-1 rounded-full bg-white/20 text-emerald-100 text-xs font-bold uppercase tracking-wider mb-3">
                <i class="fa-solid fa-file-excel mr-1.5 text-emerald-200"></i> Format Resmi CBT
            </span>
            <h2 class="text-xl sm:text-2xl font-bold leading-tight">Unduh Template Excel Siswa</h2>
            <p class="text-emerald-100/90 text-xs sm:text-sm mt-1 leading-relaxed">
                Gunakan template yang sudah diformat khusus agar nomor NISN yang diawali angka nol (contoh: <code>0061234501</code>) tidak terpotong saat dibuka di Microsoft Excel.
            </p>
        </div>

        <div class="relative z-10 flex flex-wrap gap-3">
            <a href="<?= BASE_URL ?>/admin/student_import.php?download_template=xls" class="px-5 py-3 bg-white hover:bg-emerald-50 text-emerald-800 font-extrabold rounded-2xl text-xs transition shadow-lg shadow-black/10 flex items-center">
                <i class="fa-solid fa-file-excel text-emerald-600 text-base mr-2"></i>
                <span>Unduh Format Excel (.xls)</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/student_import.php?download_template=csv" class="px-5 py-3 bg-emerald-800/60 hover:bg-emerald-800 border border-white/20 text-white font-bold rounded-2xl text-xs transition flex items-center">
                <i class="fa-solid fa-file-csv text-base mr-2"></i>
                <span>Unduh Format CSV (.csv)</span>
            </a>
        </div>

        <div class="absolute right-0 bottom-0 opacity-10 pointer-events-none transform translate-x-8 translate-y-8">
            <i class="fa-solid fa-file-spreadsheet text-[200px]"></i>
        </div>
    </div>

    <!-- Hasil Import (Jika Baru Selesai Diproses) -->
    <?php if ($importStats): ?>
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8 space-y-4">
            <h3 class="text-base font-bold text-slate-800 flex items-center">
                <i class="fa-solid fa-clipboard-check text-emerald-600 mr-2"></i> Laporan Hasil Import Siswa
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                    <div class="text-xs text-slate-400 font-bold uppercase">Total Baris</div>
                    <div class="text-2xl font-black text-slate-800 mt-1"><?= $importStats['total'] ?></div>
                </div>
                <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200">
                    <div class="text-xs text-emerald-600 font-bold uppercase">Berhasil Ditambah</div>
                    <div class="text-2xl font-black text-emerald-700 mt-1"><?= $importStats['inserted'] ?></div>
                </div>
                <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200">
                    <div class="text-xs text-blue-600 font-bold uppercase">Diperbarui</div>
                    <div class="text-2xl font-black text-blue-700 mt-1"><?= $importStats['updated'] ?></div>
                </div>
                <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200">
                    <div class="text-xs text-amber-600 font-bold uppercase">Dilewati / Gagal</div>
                    <div class="text-2xl font-black text-amber-700 mt-1"><?= $importStats['skipped'] ?></div>
                </div>
            </div>

            <?php if (!empty($importStats['errors'])): ?>
                <div class="mt-4 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-xs text-rose-800 max-h-40 overflow-y-auto space-y-1">
                    <span class="font-bold block mb-1">Catatan Peringatan / Baris yang Dilewati:</span>
                    <?php foreach ($importStats['errors'] as $err): ?>
                        <div>&bull; <?= htmlspecialchars($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Form Upload File -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8">
        <form method="POST" action="" enctype="multipart/form-data" class="space-y-6 text-xs">
            <div>
                <label class="block font-bold text-slate-800 text-sm mb-1">Pilih Berkas Excel atau CSV Siswa *</label>
                <p class="text-slate-400 text-xs mb-3">Format berkas yang didukung: <strong>.xls, .csv, .txt</strong> (Gunakan template di atas untuk hasil terbaik).</p>
                
                <div class="border-2 border-dashed border-slate-300 hover:border-emerald-500 rounded-3xl p-8 text-center transition bg-slate-50/50">
                    <div class="w-14 h-14 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-3 text-2xl shadow-sm">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </div>
                    <div class="text-sm font-bold text-slate-800 mb-1">Klik untuk memilih berkas atau seret file Anda ke sini</div>
                    <div class="text-xs text-slate-400 mb-4">Maksimal ukuran file: 10 MB</div>
                    <input type="file" name="file_siswa" accept=".xls,.xlsx,.csv,.txt" required class="mx-auto block text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer">
                </div>
            </div>

            <!-- Opsi Tambahan -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 p-5 bg-slate-50 rounded-2xl border border-slate-200">
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-2">Jika NISN / No. Peserta Sudah Ada:</label>
                    <div class="space-y-2">
                        <label class="flex items-center space-x-2.5 cursor-pointer">
                            <input type="radio" name="on_duplicate" value="update" checked class="text-emerald-600 focus:ring-emerald-500">
                            <span class="font-medium text-slate-700">Perbarui data siswa (Update Nama, Kelas & Password)</span>
                        </label>
                        <label class="flex items-center space-x-2.5 cursor-pointer">
                            <input type="radio" name="on_duplicate" value="skip" class="text-emerald-600 focus:ring-emerald-500">
                            <span class="font-medium text-slate-700">Lewati siswa tersebut (Jangan timpa data lama)</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-2">Password Bawaan (Jika kolom password di Excel kosong):</label>
                    <input type="text" name="default_password" value="siswa123" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 text-xs font-mono font-bold">
                    <span class="text-[11px] text-slate-400 block mt-1">Siswa akan dapat login menggunakan password ini jika tidak ditentukan khusus di berkas Excel.</span>
                </div>
            </div>

            <!-- Panduan Format Kolom -->
            <div class="space-y-2 pt-2">
                <h4 class="font-bold text-slate-700 uppercase tracking-wider text-[11px] flex items-center">
                    <i class="fa-solid fa-circle-info text-indigo-600 mr-1.5"></i> Susunan Kolom Pada Template Excel:
                </h4>
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left text-[11px]">
                        <thead class="bg-slate-100 text-slate-600 font-bold uppercase">
                            <tr>
                                <th class="p-2.5">No</th>
                                <th class="p-2.5">Nama Kolom</th>
                                <th class="p-2.5">Wajib?</th>
                                <th class="p-2.5">Contoh Nilai</th>
                                <th class="p-2.5">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr>
                                <td class="p-2.5 font-bold">1</td>
                                <td class="p-2.5 font-mono font-bold text-emerald-700">NISN</td>
                                <td class="p-2.5"><span class="px-2 py-0.5 rounded bg-rose-100 text-rose-700 font-bold">Wajib</span></td>
                                <td class="p-2.5 font-mono">0061234501</td>
                                <td class="p-2.5 text-slate-500">Nomor Induk Siswa Nasional (ID Login Siswa).</td>
                            </tr>
                            <tr>
                                <td class="p-2.5 font-bold">2</td>
                                <td class="p-2.5 font-mono font-bold text-emerald-700">NO_PESERTA</td>
                                <td class="p-2.5"><span class="px-2 py-0.5 rounded bg-blue-100 text-blue-700 font-bold">Opsional</span></td>
                                <td class="p-2.5 font-mono">UJN-2026-001</td>
                                <td class="p-2.5 text-slate-500">Jika dikosongkan, sistem membuat otomatis dari NISN.</td>
                            </tr>
                            <tr>
                                <td class="p-2.5 font-bold">3</td>
                                <td class="p-2.5 font-mono font-bold text-emerald-700">NAMA_LENGKAP</td>
                                <td class="p-2.5"><span class="px-2 py-0.5 rounded bg-rose-100 text-rose-700 font-bold">Wajib</span></td>
                                <td class="p-2.5 font-semibold">Ahmad Fadillah</td>
                                <td class="p-2.5 text-slate-500">Nama lengkap siswa peserta ujian.</td>
                            </tr>
                            <tr>
                                <td class="p-2.5 font-bold">4</td>
                                <td class="p-2.5 font-mono font-bold text-emerald-700">KELAS</td>
                                <td class="p-2.5"><span class="px-2 py-0.5 rounded bg-rose-100 text-rose-700 font-bold">Wajib</span></td>
                                <td class="p-2.5 font-semibold">X RPL 1</td>
                                <td class="p-2.5 text-slate-500">Jika nama kelas belum ada, sistem otomatis menambahkannya.</td>
                            </tr>
                            <tr>
                                <td class="p-2.5 font-bold">5</td>
                                <td class="p-2.5 font-mono font-bold text-emerald-700">JENIS_KELAMIN</td>
                                <td class="p-2.5"><span class="px-2 py-0.5 rounded bg-blue-100 text-blue-700 font-bold">Opsional</span></td>
                                <td class="p-2.5 font-mono">L / P</td>
                                <td class="p-2.5 text-slate-500">Isi <strong>L</strong> (Laki-laki) atau <strong>P</strong> (Perempuan). Default: L.</td>
                            </tr>
                            <tr>
                                <td class="p-2.5 font-bold">6</td>
                                <td class="p-2.5 font-mono font-bold text-emerald-700">PASSWORD</td>
                                <td class="p-2.5"><span class="px-2 py-0.5 rounded bg-blue-100 text-blue-700 font-bold">Opsional</span></td>
                                <td class="p-2.5 font-mono">siswa123</td>
                                <td class="p-2.5 text-slate-500">Jika dikosongkan, menggunakan password bawaan.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tombol Submit -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                <a href="<?= BASE_URL ?>/admin/students.php" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition">
                    Batal
                </a>
                <button type="submit" class="px-7 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold rounded-xl transition shadow-lg shadow-emerald-500/20 flex items-center text-xs">
                    <i class="fa-solid fa-file-import mr-2"></i> Proses & Simpan Data Siswa
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
