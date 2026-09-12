<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pc extends Model
{
	protected $fillable = ['user_id', 'pc_name', 'hardware_id', 'current_usage', 'max_quota'];
	public function user() { return $this->belongsTo(User::class); }

    public function shares()
    {
        return $this->hasMany(BackupShare::class, 'pc_id');
    }

    public function rootShare()
    {
        return $this->hasOne(BackupShare::class, 'pc_id')->whereNull('backup_id');
    }
}
