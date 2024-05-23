<?php

namespace Calvient\Arbol\Models;

use Illuminate\Database\Eloquent\Model;

class ArbolReport extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i',
        'updated_at' => 'datetime:Y-m-d H:i',
        'user_ids' => 'json',
    ];

    public function sections()
    {
        return $this->hasMany(ArbolSection::class)
            ->orderBy('sequence');
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
            ->orWhereJsonContains('user_ids', auth()->id());
    }
}
