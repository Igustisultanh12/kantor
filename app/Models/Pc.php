<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pc extends Model
{
	protected $fillable = ['user_id', 'pc_name', 'hardware_id', 'current_usage', 'max_quota'];
	public function user() { return $this->belongsTo(User::class); }
    //
}
