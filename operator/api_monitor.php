<?php
// operator/api_monitor.php - JSON Endpoint Live Monitoring & Kontrol Sesi Siswa
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/QuestionService.php';

if (!Auth::check() || !in_array(Auth::role(), ['operator', 'admin'])) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = Database::getConnection();

// 1. GET DATA MONITORING (POLLING REAL-TIME)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $examId = (int)($_GET['exam_id'] ?? 0);

    if (!$examId) {
        json_response(['success' => false, 'message' => 'ID Ujian wajib diisi'], 400);
    }

    // Ambil total soal di ujian ini
    $qCountStmt = $pdo->prepare("SELECT COUNT(*) FROM questions q JOIN exams e ON q.bank_id = e.bank_id WHERE e.id = ?");
    $qCountStmt->execute([$examId]);
    $totalQuestions = (int)$qCountStmt->fetchColumn() ?: 1;

    // Ambil partisipasi seluruh siswa di ujian ini
    $query = "SELECT ep.*, s.nisn, s.exam_number, s.full_name, s.is_logged_in as student_online, c.name as class_name,
                     (SELECT COUNT(*) FROM student_answers sa WHERE sa.participation_id = ep.id AND sa.student_answer IS NOT NULL AND sa.student_answer != '') as answered_count,
                     (SELECT COUNT(*) FROM student_answers sa WHERE sa.participation_id = ep.id AND sa.is_marked_doubtful = 1) as doubtful_count 
              FROM exam_participations ep 
              JOIN students s ON ep.student_id = s.id 
              JOIN classes c ON s.class_id = c.id 
              WHERE ep.exam_id = ? 
              ORDER BY ep.status DESC, s.full_name ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$examId]);
    $participants = $stmt->fetchAll();

    // Aggregasi ringkasan
    $summary = [
        'total' => count($participants),
        'in_progress' => 0,
        'submitted' => 0,
        'blocked' => 0,
        'not_started' => 0,
    ];

    $formattedList = [];
    foreach ($participants as $p) {
        $st = $p['status'];
        if (isset($summary[$st])) $summary[$st]++;

        $formattedList[] = [
            'id' => $p['id'],
            'student_id' => $p['student_id'],
            'nisn' => $p['nisn'],
            'exam_number' => $p['exam_number'],
            'full_name' => $p['full_name'],
            'class_name' => $p['class_name'],
            'status' => $p['status'],
            'is_online' => (bool)$p['student_online'],
            'ip_address' => $p['ip_address'] ?: '-',
            'start_time' => $p['start_time'] ? date('H:i:s', strtotime($p['start_time'])) : '-',
            'remaining_seconds' => (int)$p['remaining_seconds'],
            'remaining_formatted' => format_duration_minutes((int)$p['remaining_seconds']),
            'answered_count' => (int)$p['answered_count'],
            'doubtful_count' => (int)$p['doubtful_count'],
            'total_questions' => $totalQuestions,
            'progress_percent' => round(((int)$p['answered_count'] / $totalQuestions) * 100),
            'violation_count' => (int)$p['violation_count'],
            'score_total' => $p['score_total'],
        ];
    }

    json_response([
        'success' => true,
        'summary' => $summary,
        'participants' => $formattedList,
        'timestamp' => date('H:i:s')
    ]);
}

// 2. POST ACTIONS (KONTROL OPERATOR)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $partId = (int)($_POST['participation_id'] ?? 0);

    if (!$partId) {
        json_response(['success' => false, 'message' => 'ID Sesi tidak valid'], 400);
    }

    // Ambil info partisipasi
    $stmt = $pdo->prepare("SELECT * FROM exam_participations WHERE id = ?");
    $stmt->execute([$partId]);
    $part = $stmt->fetch();
    if (!$part) {
        json_response(['success' => false, 'message' => 'Data sesi tidak ditemukan'], 404);
    }

    // A. Reset Login (Buka kunci agar siswa bisa login di PC lain atau setelah browser crash)
    if ($action === 'reset_login') {
        $updS = $pdo->prepare("UPDATE students SET is_logged_in = 0 WHERE id = ?");
        $updS->execute([$part['student_id']]);

        // Catat log operator
        $log = $pdo->prepare("INSERT INTO exam_logs (participation_id, student_id, exam_id, event_type, details) VALUES (?, ?, ?, 'reset_login', ?)");
        $log->execute([$partId, $part['student_id'], $part['exam_id'], "Reset login oleh operator: " . Auth::user()['full_name']]);

        json_response(['success' => true, 'message' => 'Sesi login siswa berhasil di-reset. Siswa dapat login kembali.']);
    }

    // B. Tambah Waktu Ujian (+ menit)
    if ($action === 'extend_time') {
        $addMinutes = (int)($_POST['minutes'] ?? 10);
        $addSeconds = $addMinutes * 60;

        $updP = $pdo->prepare("UPDATE exam_participations SET remaining_seconds = remaining_seconds + ? WHERE id = ?");
        $updP->execute([$addSeconds, $partId]);

        $log = $pdo->prepare("INSERT INTO exam_logs (participation_id, student_id, exam_id, event_type, details) VALUES (?, ?, ?, 'time_extend', ?)");
        $log->execute([$partId, $part['student_id'], $part['exam_id'], "Penambahan waktu +$addMinutes menit oleh operator"]);

        json_response(['success' => true, 'message' => "Waktu pengerjaan berhasil ditambah +$addMinutes menit."]);
    }

    // C. Paksa Submit (Force Selesai)
    if ($action === 'force_submit') {
        $updP = $pdo->prepare("UPDATE exam_participations SET status = 'submitted', end_time = NOW() WHERE id = ?");
        $updP->execute([$partId]);

        $updS = $pdo->prepare("UPDATE students SET is_logged_in = 0 WHERE id = ?");
        $updS->execute([$part['student_id']]);

        // Hitung ulang nilai
        QuestionService::recalculateParticipationScore($partId);

        $log = $pdo->prepare("INSERT INTO exam_logs (participation_id, student_id, exam_id, event_type, details) VALUES (?, ?, ?, 'force_submit', ?)");
        $log->execute([$partId, $part['student_id'], $part['exam_id'], "Ujian dipaksa selesai oleh operator"]);

        json_response(['success' => true, 'message' => 'Ujian siswa berhasil diselesaikan secara paksa.']);
    }

    // D. Buka Blokir (Unblock jika terkunci akibat pindah tab melebihi batas)
    if ($action === 'unblock') {
        $updP = $pdo->prepare("UPDATE exam_participations SET status = 'in_progress', violation_count = 0 WHERE id = ?");
        $updP->execute([$partId]);

        $updS = $pdo->prepare("UPDATE students SET is_logged_in = 0 WHERE id = ?");
        $updS->execute([$part['student_id']]);

        $log = $pdo->prepare("INSERT INTO exam_logs (participation_id, student_id, exam_id, event_type, details) VALUES (?, ?, ?, 'unblock', ?)");
        $log->execute([$partId, $part['student_id'], $part['exam_id'], "Blokir ujian dibuka kembali oleh operator"]);

        json_response(['success' => true, 'message' => 'Blokir ujian siswa berhasil dibuka. Siswa dapat melanjutkan tes.']);
    }

    json_response(['success' => false, 'message' => 'Aksi tidak dikenali'], 400);
}
