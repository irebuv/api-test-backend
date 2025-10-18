<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['name', 'date', 'phone', 'description', 'business_id', 'is_read'];

    public function business() {
        return $this->belongsTo(Business::class);
    }
}
