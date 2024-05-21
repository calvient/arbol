<?php

namespace Calvient\Arbol\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Calvient\Arbol\Arbol
 */
class Arbol extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Calvient\Arbol\Arbol::class;
    }
}
