<?php

namespace Tests\Feature;

use App\Models\ClassName;
use App\Models\ClassModel;
use App\Models\Quiz;
use App\Models\QuizSubmission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StudentSingleDeviceAndImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pastikan role student & admin ada
        Role::firstOrCreate(['id' => 1], ['name' => 'admin', 'display_name' => 'Administrator']);
        Role::firstOrCreate(['id' => 3], ['name' => 'student', 'display_name' => 'Siswa']);

        // Bersihkan data dummy test jika ada sisa dari run sebelumnya
        $testUsernames = ['test_nis_001', 'test_nis_exam', 'test_nis_reset', '99990001', 'admin_test', 'admin_test_import'];
        User::whereIn('username', $testUsernames)->delete();
        Student::whereIn('nis', $testUsernames)->delete();
    }

    public function test_student_single_device_login_revokes_previous_token_with_session_replaced(): void
    {
        // 1. Buat user siswa
        $studentUser = User::create([
            'name' => 'Siswa Device Test',
            'username' => 'test_nis_001',
            'email' => 'siswa001@example.com',
            'password' => bcrypt('password123'),
            'role_id' => 3,
        ]);

        Student::create([
            'nis' => 'test_nis_001',
            'nisn' => '0099887761',
            'name' => 'Siswa Device Test',
            'gender' => 'male',
            'email' => 'siswa001@example.com',
            'status' => 'active',
        ]);

        // Login Device 1
        $login1 = $this->postJson('/api/login', [
            'login' => 'test_nis_001',
            'password' => 'password123',
        ], ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0']);

        $login1->assertStatus(200);
        $token1 = $login1->json('token');
        $this->assertNotEmpty($token1);

        // Verifikasi token 1 bisa dipakai
        $me1 = $this->withHeader('Authorization', 'Bearer ' . $token1)->getJson('/api/me');
        $me1->assertStatus(200);

        // Login Device 2
        $login2 = $this->postJson('/api/login', [
            'login' => 'test_nis_001',
            'password' => 'password123',
        ], ['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari/604.1']);

        $login2->assertStatus(200);
        $token2 = $login2->json('token');
        $this->assertNotEquals($token1, $token2);

        // Device 2 bisa akses
        $me2 = $this->withHeader('Authorization', 'Bearer ' . $token2)->getJson('/api/me');
        $me2->assertStatus(200);

        // Reset memory guard state di testing environment
        $this->app['auth']->forgetGuards();

        // Device 1 sekarang harus ditolak dengan error_code session_replaced
        $meOld = $this->withHeader('Authorization', 'Bearer ' . $token1)->getJson('/api/me');
        $meOld->assertStatus(401);
        $meOld->assertJson([
            'error_code' => 'session_replaced'
        ]);

        // Cleanup
        $studentUser->delete();
        Student::where('nis', 'test_nis_001')->delete();
    }

    public function test_student_login_blocked_if_active_exam_in_progress(): void
    {
        $studentUser = User::create([
            'name' => 'Siswa Exam Test',
            'username' => 'test_nis_exam',
            'email' => 'exam_student@example.com',
            'password' => bcrypt('password123'),
            'role_id' => 3,
        ]);

        $student = Student::create([
            'nis' => 'test_nis_exam',
            'nisn' => '0099887762',
            'name' => 'Siswa Exam Test',
            'gender' => 'female',
            'email' => 'exam_student@example.com',
            'status' => 'active',
        ]);

        // Buat quiz dummy dan submission yang belum disubmit (sedang berjalan)
        $meet = \App\Models\Meet::first();
        $quiz = Quiz::create([
            'meet_id' => $meet ? $meet->id : 1,
            'title' => 'Ujian Aktif Test',
            'token' => 'EXAM99',
            'duration' => 60,
        ]);

        $submission = QuizSubmission::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'started_at' => now()->subMinutes(10),
            'submitted_at' => null, // SEDANG BERJALAN!
        ]);

        // Coba login
        $response = $this->postJson('/api/login', [
            'login' => 'test_nis_exam',
            'password' => 'password123',
        ]);

        $response->assertStatus(409);
        $response->assertJson([
            'error_code' => 'exam_in_progress'
        ]);

        // Selesaikan ujian
        $submission->update(['submitted_at' => now()]);

        // Sekarang login harus diperbolehkan
        $responseAfter = $this->postJson('/api/login', [
            'login' => 'test_nis_exam',
            'password' => 'password123',
        ]);
        $responseAfter->assertStatus(200);

        // Cleanup
        $submission->delete();
        $quiz->delete();
        $studentUser->delete();
        $student->delete();
    }

    public function test_admin_can_reset_student_session(): void
    {
        $admin = User::firstOrCreate(
            ['username' => 'admin_test'],
            ['name' => 'Admin Test', 'email' => 'admin_test@example.com', 'password' => bcrypt('password'), 'role_id' => 1]
        );

        $studentUser = User::create([
            'name' => 'Siswa Reset Test',
            'username' => 'test_nis_reset',
            'email' => 'reset_test@example.com',
            'password' => bcrypt('password123'),
            'role_id' => 3,
        ]);

        $student = Student::create([
            'nis' => 'test_nis_reset',
            'nisn' => '0099887763',
            'name' => 'Siswa Reset Test',
            'gender' => 'male',
            'email' => 'reset_test@example.com',
            'status' => 'active',
        ]);

        $tokenInstance = $studentUser->createToken('test_token');
        $token = $tokenInstance->plainTextToken;

        $adminToken = $admin->createToken('admin_token')->plainTextToken;

        // Reset sesi via endpoint student
        $res = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/students/{$student->id}/reset-session");

        $res->assertStatus(200);
        $this->assertEquals(0, $studentUser->tokens()->count());

        $this->app['auth']->forgetGuards();

        // Token lama sekarang ditolak
        $meOld = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/me');
        $meOld->assertStatus(401);

        // Cleanup
        $studentUser->delete();
        $student->delete();
    }

    public function test_download_student_template_and_import_flow(): void
    {
        $admin = User::firstOrCreate(
            ['username' => 'admin_test_import'],
            ['name' => 'Admin Test Import', 'email' => 'admin_test_import@example.com', 'password' => bcrypt('password'), 'role_id' => 1]
        );
        $adminToken = $admin->createToken('admin_token')->plainTextToken;

        // 1. Download template
        $templateRes = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->get('/api/students/import/template');
        $templateRes->assertStatus(200);
        $this->assertStringContainsString('spreadsheet', $templateRes->headers->get('Content-Type'));

        // 2. Buat file Excel sederhana di memory untuk ditest
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'NIS');
        $sheet->setCellValue('B1', 'NISN');
        $sheet->setCellValue('C1', 'Nama Lengkap');
        $sheet->setCellValue('D1', 'Jenis Kelamin');
        $sheet->setCellValue('E1', 'Tempat Lahir');
        $sheet->setCellValue('F1', 'Tanggal Lahir');
        $sheet->setCellValue('G1', 'Email');
        $sheet->setCellValue('H1', 'No HP');
        $sheet->setCellValue('I1', 'Alamat');
        $sheet->setCellValue('J1', 'Kelas');

        // Baris 2: Siswa Valid 1
        $sheet->setCellValue('A2', '99990001');
        $sheet->setCellValue('B2', '0011223344');
        $sheet->setCellValue('C2', 'Siswa Test Import Satu');
        $sheet->setCellValue('D2', 'L');
        $sheet->setCellValue('E2', 'Jakarta');
        $sheet->setCellValue('F2', '2008-01-15');
        $sheet->setCellValue('G2', 'siswa1_test_import@example.com');
        $sheet->setCellValue('H2', '081234567890');
        $sheet->setCellValue('I2', 'Jl. Test No. 1');
        $sheet->setCellValue('J2', '');

        // Baris 3: Siswa Error (NIS kosong)
        $sheet->setCellValue('A3', '');
        $sheet->setCellValue('B3', '0011223345');
        $sheet->setCellValue('C3', 'Siswa Tanpa NIS');
        $sheet->setCellValue('D3', 'P');

        $writer = new Xlsx($spreadsheet);
        $tempFilePath = tempnam(sys_get_temp_dir(), 'test_import_') . '.xlsx';
        $writer->save($tempFilePath);

        $uploadedFile = new UploadedFile(
            $tempFilePath,
            'import_test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        // 3. Test Preview=1 (Tanpa simpan)
        $previewRes = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->post('/api/students/import?preview=1', [
                'file' => $uploadedFile,
            ]);

        $previewRes->assertStatus(200);
        $previewRes->assertJsonPath('summary.total', 2);
        $previewRes->assertJsonPath('summary.valid', 1);
        $previewRes->assertJsonPath('summary.errors', 1);

        // Pastikan belum ada di DB
        $this->assertDatabaseMissing('students', ['nis' => '99990001']);

        // 4. Test Eksekusi Import Sebenarnya
        $execFile = new UploadedFile(
            $tempFilePath,
            'import_test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $importRes = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->post('/api/students/import', [
                'file' => $execFile,
            ]);

        $importRes->assertStatus(200);
        $importRes->assertJsonPath('summary.created', 1);

        // Verifikasi tersimpan di DB
        $this->assertDatabaseHas('students', [
            'nis' => '99990001',
            'name' => 'Siswa Test Import Satu',
            'gender' => 'male',
        ]);

        // Verifikasi akun User terbuat otomatis
        $this->assertDatabaseHas('users', [
            'username' => '99990001',
            'role_id' => 3,
        ]);

        // 5. Test Idempoten: Import ulang file yang sama tidak menimbulkan error, melainkan update
        $reimportFile = new UploadedFile(
            $tempFilePath,
            'import_test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $reimportRes = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->post('/api/students/import', [
                'file' => $reimportFile,
            ]);

        $reimportRes->assertStatus(200);
        $reimportRes->assertJsonPath('summary.updated', 1);

        // Cleanup
        @unlink($tempFilePath);
        Student::where('nis', '99990001')->delete();
        User::where('username', '99990001')->delete();
    }
}
