<?php

namespace jdavidbakr\LaravelCacheGarbageCollector;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class LaravelCacheGarbageCollector extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:gc';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Garbage-collect the cache files';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $cacheDisk = [
            'driver' => 'local',
            'root'   => config('cache.stores.file.path')
        ];
        Config::set('filesystems.disks.fcache', $cacheDisk);
        $expired_file_count = 0;
        $active_file_count  = 0;
        foreach ($this->cachedfiles() as $cachefile) {
            try {
                $handle = fopen(Storage::disk('fcache')->path($cachefile), 'r');
                $expire = fread($handle, 10);
                fclose($handle);

                if ($expire && Carbon::now()->timestamp >= (int)$expire) {
                    Storage::disk('fcache')->delete($cachefile);
                    $expired_file_count++;
                } else {
                    $active_file_count++;
                }
            } catch (FileNotFoundException $e) {
                $this->error($e->getMessage());
            }
        }
        $this->warn("cleared $expired_file_count file(s)");
        $this->info("$active_file_count file(s) still active");
    }

    protected function cachedfiles()
    {
        $directories = $this->cachedFolders();
        foreach ($directories as $directory) {
            foreach (Storage::disk('fcache')->files($directory) as $file) {
                yield $file;
            }
        }
    }

    protected function cachedFolders($dir = null)
    {
        $resuts      = [[$dir]];
        $directories = Storage::disk('fcache')->directories($dir);
        if ($directories) {
            $resuts = [];
            foreach ($directories as $directory) {
                $resuts[] = $this->cachedFolders($directory);
            }
        }
        return array_merge(...$resuts);
    }
}
