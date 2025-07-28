<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = ['session_id', 'name'];

    public function chats():HasMany
    {
        return $this->hasMany(Chat::class, 'client_id');
    }
}
