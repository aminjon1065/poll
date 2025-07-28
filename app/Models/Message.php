<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['chat_id', 'sender_id', 'sender_type', 'content', 'status', 'is_edited'];

    public function chat():BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }
}
