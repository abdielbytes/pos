<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    protected $table = 'transfers';
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'id', 'date','user_id', 'from_branch_id', 'to_branch_id',
        'items', 'statut', 'notes', 'GrandTotal', 'discount', 'shipping', 'TaxNet', 'tax_rate',
        'created_at', 'updated_at', 'deleted_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'from_branch_id' => 'integer',
        'to_branch_id' => 'integer',
        'items' => 'double',
        'GrandTotal' => 'double',
        'discount' => 'double',
        'shipping' => 'double',
        'TaxNet' => 'double',
        'tax_rate' => 'double',

    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function details()
    {
        return $this->hasMany('App\Models\TransferDetail');
    }

    public function from_branch()
    {
        return $this->belongsTo('App\Models\branch', 'from_branch_id');
    }

    public function to_branch()
    {
        return $this->belongsTo('App\Models\branch', 'to_branch_id');
    }

}
