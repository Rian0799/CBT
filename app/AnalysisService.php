<?php
// app/AnalysisService.php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/QuestionService.php';

class AnalysisService {

    /**
     * Menjalankan analisis butir soal lengkap untuk satu paket Bank Soal atau Ujian
     */
    public static function analyzeExam(int $examId): array {
        $pdo = Database::getConnection();

        // Ambil info ujian
        $stmt = $pdo->prepare("SELECT e.*, qb.title as bank_title, qb.kkm, s.name as subject_name 
                               FROM exams e 
                               JOIN question_banks qb ON e.bank_id = qb.id 
                               JOIN subjects s ON qb.subject_id = s.id 
                               WHERE e.id = :id");
        $stmt->execute(['id' => $examId]);
        $exam = $stmt->fetch();
        if (!$exam) return [];

        // Ambil semua partisipan yang sudah submit / selesai
        $partStmt = $pdo->prepare("SELECT id, student_id, score_total 
                                   FROM exam_participations 
                                   WHERE exam_id = :eid AND status = 'submitted' 
                                   ORDER BY score_total DESC");
        $partStmt->execute(['eid' => $examId]);
        $participants = $partStmt->fetchAll();
        $totalParticipants = count($participants);

        // Ambil seluruh soal dalam bank soal
        $qStmt = $pdo->prepare("SELECT * FROM questions WHERE bank_id = :bid ORDER BY question_number ASC");
        $qStmt->execute(['bid' => $exam['bank_id']]);
        $questions = $qStmt->fetchAll();

        if ($totalParticipants === 0) {
            return [
                'exam' => $exam,
                'total_participants' => 0,
                'questions' => [],
                'summary' => [
                    'avg_score' => 0,
                    'highest_score' => 0,
                    'lowest_score' => 0,
                    'passed_count' => 0,
                    'failed_count' => 0
                ]
            ];
        }

        // Tentukan Kelompok Atas dan Kelompok Bawah (Top 50% & Bottom 50% atau 27% jika peserta banyak)
        $groupSize = max(1, (int)floor($totalParticipants * 0.5));
        $upperGroup = array_slice($participants, 0, $groupSize);
        $lowerGroup = array_slice($participants, -$groupSize);

        $upperPartIds = array_column($upperGroup, 'id');
        $lowerPartIds = array_column($lowerGroup, 'id');

        $analyzedQuestions = [];
        $totalScores = array_column($participants, 'score_total');
        $avgScore = array_sum($totalScores) / $totalParticipants;
        $highestScore = max($totalScores);
        $lowestScore = min($totalScores);
        $kkm = (float)$exam['kkm'];
        $passedCount = count(array_filter($totalScores, fn($s) => $s >= $kkm));
        $failedCount = $totalParticipants - $passedCount;

        foreach ($questions as $q) {
            $qid = $q['id'];
            $type = $q['type'];

            // Ambil semua jawaban peserta untuk soal ini
            $ansStmt = $pdo->prepare("SELECT sa.participation_id, sa.student_answer, sa.is_correct, sa.awarded_score 
                                      FROM student_answers sa 
                                      WHERE sa.question_id = :qid AND sa.participation_id IN (" . implode(',', array_column($participants, 'id')) . ")");
            $ansStmt->execute(['qid' => $qid]);
            $answers = $ansStmt->fetchAll();

            $ansMap = [];
            foreach ($answers as $a) {
                $ansMap[$a['participation_id']] = $a;
            }

            // Hitung Tingkat Kesukaran (P = B / N)
            $correctCount = 0;
            $distractorCounts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'OTHER' => 0];

            foreach ($participants as $p) {
                $pid = $p['id'];
                $ansObj = $ansMap[$pid] ?? null;

                if ($ansObj && (int)$ansObj['is_correct'] === 1) {
                    $correctCount++;
                }

                // Untuk Pilgan, hitung distribusi pengecoh
                if ($type === QuestionService::TYPE_PILGAN && $ansObj) {
                    $selected = strtoupper(trim(json_decode($ansObj['student_answer'] ?? '""', true) ?: (string)$ansObj['student_answer']));
                    if (isset($distractorCounts[$selected])) {
                        $distractorCounts[$selected]++;
                    } else {
                        $distractorCounts['OTHER']++;
                    }
                }
            }

            $difficultyIndex = $totalParticipants > 0 ? ($correctCount / $totalParticipants) : 0;
            if ($difficultyIndex >= 0.70) {
                $difficultyCategory = 'Mudah';
                $diffColor = 'text-emerald-700 bg-emerald-100';
            } elseif ($difficultyIndex >= 0.30) {
                $difficultyCategory = 'Sedang';
                $diffColor = 'text-blue-700 bg-blue-100';
            } else {
                $difficultyCategory = 'Sukar';
                $diffColor = 'text-rose-700 bg-rose-100';
            }

            // Hitung Daya Pembeda (D = (BA - BB) / n)
            $upperCorrect = 0;
            foreach ($upperPartIds as $uid) {
                if (isset($ansMap[$uid]) && (int)$ansMap[$uid]['is_correct'] === 1) {
                    $upperCorrect++;
                }
            }

            $lowerCorrect = 0;
            foreach ($lowerPartIds as $lid) {
                if (isset($ansMap[$lid]) && (int)$ansMap[$lid]['is_correct'] === 1) {
                    $lowerCorrect++;
                }
            }

            $discriminationIndex = $groupSize > 0 ? (($upperCorrect - $lowerCorrect) / $groupSize) : 0;
            if ($discriminationIndex >= 0.40) {
                $discCategory = 'Sangat Baik';
                $discColor = 'text-emerald-700 bg-emerald-100';
                $recommendation = 'Diterima & Sangat Baik';
            } elseif ($discriminationIndex >= 0.30) {
                $discCategory = 'Baik';
                $discColor = 'text-blue-700 bg-blue-100';
                $recommendation = 'Diterima';
            } elseif ($discriminationIndex >= 0.20) {
                $discCategory = 'Cukup';
                $discColor = 'text-amber-700 bg-amber-100';
                $recommendation = 'Perlu Revisi Minor';
            } else {
                $discCategory = 'Buruk / Jelek';
                $discColor = 'text-rose-700 bg-rose-100';
                $recommendation = 'Perbaiki Total / Ganti Soal';
            }

            $analyzedQuestions[] = [
                'question' => $q,
                'correct_count' => $correctCount,
                'total_participants' => $totalParticipants,
                'difficulty_index' => round($difficultyIndex, 2),
                'difficulty_category' => $difficultyCategory,
                'difficulty_color' => $diffColor,
                'upper_correct' => $upperCorrect,
                'lower_correct' => $lowerCorrect,
                'discrimination_index' => round($discriminationIndex, 2),
                'discrimination_category' => $discCategory,
                'discrimination_color' => $discColor,
                'recommendation' => $recommendation,
                'distractor_counts' => $distractorCounts
            ];
        }

        return [
            'exam' => $exam,
            'total_participants' => $totalParticipants,
            'questions' => $analyzedQuestions,
            'summary' => [
                'avg_score' => round($avgScore, 2),
                'highest_score' => round($highestScore, 2),
                'lowest_score' => round($lowestScore, 2),
                'passed_count' => $passedCount,
                'failed_count' => $failedCount,
                'pass_rate' => round(($passedCount / $totalParticipants) * 100, 1)
            ]
        ];
    }
}
