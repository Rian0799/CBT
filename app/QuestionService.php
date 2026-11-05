<?php
// app/QuestionService.php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/database.php';

class QuestionService {

    public const TYPE_PILGAN = 'pilgan';
    public const TYPE_KOMPLEKS = 'kompleks';
    public const TYPE_JODOHKAN = 'jodohkan';
    public const TYPE_BENARSALAH = 'benarsalah';
    public const TYPE_ISIAN = 'isian';
    public const TYPE_URAIAN = 'uraian';

    public static function getTypesList(): array {
        return [
            self::TYPE_PILGAN => 'Pilihan Ganda (Single Choice)',
            self::TYPE_KOMPLEKS => 'Pilihan Ganda Kompleks (Multi-Select)',
            self::TYPE_JODOHKAN => 'Menjodohkan (Matching Pairs)',
            self::TYPE_BENARSALAH => 'Pernyataan Benar / Salah',
            self::TYPE_ISIAN => 'Isian Singkat (Short Answer)',
            self::TYPE_URAIAN => 'Uraian / Essay',
        ];
    }

    /**
     * Menilai jawaban siswa secara otomatis berdasarkan tipe soal
     * Mengembalikan array: ['is_correct' => bool|null, 'score' => float, 'auto_graded' => bool]
     */
    public static function gradeAnswer(string $type, mixed $studentAnsRaw, mixed $correctAnsRaw, mixed $optionsDataRaw, float $weight): array {
        if ($studentAnsRaw === null || $studentAnsRaw === '' || $studentAnsRaw === 'null') {
            return ['is_correct' => 0, 'score' => 0.0, 'auto_graded' => true];
        }

        // Decode JSON jika string
        $studentAns = is_string($studentAnsRaw) ? json_decode($studentAnsRaw, true) : $studentAnsRaw;
        if (json_last_error() !== JSON_ERROR_NONE) {
            $studentAns = $studentAnsRaw;
        }

        $correctAns = is_string($correctAnsRaw) ? json_decode($correctAnsRaw, true) : $correctAnsRaw;
        if (json_last_error() !== JSON_ERROR_NONE) {
            $correctAns = $correctAnsRaw;
        }

        $optionsData = is_string($optionsDataRaw) ? json_decode($optionsDataRaw, true) : $optionsDataRaw;

        switch ($type) {
            case self::TYPE_PILGAN:
                // Pilgan: Jawaban berupa huruf "A", "B", "C", dll
                $isCorrect = (strtoupper(trim((string)$studentAns)) === strtoupper(trim((string)$correctAns))) ? 1 : 0;
                $score = $isCorrect ? $weight : 0.0;
                return ['is_correct' => $isCorrect, 'score' => round($score, 2), 'auto_graded' => true];

            case self::TYPE_KOMPLEKS:
                // Kompleks: array huruf pilihan ['A', 'C']
                $sArr = is_array($studentAns) ? $studentAns : [];
                $cArr = is_array($correctAns) ? $correctAns : [];
                sort($sArr);
                sort($cArr);

                if (empty($cArr)) {
                    return ['is_correct' => 0, 'score' => 0.0, 'auto_graded' => true];
                }

                // Penilaian Proporsional Kompleks:
                // Benar terpilih dihitung, salah terpilih mengurangi nilai (penalty), skor minimum 0
                $correctCount = count($cArr);
                $correctChosen = count(array_intersect($sArr, $cArr));
                $wrongChosen = count(array_diff($sArr, $cArr));

                if ($sArr === $cArr) {
                    return ['is_correct' => 1, 'score' => $weight, 'auto_graded' => true];
                }

                $fraction = ($correctChosen - ($wrongChosen * 0.5)) / $correctCount;
                if ($fraction < 0) $fraction = 0;
                $score = $fraction * $weight;
                $isCorrect = ($fraction >= 0.99) ? 1 : (($score > 0) ? 0 : 0);

                return ['is_correct' => $isCorrect, 'score' => round($score, 2), 'auto_graded' => true];

            case self::TYPE_JODOHKAN:
                // Menjodohkan: associative array {'p1': 't3', 'p2': 't1', 'p3': 't2'}
                $sMap = is_array($studentAns) ? $studentAns : [];
                $cMap = is_array($correctAns) ? $correctAns : [];

                $totalPairs = count($cMap);
                if ($totalPairs === 0) {
                    return ['is_correct' => 0, 'score' => 0.0, 'auto_graded' => true];
                }

                $matched = 0;
                foreach ($cMap as $premiseKey => $correctTarget) {
                    if (isset($sMap[$premiseKey]) && trim((string)$sMap[$premiseKey]) === trim((string)$correctTarget)) {
                        $matched++;
                    }
                }

                $score = ($matched / $totalPairs) * $weight;
                $isCorrect = ($matched === $totalPairs) ? 1 : 0;
                return ['is_correct' => $isCorrect, 'score' => round($score, 2), 'auto_graded' => true];

            case self::TYPE_BENARSALAH:
                // Benar Salah: associative array {'s1': 'B', 's2': 'S', 's3': 'B'}
                $sBS = is_array($studentAns) ? $studentAns : [];
                $cBS = is_array($correctAns) ? $correctAns : [];

                $totalStatements = count($cBS);
                if ($totalStatements === 0) {
                    return ['is_correct' => 0, 'score' => 0.0, 'auto_graded' => true];
                }

                $correctCount = 0;
                foreach ($cBS as $stKey => $correctVal) {
                    if (isset($sBS[$stKey]) && strtoupper(trim((string)$sBS[$stKey])) === strtoupper(trim((string)$correctVal))) {
                        $correctCount++;
                    }
                }

                $score = ($correctCount / $totalStatements) * $weight;
                $isCorrect = ($correctCount === $totalStatements) ? 1 : 0;
                return ['is_correct' => $isCorrect, 'score' => round($score, 2), 'auto_graded' => true];

            case self::TYPE_ISIAN:
                // Isian Singkat: string jawaban siswa dicocokkan dengan kunci (bisa array variasi kunci atau string tunggal)
                $trimmedStudent = trim((string)$studentAns);
                $isCaseSensitive = !empty($optionsData['case_sensitive']);

                $validKeys = is_array($correctAns) ? $correctAns : [(string)$correctAns];
                $matched = false;

                foreach ($validKeys as $keyOption) {
                    $keyStr = trim((string)$keyOption);
                    if ($isCaseSensitive) {
                        if ($trimmedStudent === $keyStr) {
                            $matched = true;
                            break;
                        }
                    } else {
                        if (mb_strtolower($trimmedStudent) === mb_strtolower($keyStr)) {
                            $matched = true;
                            break;
                        }
                    }
                }

                $score = $matched ? $weight : 0.0;
                return ['is_correct' => $matched ? 1 : 0, 'score' => round($score, 2), 'auto_graded' => true];

            case self::TYPE_URAIAN:
                // Uraian: Harus dikoreksi secara manual oleh Guru
                return ['is_correct' => null, 'score' => 0.0, 'auto_graded' => false];

            default:
                return ['is_correct' => 0, 'score' => 0.0, 'auto_graded' => true];
        }
    }

