<?php

namespace Tests\Unit;

use App\Services\QuestionScorer;
use PHPUnit\Framework\TestCase;

class QuestionScorerTest extends TestCase
{
    private function pg(): array
    {
        return [
            'number' => 1, 'type' => 'pg', 'question' => 'q', 'weight' => 10,
            'options' => [
                ['key' => 'A', 'text' => 'a', 'is_correct' => false],
                ['key' => 'B', 'text' => 'b', 'is_correct' => true],
            ],
        ];
    }

    private function kompleks(): array
    {
        return [
            'number' => 2, 'type' => 'pg_kompleks', 'question' => 'q', 'weight' => 10,
            'options' => [
                ['key' => 'A', 'text' => 'a', 'is_correct' => true],
                ['key' => 'B', 'text' => 'b', 'is_correct' => false],
                ['key' => 'C', 'text' => 'c', 'is_correct' => true],
            ],
        ];
    }

    public function test_pg(): void
    {
        $this->assertSame('benar', QuestionScorer::score($this->pg(), 'b')['status']);
        $this->assertSame(10.0, QuestionScorer::score($this->pg(), 'B')['earned']);
        $this->assertSame('salah', QuestionScorer::score($this->pg(), 'A')['status']);
        $this->assertSame('kosong', QuestionScorer::score($this->pg(), null)['status']);
    }

    public function test_pg_kompleks_partial(): void
    {
        $q = $this->kompleks();
        $this->assertSame(10.0, QuestionScorer::score($q, ['A', 'C'])['earned']);
        $this->assertSame(5.0, QuestionScorer::score($q, ['A'])['earned']);
        $this->assertSame('sebagian', QuestionScorer::score($q, ['A'])['status']);
        $this->assertSame(0.0, QuestionScorer::score($q, ['A', 'B'])['earned']);
        $this->assertSame(0.0, QuestionScorer::score($q, ['B'])['earned']);
        $this->assertSame('kosong', QuestionScorer::score($q, [])['status']);
    }

    public function test_isian(): void
    {
        $q = ['number' => 3, 'type' => 'isian', 'question' => 'q', 'weight' => 10,
            'data' => ['accepted_answers' => ['Fotosintesis', 'foto sintesis']]];
        $this->assertSame('benar', QuestionScorer::score($q, '  FOTOSINTESIS ')['status']);
        $this->assertSame('benar', QuestionScorer::score($q, 'foto   sintesis')['status']);
        $this->assertSame('salah', QuestionScorer::score($q, 'respirasi')['status']);
        $this->assertSame('kosong', QuestionScorer::score($q, '')['status']);
    }

    public function test_isian_without_key_is_manual(): void
    {
        $q = ['number' => 3, 'type' => 'isian', 'question' => 'q', 'weight' => 10];
        $this->assertTrue(QuestionScorer::score($q, 'apa saja')['manual']);
    }

    public function test_menjodohkan(): void
    {
        $q = ['number' => 4, 'type' => 'menjodohkan', 'question' => 'q', 'weight' => 9,
            'data' => ['pairs' => [
                ['id' => '1', 'left' => 'Jantung', 'right' => 'Memompa darah'],
                ['id' => '2', 'left' => 'Paru-paru', 'right' => 'Pertukaran gas'],
                ['id' => '3', 'left' => 'Ginjal', 'right' => 'Menyaring darah'],
            ]]];
        $this->assertSame(9.0, QuestionScorer::score($q, ['1' => 'Memompa darah', '2' => 'Pertukaran gas', '3' => 'Menyaring darah'])['earned']);
        $this->assertSame(3.0, QuestionScorer::score($q, ['1' => 'Memompa darah', '2' => 'x', '3' => 'y'])['earned']);
        $this->assertSame('kosong', QuestionScorer::score($q, [])['status']);
    }

    public function test_benar_salah(): void
    {
        $q = ['number' => 5, 'type' => 'benar_salah', 'question' => 'q', 'weight' => 10,
            'data' => ['statements' => [
                ['id' => '1', 'text' => 'a', 'answer' => true],
                ['id' => '2', 'text' => 'b', 'answer' => false],
            ]]];
        $this->assertSame(10.0, QuestionScorer::score($q, ['1' => true, '2' => false])['earned']);
        $this->assertSame(5.0, QuestionScorer::score($q, ['1' => true, '2' => true])['earned']);
        $this->assertSame(10.0, QuestionScorer::score($q, ['1' => 'true', '2' => 'false'])['earned']);
    }

    public function test_esai_is_manual(): void
    {
        $this->assertTrue(QuestionScorer::score(['number' => 6, 'type' => 'esai', 'question' => 'q'], 'uraian')['manual']);
    }

    public function test_sanitize_hides_keys(): void
    {
        $san = QuestionScorer::sanitize($this->pg());
        $this->assertArrayNotHasKey('is_correct', $san['options'][0]);

        $mj = ['number' => 4, 'type' => 'menjodohkan', 'question' => 'q', 'weight' => 1,
            'data' => ['pairs' => [['id' => '1', 'left' => 'L1', 'right' => 'R1'], ['id' => '2', 'left' => 'L2', 'right' => 'R2']]]];
        $out = QuestionScorer::sanitize($mj, 'seed');
        $this->assertArrayNotHasKey('right', $out['pairs'][0]);
        $this->assertEqualsCanonicalizing(['R1', 'R2'], $out['choices']);
        $this->assertSame($out['choices'], QuestionScorer::sanitize($mj, 'seed')['choices']);

        $bs = ['number' => 5, 'type' => 'benar_salah', 'question' => 'q', 'weight' => 1,
            'data' => ['statements' => [['id' => '1', 'text' => 't', 'answer' => true]]]];
        $this->assertArrayNotHasKey('answer', QuestionScorer::sanitize($bs)['statements'][0]);
    }
}
