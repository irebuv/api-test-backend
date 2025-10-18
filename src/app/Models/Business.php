<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Business extends Model
{
    protected $table = 'businesses';
    protected $fillable = ['name', 'description', 'image', 'type', 'user_id'];

    public function user(){
        return $this->belongsTo(User::class);
    }
    protected static function booted()
{
    static::saved(fn () => Cache::forget('business_types'));
    static::deleted(fn () => Cache::forget('business_types'));
}
}
