<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\DispatchDecodeJson;
use Log;

class UploadFile extends Controller
{
    protected $path = '/var/www/html/youhot/storage/app/json/';
    protected $dir = '/var/www/html/youhot/storage/app/';
    protected $database = 'mysql'; // 'test'

    public function upload(Request $request)
    {
	$site = $request->input('site');
	if (empty($site)) {
	    Log::info('param `site` is null! ');
	    return ['res' => 2, 'hits' => 'param `site` can\'t be null'];
	}
	include($this->dir.'config/stores.php'); // define accept store list
	$nums = 1;
        $sum = count($stores);
        foreach ($stores as $k => $v) {
            if (strpos($site, $v) >= 0) {
                break;
            } else {
                if ($sum == $nums) {
                    Log::info('Site isn\'t in spider\'s list!');
                    return ['res' => 3, 'hits' => '`site` isn\'t in spider\'s list!'];
                }
                $nums++;
            }
        }
	$time = $request->input('time', time());
	if (strpos($time, '-') || strpos($time, '/')) {
            $time = strtotime($time);
        }
	$res = [
	    'res'  => 1, 
	    'hits' => 'Upload Fail!',
	    'timestamp' => $time,
	    'site' => $site,
	    'order' => $request->input('order', 'default'),
	];
	$file = $request->file('file');
	if (isset($file) && $file->isValid()) {
		$res['res'] = 0;
		$res['hits'] = 'success!';
		$res['path'] = $file->storeAs('json', $file->getClientOriginalName());
		Log::info('uploaded a file! path: '.$res['path']);
	} else {
	    Log::info('Receiving Stream File...');
	    $fileName = $request->input('file_name', 'product'.time().'.json');
	    $ret = $this->receiveStreamFile($fileName);
	    if ($ret) {
	 	$res['res'] = 0;
                $res['hits'] = 'success!';
		$res['path'] = 'json/'.$fileName;
	    }
	}
	if ($res['res'] == 0) {
	    Log::info('Add a Dispatch job...');
            $job = (new DispatchDecodeJson($this->dir.$res['path'], $res['timestamp'], $res['site'], $this->database, $res['order']))->onConnection('database')->onqueue('default');
            dispatch($job);
	    Log::info('Add dispatch job done!');
	} else {
	    Log::info('upload json file, but file is not valid!!!');
	}

	return $res;
    }

    private function receiveStreamFile($fileName)
    { 
	Log::info('get contents from php:input'); //debug
        $streamData = file_get_contents('php://input');
  
  	if(empty($streamData)){ 
	    Log::info('get contents from $GLOBALS[\'HTTP_RAW_POST_DATA\']'); //debug
  	    $streamData = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : ''; 
 	} 
  	if($streamData != ''){
	    Log::info('stream data good, put to file: '.$fileName); //debug
	    $streamData = urldecode($streamData);
	    $streamData = str_replace('&file=', '', $streamData);
	    $streamData = strchr($streamData, '{');
	    $position = strrpos($streamData, '}');
	    $streamData = substr($streamData, 0, $position + 1);
    	    $res = file_put_contents($this->path.$fileName, $streamData, true);
  	}else{
	    Log::info('stream data is null'); //debug
    	    $res = false; 
  	} 

 	return $res; 
    } 

    public function read()
    {
	$file_path = 'json/goods2.json';
	$dir_path = dirname(dirname(dirname(dirname(__FILE__)))).'/storage/app/';
	if (file_exists($dir_path.$file_path)) {
	    echo 'file exist!<br>';
	    $file_arr = file($dir_path.$file_path);
	    for ($i=0;$i<count($file_arr);$i++) {
		$ct = json_decode(substr($file_arr[$i], 0, (strlen($file_arr[$i]) - 2)), true);
		if (is_array($ct)) {
		    echo $ct['sku'].'<br>';
		}
	    }
	}
    }
}
