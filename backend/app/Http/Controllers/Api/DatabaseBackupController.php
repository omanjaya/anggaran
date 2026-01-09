<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DatabaseBackupController extends Controller
{
    protected array $tables = [
        'skpd',
        'programs',
        'activities',
        'sub_activities',
        'account_codes',
        'budget_items',
        'budget_item_details',
        'monthly_plans',
        'monthly_realizations',
        'realization_documents',
        'approval_histories',
        'deviation_alerts',
        'operational_schedules',
    ];

    /**
     * List all backups
     */
    public function index(): JsonResponse
    {
        $backups = [];

        if (Storage::disk('local')->exists('backups')) {
            $files = Storage::disk('local')->files('backups');

            foreach ($files as $file) {
                $backups[] = [
                    'filename' => basename($file),
                    'path' => $file,
                    'size' => Storage::disk('local')->size($file),
                    'size_formatted' => $this->formatBytes(Storage::disk('local')->size($file)),
                    'created_at' => date('Y-m-d H:i:s', Storage::disk('local')->lastModified($file)),
                ];
            }

            // Sort by created_at descending
            usort($backups, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
        }

        return response()->json([
            'success' => true,
            'data' => $backups,
        ]);
    }

    /**
     * Create a new backup
     */
    public function create(Request $request): JsonResponse
    {
        try {
            $timestamp = now()->format('Y-m-d_His');
            $filename = "backup_{$timestamp}.sql";

            // Ensure backups directory exists
            if (!Storage::disk('local')->exists('backups')) {
                Storage::disk('local')->makeDirectory('backups');
            }

            $sql = $this->generateBackupSql();

            Storage::disk('local')->put("backups/{$filename}", $sql);

            // Log the backup (with null check for user)
            try {
                $userId = auth()->check() ? auth()->id() : null;
                DB::table('audit_logs')->insert([
                    'user_id' => $userId,
                    'action' => 'backup_created',
                    'model_type' => 'Database',
                    'model_id' => null,
                    'changes' => json_encode(['filename' => $filename]),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $logError) {
                Log::warning('Failed to log backup creation: ' . $logError->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Backup berhasil dibuat',
                'data' => [
                    'filename' => $filename,
                    'size' => Storage::disk('local')->size("backups/{$filename}"),
                    'size_formatted' => $this->formatBytes(Storage::disk('local')->size("backups/{$filename}")),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Backup creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat backup: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download a backup file
     */
    public function download(string $filename)
    {
        $path = "backups/{$filename}";

        if (!Storage::disk('local')->exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'File backup tidak ditemukan',
            ], 404);
        }

        return Storage::disk('local')->download($path, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }

    /**
     * Delete a backup file
     */
    public function destroy(string $filename): JsonResponse
    {
        $path = "backups/{$filename}";

        if (!Storage::disk('local')->exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'File backup tidak ditemukan',
            ], 404);
        }

        Storage::disk('local')->delete($path);

        return response()->json([
            'success' => true,
            'message' => 'Backup berhasil dihapus',
        ]);
    }

    /**
     * Get backup statistics
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_backups' => 0,
            'total_size' => 0,
            'total_size_formatted' => '0 B',
            'last_backup' => null,
            'database_stats' => [],
        ];

        if (Storage::disk('local')->exists('backups')) {
            $files = Storage::disk('local')->files('backups');
            $stats['total_backups'] = count($files);

            $totalSize = 0;
            $lastModified = 0;

            foreach ($files as $file) {
                $totalSize += Storage::disk('local')->size($file);
                $modified = Storage::disk('local')->lastModified($file);
                if ($modified > $lastModified) {
                    $lastModified = $modified;
                }
            }

            $stats['total_size'] = $totalSize;
            $stats['total_size_formatted'] = $this->formatBytes($totalSize);
            $stats['last_backup'] = $lastModified > 0 ? date('Y-m-d H:i:s', $lastModified) : null;
        }

        // Get database table stats
        foreach ($this->tables as $table) {
            try {
                $count = DB::table($table)->count();
                $stats['database_stats'][$table] = $count;
            } catch (\Exception $e) {
                $stats['database_stats'][$table] = 0;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Restore database from uploaded backup file
     */
    public function restore(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:sql,txt|max:51200', // Max 50MB
        ]);

        try {
            $file = $request->file('file');
            $sql = file_get_contents($file->getRealPath());

            // Verify it's a SIPERA backup
            if (!str_contains($sql, '-- SIPERA Database Backup')) {
                return response()->json([
                    'success' => false,
                    'message' => 'File bukan backup SIPERA yang valid',
                ], 422);
            }

            // Start transaction
            DB::beginTransaction();

            try {
                // Clear existing data in reverse order (to respect foreign keys)
                $reverseTables = array_reverse($this->tables);
                foreach ($reverseTables as $table) {
                    try {
                        DB::table($table)->delete();
                    } catch (\Exception $e) {
                        Log::warning("Could not clear table {$table}: " . $e->getMessage());
                    }
                }

                // Execute SQL statements
                $statements = $this->parseStatements($sql);
                $executedCount = 0;
                $errorCount = 0;

                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (empty($statement) || str_starts_with($statement, '--')) {
                        continue;
                    }

                    try {
                        DB::unprepared($statement);
                        $executedCount++;
                    } catch (\Exception $e) {
                        Log::warning("Failed to execute statement: " . $e->getMessage());
                        $errorCount++;
                    }
                }

                DB::commit();

                // Log the restore
                try {
                    $userId = auth()->check() ? auth()->id() : null;
                    DB::table('audit_logs')->insert([
                        'user_id' => $userId,
                        'action' => 'backup_restored',
                        'model_type' => 'Database',
                        'model_id' => null,
                        'changes' => json_encode([
                            'filename' => $file->getClientOriginalName(),
                            'executed' => $executedCount,
                            'errors' => $errorCount,
                        ]),
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Exception $logError) {
                    Log::warning('Failed to log restore: ' . $logError->getMessage());
                }

                return response()->json([
                    'success' => true,
                    'message' => "Database berhasil dipulihkan. {$executedCount} perintah berhasil dijalankan.",
                    'data' => [
                        'executed' => $executedCount,
                        'errors' => $errorCount,
                    ],
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Database restore failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulihkan database: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore from existing backup file
     */
    public function restoreFromFile(string $filename): JsonResponse
    {
        $path = "backups/{$filename}";

        if (!Storage::disk('local')->exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'File backup tidak ditemukan',
            ], 404);
        }

        try {
            $sql = Storage::disk('local')->get($path);

            // Verify it's a SIPERA backup
            if (!str_contains($sql, '-- SIPERA Database Backup')) {
                return response()->json([
                    'success' => false,
                    'message' => 'File bukan backup SIPERA yang valid',
                ], 422);
            }

            // Start transaction
            DB::beginTransaction();

            try {
                // Clear existing data in reverse order (to respect foreign keys)
                $reverseTables = array_reverse($this->tables);
                foreach ($reverseTables as $table) {
                    try {
                        DB::table($table)->delete();
                    } catch (\Exception $e) {
                        Log::warning("Could not clear table {$table}: " . $e->getMessage());
                    }
                }

                // Execute SQL statements
                $statements = $this->parseStatements($sql);
                $executedCount = 0;
                $errorCount = 0;

                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (empty($statement) || str_starts_with($statement, '--')) {
                        continue;
                    }

                    try {
                        DB::unprepared($statement);
                        $executedCount++;
                    } catch (\Exception $e) {
                        Log::warning("Failed to execute statement: " . $e->getMessage());
                        $errorCount++;
                    }
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Database berhasil dipulihkan dari {$filename}. {$executedCount} perintah berhasil dijalankan.",
                    'data' => [
                        'executed' => $executedCount,
                        'errors' => $errorCount,
                    ],
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Database restore failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulihkan database: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse SQL statements from backup file
     */
    protected function parseStatements(string $sql): array
    {
        $statements = [];
        $lines = explode("\n", $sql);
        $currentStatement = '';

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, '--')) {
                continue;
            }

            $currentStatement .= ' ' . $line;

            // If line ends with semicolon, it's a complete statement
            if (str_ends_with($line, ';')) {
                $statements[] = trim($currentStatement);
                $currentStatement = '';
            }
        }

        return $statements;
    }

    /**
     * Generate SQL backup content
     */
    protected function generateBackupSql(): string
    {
        $sql = "-- SIPERA Database Backup\n";
        $sql .= "-- Generated: " . now()->format('Y-m-d H:i:s') . "\n";
        $sql .= "-- Database: " . config('database.connections.pgsql.database') . "\n\n";

        foreach ($this->tables as $table) {
            try {
                $rows = DB::table($table)->get();

                if ($rows->isEmpty()) {
                    $sql .= "-- Table: {$table} (empty)\n\n";
                    continue;
                }

                $sql .= "-- Table: {$table}\n";
                $sql .= "-- Records: " . $rows->count() . "\n";

                foreach ($rows as $row) {
                    $columns = array_keys((array) $row);
                    $values = array_map(function ($value) {
                        if (is_null($value)) {
                            return 'NULL';
                        }
                        if (is_bool($value)) {
                            return $value ? 'TRUE' : 'FALSE';
                        }
                        if (is_numeric($value)) {
                            return $value;
                        }
                        return "'" . addslashes($value) . "'";
                    }, array_values((array) $row));

                    $sql .= "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
                }

                $sql .= "\n";
            } catch (\Exception $e) {
                $sql .= "-- Error backing up table {$table}: " . $e->getMessage() . "\n\n";
            }
        }

        return $sql;
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
