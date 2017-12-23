<?php

namespace App\Models;

use Moloquent;

class Mongo extends Moloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'youhot';
    protected $dataFormat = 'U';
    protected $fillable = [
	'name',
	'userid',
	'app_type',
	'app_version',
	'IMEI',
	'current_page',
	'last_page',
	'stay_time',
	'action_type',
	'event_type',
	'timestamp',
	'location_x',
	'location_y',
	'network_type',
	'ip',
	'session_id',
	'area',
	'product_id',
	'product_title',
	'product_price',
	'brand_id',
	'brand_name',
	'store_id',
	'store_name',
	'order_id',
	'order_price',
	'collection_id',
	'collection_title',
	'search_keywords',
	'search_condition',
	'sply_id',
    ];
 
    public $timestamps = true;

    // const CREATE_AT = '';
    // const UPDATE_AT = '';

}
