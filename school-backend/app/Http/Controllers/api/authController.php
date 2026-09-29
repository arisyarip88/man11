<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:users|alpha_dash',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'username' => $request->username,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role_id'  => 3, // Role default: User
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => $user->load('role'),
            'token' => $token
        ], 201);
    }

    public function login(Request $request)
    {
        $loginInput = $request->input('login') ?? $request->input('username') ?? $request->input('email');

        if (! $loginInput) {
            throw ValidationException::withMessages([
                'email' => ['Email, username, atau NIP/NISN wajib diisi.'],
            ]);
        }

        $request->validate([
            'password' => 'required',
        ]);

        $user = User::with('role')
            ->where('email', $loginInput)
            ->orWhere('username', $loginInput)
            ->first();

        // Jika tidak ditemukan, coba cari lewat relasi NIP Guru atau NIS Siswa
        if (! $user) {
            $teacher = \App\Models\Teacher::where('nip', $loginInput)->first();
            if ($teacher) {
                $user = User::with('role')->where('username', $teacher->nip)->orWhere('email', $teacher->email)->first();
            }
        }

        if (! $user) {
            $student = \App\Models\Student::where('nis', $loginInput)->orWhere('nisn', $loginInput)->first();
            if ($student) {
                $user = User::with('role')->where('username', $student->nis)->orWhere('email', $student->email)->first();
            }
        }

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial yang diberikan tidak cocok.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => $user->load(['role', 'teacher.homeroomClasses.className', 'student']),
            'token' => $token
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load(['role', 'teacher.homeroomClasses.className', 'student']));
    }
}