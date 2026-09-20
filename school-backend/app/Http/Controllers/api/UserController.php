<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;


class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::with('role','student','teacher');

        // Filter berdasarkan Role ID jika ada
        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        // Pencarian berdasarkan username atau email
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate($request->get('per_page', 10));

        return response()->json($users, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'role_id'  => 'required|exists:roles,id',
            'username' => 'required|string|max:20',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'role_id'  => $validated['role_id'],
            'username' => $validated['username'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'User berhasil dibuat',
            'data'    => $user->load('role')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $user = User::with('role')->find($id);

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        return response()->json(['data' => $user], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'role_id'  => 'sometimes|required|exists:roles,id',
            'username' => 'sometimes|required|string|max:20',
            'email'    => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
        ]);

        if (array_key_exists('password', $validated) && !empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Data user berhasil diperbarui',
            'data'    => $user->load('role')
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        $user->delete();

        return response()->json(['message' => 'User berhasil dihapus'], 200);
    }

   public function enrolBatch()
    {
        DB::beginTransaction();
        try {
            // 1. Ambil Role ID dengan pencarian nama baku / Indonesia
            $teacherRole = Role::where('name', 'like', '%teacher%')
                ->orWhere('name', 'like', '%guru%')
                ->first();
                
            $studentRole = Role::where('name', 'like', '%student%')
                ->orWhere('name', 'like', '%siswa%')
                ->orWhere('name', 'like', '%murid%')
                ->first();

            $teacherRoleId = $teacherRole ? $teacherRole->id : 2;
            $studentRoleId = $studentRole ? $studentRole->id : 3;

            // 2. Ambil data username & email eksis di tabel users
            $existingEmails = User::whereNotNull('email')->pluck('email')->toArray();
            $existingUsernames = User::whereNotNull('username')->pluck('username')->toArray();

            $usersToInsert = [];
            $now = now();

            // Helper kecil untuk ekstrak angka dari tanggal lahir
            $getCleanDob = function ($entity) {
                // Cek berbagai variasi nama kolom tanggal lahir
                $dob = $entity->birth_date 
                    ?? $entity->date_of_birth 
                    ?? $entity->tanggal_lahir 
                    ?? $entity->dob 
                    ?? '20000101';

                // Ambil angka saja (menghilangkan strip, spasi, atau karakter lain)
                $clean = preg_replace('/[^0-9]/', '', (string)$dob);
                return !empty($clean) ? $clean : '20000101';
            };

            // 3. Proses Data Teachers
            $teachers = Teacher::all();
            foreach ($teachers as $teacher) {
                $username = trim($teacher->nip ?? '');
                $email = trim($teacher->email ?? '');

                if (!$username || !$email) continue;

                if (!in_array($email, $existingEmails) && !in_array($username, $existingUsernames)) {
                    $cleanDob = $getCleanDob($teacher);
                    $rawPassword = $username . '#' . $cleanDob;

                    $usersToInsert[] = [
                        'role_id'    => $teacherRoleId,
                        'username'   => $username,
                        'email'      => $email,
                        'password'   => Hash::make($rawPassword),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $existingEmails[] = $email;
                    $existingUsernames[] = $username;
                }
            }

            // 4. Proses Data Students
            $students = Student::all();
            foreach ($students as $student) {
                $username = trim($student->nis ?? '');
                $email = trim($student->email ?? '');

                if (!$username || !$email) continue;

                if (!in_array($email, $existingEmails) && !in_array($username, $existingUsernames)) {
                    $cleanDob = $getCleanDob($student);
                    $rawPassword = $username . '#' . $cleanDob;

                    $usersToInsert[] = [
                        'role_id'    => $studentRoleId,
                        'username'   => $username,
                        'email'      => $email,
                        'password'   => Hash::make($rawPassword),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $existingEmails[] = $email;
                    $existingUsernames[] = $username;
                }
            }

            // 5. Bulk Insert dengan Chunking (Cegah error memory jika data ribuan)
            if (!empty($usersToInsert)) {
                foreach (array_chunk($usersToInsert, 200) as $chunk) {
                    User::insert($chunk);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Enrole user berhasil!',
                'total_enroled' => count($usersToInsert)
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal meng-enrole user',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine()
            ], 500);
        }
    }
}