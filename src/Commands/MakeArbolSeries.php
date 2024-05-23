<?php

namespace Calvient\Arbol\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeArbolSeries extends Command
{
    public $signature = 'make:arbol-series {name}';

    public $description = 'Create a new series of data in Arbol.';

    public function handle(): int
    {
        $filename = $this->writeArbolSeriesTextToFile();

        $this->comment('File created at: '.$filename);

        return self::SUCCESS;
    }

    private function writeArbolSeriesTextToFile(): string
    {
        $name = $this->argument('name');
        $nameStringable = Str::of($name);
        $className = $nameStringable->endsWith('Series')
            ? $nameStringable
            : $nameStringable->append('Series');

        // Generate the file contents
        $fileContents = <<<PHP
        <?php

        namespace App\Arbol;

        use Calvient\Arbol\Contracts\IArbolSeries;

        class $className implements IArbolSeries
        {
            static public function name(): string
            {
                // This is the name that will be displayed in the Arbol UI
                return '$name';
            }

            static public function description(): string
            {
                // This is the description that will be displayed in the Arbol UI
                return '';
            }

            static public function data(): array
            {
                // This should return an array of data that will be used in the series.
                return [];
            }

            static public function slices(): array
            {
                // This should return an array of functions that could be used to slice the data.
                return [];
            }

            static public function filters(): array
            {
                // This should return an array of functions that could be used to filter the data.
                return [];
            }

            static public function formats(): array
            {
                // This should return an array of ways the data can be displayed.
                return [
                    'table',
                ];
            }
        }
        PHP;

        // Make sure the directory exists before writing to it
        if (! is_dir(app_path('Arbol'))) {
            mkdir(app_path('Arbol'));
        }

        // Write the contents to the file
        file_put_contents(
            app_path("Arbol/{$className}.php"),
            $fileContents
        );

        // Return the filename
        return app_path("Arbol/{$className}.php");
    }
}
