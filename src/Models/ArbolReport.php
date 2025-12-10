<?php

namespace Calvient\Arbol\Models;

use Calvient\Arbol\Contracts\ArbolAccess;
use Calvient\Arbol\Database\Factories\ArbolReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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
        'team_ids' => 'json',
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
        $user = Auth::user();
        $userId = Auth::id();
        $teamIds = app(ArbolAccess::class)->getUserTeamIds($user);

        return $query->where(function ($scopedQuery) use ($userId, $teamIds) {
            $scopedQuery->where('author_id', $userId)
                ->orWhereJsonContains('user_ids', $userId)
                ->orWhereJsonContains('user_ids', -1);

            if (! empty($teamIds)) {
                $scopedQuery->orWhere(function ($teamQuery) use ($teamIds) {
                    foreach ($teamIds as $teamId) {
                        $teamQuery->orWhereJsonContains('team_ids', $teamId);
                    }
                });
            }
        });
    }
}
