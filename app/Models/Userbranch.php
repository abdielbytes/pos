<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Userbranch extends Model
{
    protected $table ="branch_user";

   protected $fillable = [
    'user_id', 'branch_id',
];

protected $casts = [
    'user_id' => 'integer',
    'branch_id' => 'integer',
];

    public function assignedbranches()
    {
        return $this->hasMany('App\Models\branch', 'id', 'branch_id');
    }
}
