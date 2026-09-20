<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use Carbon\Carbon;
class RoleSeeder extends Seeder

{
    public function run(): void
    {
        $roles = [
            [
                'id' => 1,
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Akses penuh ke seluruh fitur sistem',
            ],
            [
                'id' => 2,
                'name' => 'teacher',
                'display_name' => 'Guru',
                'description' => 'Mengelola kelas, materi, kuis, dan nilai',
            ],
            [
                'id' => 3,
                'name' => 'student',
                'display_name' => 'Siswa',
                'description' => 'Mengakses materi, kuis, dan forum diskusi',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['id' => $role['id']], $role);
        }
    }
}