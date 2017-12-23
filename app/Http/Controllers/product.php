<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product as products;
use JPush\Client as Jpush;
use App\Models\User;
use App\Jobs\SendRemindEmail;
use Mail;
use DB;
use App\Models\Data;
use Storage;
use Log;

class product extends Controller
{
    public function getProduct(Request $request)
    {
    	$id = $request->input('id');
	$product = products::where('id','=',$id)->get();
	dd($product);
    }

    public function jpush()
    {
	$appKey = config('jpush.appKey');
	$appSecret = config('jpush.appSecret');

	//$client = new Jpush($appKey, $appSecret);
	//$push = $client->push();
	echo 'JPush';	
    }

    public function sendRemindEmail(Request $request, $id)
    {
	$user = User::findOrFail($id);
	//return $user->cover;
	$this->dispatch(new SendRemindEmail($user));
    }

    public function send()
    {
	//Mail::raw('This is a test mail', function ($message) {
            //$message->to('goodlovefisher@126.com')->subject('test Mail');
	//});
	$name = 'fisher';
	Mail::send('emails.test',['name'=>$name],function($message){
            $to = 'goodlovefisher@126.com';
            $message ->to($to)->subject('测试邮件');
	});
    }

    public function mongo()
    {
	/*$mongodb = DB::connection('mongodb');
	$c = $mongodb->collection('youhot')->get();
	dd($c);
	*/
	$result = Data::get();
	dd($result);
    }

    public function testDb(Request $request)
    {
	//$res = DB::connection('test')->select('select * from store where id = :id', ['id' => 280]);
	//$p = DB::connection('test')->select('select id, price, title, position from product where m_sku = ?', ['10365379']);
	//$p = DB::connection('test')->insert("insert into store (name, show_name) values (?, ?)", ['baidu', 'baidu']);
	//$id = DB::connection('test')->table('store')->insertGetId(['name' => 'baidu', 'show_name' => 'baidu']);
	//print_r($id);
	//print_r($res[0]->id);
	//dd($res);
	/*
	$old_img_arr = DB::connection('test')->table('product_album')->where('product_id', 143918)->get();
	foreach ($old_img_arr as $k => $v) {
	   $path = strchr($v->content, 'album/');
	   if (!empty($path)) {
		$oia[] = $path;
	   }
	}
	print_r($oia);
	*//*
	$path = 'album/Y16207bfe80c12b45d918d22f5a663988ec.jpg';
	//$res = Storage::disk('oss')->url($path);
	$prefix = config('filesystems.disks.oss.path', $path);
	$url = 'https://s1.thcdn.com/productimg/600/600/11331372-1394419802336809.jpg';
	$image_name = 'album/Y162test.jpg';
	$file_content = $this->down_url($url);
	if (isset($file_content)) {
		print_r(strlen($file_content));die;
	} else {
	    echo 'file isn\'t download!';
	}
	$res = Storage::disk('oss')->put($image_name, $file_content);
	*/
	
	//print_r($res);
	//phpinfo();
   	//$proxy = DB::connection('mysql')->table('proxy')->select('id','ip','port','times')->where('status', 1)->orderby('get_at', 'desc')->first();
	//echo $proxy->id;
	$products = DB::connection('mysql')->table('product')->select('id')->where('store', 333)->get();
	foreach ($products as $k => $v) {
	    Log::info('product-id:'.$v->id); //debug
	    $cs = DB::connection('mysql')->table('product_album')->select('content')->where('product_id', $v->id)->groupBy('content')->havingRaw('COUNT(content) > 1')->get();
	    $css = [];
	    foreach ($cs as $m => $n) {
	 	$css[] = $n->content; 
	    }
	    $ids = DB::connection('mysql')->select("select MIN(`id`) as id from `product_album` where `product_id` = ? group by `content` having COUNT(`content`) > 1", [$v->id]);
	    $idss = [];
            foreach ($ids as $m => $n) {
                $idss[] = $n->id;
            }

	    $res = DB::connection('mysql')->table('product_album')->where('product_id', $v->id)->whereIn('content', $css)->whereNotIn('id', $idss)->delete();
	}
	echo 'done';
    }

    private function down_url($url) // download image
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,30);
        curl_exec($ch);
        $res = curl_getinfo($ch);
        //200 Image <5M
        if($res['http_code'] == 200 && preg_match('/image\//', $res['content_type']) && $res['download_content_length']<5242880) {
            //is image
        }else{
            curl_close($ch);
            return false;
        }
        curl_setopt($ch, CURLOPT_NOBODY, 0);
        curl_setopt($ch, CURLOPT_POST, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,30);
        $file_content = curl_exec($ch);
        $res2 = curl_getinfo($ch);
        curl_close($ch);
        if($res['download_content_length'] != $res2['download_content_length']){
            return false;
        }
        if( strlen($file_content) > ($res2['download_content_length']-100) ){
            return $file_content;
        }
        return false;
    }
}
