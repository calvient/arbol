<?php

namespace Calvient\Arbol\Models;

use Calvient\Arbol\Database\Factories\ArbolReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArbolReport extends Model
{
    /** @use HasFactory<ArbolReportFactory> */
    use HasFactory;

    protected static function newFactory(): ArbolReportFactory
    {
        return ArbolReportFactory::new();
    }

    protected $guarded = ['id', 'client_id'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i',
        'updated_at' => 'datetime:Y-m-d H:i',
        'user_ids' => 'json',
    ];

    public function sections()
    {
        return $this->hasMany(ArbolSection::class);
    }

    public function author()
    {
        return $this->belongsTo($this->getUserModel(), 'author_id');
    }

    public function users()
    {
        return $this->getUserModel()::whereIn('id', $this->user_ids)->get();
    }

    private function getUserModel()
    {
        return config('arbol.user_model');
    }

    public function scopeMine($query)
    {
        return $query->where('author_id', auth()->id())
            ->orWhereJsonContains('user_ids', auth()->id())
            ->orWhereJsonContains('user_ids', -1);
    }
}
