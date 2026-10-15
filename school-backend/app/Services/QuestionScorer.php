<?php

namespace App\Services;

/**
 * Penilaian otomatis dan sanitasi butir soal CBT per tipe.
 *
 * Bentuk butir (snapshot di quizzes.questions_data):
 *  pg           options[{key,text,is_correct}] satu benar          jawaban: "B"
 *  pg_kompleks  options[{key,text,is_correct}] banyak benar        jawaban: ["A","C"]
 *  isian        data.accepted_answers[string]                       jawaban: "teks"
 *  menjodohkan  data.pairs[{id,left,right}]                         jawaban: {"1":"teks kanan"}
 *  benar_salah  data.statements[{id,text,answer:bool}]              jawaban: {"1":true}
 *  esai         tanpa kunci                                         dinilai manual
 */
class QuestionScorer
{
    public const TYPES = ['pg', 'pg_kompleks', 'isian', 'menjodohkan', 'benar_salah', 'esai'];

    /**
     * @return array{earned: float, max: float, manual: bool, status: string|null}
     *   status: 'benar' | 'sebagian' | 'salah' | 'kosong' | null (manual)
     */
    public static function score(array $q, $answer): array
    {
        $weight = (float) ($q['weight'] ?? 1.0);
        $type = $q['type'] ?? 'pg';

        switch ($type) {
            case 'pg':
                $fraction = self::scorePg($q, $answer);
                break;
            case 'pg_kompleks':
                $fraction = self::scorePgKompleks($q, $answer);
                break;
            case 'isian':
                $fraction = self::scoreIsian($q, $answer);
                break;
            case 'menjodohkan':
                $fraction = self::scoreMenjodohkan($q, $answer);
                break;
            case 'benar_salah':
                $fraction = self::scoreBenarSalah($q, $answer);
                break;
            default:
                $fraction = null; // esai atau tipe tak dikenal
        }

        if ($fraction === null) {
            return ['earned' => 0.0, 'max' => 0.0, 'manual' => true, 'status' => null];
        }

        $status = self::isBlank($answer)
            ? 'kosong'
            : ($fraction >= 0.9999 ? 'benar' : ($fraction > 0 ? 'sebagian' : 'salah'));

        return [
            'earned' => round($weight * $fraction, 4),
            'max' => $weight,
            'manual' => false,
            'status' => $status,
        ];
    }

    /**
     * Bentuk butir untuk siswa: tanpa kunci jawaban dan pembahasan.
     */
    public static function sanitize(array $q, string $seed = ''): array
    {
        $type = $q['type'] ?? 'pg';
        $out = [
            'number' => $q['number'],
            'type' => $type,
            'question' => $q['question'],
            'options' => [],
            'weight' => $q['weight'] ?? 1,
            'difficulty' => $q['difficulty'] ?? 'medium',
        ];

        if (in_array($type, ['pg', 'pg_kompleks']) && !empty($q['options']) && is_array($q['options'])) {
            $optIndex = 0;
            foreach ($q['options'] as $key => $opt) {
                $defaultKey = chr(65 + $optIndex);
                if (is_array($opt)) {
                    $out['options'][] = ['key' => (string) ($opt['key'] ?? $defaultKey), 'text' => (string) ($opt['text'] ?? '')];
                } elseif (is_string($opt)) {
                    $out['options'][] = ['key' => is_string($key) && strlen($key) <= 2 ? $key : $defaultKey, 'text' => $opt];
                }
                $optIndex++;
            }
        }

        if ($type === 'menjodohkan') {
            $pairs = $q['data']['pairs'] ?? [];
            $rights = array_values(array_unique(array_map(fn ($p) => (string) $p['right'], $pairs)));
            // Urutan kanan diacak stabil per siswa agar tidak berubah saat dimuat ulang
            usort($rights, fn ($a, $b) => strcmp(md5($seed . $a), md5($seed . $b)));
            $out['pairs'] = array_map(fn ($p) => ['id' => (string) $p['id'], 'left' => $p['left']], $pairs);
            $out['choices'] = $rights;
        }

        if ($type === 'benar_salah') {
            $out['statements'] = array_map(
                fn ($s) => ['id' => (string) $s['id'], 'text' => $s['text']],
                $q['data']['statements'] ?? []
            );
        }

        return $out;
    }

