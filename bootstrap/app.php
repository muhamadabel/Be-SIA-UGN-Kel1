<?php

use App\Middleware\CheckRole;
use App\Middleware\VerifyIntegrationToken;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up'
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn (Request $request) => null);

        $middleware->alias([
            'role' => CheckRole::class,
            'integration.token' => VerifyIntegrationToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            $errors = $e->errors();

            $allMessages = collect($errors)->flatten()->values();
            $firstMessage = (string) ($allMessages->first() ?? '');
            $remainingCount = max(0, $allMessages->count() - 1);

            if ($firstMessage === '') {
                $message = 'Validasi gagal';
            } elseif ($remainingCount > 0) {
                $message = $firstMessage.', dan '.$remainingCount.' kesalahan lainnya';
            } else {
                $message = $firstMessage;
            }

            return response()->json([
                'status' => 'error',
                'message' => $message,
                'errors' => $errors,
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'status' => 'unauthenticated',
                'message' => 'Anda belum login atau sesi Anda telah berakhir',
            ], 401);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            $model = $e->getModel();
            $baseName = $model ? class_basename($model) : null;

            $message = match ($baseName) {
                'AcademicPeriod' => 'Periode akademik tidak ditemukan',
                'Announcement' => 'Pengumuman tidak ditemukan',
                'AttendaceSession' => 'Sesi kehadiran tidak ditemukan',
                'ChatConversation' => 'Percakapan tidak ditemukan',
                'ChatMessage' => 'Pesan tidak ditemukan',
                'Classes' => 'Kelas tidak ditemukan',
                'DeviceToken' => 'Token perangkat tidak ditemukan',
                'GradeConversion' => 'Konversi nilai tidak ditemukan',
                'Grades' => 'Nilai tidak ditemukan',
                'Notification' => 'Notifikasi tidak ditemukan',
                'Presence' => 'Presensi tidak ditemukan',
                'Programs' => 'Program studi tidak ditemukan',
                'Schedule' => 'Jadwal tidak ditemukan',
                'StaffProfile' => 'Profil staf tidak ditemukan',
                'StudentProfile' => 'Profil mahasiswa tidak ditemukan',
                'Subject' => 'Mata pelajaran tidak ditemukan',
                'User_Si' => 'User tidak ditemukan',
                default => 'Data tidak ditemukan',
            };

            return response()->json([
                'status' => 'error',
                'message' => $message,
            ], 404);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $statusCode = $e->getStatusCode();

            $rawMessage = (string) $e->getMessage();
            $message = $rawMessage;

            if ($statusCode === 404 && $rawMessage !== '') {
                if (preg_match('/^No query results for model \[([^\]]+)\]\s*(.*)$/', $rawMessage, $matches) === 1) {
                    $modelClass = $matches[1] ?? null;
                    $baseName = $modelClass ? class_basename($modelClass) : null;

                    $message = match ($baseName) {
                        'AcademicPeriod' => 'Periode akademik tidak ditemukan',
                        'Announcement' => 'Pengumuman tidak ditemukan',
                        'AttendaceSession' => 'Sesi kehadiran tidak ditemukan',
                        'ChatConversation' => 'Percakapan tidak ditemukan',
                        'ChatMessage' => 'Pesan tidak ditemukan',
                        'Classes' => 'Kelas tidak ditemukan',
                        'DeviceToken' => 'Token perangkat tidak ditemukan',
                        'GradeConversion' => 'Konversi nilai tidak ditemukan',
                        'Grades' => 'Nilai tidak ditemukan',
                        'Notification' => 'Notifikasi tidak ditemukan',
                        'Presence' => 'Presensi tidak ditemukan',
                        'Programs' => 'Program studi tidak ditemukan',
                        'Schedule' => 'Jadwal tidak ditemukan',
                        'StaffProfile' => 'Profil staf tidak ditemukan',
                        'StudentProfile' => 'Profil mahasiswa tidak ditemukan',
                        'Subject' => 'Mata pelajaran tidak ditemukan',
                        'User_Si' => 'User tidak ditemukan',
                        default => 'Data tidak ditemukan',
                    };
                }
            }

            return response()->json([
                'status' => $statusCode === 401 ? 'unauthenticated' : ($statusCode === 403 ? 'forbidden' : 'error'),
                'message' => $message !== '' ? $message : 'Terjadi kesalahan',
            ], $statusCode);
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }

            $debug = (bool) config('app.debug');

            return response()->json([
                'status' => 'error',
                'message' => $debug ? $e->getMessage() : 'Terjadi kesalahan pada server',
            ], 500);
        });
    })->create();
