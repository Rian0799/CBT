<?php
// ujian/api_ujian.php - AJAX API Backend untuk CBT Exam Engine
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/QuestionService.php';

if (!Auth::checkStudent()) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

$student = Auth::student();
$pdo = Database::getConnection();

// Ambil sesi partisipasi aktif siswa
$pStmt = $pdo->prepare("SELECT ep.*, e.status as exam_status, e.anti_cheat_enabled 
                        FROM exam_participations ep 
                        JOIN exams e ON ep.exam_id = e.id 
                        WHERE ep.student_id = ? AND ep.status = 'in_progress' 
                        ORDER BY ep.id DESC LIMIT 1");
$pStmt->execute([$student['id']]);
$part = $pStmt->fetch();

if (!$part) {
    json_response(['success' => false, 'message' => 'Sesi ujian tidak aktif atau telah diselesaikan'], 403);
}

$partId = (int)$part['id'];

// Handle Actions
$action = $_POST['action'] ?? '';

// 1. SAVE ANSWER (Auto-save jawaban real-time)
if ($action === 'save_answer') {
    $questionId = (int)($_POST['question_id'] ?? 0);
    $rawAnswer = $_POST['student_answer'] ?? null;
    $isDoubtful = isset($_POST['is_marked_doubtful']) ? (int)$_POST['is_marked_doubtful'] : null;

    // Ambil info kunci soal untuk auto-grading instan
    $qStmt = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
    $qStmt->execute([$questionId]);
    $q = $qStmt->fetch();

    if ($q) {
        $gradeResult = QuestionService::gradeAnswer(
            $q['type'],
            $rawAnswer,
            $q['correct_answer'],
            $q['options_data'],
            (float)$q['score_weight']
        );

        // Update student_answers
        $query = "INSERT INTO student_answers (participation_id, question_id, student_answer, is_marked_doubtful, is_correct, awarded_score) 
                  VALUES (:pid, :qid, :ans, :doubtful, :is_corr, :score) 
                  ON DUPLICATE KEY UPDATE 
                  student_answer = :ans2, 
                  is_correct = :is_corr2, 
                  awarded_score = :score2";
        if ($isDoubtful !== null) {
            $query .= ", is_marked_doubtful = :doubtful2";
        }

        $stmt = $pdo->prepare($query);
        $params = [
            'pid' => $partId,
            'qid' => $questionId,
            'ans' => is_array($rawAnswer) ? json_encode($rawAnswer, JSON_UNESCAPED_UNICODE) : $rawAnswer,
            'doubtful' => $isDoubtful ?? 0,
            'is_corr' => $gradeResult['is_correct'],
            'score' => $gradeResult['score'],
            'ans2' => is_array($rawAnswer) ? json_encode($rawAnswer, JSON_UNESCAPED_UNICODE) : $rawAnswer,
            'is_corr2' => $gradeResult['is_correct'],
            'score2' => $gradeResult['score'],
        ];
        if ($isDoubtful !== null) {
            $params['doubtful2'] = $isDoubtful;
        }

        $stmt->execute($params);

        json_response([
            'success' => true,
            'message' => 'Jawaban berhasil disimpan otomatis',
            'time' => date('H:i:s')
        ]);
    }

    json_response(['success' => false, 'message' => 'Soal tidak ditemukan'], 404);
}

// 2. TOGGLE DOUBTFUL (Ragu-ragu)
if ($action === 'toggle_doubtful') {
    $questionId = (int)($_POST['question_id'] ?? 0);
    $isDoubtful = (int)($_POST['is_marked_doubtful'] ?? 0);

    $stmt = $pdo->prepare("UPDATE student_answers SET is_marked_doubtful = ? WHERE participation_id = ? AND question_id = ?");
    $stmt->execute([$isDoubtful, $partId, $questionId]);

    json_response(['success' => true, 'is_marked_doubtful' => $isDoubtful]);
}

// 3. SYNC TIMER
if ($action === 'sync_timer') {
    $clientSecs = (int)($_POST['remaining_seconds'] ?? 0);

    if ($clientSecs <= 0) {
        // Waktu habis! Selesaikan ujian otomatis
        QuestionService::recalculateParticipationScore($partId);
        $upd = $pdo->prepare("UPDATE exam_participations SET status = 'submitted', remaining_seconds = 0, end_time = NOW() WHERE id = ?");
        $upd->execute([$partId]);

        $pdo->prepare("UPDATE students SET is_logged_in = 0 WHERE id = ?")->execute([$student['id']]);

        json_response(['success' => true, 'time_up' => true, 'redirect' => BASE_URL . "/ujian/selesai.php?participation_id=$partId"]);
    }

    $upd = $pdo->prepare("UPDATE exam_participations SET remaining_seconds = ? WHERE id = ?");
    $upd->execute([$clientSecs, $partId]);

    json_response(['success' => true, 'server_time' => date('H:i:s')]);
}

// 4. LOG VIOLATION (Deteksi Pindah Tab / Layar)
if ($action === 'log_violation') {
    $eventType = $_POST['event_type'] ?? 'tab_switch';
    $maxViolations = (int)get_setting('max_tab_violations', '3');
    $strictness = get_setting('anti_cheat_strictness', 'medium');

    $upd = $pdo->prepare("UPDATE exam_participations SET violation_count = violation_count + 1 WHERE id = ?");
    $upd->execute([$partId]);

    // Ambil jumlah pelanggaran saat ini
    $vStmt = $pdo->prepare("SELECT violation_count FROM exam_participations WHERE id = ?");
    $vStmt->execute([$partId]);
    $vCount = (int)$vStmt->fetchColumn();

    // Catat ke log
    $log = $pdo->prepare("INSERT INTO exam_logs (participation_id, student_id, exam_id, event_type, details) VALUES (?, ?, ?, ?, ?)");
    $log->execute([$partId, $student['id'], $part['exam_id'], $eventType, "Pelanggaran ke-$vCount: Pindah tab / keluar jendela"]);

    $isBlocked = false;
    if ($strictness === 'high' && $vCount >= $maxViolations) {
        $isBlocked = true;
        $pdo->prepare("UPDATE exam_participations SET status = 'blocked' WHERE id = ?")->execute([$partId]);
        $pdo->prepare("UPDATE students SET is_logged_in = 0 WHERE id = ?")->execute([$student['id']]);
    }

    json_response([
        'success' => true,
        'violation_count' => $vCount,
        'max_violations' => $maxViolations,
        'is_blocked' => $isBlocked,
        'message' => "Peringatan: Terdeteksi meninggalkan jendela ujian! Pelanggaran: $vCount/$maxViolations"
    ]);
}

// 5. SUBMIT EXAM (Selesai Ujian)
if ($action === 'submit_exam') {
    // Hitung skor total
    QuestionService::recalculateParticipationScore($partId);

    // Update status partisipasi
    $upd = $pdo->prepare("UPDATE exam_participations SET status = 'submitted', end_time = NOW() WHERE id = ?");
    $upd->execute([$partId]);

    // Logout siswa
    $pdo->prepare("UPDATE students SET is_logged_in = 0 WHERE id = ?")->execute([$student['id']]);

    // Catat log
    $log = $pdo->prepare("INSERT INTO exam_logs (participation_id, student_id, exam_id, event_type, details) VALUES (?, ?, ?, 'submit', 'Siswa menyelesaikan ujian secara mandiri')");
    $log->execute([$partId, $student['id'], $part['exam_id']]);

    json_response([
        'success' => true,
        'message' => 'Ujian berhasil diselesaikan!',
        'redirect' => BASE_URL . "/ujian/selesai.php?participation_id=$partId"
    ]);
}

json_response(['success' => false, 'message' => 'Aksi tidak valid'], 400);
