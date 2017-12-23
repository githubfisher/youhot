<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnBack extends Model
{
    protected $table = 'return_back';

    public function user()
    {
	return $this->belongsTo('App\Models\User', 'userid');
    }
}
