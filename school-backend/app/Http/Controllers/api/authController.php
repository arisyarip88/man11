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

        $isStudent = ((int) $user->role_id === 3) || (strtolower($user->role?->name ?? '') === 'student') || ($user->student()->exists());

        // A1: Aturan Single Device & Ujian Aktif Siswa
        if ($isStudent) {
            $student = $user->student;
            if ($student) {
                $hasActiveExam = \App\Models\QuizSubmission::where('student_id', $student->id)
                    ->whereNotNull('started_at')
                    ->whereNull('submitted_at')
                    ->exists();

                if ($hasActiveExam) {
                    return response()->json([
                        'message' => 'Anda sedang memiliki sesi ujian aktif yang belum selesai. Anda tidak dapat login di perangkat lain saat ujian berlangsung.',
                        'error_code' => 'exam_in_progress',
                    ], 409);
                }
            }

            // Jika tidak sedang ujian, batalkan token lama agar hanya aktif di satu perangkat
            $user->tokens()->delete();
            \App\Models\UserSession::where('user_id', $user->id)->update(['is_active' => false]);
        }

        $tokenInstance = $user->createToken('auth_token');
        $token = $tokenInstance->plainTextToken;

        // A2: Simpan info perangkat di user_sessions
        $userAgent = $request->userAgent();
        \App\Models\UserSession::create([
            'user_id' => $user->id,
            'token_id' => $tokenInstance->accessToken->id,
            'device_name' => $this->parseDeviceName($userAgent),
            'user_agent' => $userAgent,
            'ip_address' => $request->ip(),
            'last_active_at' => now(),
            'is_active' => true,
        ]);

        return response()->json([
            'user'  => $user->load(['role', 'teacher.homeroomClasses.className', 'student']),
            'token' => $token
        ]);
    }

    public function logout(Request $request)
    {
        $currentAccessToken = $request->user()->currentAccessToken();
        if ($currentAccessToken) {
            \App\Models\UserSession::where('user_id', $request->user()->id)
                ->where('token_id', $currentAccessToken->id)
                ->update(['is_active' => false]);

            $currentAccessToken->delete();
        }

        return response()->json(['message' => 'Berhasil logout']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load(['role', 'teacher.homeroomClasses.className', 'student']));
    }

    /**
     * A4: Admin endpoint untuk reset sesi user/siswa jika perangkat rusak atau hilang.
     */
    public function resetSessions(Request $request, $id)
    {
        $targetUser = User::findOrFail($id);
        $targetUser->tokens()->delete();
        \App\Models\UserSession::where('user_id', $targetUser->id)->update(['is_active' => false]);

        return response()->json([
            'message' => "Sesi perangkat untuk akun {$targetUser->username} berhasil direset.",
            'user_id' => $targetUser->id,
        ]);
    }

    /**
     * Helper sederhana penamaan perangkat dari User-Agent
     */
    private function parseDeviceName(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'Perangkat Tidak Dikenal';
        }

        $platform = 'Desktop';
        if (preg_match('/(android)/i', $userAgent)) {
            $platform = 'Android';
        } elseif (preg_match('/(iphone|ipad|ipod)/i', $userAgent)) {
            $platform = 'iOS';
        } elseif (preg_match('/(windows)/i', $userAgent)) {
            $platform = 'Windows';
        } elseif (preg_match('/(macintosh|mac os x)/i', $userAgent)) {
            $platform = 'macOS';
        } elseif (preg_match('/(linux)/i', $userAgent)) {
            $platform = 'Linux';
        }

        $browser = 'Browser';
        if (preg_match('/edg/i', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/chrome/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/safari/i', $userAgent) && ! preg_match('/chrome/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/firefox/i', $userAgent)) {
            $browser = 'Firefox';
        }

        return "{$browser} ({$platform})";
    }
}