<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    // BARIS INI WAJIB ADA untuk mengatasi MassAssignmentException
    protected $fillable = ['key', 'value'];
}