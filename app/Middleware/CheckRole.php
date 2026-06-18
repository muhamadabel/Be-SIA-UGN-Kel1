<?php

namespace App\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * @param  array<int, string>  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $roles = $this->normalizeRoles($roles);

        // Prioritaskan Sanctum untuk request API.
        $user = auth('sanctum')->user() ?? $request->user();

        if (!$user) {
            abort(401, 'Anda belum masuk atau sesi Anda telah berakhir.');
        }

        if ($roles === []) {
            return $next($request);
        }

        if (!$this->userHasAnyRole($user, $roles)) {
            abort(403, 'Anda tidak memiliki izin untuk mengakses sumber daya ini.');
        }

        return $next($request);
    }

    /**
     * @param  mixed  $user
     * @param  array<int, string>  $roles
     */
    private function userHasAnyRole($user, array $roles): bool
    {
        $userRole = is_object($user) ? (string) ($user->role ?? '') : '';

        // Utamakan kolom `role` karena dipakai luas di codebase.
        if ($userRole !== '' && in_array($userRole, $roles, true)) {
            return true;
        }

        // Kompatibel dengan Spatie\Permission (HasRoles trait)
        if (is_object($user) && method_exists($user, 'hasAnyRole')) {
            try {
                return (bool) $user->hasAnyRole($roles);
            } catch (\Throwable $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    private function normalizeRoles(array $roles): array
    {
        $normalized = [];

        foreach ($roles as $roleChunk) {
            foreach (preg_split('/[|,]/', $roleChunk) ?: [] as $role) {
                $role = trim($role);
                if ($role !== '') {
                    $normalized[] = $role;
                }
            }
        }

        return array_values(array_unique($normalized));
    }
}
