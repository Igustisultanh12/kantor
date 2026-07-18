<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeNetwork extends Model
{
    protected $fillable = ['location_name', 'ip_address', 'is_active'];
}
