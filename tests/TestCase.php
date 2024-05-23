<?php

namespace Calvient\Arbol\Tests;

use Calvient\Arbol\ArbolServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            ArbolServiceProvider::class,
        ];
    }
}
