<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function operations()
    {
        return $this->hasMany(Operation::class);
    }
}
