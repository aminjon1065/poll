<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Operator extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'login', 'password', 'max_chats'];
    protected $hidden = ['password'];

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class, 'operator_id');
    }
}
