<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     * Memvalidasi apakah user yang login memiliki peran (role) yang diizinkan.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'error_code' => 'unauthenticated',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $roleName = strtolower($user->role?->name ?? '');
        $roleId = (int) $user->role_id;

        $hasAccess = false;
        foreach ($roles as $role) {
            $r = strtolower(trim($role));
            if ($r === 'admin' && ($roleId === 1 || in_array($roleName, ['admin', 'super_admin']))) {
                $hasAccess = true;
                break;
            }
            if ($r === 'teacher' && ($roleId === 2 || in_array($roleName, ['teacher', 'guru']))) {
                $hasAccess = true;
                break;
            }
            if ($r === 'student' && ($roleId === 3 || in_array($roleName, ['student', 'siswa']))) {
                $hasAccess = true;
                break;
            }
            if ($roleName === $r) {
                $hasAccess = true;
                break;
            }
        }

        if (! $hasAccess) {
            return response()->json([
                'message' => 'Akses ditolak. Anda tidak memiliki izin untuk mengakses resource ini.',
                'error_code' => 'forbidden',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}

