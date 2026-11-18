<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitorSession extends Model
{
    protected $fillable = ['session_key', 'device', 'browser', 'landing_page', 'started_at', 'last_seen_at', 'duration_seconds', 'is_demo'];

    protected $casts = ['started_at' => 'datetime', 'last_seen_at' => 'datetime', 'is_demo' => 'boolean'];

    public function events()
    {
        return $this->hasMany(AnalyticsEvent::class);
    }
}
