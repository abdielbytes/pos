<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class product_branch extends Model
{
    protected $table = 'product_branch';

    protected $fillable = [
        'product_id', 'branch_id', 'qte','manage_stock'
    ];

    protected $casts = [
        'product_id' => 'integer',
        'branch_id' => 'integer',
        'manage_stock' => 'integer',
        'qte' => 'double',
    ];

    public function branch()
    {
        return $this->belongsTo('App\Models\branch');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }

    public function productVariant()
    {
        return $this->belongsTo('App\Models\ProductVariant');
    }

}
