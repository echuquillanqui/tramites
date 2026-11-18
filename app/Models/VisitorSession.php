<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitorSession extends Model
{
    protected $fillable = ['session_key', 'device', 'browser', 'landing_page', 'started_at', 'last_seen_at', 'duration_seconds'];

    protected $casts = ['started_at' => 'datetime', 'last_seen_at' => 'datetime'];

    public function events()
    {
        return $this->hasMany(AnalyticsEvent::class);
    }
}
