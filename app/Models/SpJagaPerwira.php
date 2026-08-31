<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpJagaPerwira extends Model
{
    use HasFactory;

    protected $fillable = [
        'sp_jaga_id',
        'user_id',
        'no_urut',
        'nama',
        'pangkat_korps',
        'nrp',
        'tgl_1',
        'tgl_2',
        'tgl_3',
        'tgl_4',
        'tgl_5',
        'tgl_6',
        'tgl_7',
        'tgl_list',
    ];

    protected $casts = [
        'tgl_list' => 'array',
    ];

    public function spJaga()
    {
        return $this->belongsTo(SpJaga::class, 'sp_jaga_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}