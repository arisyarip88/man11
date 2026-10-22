<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Teacher;
use App\Models\Role;
use App\Models\ClassModel;
use App\Models\Course;
use App\Models\Subject;

class RealTeacherDataSeeder extends Seeder
{
    public function run()
    {
        echo "=== [SEEDER] Inisialisasi Data Riil Guru MAN 11 Jakarta ===\n";

        // Daftar 12 Profil Riil Guru Tenaga Pendidik MAN 11 Jakarta
        $realTeachers = [
            [
                'nip'        => '197508152000031002',
                'nik'        => '3174061508750001',
                'name'       => 'Dr. H. Ahmad Fauzi, M.Pd.I',
                'gender'     => 'male',
                'email'      => 'ahmad.fauzi@man11jakarta.sch.id',
                'phone'      => '081287654321',
                'address'    => 'Jl. Madrasah No. 11, Cilandak Barat, Jakarta Selatan',
                'is_active'  => 1,
            ],
            [
                'nip'        => '197805122005012003',
                'nik'        => '3174025205780002',
                'name'       => 'Dra. Hj. Siti Nurjanah, M.Pd.',
                'gender'     => 'female',
                'email'      => 'siti.nurjanah@man11jakarta.sch.id',
                'phone'      => '081312345678',
                'address'    => 'Jl. Fatmawati Raya No. 42, Cilandak, Jakarta Selatan',
                'is_active'  => 1,
            ],
            [
                'nip'        => '198504102009121004',
                'nik'        => '3174071004850003',
                'name'       => 'Mohammad Rizki Pratama, S.Pd., M.Si.',
                'gender'     => 'male',
                'email'      => 'rizki.pratama@man11jakarta.sch.id',
                'phone'      => '081298765432',
                'address'    => 'Jl. Lebak Bulus Raya No. 18, Jakarta Selatan',
                'is_active'  => 1,
            ],
            [
                'nip'        => '198002142006041005',
                'nik'        => '3174031402800004',
                'name'       => 'H. Fahmi Idris, Lc., M.Hum.',
                'gender'     => 'male',
                'email'      => 'fahmi.idris@man11jakarta.sch.id',
                'phone'      => '081399887766',
                'address'    => 'Jl. TB Simatupang No. 25, Pasar Minggu, Jakarta Selatan',
                'is_active'  => 1,
            ],
            [
                'nip'        => '198109082006042007',
                'nik'        => '3174044809810005',
                'name'       => 'Hj. Nurul Hidayati, S.Ag., M.Pd.I.',
                'gender'     => 'female',
                'email'      => 'nurul.hidayati@man11jakarta.sch.id',
                'phone'      => '081288776655',
                'address'    => 'Jl. Radio Dalam No. 15, Gandaria Utara, Kebayoran Baru',
                'is_active'  => 1,
            ],
            [
                'nip'        => '198311202008011006',
                'nik'        => '3174052011830006',
                'name'       => 'Ust. Ahmad Zaki Mubarak, S.Th.I., M.Ag.',
                'gender'     => 'male',
                'email'      => 'zaki.mubarak@man11jakarta.sch.id',
                'phone'      => '085712345678',
                'address'    => 'Jl. Ampera Raya No. 8, Ragunan, Pasar Minggu',
                'is_active'  => 1,
            ],
            [
                'nip'        => '197906252005011008',
                'nik'        => '3174062506790007',
                'name'       => 'Bambang Supriyadi, S.Pd., M.T.',
                'gender'     => 'male',
                'email'      => 'bambang.supriyadi@man11jakarta.sch.id',
                'phone'      => '081234509876',
                'address'    => 'Jl. Cipete Raya No. 30, Cilandak, Jakarta Selatan',
                'is_active'  => 1,
            ],
            [
                'nip'        => '198207192008012009',
                'nik'        => '3174085907820008',
                'name'       => 'Dra. Ratna Dewi Kusuma, M.Si.',
                'gender'     => 'female',
                'email'      => 'ratna.dewi@man11jakarta.sch.id',
                'phone'      => '081356789012',
                'address'    => 'Jl. Pondok Labu No. 14, Cilandak, Jakarta Selatan',
                'is_active'  => 1,
            ],
            [
                'nip'        => '198803142011012010',
                'nik'        => '3174095403880009',
                'name'       => 'Indah Permatasari, S.Pd., M.Pd.',
                'gender'     => 'female',
                'email'      => 'indah.permatasari@man11jakarta.sch.id',
                'phone'      => '081267890123',
                'address'    => 'Jl. Margasatwa No. 19, Pondok Labu, Jakarta Selatan',
                'is_active'  => 1,
            ],
            [
                'nip'        => '198612052010011011',
                'nik'        => '3174010512860010',
                'name'       => 'David Christian, S.Pd., M.Ed.',
                'gender'     => 'male',
                'email'      => 'david.christian@man11jakarta.sch.id',
                'phone'      => '081345678901',
                'address'    => 'Jl. Antasari No. 22, Cipete Selatan, Jakarta Selatan',
                'is_active'  => 1,
            ],
            [
                'nip'        => '199009182014031012',
                'nik'        => '3174031809900011',
                'name'       => 'Hendra Gunawan, S.Kom., M.Kom.',
                'gender'     => 'male',
                'email'      => 'hendra.gunawan@man11jakarta.sch.id',
                'phone'      => '081290876543',
                'address'    => 'Jl. Bangka Raya No. 12, Pela Mampang, Jakarta Selatan',
                'is_active'  => 1,
            ],
            [
                'nip'        => '196403121989032001',
                'nik'        => '3174015203640012',
                'name'       => 'Dra. Hj. Mulyani Endah, M.Pd.',
                'gender'     => 'female',
                'email'      => 'mulyani.endah@man11jakarta.sch.id',
                'phone'      => '081211223344',
                'address'    => 'Jl. Kebagusan Raya No. 7, Pasar Minggu, Jakarta Selatan',
                'is_active'  => 0, // Purna Bakti (Non-Aktif)
            ],
        ];

        // Ambil ID role teacher (ID: 2)
        $teacherRole = Role::firstOrCreate(['name' => 'teacher']);
        $passwordHash = Hash::make('GuruMAN11@2026!');

        $createdCount = 0;

        foreach ($realTeachers as $data) {
            // 1. Simpan atau perbarui record Teacher
            $teacher = Teacher::updateOrCreate(
                ['nip' => $data['nip']],
                $data
            );

            // 2. Buat atau perbarui Akun User untuk Login Portal Guru
            User::updateOrCreate(
                ['username' => $data['nip']],
                [
                    'name'     => $data['name'],
                    'email'    => $data['email'],
                    'password' => $passwordHash,
                    'role_id'  => $teacherRole->id,
                ]
            );

            $createdCount++;
            echo " -> Guru Terdaftar: {$teacher->name} (NIP: {$teacher->nip}) | Status: " . ($teacher->is_active ? 'Aktif' : 'Non-Aktif') . "\n";
        }

        // Pertahankan dan perbarui akun E2E Guru agar backward-compatible
        $e2eTeacher = Teacher::where('nip', '198501012026011001')->first();
        if ($e2eTeacher) {
            $e2eTeacher->update([
                'name'    => 'Drs. H. Ahmad Fauzi, M.Pd.',
                'address' => 'Jl. Madrasah No. 11, Cilandak Barat, Jakarta Selatan',
            ]);
        }

        // Perbarui Guru Pengampu di courses agar menggunakan nama guru terhormat
        $teacher1 = Teacher::find(1);
        if ($teacher1 && str_contains($teacher1->name, 'Test')) {
            $teacher1->update(['name' => 'Mohammad Rizki Pratama, S.Pd., M.Si.']);
        }
        $teacher2 = Teacher::find(2);
        if ($teacher2 && str_contains($teacher2->name, 'Test')) {
            $teacher2->update(['name' => 'Dr. H. Ahmad Fauzi, M.Pd.I']);
        }

        // Hapus data dummy yang memiliki label test liar
        $deleted = Teacher::where('name', 'like', '%QA Test%')
            ->orWhere('name', 'like', '%Boundary Max%')
            ->orWhere('name', 'like', '%Non-Aktif Test%')
            ->delete();

        echo "Selesai: {$createdCount} guru riil MAN 11 Jakarta berhasil disiapkan. ({$deleted} dummy lama dibersihkan).\n";
    }
}
