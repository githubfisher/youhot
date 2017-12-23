<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Laravel\Scout\Searchable;

class Product extends Model
{
    use Searchable;

    protected $table = 'product';

    public function searchableAs()
    {
	return 'products';
    }

    public function toSearchableArray()
    {
	$array = $this->toArray();

	return [
		'title',
		'description',
		'keywords'
	];
    }

    public function users()
    {
	return $this->belongsToMany('App\Models\User', 'product_liker', 'product_id', 'userid');
    }

    public function buyers()
    {
	return $this->belongsToMany('App\Models\User', 'dealcart', 'product_id', 'userid');
    }
}
