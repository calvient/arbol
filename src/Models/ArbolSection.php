<?php

namespace Calvient\Arbol\Models;

use Illuminate\Database\Eloquent\Model;

class ArbolSection extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'filters' => 'json',
    ];

    public function report()
    {
        return $this->belongsTo(ArbolReport::class);
    }
}
