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
        // Make a storage disk for the cache location
        $cacheDisk = [
            'driver' => 'local',
            'root'   => config('cache.stores.file.path')
        ];
        Config::set('filesystems.disks.fcache', $cacheDisk);
        $expired_file_count = 0;
        $active_file_count  = 0;
        // Loop the files and get rid of any that have expired
        foreach ($this->cachedfiles() as $cachefile) {

            try {
                // Grab the contents of the file
                $contents = Storage::disk('fcache')->get($cachefile);
                // Get the expiration time
                $expire = substr($contents, 0, 10);

                // See if we have expired
                if (Carbon::now()->timestamp >= $expire) {
                    // Delete the file
                    Storage::disk('fcache')->delete($cachefile);
                    $expired_file_count++;
                } else {
                    $active_file_count++;
                }
            } catch (FileNotFoundException $e) {
                $this->error($e->getMessage(), $this->getOutput());
                // Getting an occasional error of this type on the 'get' command above,
                // so adding a try-catch to skip the file if we do.
            }
        }
        $this->warn("cleared $expired_file_count file(s)", $this->getOutput());
        $this->info("$active_file_count file(s) still active", $this->getOutput());
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
