<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BytidWebhookEvent extends Model
{
    protected $fillable = ['event_id', 'event_type', 'processed_at'];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }
}
