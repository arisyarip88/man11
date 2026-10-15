<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\ClassModel;
use App\Models\Subject;
use App\Models\Course;

class E2EAccountsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pastikan Role Terdaftar
        $adminRole = Role::firstOrCreate(['id' => 1], ['name' => 'admin', 'display_name' => 'Administrator']);
        $teacherRole = Role::firstOrCreate(['id' => 2], ['name' => 'teacher', 'display_name' => 'Guru']);
        $studentRole = Role::firstOrCreate(['id' => 3], ['name' => 'student', 'display_name' => 'Siswa']);

        // 2. AKUN ADMIN
        // | Admin | admin.e2e@sekolah.sch.id | AdminE2E@2026! | /admin/dashboard
        $adminUser = User::updateOrCreate(
            ['email' => 'admin.e2e@sekolah.sch.id'],
            [
                'name'     => 'Administrator E2E',
                'username' => 'admin.e2e',
                'password' => Hash::make('AdminE2E@2026!'),
                'role_id'  => $adminRole->id,
            ]
        );

        // 3. AKUN GURU
        // | Guru | guru.e2e@sekolah.sch.id | GuruE2E@2026! | /teacher/dashboard
        $teacherNip = '198501012026011001';
        $teacher = Teacher::updateOrCreate(
            ['email' => 'guru.e2e@sekolah.sch.id'],
            [
                'nip'       => $teacherNip,
                'nik'       => '3174000000000001',
                'name'      => 'Drs. H. Ahmad Fauzi, M.Pd.',
                'gender'    => 'male',
                'phone'     => '081234567890',
                'address'   => 'Jl. Madrasah No. 11, Jakarta Selatan',
                'is_active' => true,
            ]
        );

        $teacherUser = User::updateOrCreate(
            ['email' => 'guru.e2e@sekolah.sch.id'],
            [
                'name'     => $teacher->name,
                'username' => $teacherNip,
                'password' => Hash::make('GuruE2E@2026!'),
                'role_id'  => $teacherRole->id,
            ]
        );

        // Hubungkan guru ini ke setidaknya 1 rombel & course
        $class = ClassModel::first();
        if ($class) {
            $class->update(['homeroom_teacher_id' => $teacher->id]);

            $subject = Subject::first();
            if ($subject) {
                Course::firstOrCreate(
                    [
                        'teacher_id' => $teacher->id,
                        'class_id'   => $class->id,
                        'subject_id' => $subject->id,
                    ],
                    [
                        'credit_hours' => 2,
                    ]
                );
            }
        }

        // 4. AKUN SISWA
        // | Siswa | siswa.e2e@sekolah.sch.id | SiswaE2E@2026! | /student/dashboard
        $studentNis = '2026000002';
        $student = Student::updateOrCreate(
            ['email' => 'siswa.e2e@sekolah.sch.id'],
            [
                'nis'         => $studentNis,
                'nisn'        => '0081234501',
                'name'        => 'Muhammad Fadhil Rahman',
                'gender'      => 'male',
                'birth_place' => 'Jakarta',
                'birth_date'  => '2008-04-12',
                'phone'       => '081298765401',
                'address'     => 'Jl. Madrasah No. 11, Cilandak, Jakarta Selatan',
            ]
        );

        $studentUser = User::updateOrCreate(
            ['email' => 'siswa.e2e@sekolah.sch.id'],
            [
                'name'     => $student->name,
                'username' => $studentNis,
                'password' => Hash::make('SiswaE2E@2026!'),
                'role_id'  => $studentRole->id,
            ]
        );

        // Daftarkan siswa ke rombel kelas yang ada
        if ($class) {
            $class->students()->syncWithoutDetaching([$student->id]);
        }

        echo "Berhasil membuat/memperbarui 3 Akun E2E:\n";
        echo "- Admin: admin.e2e@sekolah.sch.id (Password: AdminE2E@2026!)\n";
        echo "- Guru:  guru.e2e@sekolah.sch.id (Password: GuruE2E@2026!)\n";
        echo "- Siswa: siswa.e2e@sekolah.sch.id (Password: SiswaE2E@2026!)\n";
    }
}
