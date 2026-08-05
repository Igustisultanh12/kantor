<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SkhppMember extends Model
{
    use HasFactory;

    protected $table = 'skhpp_members';

    protected $fillable = [
        'skhpp_id',
        'no_urut',
        'nama',
        'pangkat_nrp_nik',
        'jabatan',
    ];

    public function skhpp()
    {
        return $this->belongsTo(Skhpp::class, 'skhpp_id');
    }
}
