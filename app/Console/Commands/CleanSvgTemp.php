<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanSvgTemp extends Command
{
    protected $signature = 'svg:clean-temp {--dry-run : Show what would be deleted without deleting}
                                       {--path= : Override temp path}
                                       {--max-age= : Override max age seconds}';

    protected $description = 'Clean up temporary SVG conversion files older than configured max age.';

    public function handle(): int
    {
        $dir = $this->option('path') ?: (string) config('svg.temp.dir');
        $maxAge = $this->option('max-age');
        $maxAge = is_numeric($maxAge) ? (int) $maxAge : (int) config('svg.temp.max_age_seconds', 24 * 3600);
        $dry = (bool) $this->option('dry-run');

        if ($dir === '' || !is_dir($dir)) {
            $this->warn("Temp directory does not exist: {$dir}");
            return self::SUCCESS;
        }

        $now = time();
        $count = 0;
        $bytes = 0;

        $paths = File::allFiles($dir);
        foreach ($paths as $file) {
            $path = $file->getPathname();
            $mtime = @filemtime($path) ?: $now;
            if ($now - $mtime >= $maxAge) {
                $size = @filesize($path) ?: 0;
                if ($dry) {
                    $this->line("Would delete: {$path} (" . $size . ' bytes)');
                } else {
                    @unlink($path);
                    $this->line("Deleted: {$path}");
                }
                $count++;
                $bytes += $size;
            }
        }

        if (!$dry) {
            // Try removing empty directories
            $this->cleanupEmptyDirs($dir);
        }

        $this->info(($dry ? 'Would remove ' : 'Removed ') . "$count files, " . $bytes . ' bytes.');
        return self::SUCCESS;
    }

    private function cleanupEmptyDirs(string $dir): void
    {
        $dirs = File::directories($dir);
        foreach ($dirs as $sub) {
            $this->cleanupEmptyDirs($sub);
            if ($this->isDirEmpty($sub)) {
                @rmdir($sub);
            }
        }
    }

    private function isDirEmpty(string $dir): bool
    {
        $h = @opendir($dir);
        if (!$h) return false;
        $n = 0;
        while (($e = readdir($h)) !== false) {
            if ($e === '.' || $e === '..') continue;
            $n++;
            if ($n > 0) break;
        }
        closedir($h);
        return $n === 0;
    }
}
