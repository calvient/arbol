<?php

namespace Calvient\Arbol\Commands;

use Illuminate\Console\Command;

class ArbolCommand extends Command
{
    public $signature = 'arbol';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
