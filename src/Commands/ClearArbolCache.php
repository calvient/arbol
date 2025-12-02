<?php

namespace Calvient\Arbol\Commands;

use Calvient\Arbol\Models\ArbolSection;
use Calvient\Arbol\Services\ArbolService;
use Illuminate\Console\Command;

class ClearArbolCache extends Command
{
    public $signature = 'arbol:clear {--section= : Clear cache for a specific section ID}';

    public $description = 'Clear all data in the arbol cache.';

    public function handle(ArbolService $arbolService): int
    {
        $sectionId = $this->option('section');

        if ($sectionId) {
            $section = ArbolSection::find($sectionId);

            if (! $section) {
                $this->error("Section with ID {$sectionId} not found.");

                return self::FAILURE;
            }

            $arbolService->clearCacheForSection($section);
            $this->info("Cache cleared for section: {$section->name}");

            return self::SUCCESS;
        }

        // Clear cache for all sections
        $sections = ArbolSection::all();
        $count = 0;

        foreach ($sections as $section) {
            $arbolService->clearCacheForSection($section);
            $count++;
        }

        $this->info("Cache cleared for {$count} section(s).");

        return self::SUCCESS;
    }
}