    public static function normalizeText($value): string
    {
        $value = mb_strtolower(trim((string) $value));
        return preg_replace('/\s+/u', ' ', $value) ?? '';
    }

    // ---- per tipe ----

    private static function scorePg(array $q, $answer): float
    {
        $correctKey = null;
        foreach (($q['options'] ?? []) as $opt) {
            if (!empty($opt['is_correct'])) {
                $correctKey = $opt['key'];
                break;
            }
        }
        if ($correctKey === null && !empty($q['correct_answer'])) {
            $correctKey = $q['correct_answer'];
        }
        if ($correctKey === null || self::isBlank($answer) || is_array($answer)) {
            return 0.0;
        }
        return strtoupper((string) $answer) === strtoupper((string) $correctKey) ? 1.0 : 0.0;
    }

    /**
     * Skor parsial: tiap pilihan benar menambah, tiap pilihan salah mengurangi, minimum 0.
     * Contoh kunci A,C: jawab A,C = 1.0; jawab A = 0.5; jawab A,B = 0.0
     */
    private static function scorePgKompleks(array $q, $answer): float
    {
        $correct = [];
        foreach (($q['options'] ?? []) as $opt) {
            if (!empty($opt['is_correct'])) {
                $correct[] = strtoupper((string) $opt['key']);
            }
        }
        if (empty($correct) || !is_array($answer)) {
            return 0.0;
        }
        $chosen = array_values(array_unique(array_map(fn ($k) => strtoupper((string) $k), $answer)));
        $right = count(array_intersect($chosen, $correct));
        $wrong = count(array_diff($chosen, $correct));
        return max(0.0, ($right - $wrong) / count($correct));
    }

    /** null berarti tidak ada kunci, dinilai manual oleh guru. */
    private static function scoreIsian(array $q, $answer): ?float
    {
        $accepted = array_filter(
            array_map([self::class, 'normalizeText'], $q['data']['accepted_answers'] ?? []),
            fn ($v) => $v !== ''
        );
        if (empty($accepted)) {
            return null;
        }
        if (self::isBlank($answer) || is_array($answer)) {
            return 0.0;
        }
        return in_array(self::normalizeText($answer), $accepted, true) ? 1.0 : 0.0;
    }

    private static function scoreMenjodohkan(array $q, $answer): float
    {
        $pairs = $q['data']['pairs'] ?? [];
        if (empty($pairs) || !is_array($answer)) {
            return 0.0;
        }
        $right = 0;
        foreach ($pairs as $p) {
            $given = $answer[(string) $p['id']] ?? null;
            if ($given !== null && self::normalizeText($given) === self::normalizeText($p['right'])) {
                $right++;
            }
        }
        return $right / count($pairs);
    }

    private static function scoreBenarSalah(array $q, $answer): float
    {
        $statements = $q['data']['statements'] ?? [];
        if (empty($statements) || !is_array($answer)) {
            return 0.0;
        }
        $right = 0;
        foreach ($statements as $s) {
            $given = $answer[(string) $s['id']] ?? null;
            if ($given !== null && filter_var($given, FILTER_VALIDATE_BOOLEAN) === (bool) $s['answer']) {
                $right++;
            }
        }
        return $right / count($statements);
    }

    private static function isBlank($answer): bool
    {
        if ($answer === null) {
            return true;
        }
        if (is_string($answer)) {
            return trim($answer) === '';
        }
        if (is_array($answer)) {
            return empty(array_filter($answer, fn ($v) => $v !== null && $v !== '' && $v !== []));
        }
        return false;
    }
}
