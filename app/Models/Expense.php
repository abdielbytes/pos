<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'date', 'user_id', 'expense_category_id', 'branch_id', 'details',
        'amount', 'Ref', 'created_at', 'updated_at', 'deleted_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'expense_category_id' => 'integer',
        'branch_id' => 'integer',
        'amount' => 'double',
    ];

    public function branch()
    {
        return $this->belongsTo('App\Models\branch');
    }

    public function expense_category()
    {
        return $this->belongsTo('App\Models\ExpenseCategory');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

}
