<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrototypeCase extends Model
{
    protected $fillable = ['slug', 'title', 'summary', 'content', 'sort_order', 'is_active'];

    protected $casts = ['content' => 'array', 'is_active' => 'boolean'];

    public function events()
    {
        return $this->hasMany(AnalyticsEvent::class);
    }
}
