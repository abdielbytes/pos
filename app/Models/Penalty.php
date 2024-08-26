<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

//use App\Models\Sale;

class Penalty extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'amount',
    ];

    public function Sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
