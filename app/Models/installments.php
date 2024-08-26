<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class installments extends Model
{
    use HasFactory;
    protected $table = 'installments';
    protected $fillable = [
        'installment'
    ];
}
