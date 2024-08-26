<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PenaltyPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'Ref',
        'client_id',
        'date',
        'Reglement',
        'montant',
        'change',
        'notes',
        'user_id'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
