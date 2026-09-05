<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index()
    {
        return Inertia::render('Backup/Index', [
            'dbInfo' => [
                'driver' => config('database.default'),
                'path'   => $this->dbPath(),
                'size'   => $this->humanSize($this->dbPath()),
            ],
        ]);
    }

    /**
     * Download the raw SQLite database file.
     */
    public function downloadSqlite()
    {
        $path = $this->dbPath();
        abort_unless($path && file_exists($path), 404, 'Database file not found.');

        $filename = 'wjcafe-backup-' . now()->format('Y-m-d_His') . '.sqlite';

        return response()->download($path, $filename);
    }

    /**
     * Download a portable SQL dump (INSERT statements for all tables).
     */
    public function downloadSql(): StreamedResponse
    {
        $filename = 'wjcafe-backup-' . now()->format('Y-m-d_His') . '.sql';

        return response()->streamDownload(function () {
            $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
                ->pluck('name');

            echo "-- WhiteJersey Cafe POS backup\n";
            echo '-- Generated: ' . now()->toDateTimeString() . "\n\n";
            echo "PRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n\n";

            foreach ($tables as $table) {
                // Schema
                $create = DB::selectOne(
                    "SELECT sql FROM sqlite_master WHERE type='table' AND name = ?",
                    [$table]
                );
                if ($create && $create->sql) {
                    echo "DROP TABLE IF EXISTS \"{$table}\";\n";
                    echo $create->sql . ";\n";
                }

                // Data
                $rows = DB::table($table)->get();
                foreach ($rows as $row) {
                    $cols = array_keys((array) $row);
                    $vals = array_map(function ($v) {
                        if (is_null($v)) return 'NULL';
                        if (is_numeric($v)) return $v;
                        return "'" . str_replace("'", "''", (string) $v) . "'";
                    }, array_values((array) $row));

                    $colList = '"' . implode('","', $cols) . '"';
                    echo "INSERT INTO \"{$table}\" ({$colList}) VALUES (" . implode(',', $vals) . ");\n";
                }
                echo "\n";
            }

            echo "COMMIT;\nPRAGMA foreign_keys=ON;\n";
        }, $filename, ['Content-Type' => 'application/sql']);
    }

    /**
     * Restore the database from an uploaded .sqlite file.
     */
    public function restore(Request $request)
    {
        $request->validate([
            'backup' => 'required|file',
        ]);

        $file = $request->file('backup');

        // Only accept .sqlite files
        if (! in_array($file->getClientOriginalExtension(), ['sqlite', 'db'])) {
            throw ValidationException::withMessages([
                'backup' => 'Please upload a valid .sqlite backup file.',
            ]);
        }

        // Validate it's actually a SQLite file (magic header)
        $handle = fopen($file->getRealPath(), 'rb');
        $header = fread($handle, 16);
        fclose($handle);
        if (strpos($header, 'SQLite format 3') !== 0) {
            throw ValidationException::withMessages([
                'backup' => 'The uploaded file is not a valid SQLite database.',
            ]);
        }

        $current = $this->dbPath();

        // Safety copy of the current DB before overwriting
        if (file_exists($current)) {
            @copy($current, $current . '.before-restore');
        }

        // Close DB connections, then overwrite the file
        DB::disconnect();
        copy($file->getRealPath(), $current);

        return redirect()->route('backup.index')
            ->with('success', 'Database restored successfully.');
    }

    private function dbPath(): ?string
    {
        return config('database.connections.sqlite.database');
    }

    private function humanSize(?string $path): string
    {
        if (! $path || ! file_exists($path)) return '0 KB';
        $bytes = filesize($path);
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 2) . ' MB';
    }
}
