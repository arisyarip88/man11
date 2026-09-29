<?php

namespace Database\Seeders;

use App\Models\ClassModel;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClassEnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pastikan Rombel Kelas X IPA 1 (ID 1) ada
        $rombel = ClassModel::firstOrCreate(
            ['id' => 1],
            [
                'semester_id' => 1,
                'class_name_id' => 1,
                'homeroom_teacher_id' => 7,
                'capacity' => 36,
                'room_number' => 'R.101',
            ]
        );

        // 2. Data Siswa Default untuk Testing & Operasional LMS
        $studentsData = [
            [
                'nis' => '2026000001',
                'nisn' => '0071234561',
                'name' => 'Siswa Demo',
                'email' => 'siswa.demo@sekolah.sch.id',
                'gender' => 'male',
                'phone' => '081234567890',
                'address' => 'Jl. Madrasah No. 11, Jakarta Selatan',
                'is_active' => true,
            ],
            [
                'nis' => '2026000002',
                'nisn' => '0071234562',
                'name' => 'Siswa E2E',
                'email' => 'siswa.e2e@sekolah.sch.id',
                'gender' => 'female',
                'phone' => '081234567891',
                'address' => 'Jl. Madrasah No. 11, Jakarta Selatan',
                'is_active' => true,
            ],
            [
                'nis' => '123456',
                'nisn' => '0071234563',
                'name' => 'Joko Suwarno',
                'email' => 'joko@gmail.com',
                'gender' => 'male',
                'phone' => '081234567892',
                'address' => 'Jakarta Selatan',
                'is_active' => true,
            ],
        ];

        $studentIds = [];

        foreach ($studentsData as $data) {
            $student = Student::updateOrCreate(
                ['nis' => $data['nis']],
                $data
            );

            $studentIds[] = $student->id;

            // Pastikan Akun User (Auth) Terhubung dengan Role Siswa (role_id: 3)
            User::updateOrCreate(
                ['username' => $data['nis']],
                [
                    'email' => $data['email'],
                    'password' => Hash::make('password123'),
                    'role_id' => 3, // Role Siswa
                ]
            );
        }

        // Juga sertakan semua student yang ada di database ke Rombel ID 1
        $allStudentIds = Student::pluck('id')->toArray();
        $rombel->students()->syncWithoutDetaching($allStudentIds);

        $this->command->info("Berhasil mendaftarkan " . count($allStudentIds) . " siswa ke Rombel {$rombel->id} (" . ($rombel->className->name ?? 'X IPA 1') . ")");
    }
}
