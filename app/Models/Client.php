<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'name', 'code', 'adresse', 'email', 'phone', 'country', 'city','tax_number', 'Guarantor1_name', 'Guarantor1_phone',
        'Guarantor1_house', 'Guarantor2_name', 'Guarantor2_house', 'Guarantor1_phone','tracker_model','tracker_id','google_map_address','Guarantor2_phone',

    ];

    protected $casts = [
        'code' => 'integer',
    ];

    public function penaltyPayments()
    {
        return $this->hasMany(PenaltyPayment::class);
    }
}
