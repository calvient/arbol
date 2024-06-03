<?php

namespace Calvient\Arbol\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearArbolCache extends Command
{
    public $signature = 'arbol:clear';

    public $description = 'Clear all data in the arbol cache.';

    public function handle(): int
    {
        $keys = collect(Cache::getStore()->getPrefix().'arbol:*');
        $keys->each(fn ($key) => Cache::forget($key));

        return self::SUCCESS;
    }
}
