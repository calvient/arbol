<?php

namespace Calvient\Arbol\Models;

use Calvient\Arbol\Database\Factories\ArbolSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArbolSection extends Model
{
    /** @use HasFactory<ArbolSectionFactory> */
    use HasFactory;

    protected static function newFactory(): ArbolSectionFactory
    {
        return ArbolSectionFactory::new();
    }

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'filters' => 'json',
    ];

    public function report()
    {
        return $this->belongsTo(ArbolReport::class, 'arbol_report_id');
    }
}
