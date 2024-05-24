<?php

namespace Calvient\Arbol\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ArbolPublish extends Command
{
    public $signature = 'arbol:publish';

    public $description = 'Publish the Arbol package assets.';

    public function handle(): int
    {
        $this->deleteOldAssets();

        $this->call('vendor:publish', [
            '--tag' => 'arbol-assets',
        ]);

        $this->info('Arbol assets published successfully.');

        return self::SUCCESS;
    }

    protected function deleteOldAssets()
    {
        $assetPath = public_path('vendor/arbol');
        if (File::exists($assetPath)) {
            File::deleteDirectory($assetPath);
        }
    }
}