    /**
     * Hitung ulang total nilai untuk seluruh sesi pengerjaan ujian peserta
     */
    public static function recalculateParticipationScore(int $participationId): array {
        $pdo = Database::getConnection();

        // Ambil semua soal dan jawaban
        $query = "SELECT sa.*, q.type, q.score_weight, q.correct_answer, q.options_data 
                  FROM student_answers sa 
                  JOIN questions q ON sa.question_id = q.id 
                  WHERE sa.participation_id = :pid";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['pid' => $participationId]);
        $rows = $stmt->fetchAll();

        $scores = [
            'total' => 0.0,
            'pilgan' => 0.0,
            'kompleks' => 0.0,
            'jodohkan' => 0.0,
            'benarsalah' => 0.0,
            'isian' => 0.0,
            'uraian' => 0.0,
            'has_ungraded_uraian' => false,
        ];

        foreach ($rows as $row) {
            $type = $row['type'];
            $awarded = (float)$row['awarded_score'];

            // Jika tipe uraian dan is_correct masih NULL, tandai perlu koreksi
            if ($type === self::TYPE_URAIAN && $row['is_correct'] === null) {
                $scores['has_ungraded_uraian'] = true;
            }

            if (isset($scores[$type])) {
                $scores[$type] += $awarded;
            }
            $scores['total'] += $awarded;
        }

        // Update ke database
        $isGraded = $scores['has_ungraded_uraian'] ? 0 : 1;
        $upd = $pdo->prepare("UPDATE exam_participations 
                              SET score_total = :tot, 
                                  score_pilgan = :sp, 
                                  score_kompleks = :sk, 
                                  score_jodohkan = :sj, 
                                  score_benarsalah = :sb, 
                                  score_isian = :si, 
                                  score_uraian = :su, 
                                  is_graded = :ig 
                              WHERE id = :id");
        $upd->execute([
            'tot' => round($scores['total'], 2),
            'sp' => round($scores['pilgan'], 2),
            'sk' => round($scores['kompleks'], 2),
            'sj' => round($scores['jodohkan'], 2),
            'sb' => round($scores['benarsalah'], 2),
            'si' => round($scores['isian'], 2),
            'su' => round($scores['uraian'], 2),
            'ig' => $isGraded,
            'id' => $participationId
        ]);

        return $scores;
    }
}
