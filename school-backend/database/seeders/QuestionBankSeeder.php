<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\QuestionBank;
use App\Models\QuestionBankItem;
use App\Models\Subject;
use App\Models\Teacher;

class QuestionBankSeeder extends Seeder
{
    public function run()
    {
        $subject = Subject::first();
        if (!$subject) {
            $subject = Subject::create([
                'name' => 'Biologi Sel Molekuler',
                'code' => 'BIO_MOL_5'
            ]);
        }

        $teacher = Teacher::first();
        $teacherId = $teacher ? $teacher->id : 1;

        // 1. Paket Soal 1: Struktur & Fungsi Sel Organel (Kurikulum Merdeka)
        $bank1 = QuestionBank::firstOrCreate(
            ['title' => 'Struktur & Fungsi Sel Organel (Kurikulum Merdeka)'],
            [
                'subject_id'   => $subject->id,
                'teacher_id'   => $teacherId,
                'grade_level'  => '11',
                'difficulty'   => 'medium',
                'description'  => 'Paket soal evaluasi pemahaman struktur organel sel eukariotik dan prokariotik beserta transport membran.',
                'is_public'    => true,
            ]
        );

        $bank1Items = [
            [
                'question' => 'Organel sel yang berfungsi sebagai pusat respirasi seluler dan penghasil energi utama (ATP) adalah...',
                'type' => 'pg',
                'options' => [
                    ['key' => 'A', 'text' => 'Ribosom', 'is_correct' => false],
                    ['key' => 'B', 'text' => 'Mitokondria', 'is_correct' => true],
                    ['key' => 'C', 'text' => 'Badan Golgi', 'is_correct' => false],
                    ['key' => 'D', 'text' => 'Retikulum Endoplasma', 'is_correct' => false],
                ],
                'correct_answer' => 'B',
                'weight' => 20,
                'difficulty' => 'easy',
                'explanation' => 'Mitokondria merupakan organel penghasil ATP melalui respirasi aerobik.'
            ],
            [
                'question' => 'Peristiwa perpindahan molekul air melintasi membran semipermeabel dari larutan berkonsentrasi rendah ke larutan berkonsentrasi tinggi disebut...',
                'type' => 'pg',
                'options' => [
                    ['key' => 'A', 'text' => 'Difusi sederhana', 'is_correct' => false],
                    ['key' => 'B', 'text' => 'Osmosis', 'is_correct' => true],
                    ['key' => 'C', 'text' => 'Transpor aktif', 'is_correct' => false],
                    ['key' => 'D', 'text' => 'Endositosis', 'is_correct' => false],
                ],
                'correct_answer' => 'B',
                'weight' => 20,
                'difficulty' => 'easy',
                'explanation' => 'Osmosis adalah pergerakan pelarut (air) menembus membran selektif permeabel.'
            ],
            [
                'question' => 'Struktur pada sel tumbuhan yang tersusun atas selulosa dan berfungsi memberikan bentuk kaku serta perlindungan mekanik adalah...',
                'type' => 'pg',
                'options' => [
                    ['key' => 'A', 'text' => 'Membran plasma', 'is_correct' => false],
                    ['key' => 'B', 'text' => 'Dinding sel', 'is_correct' => true],
                    ['key' => 'C', 'text' => 'Kloroplas', 'is_correct' => false],
                    ['key' => 'D', 'text' => 'Vakuola sentral', 'is_correct' => false],
                ],
                'correct_answer' => 'B',
                'weight' => 20,
                'difficulty' => 'medium',
                'explanation' => 'Dinding sel tumbuhan mengandung peptidoglikan/selulosa yang memberikan kekakuan struktural.'
            ],
            [
                'question' => 'Fungsi utama dari Retikulum Endoplasma Kasar (REK) yang membedakannya dari Retikulum Endoplasma Halus (REH) adalah...',
                'type' => 'pg',
                'options' => [
                    ['key' => 'A', 'text' => 'Sintesis lipid dan hormon steroid', 'is_correct' => false],
                    ['key' => 'B', 'text' => 'Sintesis dan modifikasi protein karena ditempeli ribosom', 'is_correct' => true],
                    ['key' => 'C', 'text' => 'Detoksifikasi racun obat di hati', 'is_correct' => false],
                    ['key' => 'D', 'text' => 'Penyimpanan ion kalsium intraseluler', 'is_correct' => false],
                ],
                'correct_answer' => 'B',
                'weight' => 20,
                'difficulty' => 'medium',
                'explanation' => 'REK memiliki ribosom pada permukaannya yang berperan aktif dalam sintesis polipeptida/protein.'
            ],
            [
                'question' => 'Jika sel darah merah (eritrosit) dimasukkan ke dalam larutan hipotonik (air murni), apa yang akan terjadi?',
                'type' => 'pg',
                'options' => [
                    ['key' => 'A', 'text' => 'Mengkerut (krenasi)', 'is_correct' => false],
                    ['key' => 'B', 'text' => 'Membengkak lalu pecah (hemolisis)', 'is_correct' => true],
                    ['key' => 'C', 'text' => 'Mengalami plasmolisis', 'is_correct' => false],
                    ['key' => 'D', 'text' => 'Tidak terjadi perubahan volume', 'is_correct' => false],
                ],
                'correct_answer' => 'B',
                'weight' => 20,
                'difficulty' => 'hard',
                'explanation' => 'Air berpindah masuk ke dalam eritrosit secara osmosis hingga membran eritrosit pecah (lisis).'
            ],
        ];

        foreach ($bank1Items as $idx => $item) {
            QuestionBankItem::firstOrCreate(
                [
                    'question_bank_id' => $bank1->id,
                    'question'         => $item['question'],
                ],
                [
                    'type'           => $item['type'],
                    'options'        => $item['options'],
                    'correct_answer' => $item['correct_answer'],
                    'difficulty'     => $item['difficulty'],
                    'weight'         => $item['weight'],
                    'explanation'    => $item['explanation'],
                ]
            );
        }

        // 2. Paket Soal 2: Enzim & Metabolisme Katabolisme
        $bank2 = QuestionBank::firstOrCreate(
            ['title' => 'Enzim & Katabolisme Glukosa (Tingkat Lanjut)'],
            [
                'subject_id'   => $subject->id,
                'teacher_id'   => $teacherId,
                'grade_level'  => '11',
                'difficulty'   => 'hard',
                'description'  => 'Bank soal pengujian mekanisme kerja enzim, glikolisis, siklus Krebs, dan rantai transpor elektron.',
                'is_public'    => true,
            ]
        );

        $bank2Items = [
            [
                'question' => 'Tahapan glikolisis berlangsung di bagian sel...',
                'type' => 'pg',
                'options' => [
                    ['key' => 'A', 'text' => 'Matriks mitokondria', 'is_correct' => false],
                    ['key' => 'B', 'text' => 'Krista mitokondria', 'is_correct' => false],
                    ['key' => 'C', 'text' => 'Sitosol (Sitoplasma)', 'is_correct' => true],
                    ['key' => 'D', 'text' => 'Nukleus', 'is_correct' => false],
                ],
                'correct_answer' => 'C',
                'weight' => 25,
                'difficulty' => 'medium',
                'explanation' => 'Glikolisis adalah pemecahan glukosa yang berlangsung di sitoplasma tanpa memerlukan oksigen.'
            ],
            [
                'question' => 'Senyawa penerima elektron terakhir pada rantai transpor elektron respirasi aerob adalah...',
                'type' => 'pg',
                'options' => [
                    ['key' => 'A', 'text' => 'Oksigen (O2)', 'is_correct' => true],
                    ['key' => 'B', 'text' => 'NAD+', 'is_correct' => false],
                    ['key' => 'C', 'text' => 'FAD', 'is_correct' => false],
                    ['key' => 'D', 'text' => 'Sitokrom c', 'is_correct' => false],
                ],
                'correct_answer' => 'A',
                'weight' => 25,
                'difficulty' => 'hard',
                'explanation' => 'Oksigen mengikat elektron dan ion H+ membentuk molekul air (H2O).'
            ],
            [
                'question' => 'Model kerja enzim yang menyatakan sisi aktif enzim mengalami penyesuaian bentuk saat substrat terikat adalah...',
                'type' => 'pg',
                'options' => [
                    ['key' => 'A', 'text' => 'Lock and Key Theory', 'is_correct' => false],
                    ['key' => 'B', 'text' => 'Induced Fit Theory (Ketepatan Induksi)', 'is_correct' => true],
                    ['key' => 'C', 'text' => 'Allosteric Model', 'is_correct' => false],
                    ['key' => 'D', 'text' => 'Competitive Inhibition', 'is_correct' => false],
                ],
                'correct_answer' => 'B',
                'weight' => 25,
                'difficulty' => 'medium',
                'explanation' => 'Daniel Koshland mengemukakan teori induced fit di mana sisi aktif enzim bersifat fleksibel.'
            ],
            [
                'question' => 'Total energi bersih ATP yang dihasilkan dari 1 molekul glukosa secara teoritis pada respirasi aerob sel eukariotik adalah...',
                'type' => 'pg',
                'options' => [
                    ['key' => 'A', 'text' => '2 ATP', 'is_correct' => false],
                    ['key' => 'B', 'text' => '30 - 32 ATP', 'is_correct' => true],
                    ['key' => 'C', 'text' => '54 ATP', 'is_correct' => false],
                    ['key' => 'D', 'text' => '38 - 42 ATP', 'is_correct' => false],
                ],
                'correct_answer' => 'B',
                'weight' => 25,
                'difficulty' => 'hard',
                'explanation' => 'Perhitungan modern biokimia menunjukkan rentang 30-32 ATP bersih per molekul glukosa.'
            ]
        ];

        foreach ($bank2Items as $idx => $item) {
            QuestionBankItem::firstOrCreate(
                [
                    'question_bank_id' => $bank2->id,
                    'question'         => $item['question'],
                ],
                [
                    'type'           => $item['type'],
                    'options'        => $item['options'],
                    'correct_answer' => $item['correct_answer'],
                    'difficulty'     => $item['difficulty'],
                    'weight'         => $item['weight'],
                    'explanation'    => $item['explanation'],
                ]
            );
        }
    }
}
