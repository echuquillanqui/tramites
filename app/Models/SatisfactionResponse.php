<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SatisfactionResponse extends Model
{
    protected $fillable = ['visitor_session_id', 'prototype_case_id', 'rating', 'is_demo', 'responded_at'];

    protected $casts = ['rating' => 'integer', 'is_demo' => 'boolean', 'responded_at' => 'datetime'];

    public function visitorSession()
    {
        return $this->belongsTo(VisitorSession::class);
    }

    public function prototypeCase()
    {
        return $this->belongsTo(PrototypeCase::class);
    }
}
