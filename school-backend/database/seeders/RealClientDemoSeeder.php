<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Student;
use App\Models\Course;
use App\Models\Meet;
use App\Models\Material;
use App\Models\Quiz;
use App\Models\QuizSubmission;
use App\Models\MeetAttendance;
use App\Models\ScoreComponent;
use App\Models\StudentGrade;
use App\Models\QuestionBank;
use App\Models\QuestionBankItem;
use App\Models\Discussion;

class RealClientDemoSeeder extends Seeder
{
    public function run()
    {
        DB::beginTransaction();
        try {
            echo "Memulai seeding data riil MAN 11 Jakarta...\n";

            // 1. DATA 10 SISWA RIIL KELAS X IPA 1
            $studentsData = [
                [
                    'name'        => 'Muhammad Fadhil Rahman',
                    'nis'         => '2026000002',
                    'nisn'        => '0081234501',
                    'email'       => 'siswa.e2e@sekolah.sch.id',
                    'gender'      => 'male',
                    'birth_place' => 'Jakarta',
                    'birth_date'  => '2008-04-12',
                    'phone'       => '081298765401',
                    'address'     => 'Jl. Madrasah No. 11, Cilandak, Jakarta Selatan',
                ],
                [
                    'name'        => 'Siti Aisyah Nuraini',
                    'nis'         => '2026000003',
                    'nisn'        => '0081234502',
                    'email'       => 'siti.aisyah@man11jakarta.sch.id',
                    'gender'      => 'female',
                    'birth_place' => 'Jakarta',
                    'birth_date'  => '2008-06-18',
                    'phone'       => '081298765402',
                    'address'     => 'Jl. Fatmawati Raya No. 45, Jakarta Selatan',
                ],
                [
                    'name'        => 'Rizky Pratama Ramadhan',
                    'nis'         => '2026000004',
                    'nisn'        => '0081234503',
                    'email'       => 'rizky.pratama@man11jakarta.sch.id',
                    'gender'      => 'male',
                    'birth_place' => 'Depok',
                    'birth_date'  => '2008-02-25',
                    'phone'       => '081298765403',
                    'address'     => 'Jl. Margonda Raya No. 120, Beji, Depok',
                ],
                [
                    'name'        => 'Zahra Amelia Putri',
                    'nis'         => '2026000005',
                    'nisn'        => '0081234504',
                    'email'       => 'zahra.amelia@man11jakarta.sch.id',
                    'gender'      => 'female',
                    'birth_place' => 'Jakarta',
                    'birth_date'  => '2008-09-09',
                    'phone'       => '081298765404',
                    'address'     => 'Jl. TB Simatupang No. 88, Pasar Minggu',
                ],
                [
                    'name'        => 'Farhan Dwi Wicaksono',
                    'nis'         => '2026000006',
                    'nisn'        => '0081234505',
                    'email'       => 'farhan.dwi@man11jakarta.sch.id',
                    'gender'      => 'male',
                    'birth_place' => 'Tangerang',
                    'birth_date'  => '2008-05-14',
                    'phone'       => '081298765405',
                    'address'     => 'Jl. Ciputat Raya No. 34, Kebayoran Lama',
                ],
                [
                    'name'        => 'Nabila Syifa Azzahra',
                    'nis'         => '2026000007',
                    'nisn'        => '0081234506',
                    'email'       => 'nabila.syifa@man11jakarta.sch.id',
                    'gender'      => 'female',
                    'birth_place' => 'Jakarta',
                    'birth_date'  => '2008-08-21',
                    'phone'       => '081298765406',
                    'address'     => 'Jl. Ampera Raya No. 15, Ragunan',
                ],
                [
                    'name'        => 'Rayhan Aditya Saputra',
                    'nis'         => '2026000008',
                    'nisn'        => '0081234507',
                    'email'       => 'rayhan.aditya@man11jakarta.sch.id',
                    'gender'      => 'male',
                    'birth_place' => 'Bekasi',
                    'birth_date'  => '2008-01-30',
                    'phone'       => '081298765407',
                    'address'     => 'Jl. Pondok Labu No. 22, Cilandak',
                ],
                [
                    'name'        => 'Salma Khairunnisa',
                    'nis'         => '2026000009',
                    'nisn'        => '0081234508',
                    'email'       => 'salma.khairunnisa@man11jakarta.sch.id',
                    'gender'      => 'female',
                    'birth_place' => 'Jakarta',
                    'birth_date'  => '2008-11-11',
                    'phone'       => '081298765408',
                    'address'     => 'Jl. Lebak Bulus Raya No. 19, Jakarta Selatan',
                ],
                [
                    'name'        => 'Dimas Aryasatya',
                    'nis'         => '2026000010',
                    'nisn'        => '0081234509',
                    'email'       => 'dimas.aryasatya@man11jakarta.sch.id',
                    'gender'      => 'male',
                    'birth_place' => 'Jakarta',
                    'birth_date'  => '2008-03-05',
                    'phone'       => '081298765409',
                    'address'     => 'Jl. Radio Dalam No. 50, Kebayoran Baru',
                ],
                [
                    'name'        => 'Kayla Putri Maharani',
                    'nis'         => '2026000011',
                    'nisn'        => '0081234510',
                    'email'       => 'kayla.putri@man11jakarta.sch.id',
                    'gender'      => 'female',
                    'birth_place' => 'Jakarta',
                    'birth_date'  => '2008-07-27',
                    'phone'       => '081298765410',
                    'address'     => 'Jl. Kemang Timur No. 7, Bangka, Mampang',
                ],
            ];

            $createdStudents = [];
            $passwordHash = Hash::make('SiswaE2E@2026!');

            foreach ($studentsData as $st) {
                // Update atau buat User
                $user = User::updateOrCreate(
                    ['email' => $st['email']],
                    [
                        'username' => $st['nis'],
                        'password' => $passwordHash,
                        'role_id'  => 3, // Student role
                    ]
                );

                // Update atau buat Student record
                $student = Student::updateOrCreate(
                    ['nis' => $st['nis']],
                    [
                        'nisn'        => $st['nisn'],
                        'name'        => $st['name'],
                        'gender'      => $st['gender'],
                        'email'       => $st['email'],
                        'phone'       => $st['phone'],
                        'birth_place' => $st['birth_place'],
                        'birth_date'  => $st['birth_date'],
                        'address'     => $st['address'],
                        'is_active'   => 'active',
                    ]
                );

                // Enrol ke Kelas 1 (X IPA 1)
                $student->classes()->syncWithoutDetaching([1]);
                $createdStudents[] = $student;
            }

            echo "Berhasil menyiapkan 10 siswa aktif di Kelas X IPA 1.\n";

            // 2. KELAS & MAPEL IPAS (Course ID: 2)
            $course = Course::findOrFail(2);
            $teacherId = $course->teacher_id; // Guru E2E (ID 6)

            // 3. DAFTAR 10 PERTEMUAN KELAS RIIL (KURIKULUM MERDEKA FASE E)
            $meetsData = [
                [
                    'number' => 1,
                    'title'  => 'Hakikat Ilmu Pengetahuan Alam dan Metode Ilmiah',
                    'desc'   => 'Memahami tahapan kerja ilmiah secara sistematis: observasi fenomena alam, identifikasi rumusan masalah, hipotesis teruji, perancangan variabel eksperimen, hingga penarikan kesimpulan berbasis bukti empiris.',
                    'module_title' => 'Modul 01: Panduan Metode Ilmiah & Keselamatan Kerja Laboratorium',
                    'module_content' => 'Modul ajar komprehensif mengenai kaidah penyelidikan ilmiah, etika sains, penggunaan alat ukur berskala presisi (mikroskop, neraca ohaus, gelas ukur), serta pertolongan pertama di laboratorium sains madrasah.',
                ],
                [
                    'number' => 2,
                    'title'  => 'Makhluk Hidup dan Lingkungannya: Interaksi Biotik dan Abiotik',
                    'desc'   => 'Menganalisis hubungan timbal balik antara faktor abiotik (suhu, cahaya matahari, pH tanah, kelembaban) dengan pola adaptasi flora dan fauna di lingkungan perkotaan Jakarta Selatan.',
                    'module_title' => 'Modul 02: Ekologi Tropis & Observasi Keanekaragaman Hayati',
                    'module_content' => 'Bahan bacaan interaktif mengenai tingkatan organisasi kehidupan mulai dari individu, populasi, komunitas, hingga biosfer, dilengkapi lembar kerja pengamatan lingkungan madrasah.',
                ],
                [
                    'number' => 3,
                    'title'  => 'Aliran Energi, Rantai Makanan, dan Keseimbangan Ekosistem',
                    'desc'   => 'Kajian siklus trofik organisme autotrof dan heterotrof, piramida biomassa, hukum termodinamika pada aliran energi, serta dampak kepunahan spesies predator puncak.',
                    'module_title' => 'Modul 03: Aliran Energi, Rantai Makanan & Daur Biogeokimia',
                    'module_content' => 'Materi ajar memuat infografis jaring-jaring makanan, siklus nitrogen, karbon, dan fosfor, serta studi kasus penurunan populasi elang bondol di Kepulauan Seribu.',
                ],
                [
                    'number' => 4,
                    'title'  => 'Zat dan Karakteristiknya: Unsur, Senyawa, dan Campuran',
                    'desc'   => 'Identifikasi wujud materi, partikel atom penyusun bahan, klasifikasi larutan homogen vs heterogen, serta metode pemisahan campuran (filtrasi, destilasi, kromatografi).',
                    'module_title' => 'Modul 04: Karakterisasi Materi, Pemisahan Campuran & Tabel Periodik',
                    'module_content' => 'Panduan terstruktur mengenai struktur atom, pengenalan unsur logam dan non-logam esensial, serta eksperimen pemisahan zat warna tinta dengan kromatografi kertas.',
                ],
                [
                    'number' => 5,
                    'title'  => 'Perubahan Fisika dan Kimia dalam Kehidupan Sehari-hari',
                    'desc'   => 'Perbedaan mendasar antara perubahan bentuk fisik tanpa menghasilkan zat baru dengan reaksi kimia yang menghasilkan endapan, perubahan warna, pembentukan gas, atau perubahan kalor.',
                    'module_title' => 'Modul 05: Reaksi Kimia Lingkungan & Analisis Asam-Basa',
                    'module_content' => 'Dokumen kajian reaksi pembakaran, perkaratan besi, fermentasi glukosa, serta pengukuran derajat keasaman (pH) air hujan perkotaan menggunakan indikator universal.',
                ],
                [
                    'number' => 6,
                    'title'  => 'Energi Terbarukan: Pemanfaatan Energi Surya, Angin, dan Biomassa',
                    'desc'   => 'Eksplorasi potensi energi alternatif ramah lingkungan: prinsip kerja panel fotovoltaik, turbin angin, dan pengolahan limbah organik kantin madrasah menjadi bahan bakar biogas.',
                    'module_title' => 'Modul 06: Pembangkit Listrik Energi Terbarukan & Efisiensi Energi',
                    'module_content' => 'Modul panduan inovasi teknologi hijau, perhitungan konversi energi cahaya ke listrik, dan prinsip desain bangunan madrasah hemat energi (Green Building).',
                ],
                [
                    'number' => 7,
                    'title'  => 'Struktur Lapisan Bumi dan Dinamika Litosfer',
                    'desc'   => 'Karakteristik kerak bumi, mantel, dan inti bumi, teori tektonika lempeng, batas lempeng konvergen-divergen, serta persebaran gunung api aktif di sepanjang jalur Ring of Fire Indonesia.',
                    'module_title' => 'Modul 07: Geodinamika Lempeng Tektonik & Vulkanologi Nusantara',
                    'module_content' => 'Bahan referensi visual 3D pergerakan lempeng Indo-Australia dan Eurasia, pembentukan sesar aktif, serta mekanisme pelepasan energi gelombang seismik gempa bumi.',
                ],
                [
                    'number' => 8,
                    'title'  => 'Mitigasi Bencana Alam dan Kesiapsiagaan Mandiri',
                    'desc'   => 'Simulasi kesiapsiagaan menghadapi gempa bumi, banjir luapan kali Krukut, pemahaman rute evakuasi darurat, serta pengoperasian sistem peringatan dini (Early Warning System).',
                    'module_title' => 'Modul 08: Standard Operating Procedure (SOP) Mitigasi Bencana Madrasah',
                    'module_content' => 'Buku panduan tanggap darurat bencana untuk siswa madrasah, teknik drop-cover-hold on, dan tata cara pembuatan tas siaga bencana (TSB) keluarga.',
                ],
                [
                    'number' => 9,
                    'title'  => 'Pengelolaan Limbah dan Penerapan Prinsip 5R (Zero Waste)',
                    'desc'   => 'Pencegahan krisis sampah plastik di perkotaan melalui implementasi prinsip Reduce, Reuse, Recycle, Replace, dan Replant serta pengelolaan limbah organik menjadi pupuk kompos.',
                    'module_title' => 'Modul 09: Panduan Praktik Pembuatan Kompos & Bank Sampah Madrasah',
                    'module_content' => 'Tutorial langkah demi langkah pembuatan pupuk kompos komposter takakura dan biopori di area resapan air MAN 11 Jakarta.',
                ],
                [
                    'number' => 10,
                    'title'  => 'Asesmen Sumatif Tengah Semester (STS) IPAS Terpadu',
                    'desc'   => 'Sesi evaluasi tengah semester mandiri berbasis CBT terintegrasi. Mencakup pengujian pemahaman konsep sains terpadu dari materi Pertemuan 1 hingga Pertemuan 9.',
                    'module_title' => 'Petunjuk Teknis & Kisi-Kisi Asesmen STS IPAS Terpadu 2026',
                    'module_content' => 'Dokumen resmi petunjuk pengerjaan CBT: durasi 60 menit, 10 butir soal pilihan ganda acak dengan bobot setara, autosave jawaban berkala, dan sistem penilaian otomatis.',
                ],
            ];

            $createdMeets = [];
            foreach ($meetsData as $m) {
                $meet = Meet::updateOrCreate(
                    [
                        'course_id'      => $course->id,
                        'meeting_number' => $m['number'],
                    ],
                    [
                        'title'        => $m['title'],
                        'description'  => $m['desc'],
                        'type'         => 'offline',
                        'start_time'   => now()->subDays(10 - $m['number'])->setTime(7, 30),
                        'end_time'     => now()->subDays(10 - $m['number'])->setTime(9, 30),
                        'meeting_link' => null,
                    ]
                );

                // Tambahkan Bahan Ajar di setiap Pertemuan
                Material::updateOrCreate(
                    [
                        'meet_id' => $meet->id,
                        'title'   => $m['module_title'],
                    ],
                    [
                        'type'         => 'file',
                        'content'      => $m['module_content'],
                        'file_path'    => 'materials/modul_ipas_bab_' . $m['number'] . '.pdf',
                        'external_url' => 'https://man11jakarta.sch.id/materi/ipas-fase-e-bab-' . $m['number'],
                    ]
                );

                $createdMeets[$m['number']] = $meet;
            }

            echo "Berhasil membuat 10 pertemuan kelas dan bahan ajar IPAS.\n";

            // 4. PRESENSI KELAS
            // Untuk Pertemuan 1 - 9: Semua 10 siswa ditandai Hadir
            // Untuk Pertemuan 10 (Sesi Aktif Demo): 9 siswa lain hadir, siswa user (Fadhil) BELUM presensi agar bisa didemo langsung!
            $fadhilStudentId = $createdStudents[0]->id;

            foreach ($createdMeets as $num => $meet) {
                foreach ($createdStudents as $student) {
                    if ($num === 10 && $student->id === $fadhilStudentId) {
                        // Siswa user belum presensi di pertemuan 10
                        MeetAttendance::where('meet_id', $meet->id)->where('student_id', $student->id)->delete();
                        continue;
                    }

                    MeetAttendance::updateOrCreate(
                        [
                            'meet_id'    => $meet->id,
                            'student_id' => $student->id,
                        ],
                        [
                            'status'      => 'H',
                            'notes'       => 'Hadir mengikuti sesi pembelajaran tatap muka',
                            'recorded_by' => $teacherId,
                        ]
                    );
                }
            }

            echo "Presensi pertemuan 1-9 tersimpan, pertemuan 10 siap untuk demo live self-checkin.\n";

            // 5. SOAL CBT RIIL DI PERTEMUAN 10 (10 BUTIR SOAL IPAS PILIHAN GANDA)
            $cbtQuestions = [
                [
                    'number'   => 1,
                    'id'       => 101,
                    'type'     => 'pg',
                    'question' => 'Seorang peserta didik ingin menguji pengaruh intensitas cahaya terhadap laju fotosintesis tanaman Hydrilla verticillata. Dalam percobaan tersebut, jumlah gelembung oksigen yang dihasilkan dihitung setiap menit pada jarak lampu yang berbeda-beda. Manakah yang bertindak sebagai variabel bebas (independent variable) dalam eksperimen tersebut?',
                    'options'  => [
                        ['key' => 'A', 'text' => 'Jarak sumber cahaya (intensitas cahaya yang diterima tanaman)', 'is_correct' => true],
                        ['key' => 'B', 'text' => 'Jumlah gelembung gas oksigen yang terbentuk tiap menit', 'is_correct' => false],
                        ['key' => 'C', 'text' => 'Volume air dan jenis tanaman air Hydrilla yang digunakan', 'is_correct' => false],
                        ['key' => 'D', 'text' => 'Suhu ruangan dan waktu pengamatan selama praktikum', 'is_correct' => false],
                        ['key' => 'E', 'text' => 'Kandungan klorofil pada daun Hydrilla verticillata', 'is_correct' => false],
                    ],
                    'weight'     => 10,
                    'difficulty' => 'medium',
                    'explanation'=> 'Variabel bebas adalah perlakuan yang sengaja diubah oleh peneliti, yaitu jarak sumber lampu terhadap tanaman yang merepresentasikan intensitas cahaya.',
                ],
                [
                    'number'   => 2,
                    'id'       => 102,
                    'type'     => 'pg',
                    'question' => 'Dalam suatu ekosistem persawahan, ular sawah diburu secara masif oleh manusia karena dianggap hama dan diambil kulitnya. Dampak ekologis langsung yang akan terjadi pada rantai makanan di persawahan tersebut adalah...',
                    'options'  => [
                        ['key' => 'A', 'text' => 'Populasi katak dan elang akan melonjak drastis', 'is_correct' => false],
                        ['key' => 'B', 'text' => 'Populasi tikus sawah meningkat tajam sehingga merusak hasil panen padi', 'is_correct' => true],
                        ['key' => 'C', 'text' => 'Produksi gabah padi meningkat karena hama berkurang', 'is_correct' => false],
                        ['key' => 'D', 'text' => 'Populasi elang meningkat karena mencari mangsa pengganti', 'is_correct' => false],
                        ['key' => 'E', 'text' => 'Dekomposer kehilangan peran dalam siklus hara tanah', 'is_correct' => false],
                    ],
                    'weight'     => 10,
                    'difficulty' => 'easy',
                    'explanation'=> 'Ular sawah adalah predator alami tikus. Hilangnya populasi ular akan menyebabkan ledakan populasi tikus sawah (hama primer padi).',
                ],
                [
                    'number'   => 3,
                    'id'       => 103,
                    'type'     => 'pg',
                    'question' => 'Berdasarkan Hukum Lindeman tentang efisiensi aliran energi dalam ekosistem, rata-rata energi yang dapat ditransfer dari satu tingkat trofik ke tingkat trofik berikutnya hanya sekitar 10%. Ke manakah sebagian besar energi (90%) lainnya hilang?',
                    'options'  => [
                        ['key' => 'A', 'text' => 'Tersimpan permanen di dalam sel tubuh organisme karnivora', 'is_correct' => false],
                        ['key' => 'B', 'text' => 'Diserap kembali oleh matahari melalui radiasi kosmik', 'is_correct' => false],
                        ['key' => 'C', 'text' => 'Hilang sebagai energi panas melalui metabolisme respirasi dan sisa ekskresi', 'is_correct' => true],
                        ['key' => 'D', 'text' => 'Diubah menjadi materi mineral yang tidak dapat terurai', 'is_correct' => false],
                        ['key' => 'E', 'text' => 'Diserap seutuhnya oleh produsen primer di darat', 'is_correct' => false],
                    ],
                    'weight'     => 10,
                    'difficulty' => 'medium',
                    'explanation'=> 'Sebagian besar energi (~90%) terdisipasi sebagai panas melalui proses respirasi seluler, pergerakan, dan sisa bahan yang tidak tercerna (feses/urin).',
                ],
                [
                    'number'   => 4,
                    'id'       => 104,
                    'type'     => 'pg',
                    'question' => 'Laboratorium madrasah memiliki sampel larutan tinta hitam yang diduga merupakan campuran dari beberapa zat pewarna sintetis. Metode pemisahan campuran yang paling tepat dan sensitif untuk menguji komponen zat warna tersebut adalah...',
                    'options'  => [
                        ['key' => 'A', 'text' => 'Kromatografi kertas (paper chromatography)', 'is_correct' => true],
                        ['key' => 'B', 'text' => 'Kristalisasi penguapan larutan pekat', 'is_correct' => false],
                        ['key' => 'C', 'text' => 'Filtrasi menggunakan kertas saring berpori halus', 'is_correct' => false],
                        ['key' => 'D', 'text' => 'Destilasi bertingkat berdasarkan titik didih', 'is_correct' => false],
                        ['key' => 'E', 'text' => 'Sublimasi dengan pemanasan langsung', 'is_correct' => false],
                    ],
                    'weight'     => 10,
                    'difficulty' => 'easy',
                    'explanation'=> 'Kromatografi kertas memisahkan zat warna berdasarkan perbedaan kecepatan rambat zat terlarut pada fase diam (kertas) dan fase gerak (pelarut).',
                ],
                [
                    'number'   => 5,
                    'id'       => 105,
                    'type'     => 'pg',
                    'question' => 'Perhatikan beberapa fenomena berikut:\n1) Kayu terbakar menjadi abu\n2) Es mencair menjadi air\n3) Besi berkarat karena kelembaban udara\n4) Gula larut di dalam air teh hangat\n5) Singkong mengalami fermentasi menjadi tape\nManakah yang tergolong ke dalam peristiwa PERUBAHAN KIMIA?',
                    'options'  => [
                        ['key' => 'A', 'text' => '1, 2, dan 4', 'is_correct' => false],
                        ['key' => 'B', 'text' => '1, 3, dan 5', 'is_correct' => true],
                        ['key' => 'C', 'text' => '2, 3, dan 4', 'is_correct' => false],
                        ['key' => 'D', 'text' => '2, 4, dan 5', 'is_correct' => false],
                        ['key' => 'E', 'text' => '3, 4, dan 5', 'is_correct' => false],
                    ],
                    'weight'     => 10,
                    'difficulty' => 'medium',
                    'explanation'=> 'Perubahan kimia menghasilkan zat baru dengan komposisi kimia berbeda: pembakaran kayu (1), perkaratan besi (3), dan fermentasi tape (5).',
                ],
                [
                    'number'   => 6,
                    'id'       => 106,
                    'type'     => 'pg',
                    'question' => 'Panel surya fotovoltaik (Solar PV) yang dipasang di atap gedung MAN 11 Jakarta bekerja dengan prinsip efek fotolistrik. Perubahan bentuk energi utama yang terjadi pada sel surya tersebut adalah...',
                    'options'  => [
                        ['key' => 'A', 'text' => 'Energi cahaya matahari menjadi energi listrik', 'is_correct' => true],
                        ['key' => 'B', 'text' => 'Energi panas matahari menjadi energi kinetik', 'is_correct' => false],
                        ['key' => 'C', 'text' => 'Energi kimia semikonduktor menjadi energi termal', 'is_correct' => false],
                        ['key' => 'D', 'text' => 'Energi elektromagnetik menjadi energi mekanik', 'is_correct' => false],
                        ['key' => 'E', 'text' => 'Energi potensial elektron menjadi energi gelombang', 'is_correct' => false],
                    ],
                    'weight'     => 10,
                    'difficulty' => 'easy',
                    'explanation'=> 'Efek fotolistrik pada semikonduktor silikon sel surya menyerap foton cahaya matahari dan melepaskan elektron bebas menghasilkan arus listrik DC.',
                ],
                [
                    'number'   => 7,
                    'id'       => 107,
                    'type'     => 'pg',
                    'question' => 'Kepulauan Indonesia memiliki potensi gempa bumi dan gunung api aktif yang tinggi karena berada pada pertemuan tiga lempeng tektonik utama dunia, yaitu...',
                    'options'  => [
                        ['key' => 'A', 'text' => 'Lempeng Indo-Australia, Lempeng Eurasia, dan Lempeng Pasifik', 'is_correct' => true],
                        ['key' => 'B', 'text' => 'Lempeng Nazca, Lempeng Amerika Selatan, dan Lempeng Antartika', 'is_correct' => false],
                        ['key' => 'C', 'text' => 'Lempeng Afrika, Lempeng Arab, dan Lempeng India', 'is_correct' => false],
                        ['key' => 'D', 'text' => 'Lempeng Pasifik, Lempeng Karibia, dan Lempeng Scotia', 'is_correct' => false],
                        ['key' => 'E', 'text' => 'Lempeng Eurasia, Lempeng Juan de Fuca, dan Lempeng Filipina', 'is_correct' => false],
                    ],
                    'weight'     => 10,
                    'difficulty' => 'medium',
                    'explanation'=> 'Indonesia terletak di pertemuan Lempeng Indo-Australia di selatan, Lempeng Eurasia di utara-barat, dan Lempeng Pasifik di timur.',
                ],
                [
                    'number'   => 8,
                    'id'       => 108,
                    'type'     => 'pg',
                    'question' => 'Ketika sedang berada di lantai dua gedung madrasah dan tiba-tiba terjadi guncangan gempa bumi berkekuatan cukup besar, tindakan penyelamatan mandiri yang paling aman dan tepat sesuai standar mitigasi kebencanaan adalah...',
                    'options'  => [
                        ['key' => 'A', 'text' => 'Langsung berlari kencang menuju tangga darurat sambil berteriak', 'is_correct' => false],
                        ['key' => 'B', 'text' => 'Mencari perlindungan di bawah meja kokoh dan melindungi kepala (Drop, Cover, Hold On)', 'is_correct' => true],
                        ['key' => 'C', 'text' => 'Berdiri tegak di samping jendela kaca untuk mengamati kondisi luar gedung', 'is_correct' => false],
                        ['key' => 'D', 'text' => 'Masuk ke dalam lift gedung agar cepat sampai di lantai dasar', 'is_correct' => false],
                        ['key' => 'E', 'text' => 'Melompat melalui jendela balkon ke halaman sekolah', 'is_correct' => false],
                    ],
                    'weight'     => 10,
                    'difficulty' => 'easy',
                    'explanation'=> 'Tindakan mitigasi pertama saat gempa di dalam gedung bertingkat adalah Drop, Cover, and Hold On di bawah meja untuk menghindari reruntuhan plafon.',
                ],
                [
                    'number'   => 9,
                    'id'       => 109,
                    'type'     => 'pg',
                    'question' => 'Limbah plastik di perairan dapat mengalami degradasi fotolitik menjadi partikel mikroplastik (< 5 mm). Bahaya terbesar mikroplastik bagi organisme tingkat tinggi dan manusia adalah terjadinya peristiwa...',
                    'options'  => [
                        ['key' => 'A', 'text' => 'Eutrofikasi alga yang menutupi permukaan danau', 'is_correct' => false],
                        ['key' => 'B', 'text' => 'Biomagnifikasi (akumulasi racun berlipat ganda sepanjang rantai makanan)', 'is_correct' => true],
                        ['key' => 'C', 'text' => 'Pengasaman air laut akibat penurunan kadar kalsium', 'is_correct' => false],
                        ['key' => 'D', 'text' => 'Penurunan daya hantar listrik pada molekul air sungai', 'is_correct' => false],
                        ['key' => 'E', 'text' => 'Peningkatan laju fotosintesis fitoplankton di perairan', 'is_correct' => false],
                    ],
                    'weight'     => 10,
                    'difficulty' => 'hard',
                    'explanation'=> 'Biomagnifikasi terjadi ketika zat toksik dari mikroplastik terkonsentrasi semakin tinggi pada setiap tingkat trofik rantai makanan hingga manusia di puncak.',
                ],
                [
                    'number'   => 10,
                    'id'       => 110,
                    'type'     => 'pg',
                    'question' => 'Gas rumah kaca (seperti CO2, CH4, dan uap air) di atmosfer bumi memiliki kemampuan khas yang memicu terjadinya pemanasan global (Global Warming). Kemampuan tersebut adalah...',
                    'options'  => [
                        ['key' => 'A', 'text' => 'Menyerap dan meradiasikan kembali radiasi gelombang panjang inframerah ke permukaan bumi', 'is_correct' => true],
                        ['key' => 'B', 'text' => 'Menghalangi seluruh cahaya tampak matahari yang masuk ke atmosfer bumi', 'is_correct' => false],
                        ['key' => 'C', 'text' => 'Menguraikan molekul ozon di lapisan troposfer secara spontan', 'is_correct' => false],
                        ['key' => 'D', 'text' => 'Memantulkan radiasi sinar ultraviolet matahari kembali ke ruang angkasa', 'is_correct' => false],
                        ['key' => 'E', 'text' => 'Menyerap gas nitrogen bebas sehingga menurunkan tekanan udara bumi', 'is_correct' => false],
                    ],
                    'weight'     => 10,
                    'difficulty' => 'medium',
                    'explanation'=> 'Gas rumah kaca meneruskan cahaya tampak matahari, tetapi menyerap radiasi inframerah panas yang dipancarkan bumi dan memantulkannya kembali.',
                ],
            ];

            // Update atau buat Kuis di Pertemuan 10
            $meet10 = $createdMeets[10];
            $quiz = Quiz::updateOrCreate(
                [
                    'meet_id' => $meet10->id,
                    'title'   => 'Asesmen Sumatif Tengah Semester (STS) IPAS Terpadu',
                ],
                [
                    'instructions'       => 'Kerjakan 10 butir soal asesmen pilihan ganda dengan cermat dan mandiri. Waktu pengerjaan adalah 60 menit. Jawaban akan disimpan secara otomatis.',
                    'quiz_type'          => 'multiple_choice',
                    'duration_minutes'   => 60,
                    'due_date'           => now()->addDays(14)->setTime(23, 59),
                    'max_score'          => 100,
                    'is_random_question' => false,
                    'is_random_option'   => false,
                    'questions_data'     => $cbtQuestions,
                    'access_token'       => 'STS911',
                    'token_expires_at'   => now()->addDays(7),
                    'available_from'     => now()->subDays(1),
                    'available_until'    => now()->addDays(7)->setTime(23, 59),
                    'max_violations'     => 3,
                ]
            );

            // Bersihkan submission sebelumnya untuk Fadhil agar user bisa mulai ujian fresh!
            QuizSubmission::where('quiz_id', $quiz->id)->where('student_id', $fadhilStudentId)->delete();

            // Masukkan submission nilai untuk 3 siswa rekanan agar tampak riil
            $sampleAnswers = [
                '1' => 'A', '2' => 'B', '3' => 'C', '4' => 'A', '5' => 'B',
                '6' => 'A', '7' => 'A', '8' => 'B', '9' => 'B', '10' => 'A'
            ];

            // Siswa Aisyah dapat nilai 90
            QuizSubmission::updateOrCreate(
                ['quiz_id' => $quiz->id, 'student_id' => $createdStudents[1]->id],
                [
                    'started_at'   => now()->subHours(2),
                    'submitted_at' => now()->subHours(1),
                    'answers'      => $sampleAnswers,
                    'score_pg'     => 90.00,
                    'score'        => 90.00,
                    'is_graded'    => true,
                    'status'       => 'graded',
                ]
            );

            // Siswa Rizky dapat nilai 80
            QuizSubmission::updateOrCreate(
                ['quiz_id' => $quiz->id, 'student_id' => $createdStudents[2]->id],
                [
                    'started_at'   => now()->subHours(3),
                    'submitted_at' => now()->subHours(2),
                    'answers'      => $sampleAnswers,
                    'score_pg'     => 80.00,
                    'score'        => 80.00,
                    'is_graded'    => true,
                    'status'       => 'graded',
                ]
            );

            echo "Kuis CBT 10 butir soal riil siap di Pertemuan 10 (Submission siswa Fadhil di-reset fresh).\n";

            // Tambahan Tugas Mandiri di Pertemuan 1
            $meet1 = $createdMeets[1];
            Quiz::updateOrCreate(
                [
                    'meet_id' => $meet1->id,
                    'title'   => 'Tugas Mandiri: Rancangan Eksperimen Metode Ilmiah',
                ],
                [
                    'instructions'       => 'Susunlah rancangan eksperimen mandiri mengenai pengamatan faktor ekosistem. Tuliskan hipotesis, identifikasi variabel (bebas, terikat, kontrol), dan langkah kerja sistematis.',
                    'quiz_type'          => 'assignment',
                    'duration_minutes'   => null,
                    'due_date'           => now()->addDays(5)->setTime(23, 59),
                    'max_score'          => 100,
                    'is_random_question' => false,
                    'is_random_option'   => false,
                    'questions_data'     => null,
                ]
            );

            // Tambahan Kuis Singkat di Pertemuan 2
            $meet2 = $createdMeets[2];
            $meet2Questions = [
                [
                    'number'   => 1,
                    'id'       => 201,
                    'type'     => 'pg',
                    'question' => 'Faktor abiotik berikut yang paling dominan mempengaruhi laju transpirasi pada daun tumbuhan di area lapangan madrasah adalah...',
                    'options'  => [
                        ['key' => 'A', 'text' => 'Intensitas cahaya dan kelembaban udara sekitar', 'is_correct' => true],
                        ['key' => 'B', 'text' => 'Keberadaan serangga penyerbuk', 'is_correct' => false],
                        ['key' => 'C', 'text' => 'Tekstur pasir pada tanah pot', 'is_correct' => false],
                        ['key' => 'D', 'text' => 'Jumlah cacing tanah di dalam tanah', 'is_correct' => false],
                    ],
                    'weight'     => 50,
                    'difficulty' => 'easy',
                    'explanation'=> 'Intensitas radiasi matahari dan kelembaban udara adalah variabel abiotik utama yang mengatur membuka-menutupnya stomata dan penguapan air.',
                ],
                [
                    'number'   => 2,
                    'id'       => 202,
                    'type'     => 'pg',
                    'question' => 'Hubungan simbiosis antara bakteri Rhizobium leguminosarum dengan bintil akar tanaman kacang-kacangan tergolong ke dalam...',
                    'options'  => [
                        ['key' => 'A', 'text' => 'Mutualisme (keduanya saling menguntungkan dalam fiksasi nitrogen)', 'is_correct' => true],
                        ['key' => 'B', 'text' => 'Komensalisme (satu pihak untung, pihak lain tidak terpengaruh)', 'is_correct' => false],
                        ['key' => 'C', 'text' => 'Parasitisme (bakteri merugikan jaringan perakaran tanaman)', 'is_correct' => false],
                        ['key' => 'D', 'text' => 'Predasi (bakteri memangsa sel tanaman inang)', 'is_correct' => false],
                    ],
                    'weight'     => 50,
                    'difficulty' => 'easy',
                    'explanation'=> 'Bakteri memfiksasi nitrogen bebas dari udara untuk tanaman, sedangkan tanaman menyediakan nutrisi karbohidrat bagi bakteri.',
                ],
            ];

            Quiz::updateOrCreate(
                [
                    'meet_id' => $meet2->id,
                    'title'   => 'Kuis Cepat: Interaksi Komponen Biotik dan Abiotik',
                ],
                [
                    'instructions'       => 'Kuis formatif singkat 2 butir soal untuk menguji pemahaman konsep interaksi biotik dan abiotik.',
                    'quiz_type'          => 'multiple_choice',
                    'duration_minutes'   => 15,
                    'due_date'           => now()->addDays(7)->setTime(23, 59),
                    'max_score'          => 100,
                    'is_random_question' => false,
                    'is_random_option'   => false,
                    'questions_data'     => $meet2Questions,
                ]
            );

            // =========================================================================
            // S1 (ITERASI 1): PAKET 5 TIPE SOAL MODEL BARU (2 BUTIR PER TIPE = 10 SOAL)
            // =========================================================================
            $multiTypeBank = QuestionBank::updateOrCreate(
                [
                    'teacher_id' => $teacherId,
                    'title'      => 'Bank Soal AKM & Literasi Sains (5 Tipe Soal Terpadu)',
                ],
                [
                    'grade_level' => '10',
                    'subject_id'  => $course->subject_id,
                    'description' => 'Paket soal standar asesmen nasional MAN 11: Pilihan Ganda, PG Kompleks, Isian Singkat, Menjodohkan, dan Benar/Salah dengan auto-grading.',
                ]
            );

            $multiTypeQuestions = [
                // 1. PG 1
                [
                    'number'   => 1,
                    'id'       => 301,
                    'type'     => 'pg',
                    'question' => 'Organel sel yang berfungsi sebagai pusat respirasi seluler dan menghasilkan molekul energi ATP adalah...',
                    'options'  => [
                        ['key' => 'A', 'text' => 'Mitokondria', 'is_correct' => true],
                        ['key' => 'B', 'text' => 'Ribosom', 'is_correct' => false],
                        ['key' => 'C', 'text' => 'Badan Golgi', 'is_correct' => false],
                        ['key' => 'D', 'text' => 'Retikulum Endoplasma', 'is_correct' => false],
                    ],
                    'data'       => null,
                    'weight'     => 10,
                    'difficulty' => 'easy',
                    'explanation'=> 'Mitokondria adalah organel pembuat energi utama sel melalui fosforilasi oksidatif dan siklus Krebs.',
                ],
                // 2. PG 2
                [
                    'number'   => 2,
                    'id'       => 302,
                    'type'     => 'pg',
                    'question' => 'Zat hijau daun yang berperan vital menangkap foton cahaya dalam fotosintesis adalah...',
                    'options'  => [
                        ['key' => 'A', 'text' => 'Klorofil', 'is_correct' => true],
                        ['key' => 'B', 'text' => 'Karotenoid', 'is_correct' => false],
                        ['key' => 'C', 'text' => 'Antosianin', 'is_correct' => false],
                        ['key' => 'D', 'text' => 'Xantofil', 'is_correct' => false],
                    ],
                    'data'       => null,
                    'weight'     => 10,
                    'difficulty' => 'easy',
                    'explanation'=> 'Klorofil merupakan pigmen fotosintetik utama di membran tilakoid kloroplas.',
                ],
                // 3. PG Kompleks 1
                [
                    'number'   => 3,
                    'id'       => 303,
                    'type'     => 'pg_kompleks',
                    'question' => 'Manakah di antara gas-gas berikut yang tergolong ke dalam Gas Rumah Kaca (GRK) penyebab pemanasan global? (Pilih semua yang benar)',
                    'options'  => [
                        ['key' => 'A', 'text' => 'Karbondioksida (CO2)', 'is_correct' => true],
                        ['key' => 'B', 'text' => 'Metana (CH4)', 'is_correct' => true],
                        ['key' => 'C', 'text' => 'Gas Oksigen murni (O2)', 'is_correct' => false],
                        ['key' => 'D', 'text' => 'Dinitrogen Oksida (N2O)', 'is_correct' => true],
                    ],
                    'data'       => null,
                    'weight'     => 10,
                    'difficulty' => 'medium',
                    'explanation'=> 'CO2, CH4, dan N2O memiliki kemampuan menyerap radiasi inframerah termal dari permukaan bumi.',
                ],
                // 4. PG Kompleks 2
                [
                    'number'   => 4,
                    'id'       => 304,
                    'type'     => 'pg_kompleks',
                    'question' => 'Ciri-ciri perubahan fisika pada materi yang membedakannya dari perubahan kimia adalah... (Pilih semua yang benar)',
                    'options'  => [
                        ['key' => 'A', 'text' => 'Tidak terbentuk zat kimia baru', 'is_correct' => true],
                        ['key' => 'B', 'text' => 'Komposisi molekul penyusun tetap sama', 'is_correct' => true],
                        ['key' => 'C', 'text' => 'Menghasilkan endapan atau gas hasil reaksi kimia', 'is_correct' => false],
                        ['key' => 'D', 'text' => 'Perubahan bersifat reversibel (dapat kembali ke bentuk awal)', 'is_correct' => true],
                    ],
                    'data'       => null,
                    'weight'     => 10,
                    'difficulty' => 'medium',
                    'explanation'=> 'Perubahan fisika hanya mengubah wujud atau bentuk materi tanpa mengubah struktur identitas kimianya.',
                ],
                // 5. Isian Singkat 1
                [
                    'number'   => 5,
                    'id'       => 305,
                    'type'     => 'isian',
                    'question' => 'Proses pembuatan makanan oleh tumbuhan hijau dengan memanfaatkan energi cahaya matahari dan air disebut...',
                    'options'  => null,
                    'data'     => [
                        'accepted_answers' => ['fotosintesis', 'foto sintesis', 'photosynthesis'],
                    ],
                    'weight'     => 10,
                    'difficulty' => 'easy',
                    'explanation'=> 'Fotosintesis adalah reaksi anabolisme pembentukan karbohidrat (glukosa) dari CO2 dan H2O dengan bantuan cahaya.',
                ],
                // 6. Isian Singkat 2
                [
                    'number'   => 6,
                    'id'       => 306,
                    'type'     => 'isian',
                    'question' => 'Satuan Sistem Internasional (SI) untuk besaran intensitas arus listrik adalah...',
                    'options'  => null,
                    'data'     => [
                        'accepted_answers' => ['ampere', 'amper', 'a'],
                    ],
                    'weight'     => 10,
                    'difficulty' => 'easy',
                    'explanation'=> 'Ampere (A) adalah satuan dasar SI untuk arus listrik.',
                ],
                // 7. Menjodohkan 1
                [
                    'number'   => 7,
                    'id'       => 307,
                    'type'     => 'menjodohkan',
                    'question' => 'Pasangkan setiap organel sel berikut dengan fungsinya yang paling tepat!',
                    'options'  => null,
                    'data'     => [
                        'pairs' => [
                            ['id' => '1', 'left' => 'Kloroplas', 'right' => 'Tempat fotosintesis'],
                            ['id' => '2', 'left' => 'Ribosom', 'right' => 'Sintesis protein'],
                            ['id' => '3', 'left' => 'Nukleus', 'right' => 'Pengendali aktivitas dan materi genetik sel'],
                        ],
                    ],
                    'weight'     => 10,
                    'difficulty' => 'medium',
                    'explanation'=> 'Kloroplas memfasilitasi fotosintesis, ribosom merakit polipeptida, dan nukleus mengorganisasi DNA sel.',
                ],
                // 8. Menjodohkan 2
                [
                    'number'   => 8,
                    'id'       => 308,
                    'type'     => 'menjodohkan',
                    'question' => 'Jodohkan jenis sumber energi terbarukan berikut dengan media alam yang dimanfaatkannya!',
                    'options'  => null,
                    'data'     => [
                        'pairs' => [
                            ['id' => '1', 'left' => 'Pembangkit PLTS', 'right' => 'Radiasi foton cahaya matahari'],
                            ['id' => '2', 'left' => 'Pembangkit PLTB', 'right' => 'Hembusan kinetik angin'],
                            ['id' => '3', 'left' => 'Pembangkit PLTP', 'right' => 'Panas uap bumi (geotermal)'],
                        ],
                    ],
                    'weight'     => 10,
                    'difficulty' => 'medium',
                    'explanation'=> 'PLTS memanfaatkan tenaga surya, PLTB tenaga bayu/angin, dan PLTP panas bumi bumi.',
                ],
                // 9. Benar / Salah 1
                [
                    'number'   => 9,
                    'id'       => 309,
                    'type'     => 'benar_salah',
                    'question' => 'Tentukan status kebenaran (Benar atau Salah) dari setiap pernyataan berikut mengenai sifat zat dan energi!',
                    'options'  => null,
                    'data'     => [
                        'statements' => [
                            ['id' => '1', 'text' => 'Air murni membeku pada suhu 0 derajat Celsius pada tekanan normal 1 atmosfer.', 'answer' => true],
                            ['id' => '2', 'text' => 'Minyak goreng dapat larut sempurna menyatu dengan air murni tanpa emulgator.', 'answer' => false],
                            ['id' => '3', 'text' => 'Energi tidak dapat dimusnahkan melainkan hanya dapat diubah dari satu bentuk ke bentuk lain.', 'answer' => true],
                        ],
                    ],
                    'weight'     => 10,
                    'difficulty' => 'medium',
                    'explanation'=> 'Titik beku air adalah 0 C, minyak bersifat non-polar sehingga tidak larut dalam air polar, dan hukum kekekalan energi berlaku.',
                ],
                // 10. Benar / Salah 2
                [
                    'number'   => 10,
                    'id'       => 310,
                    'type'     => 'benar_salah',
                    'question' => 'Evaluasilah pernyataan berikut terkait interaksi ekosistem dan keanekaragaman hayati!',
                    'options'  => null,
                    'data'     => [
                        'statements' => [
                            ['id' => '1', 'text' => 'Tumbuhan berperan sebagai produsen primer karena mampu memproduksi zat organik sendiri.', 'answer' => true],
                            ['id' => '2', 'text' => 'Jamur dekomposer memperoleh makanan melalui proses fotosintesis aktif.', 'answer' => false],
                        ],
                    ],
                    'weight'     => 10,
                    'difficulty' => 'easy',
                    'explanation'=> 'Tumbuhan bersifat autotrof, sedangkan jamur bersifat heterotrof saprofit (tidak berfotosintesis).',
                ],
            ];

            // Simpan ke question_bank_items
            foreach ($multiTypeQuestions as $qItem) {
                QuestionBankItem::updateOrCreate(
                    [
                        'question_bank_id' => $multiTypeBank->id,
                        'question'         => $qItem['question'],
                    ],
                    [
                        'type'           => $qItem['type'],
                        'options'        => $qItem['options'],
                        'data'           => $qItem['data'],
                        'correct_answer' => null,
                        'explanation'    => $qItem['explanation'],
                        'difficulty'     => $qItem['difficulty'],
                        'weight'         => $qItem['weight'],
                    ]
                );
            }

            // Pasang kuis ini di Pertemuan 3 ($meet3)
            $meet3 = $createdMeets[3];
            $meet3Quiz = Quiz::updateOrCreate(
                [
                    'meet_id' => $meet3->id,
                    'title'   => 'Asesmen Formatif: Model Baru AKM (5 Tipe Soal)',
                ],
                [
                    'instructions'       => 'Ujian latihan 5 variasi tipe soal (PG, PG Kompleks, Isian Singkat, Menjodohkan, Benar/Salah) dengan penilaian sistem otomatis.',
                    'quiz_type'          => 'multiple_choice',
                    'duration_minutes'   => 45,
                    'due_date'           => now()->addDays(10)->setTime(23, 59),
                    'max_score'          => 100,
                    'is_random_question' => false,
                    'is_random_option'   => false,
                    'questions_data'     => $multiTypeQuestions,
                    'question_bank_id'   => $multiTypeBank->id,
                    'access_token'       => 'AKM11A',
                    'token_expires_at'   => now()->addDays(7),
                    'available_from'     => now()->subDays(1),
                    'available_until'    => now()->addDays(7)->setTime(23, 59),
                    'max_violations'     => 3,
                ]
            );

            // Bersihkan submission Fadhil di meet3 agar siswa bisa mencoba langsung
            QuizSubmission::where('quiz_id', $meet3Quiz->id)->where('student_id', $fadhilStudentId)->delete();

            // Masukkan contoh submission untuk siswa Siti Aisyah dengan skor variatif
            $sampleAnswersMeet3 = [
                '1' => 'A',                             // PG: benar
                '2' => 'A',                             // PG: benar
                '3' => ['A', 'B', 'D'],                 // PG Kompleks: benar semua
                '4' => ['A', 'B'],                      // PG Kompleks: sebagian
                '5' => 'fotosintesis',                  // Isian: benar
                '6' => 'ampere',                        // Isian: benar
                '7' => ['1' => 'Tempat fotosintesis', '2' => 'Sintesis protein', '3' => 'Pengendali aktivitas dan materi genetik sel'], // Menjodohkan: benar
                '8' => ['1' => 'Radiasi foton cahaya matahari', '2' => 'Hembusan kinetik angin', '3' => 'Panas uap bumi (geotermal)'], // Menjodohkan: benar
                '9' => ['1' => true, '2' => false, '3' => true], // Benar salah: benar semua
                '10' => ['1' => true, '2' => false],    // Benar salah: benar semua
            ];
            QuizSubmission::updateOrCreate(
                ['quiz_id' => $meet3Quiz->id, 'student_id' => $createdStudents[1]->id],
                [
                    'started_at'   => now()->subMinutes(50),
                    'submitted_at' => now()->subMinutes(15),
                    'answers'      => $sampleAnswersMeet3,
                    'score_pg'     => 96.67,
                    'score'        => 96.67,
                    'is_graded'    => true,
                    'status'       => 'graded',
                ]
            );
            echo "Paket 5 Tipe Soal (S1) berhasil diinjeksi ke Bank Soal & Pertemuan 3!\n";

            // Forum Diskusi Pertemuan 1
            $disc1 = Discussion::updateOrCreate(
                [
                    'meet_id'    => $meet1->id,
                    'parent_id'  => null,
                    'teacher_id' => $teacherId,
                ],
                [
                    'student_id' => null,
                    'message'    => "Assalamu'alaikum siswa-siswi kelas X IPA 1. Selamat datang di modul pembelajaran IPAS. Silakan cermati Modul 01 mengenai tahapan metode ilmiah. Jika ada pertanyaan seputar penentuan variabel bebas, terikat, dan kontrol pada penelitian mandiri, silakan diskusikan di thread ini.",
                ]
            );

            Discussion::updateOrCreate(
                [
                    'meet_id'    => $meet1->id,
                    'parent_id'  => $disc1->id,
                    'student_id' => $createdStudents[1]->id, // Aisyah
                ],
                [
                    'teacher_id' => null,
                    'message'    => "Wa'alaikumsalam Pak Guru, izin bertanya apakah variabel kontrol pada eksperimen pertumbuhan kecambah kacang hijau harus lebih dari satu variabel, misalnya jenis tanah dan takaran penyiraman air?",
                ]
            );

            Discussion::updateOrCreate(
                [
                    'meet_id'    => $meet1->id,
                    'parent_id'  => $disc1->id,
                    'teacher_id' => $teacherId,
                    'message'    => "Pertanyaan yang sangat bagus Aisyah. Betul sekali, variabel kontrol adalah semua kondisi di luar variabel bebas yang sengaja kita jaga seragam agar perubahan pada variabel terikat murni akibat perlakuan yang diuji.",
                ],
                [
                    'student_id' => null,
                ]
            );

            // Forum Diskusi Pertemuan 2
            $disc2 = Discussion::updateOrCreate(
                [
                    'meet_id'    => $meet2->id,
                    'parent_id'  => null,
                    'student_id' => $createdStudents[2]->id, // Rizky
                ],
                [
                    'teacher_id' => null,
                    'message'    => "Pak Guru, untuk pengamatan faktor abiotik di taman madrasah, apakah alat pengukur pH tanah (soil meter) dan lux meter cahaya matahari sudah tersedia di laboratorium IPA?",
                ]
            );

            Discussion::updateOrCreate(
                [
                    'meet_id'    => $meet2->id,
                    'parent_id'  => $disc2->id,
                    'teacher_id' => $teacherId,
                ],
                [
                    'student_id' => null,
                    'message'    => "Halo Rizky. Ya, 5 unit soil tester analog dan lux meter digital sudah disiapkan di meja laboratorium untuk masing-masing kelompok kerja.",
                ]
            );

            // Forum Diskusi Pertemuan 10 (STS CBT)
            Discussion::updateOrCreate(
                [
                    'meet_id'    => $meet10->id,
                    'parent_id'  => null,
                    'teacher_id' => $teacherId,
                ],
                [
                    'student_id' => null,
                    'message'    => "Pemberitahuan Pelaksanaan STS IPAS: Ujian CBT berdurasi 60 menit dengan 10 butir soal pilihan ganda acak. Sistem menerapkan autosave jawaban secara real-time. Pastikan kuota dan koneksi internet stabil sebelum klik tombol Mulai Ujian. Selamat mengerjakan!",
                ]
            );

            echo "Forum diskusi interaktif dan kuis formatif tambahan berhasil diinjeksi.\n";

            // 6. BUKU NILAI KURIKULUM MERDEKA (SCORE COMPONENTS & REAL GRADES)
            $components = [
                ['code' => 'TP-1', 'title' => 'Tugas Metode Ilmiah & Praktikum', 'type' => 'formatif', 'weight' => 1.0],
                ['code' => 'TP-2', 'title' => 'Portofolio Ekosistem & Ekologi', 'type' => 'formatif', 'weight' => 1.0],
                ['code' => 'TP-3', 'title' => 'Kuis Zat & Perubahannya', 'type' => 'formatif', 'weight' => 1.0],
                ['code' => 'TP-4', 'title' => 'Proyek Energi Terbarukan', 'type' => 'formatif', 'weight' => 1.0],
                ['code' => 'STS',  'title' => 'Sumatif Tengah Semester (STS)', 'type' => 'sumatif_materi', 'weight' => 2.0],
                ['code' => 'SAS',  'title' => 'Sumatif Akhir Semester (SAS)', 'type' => 'sumatif_akhir', 'weight' => 2.0],
            ];

            $createdComponents = [];
            foreach ($components as $c) {
                $comp = ScoreComponent::updateOrCreate(
                    [
                        'course_id' => $course->id,
                        'code'      => $c['code'],
                    ],
                    [
                        'title'  => $c['title'],
                        'type'   => $c['type'],
                        'weight' => $c['weight'],
                    ]
                );
                $createdComponents[$c['code']] = $comp;
            }

            // Beri nilai formatif riil (85 s/d 95) untuk seluruh 10 siswa
            $baseScores = [92, 88, 85, 95, 87, 90, 86, 94, 89, 91];
            foreach ($createdStudents as $idx => $st) {
                $base = $baseScores[$idx % count($baseScores)];

                // Nilai TP-1
                StudentGrade::updateOrCreate(
                    ['student_id' => $st->id, 'score_component_id' => $createdComponents['TP-1']->id],
                    ['score' => $base, 'feedback' => 'Pemahaman metode ilmiah dan penalaran sangat baik.']
                );
                // Nilai TP-2
                StudentGrade::updateOrCreate(
                    ['student_id' => $st->id, 'score_component_id' => $createdComponents['TP-2']->id],
                    ['score' => max(78, $base - 2), 'feedback' => 'Analisis komponen ekosistem lengkap dan akurat.']
                );
                // Nilai TP-3
                StudentGrade::updateOrCreate(
                    ['student_id' => $st->id, 'score_component_id' => $createdComponents['TP-3']->id],
                    ['score' => min(98, $base + 3), 'feedback' => 'Karakterisasi zat dan reaksi kimia dikuasai dengan tuntas.']
                );
                // Nilai TP-4
                StudentGrade::updateOrCreate(
                    ['student_id' => $st->id, 'score_component_id' => $createdComponents['TP-4']->id],
                    ['score' => $base, 'feedback' => 'Kreativitas desain model energi terbarukan sangat memuaskan.']
                );
            }

            echo "Buku Nilai Kurikulum Merdeka (TP 1-4) terisi dengan capaian KKTP tuntas.\n";

            DB::commit();
            echo "SELESAI! Seluruh data riil MAN 11 Jakarta berhasil diinjeksi.\n";
        } catch (\Exception $e) {
            DB::rollBack();
            echo "Gagal: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}
