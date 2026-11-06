<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PrivatizeAccountPhotos extends Command
{
    protected $signature = 'accounts:privatize-photos {--apply : Copy, verify, and remove public originals}';
    protected $description = 'Move public account photo files to owner-authorized storage';

    public function handle(): int
    {
        $private = Storage::disk('local');
        $sources = ['public'];
        if (config('filesystems.disks.default') === 's3' || config('filesystems.disks.s3.bucket')) {
            $sources[] = 's3';
        }
        $count = 0;

        foreach ($sources as $diskName) {
            $source = Storage::disk($diskName);
            foreach (['profile', 'seller'] as $directory) {
                foreach ($source->files($directory) as $path) {
                    if (!preg_match('~\A(?:profile|seller)/[a-zA-Z0-9_-]+\.(?:webp|png|jpe?g|gif|svg)\z~i', $path)) {
                        $this->warn("Unrecognized file left for review: {$diskName}:{$path}");
                        continue;
                    }
                    $count++;
                    if (!$this->option('apply')) {
                        continue;
                    }
                    $target = 'private-account-photos/'.$path;
                    $bytes = $source->get($path);
                    if (!is_string($bytes) || $bytes === '') {
                        throw new RuntimeException("Could not read {$diskName}:{$path}");
                    }
                    if (!$private->exists($target) && !$private->put($target, $bytes)) {
                        throw new RuntimeException("Could not copy {$diskName}:{$path}");
                    }
                    if (!hash_equals(hash('sha256', $bytes), hash('sha256', $private->get($target) ?? ''))) {
                        throw new RuntimeException("Photo collision or failed verification for {$diskName}:{$path}");
                    }
                    if (!$source->delete($path) || $source->exists($path)) {
                        throw new RuntimeException("Could not retire {$diskName}:{$path}");
                    }
                }
            }
        }

        $this->info($this->option('apply') ? "Moved and verified {$count} files." : "{$count} account photo files eligible to move.");
        return self::SUCCESS;
    }
}
