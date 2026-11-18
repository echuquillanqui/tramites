<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['event_uuid', 'visitor_session_id', 'event_type', 'prototype_case_id', 'page', 'duration_seconds', 'metadata', 'created_at'];

    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public function visitorSession()
    {
        return $this->belongsTo(VisitorSession::class);
    }

    public function prototypeCase()
    {
        return $this->belongsTo(PrototypeCase::class);
    }
}
