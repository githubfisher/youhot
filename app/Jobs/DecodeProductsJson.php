<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use DB;
use Log;
use Storage;

class DecodeProductsJson implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $path;
    protected $timestamp;
    protected $site;
    protected $tax  = 1.03; //税
    protected $database = 'test'; // 'mysql'
    protected $dir = '/var/www/html/youhot/storage/app/';
    protected $content;
    protected $line;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($path, $timestamp, $site, $content, $line, $database)
    {
        $this->path = $path;
	$this->timestamp = $timestamp;
	$this->site = $site;
	$this->database = $database;
	$this->content = $content;
	$this->line = $line;
    }

    /**

     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
	include($this->dir.'config/property.php'); // keywords mapping
	include($this->dir.'config/categorys.php'); // category mapping
	include($this->dir.'config/rates.php'); // category mapping
	include($this->dir.'config/weights.php'); // category mapping
        $ct = json_decode($this->content, true);
        if (is_array($ct)) {
            $res = $this->dealWithProduct($ct, $this->line, $mapping, $categorys, $rates, $weights);
	    if (!empty($res)) {
		Log::info($res);
	    }
        } else {
	    Log::error($this->path.'. Line'.$this->line.' can\'t decode!');
	}

	return true;
    }

    private function dealWithProduct($ct, $line, $mapping, $categorys, $rates, $weights)
    {
	if (isset($ct['sku'])) {
	    if (is_numeric($ct['category'])) {
		Log::info('spider take the category ID!');
		$category = $ct['category'];
	    } else {
	        $category = $this->ex_category($ct['category'], $categorys);
	    }
	    if (empty($category) || !is_numeric($category)) {
		return 'Category  \\'.$ct['category'].'\\ isn\'t in mapping table!';
	    }
	    $data['category'] = $category; // category id
	    $data['m_sku'] = $ct['sku'];
	    $data['author'] = $this->ex_user($ct['brand']); // brand id
	    $ex_store = $this->ex_store($this->site); // store id && store name
	    $data['store'] = $ex_store['id'];
	    $data['tmp_img'] = addslashes(json_encode(['jurl'=>$this->site]));
	    $currency = isset($ct['currency']) ? $ct['currency'] : 'CNY';
	    if (isset($ct['price']) && isset($currency)) {
	        $data['price'] = $this->ex_price($ct['price'], $currency); // price
  	    }
	    if (isset($ct['original_price']) && isset($currency)) {
                $data['presale_price'] = $this->ex_price($ct['original_price'], $currency); // price
            }
	    $shippings = DB::connection($this->database)->select("SELECT shipping.* FROM shipping JOIN store ON store.`id` = shipping.`store_id` WHERE store.`id`= '?' AND shipping.`status` = 1", [$data['store']]); // Including_mail_fee_START
	    $weight = DB::connection($this->database)->select("SELECT weight FROM category WHERE id = '?'", [$data['category']]);
	    $product = array(
            	'price' => $data['price'],
            	'weight' => $weight > 0 ? $weight : 0,
                'num' => 1,
            );
	    $store_mail_fee = $this->getStoreCommondFee($shippings, $product, $rates, $weights);
	    if ($store_mail_fee > 0) {
                $data['pdt_price'] = $data['price'];
                $data['price'] += $store_mail_fee;
                if (isset($data['presale_price']) && $data['presale_price'] > 0) {
                    $data['presale_price'] += $store_mail_fee;
            	}
            } else {
           	$data['pdt_price'] = $data['price'];
            } // Including_mail_fee_END
	    $data['title'] = $this->ex_translator($ct['title']); // $map
	    $data['property'] = $this->find_property($ct['description'], $ct['sku']);
	    $data['description'] = $this->desc_encode(['描述'], [strip_tags($ct['description'])]);
	    $data['create_time'] = $data['save_time'] = $data['last_update'] = date('Y-m-d H:i:s', time());
	    $data['rank'] = $this->timestamp;
	    $data['m_url'] = $ct['url'];
	    $data['status'] = 1;
	    $colors = $sizes = $img_arr = [];
	    if (isset($ct['color']) && is_array($ct['color'])) {
		foreach ($ct['color'] as $k => $v) {
		    $colors[] = $k;
		    if (isset($v['size']) && is_array($v['size'])) {
			$sizes = array_merge($sizes, $v['size']);
		    }
		    if (isset($v['thumbnails']) && is_array($v['thumbnails'])) {
			$img_arr = array_merge($img_arr, $v['thumbnails']);
		    }
		}
		$colors = array_unique(array_filter($colors));
		$sizes = array_unique(array_filter($sizes));
		$img_arr = array_unique(array_filter($img_arr));
	    }
	    $color_ids = $size_ids = [];
	    if (is_array($colors) && count($colors)) {
		foreach ($colors as $k => $v) {
		    $cid = $this->ex_color($v);
		    if ($cid) {
			$color_ids[] = $cid;
		    }
		}
	    }
	    if (is_array($sizes) && count($sizes)) {
                foreach ($sizes as $k => $v) {
                    $sid = $this->ex_size($v);
                    if ($sid) {
                        $size_ids[] = $sid;
                    }
                }
            }
	    // other
	    if (isset($ct['thumbnails'])) {
                $img_arr = array_unique(array_filter(array_merge($img_arr,explode(";", $ct['thumbnails']))));
            }
	    $keywords = $ct['category'].' '.$ct['brand'];
	    if (isset($data['author']) && isset($data['category']) && isset($data['store']) && isset($data['price']) && count($img_arr)) {
	        $p = DB::connection($this->database)->select('select id, price, title, position, position_at from product where m_sku = ?', [$ct['sku']]);
	    	if (is_array($p) && count($p)) {
		    Log::info('Product SKU:'.$ct['sku'].' is in table!'); //debug
		    $data['last_price'] = $p[0]->price;
		    if (isset($data['presale_price'])) {
		        $data['discount'] = $this->getDiscount($data['price'], $data['presale_price']);
		    } else {
			$data['discount'] = $this->getDiscount($data['price'], $p[0]->price);
			if ($data['discount'] < 1) {
			    $data['presale_price'] = $p[0]->price;
			}
		    }
		    if ($p[0]->position == 0) {
			$data['position_at'] = time();
		    }
		    if ($p[0]->position > 0) {
                	if (((int)$p[0]->position_at + 7 * 86400) <= time()) {
                            $data['position'] = 0;
                            $data['position_at'] = time();
                        }
            	    }
  		    if (($p[0]->title == $data['title']) && ($p[0]->price > $data['price'])) { // cut price
			$msg = ['id' => $id['id'], 'price' => $price, 'presale_price' => $id['price'], 'type' => 2];
                	$send_url = "http://10.26.95.72/index.php/note?" . http_build_query($msg);
                	$this->curl_note($send_url);	
		    }
                    $data['cover_image'] = $this->ex_album($p[0]->id, $img_arr); // Insert new images
  		    $res = DB::connection($this->database)->table('product')->where('id', $p[0]->id)->update($data);
		    Log::info('Update result:'.$res); //debug
		    if (is_array($color_ids) && count($color_ids)) {
			$res = $this->del_old_product_color($p[0]->id, $color_ids);
			Log::info('Del product old color: '.$res); //debug
			$res = $this->add_product_color($p[0]->id, $color_ids);
			Log::info('Add product color: '.$res); //debug
		    }
		    if (is_array($size_ids) && count($size_ids)) {
                        $res = $this->del_old_product_size($p[0]->id, $size_ids);
                        Log::info('Del product old size: '.$res); //debug
                        $res = $this->add_product_size($p[0]->id, $size_ids);
                        Log::info('Add product size: '.$res); //debug
                    }
        	    $data['discount_id'] = $this->ex_discount($data['discount']);
		    $data['price_id'] = $this->ex_tag($data['price']);
		    $data['keywords'] = $keywords.$this->findKey($data['description'], $mapping);
		    $data['store_name'] = $ex_store['name'];
		    $this->esDoc($p[0]->id, $data); // ElasticSearch Update
	    	} else {
		    Log::info('Product SKU:'.$ct['sku'].' isn\'t in table!'); // debug
		    $oldprice = isset($data['presale_price']) ? $data['presale_price'] : 0;
		    $data['discount'] = $this->getDiscount($data['price'], $oldprice);
		    $data['position_at'] = time();
            	    $pid = DB::connection($this->database)->table('product')->insertGetId($data);
		    Log::info('Insert result:'.$pid); //debug
		    if (is_array($color_ids) && count($color_ids)) {
                        $res = $this->add_product_color($pid, $color_ids);
                        Log::info('Add product color: '.$res); //debug
                    }
                    if (is_array($size_ids) && count($size_ids)) {
                        $res = $this->add_product_size($pid, $size_ids);
                        Log::info('Add product size: '.$res); //debug
                    }
                    $data['cover_image'] = $this->ex_album($pid, $img_arr); // Insert new images
		    if (!empty($data['cover_image'])) {
  		       $res = DB::connection($this->database)->table('product')->where('id', $pid)->update(['cover_image'=>$data['cover_image'], 'last_update'=>date('Y-m-d H:i:s', time())]);
		    }
		    $data['discount_id'] = $this->ex_discount($data['discount']);
                    $data['price_id'] = $this->ex_tag($data['price']);
		    $data['keywords'] = $keywords.$this->findKey($data['description'], $mapping);
		    $data['store_name'] = $ex_store['name'];
		    $this->esDoc($pid, $data, 'create'); // ElasticSearch Insert
	      	}
	    } else {
		return 'Product SKU:'.$ct['sku'].' missing infomation. author,category,store,price,or image';
	    }
	    return;
	} else {
	    return 'line'.$line.' isn\'t set sku';
	}
    }

    private function ex_user($name){
        $new_name = addslashes($name);
        $new_name = str_replace(' ', '', $new_name);
        if (empty($new_name)) {
            return false;
        }
        $username = $new_name . '@data.st';
        $one = DB::connection($this->database)->select("SELECT userid FROM user WHERE username = ?", [$username]);
        if(is_array($one) && count($one)){
            return $one[0]->userid;
        }else{
            $facepic = 'http://product-album-n.img-cn-hangzhou.aliyuncs.com/avatar/default_avatar.png';
            $data['username'] = $username;
            $data['usertype'] = 2;
            $data['password'] = md5('Sjs#2016#' . '&*xc_@12');
            $data['regtime']  = date('Y-m-d H:i:s');
            $data['facepic']  = $facepic;
            $data['nickname'] = $name;
            return DB::connection($this->database)->table('user')->insertGetId($data);
        }
        return false;
    }

    private function ex_store($store)
    {
        $name = addslashes($store);
        $name = str_replace(' ', '', $name);
        $name = rtrim(rtrim($name, '.com'),'.COM');
	$name = ltrim(ltrim($name, 'www.'), 'WWW.');
        $one = DB::connection($this->database)->select("SELECT id,show_name FROM store WHERE name = ?", [$name]);
        if (is_array($one) && count($one)) {
	    $res['id'] = $one[0]->id;
            $res['name'] = $one[0]->show_name;
        } else {
            $res['id'] = DB::connection($this->database)->table('store')->insertGetId(['name'=>$name, 'show_name'=>$store]);
	    $res['name'] = $name;
        }

        return $res;
    }

    private function ex_price($price, $currency)
    {
	$c = [
	    'USD' => 6.9,
   	    'HKD' => 0.9,
   	    'GBP' => 8.8,
  	    'EUR' => 7.7,
   	    'JPY' => 0.062,
   	    'KRW' => 0.0061,
   	    'CHF' => 6.8,
   	    'CAD' => 5.1,
	    'CNY' => 1,
	];
	return ceil(bcmul($price*$c[$currency], $this->tax, 2));
    }

    private function ex_translator($word, $map=array()) {
        if(!empty($word)) {
            foreach ($map as $k => $v) {
                $word = preg_replace('/\b'.$k.'\b/i', $v, $word);
            }
        }
        return $word;
    }

    private function ex_tag($price){
	if( $price<=500 ){
            $tagname = '< 500';
        }elseif( $price>500  && $price<=1000 ){
            $tagname = '500 - 1000';
        }elseif( $price>1000 && $price<=1500 ){
            $tagname = '1000 - 1500';
        }elseif( $price>1500 && $price<=2000 ){
            $tagname = '1500 - 2000';
        }elseif( $price>2000 && $price<=3000 ){
            $tagname = '2000 - 3000';
        }else{
            $tagname = '> 3000';
        }
        $name = addslashes($tagname);
        $name = strtoupper($name);
        $one = DB::connection($this->database)->select("SELECT id FROM tags WHERE name = ?", [$name]);
        if(is_array($one) && count($one)){
            return $one[0]->id;
        }else{
            return DB::connection($this->database)->table('tags')->insertGetId(['name'=>$name]);
        }
	return false;
    }

    private function ex_discount($dr){
	if ($dr <= 0.3) {
            $tagname = '<=3';
        } elseif (0.3 < $dr && $dr <= 0.5) {
            $tagname = '3-5';
        } elseif (0.5 < $dr && $dr <= 0.7) {
            $tagname = '5-7';
        } else {
            $tagname = '>=7';
        }
        $name = addslashes($tagname);
        $name = strtoupper($name);
        $one = DB::connection($this->database)->select("SELECT id FROM discount_tags WHERE name = ?", [$name]);
        if(is_array($one) && count($one)){
            return $one[0]->id;
        }else{
            return DB::connection($this->database)->table('discount_tags')->insertGetId(['name'=>$name]);
        }
     	return false;
    }

    private function getDiscount($price, $oldprice, $num=2)
    {
        $discount = 1;
        if ($oldprice >0) {
            $discount = round($price/$oldprice, $num);
        }
        return $discount;
    }

    private function find_property($des, $number)
    {
        $property = array(
            0 => array(
                'name' => '商品编号',
                'value' => $number,
            ),
        );
        // material
        preg_match_all('/(virgin|Virgin|VIRGIN)\s{0,2}[A-Za-z]+\s{0,2}[1-9][0-9]{0,2}%/', $des, $material);
        preg_match_all('/(other|OTHER|Other)\s{0,2}[A-Za-z]+\s{0,2}[1-9][0-9]{0,2}%/', $des, $material1);
        preg_match_all('/[1-9][0-9]{0,2}%\s{0,2}(virgin|Virgin|VIRGIN)\s{0,2}[A-Za-z]+/', $des, $material2);
        preg_match_all('/[1-9][0-9]{0,2}%\s{0,2}(other|OTHER|Other)\s{0,2}[A-Za-z]+/', $des, $material3);
        preg_match_all('/[1-9][0-9]{0,2}%\s{0,2}[A-Za-z]+/', $des, $material4);
        preg_match_all('/[A-Za-z]+\s{0,2}[1-9][0-9]{0,2}%/', $des, $material5);
        $material[0] = array_merge($material[0], $material1[0], $material2[0], $material3[0], $material4[0], $material5[0]);
        $materials = array(
            'virgin wool' => '初剪羊毛',
            'other fibers' => '其他纤维',
            'leather' => '真皮',
            'suede' => '真皮',
            'cashmere' => '羊绒',
            'wool' => '羊毛',
            'cotton' => '棉',
            'elastane' => '氨纶',
            'acrylic' => '腈纶',
            'polyester' => '涤纶',
            'nylon' => '尼龙',
            'viscose' => '人造棉(粘纤)',
            'polyamide' => '锦纶',
            'silk' => '丝',
            'Lycra' => '莱卡',
            'PU' => '仿皮',
            'linen' => '麻',
            'fur' => '皮草',
            'shea butter' => '乳木果油',
            'spandex' => '氨纶（高弹纤维）',
            'rayon' => '人造丝',
	    'Triacetate' => '醋脂纤维',
            'acetate' => '醋脂纤维',
            'rhinestone' => '水钻',
            'mohair' => '安哥拉山羊毛',
            'alpaca' => '驼羊毛',
            'mink' => '水貂毛',
            'Chiffon' => '雪纺',
            'rhinestone' => '水钻',
            'rabbit' => '兔毛',
            'fleece' => '抓绒',
            'modal' => '天然纤维',
        );
        if (empty($material[0])) {
                $material = $this->findKey($des, $materials);
        } else {
            $keys = array_keys($materials);
            $material = array_unique($material[0]);
            foreach ($material as $k => $v) {
                foreach ($keys as $m => $n) {
                    $pos = stripos($v, $n);
                    if ($pos || ($pos === 0)) {
                        $string = str_ireplace($n, $materials[$n], $v);
                        $material[$k] = $string;
                        break;
                    } else {
                        unset($material[$k]);
                    }
                }
            }
            $material = implode(',', $material);
        }
        if (!empty($material)) {
            $property[] = array(
                    'name' => '材质',
                    'value' => $material,
            );
        }
        // Width,Height,depth, mL
	preg_match_all('/[A-Za-z]{2,10} [1-9][0-9]{0,3}.[0-9]{0,2}\s{0,2}cm/', $des, $specs);
        preg_match_all('/[A-Za-z]{2,10}\s{0,2}[1-9][0-9]{0,3}.{0,1}[0-9]{0,2}("|inch|in)/', $des, $specs1);
        preg_match_all('/[1-9][0-9]{0,3}.{0,1}[0-9]{0,2}("|inch|in)\s{0,2}[A-Za-z]{1,10}/', $des, $specs2);
        preg_match_all('/[1-9][0-9]{0,3}.{0,1}[0-9]{0,2}("|inch|in)\s{0,2}[A-Za-z]{1,10}/', $des, $specs3);
        preg_match_all('/[1-9][0-9]{0,3}.{0.1}[0-9]{0,2} m(l|L)/', $des, $specs4);
        preg_match_all('/[1-9][0-9]{0,3}.{0,1}[0-9]{0,2}(oz.|fl. oz.)/', $des, $specs5);
        preg_match_all('/Lens measures approx\s{0,2}[1-9][0-9]{0,3}.{0,1}[0-9]{0,2}(mm|"|in|inch)/', $des, $specs6);
        preg_match_all('/Bridge measures approx\s{0,2}[1-9][0-9]{0,3}.{0,1}[0-9]{0,2}(mm|"|in|inch)/', $des, $specs7);
        preg_match_all('/Arm measures approx\s{0,2}[1-9][0-9]{0,3}.{0,1}[0-9]{0,2}(mm|"|in|inch)/', $des, $specs8);
        preg_match_all('/Frame\s{0,2}[A-Za-z]+\s{0,2}[1-9][0-9]{0,3}.{0,1}[0-9]{0,2}(mm|"|in|inch)/', $des, $specs9);
        preg_match_all('/Arm\s{0,2}[A-Za-z]+\s{0,2}[1-9][0-9]{0,3}.{0,1}[0-9]{0,2}(mm|"|in|inch)/', $des, $specs10);
        preg_match_all('/Bridge\s{0,2}[A-Za-z]+\s{0,2}[1-9][0-9]{0,3}.{0,1}[0-9]{0,2}(mm|"|in|inch)/', $des, $specs11);
        preg_match_all('/Lens\s{0,2}[A-Za-z]+\s{0,2}[1-9][0-9]{0,3}.{0,1}[0-9]{0,2}(mm|"|in|inch)/', $des, $specs12);
        $specs[0] = array_merge($specs[0], $specs1[0], $specs2[0], $specs3[0], $specs4[0], $specs5[0], $specs6[0], $specs7[0], $specs8[0]);
        $specs[0] = array_merge($specs[0], $specs9[0], $specs10[0], $specs11[0], $specs12[0]);
        if (!empty($specs[0])) {
            $specs = array_unique($specs[0]);
            $spec = array(
                'Lens measures approx' => '镜片宽约',
                'Bridge measures approx' => '鼻梁宽约',
                'Arm measures approx' => '镜腿长约',
                'Brim measures approx' => '镜面宽约',
                'Lens Width' => '镜片宽',
                'Bridge Width' => '鼻梁宽',
                'Arm length' => '镜腿长',
                'Temple length' => '镜腿长',
                'Frame Width' => '镜框宽',
                'Frame Height' => '镜框高',
                'Frame Length' => '镜框长',
                'Lens' => '镜片',
                'Bridge' => '鼻梁',
                'Arm' => '镜腿',
                'Temple' => '镜腿',
                'Frame' => '镜框',
                'length' => '长',
                'width' => '宽',
                'Height' => '高',
                'depth' => '深',
                'ml' => '毫升',
		'oz' => '盎司',
                'fl.oz' => '液量盎司',
                'inch' => '英寸',
                '"' => '英寸',
                'in' => '英寸',
                'H' => '高',
                'W' => '宽',
                'D' => '深',
                'L' => '长',
            );
            $keys = array_keys($spec);
            foreach ($specs as $k => $v) {
                foreach ($keys as $m => $n) {
                    $pos = stripos($v, $n);
                    if ($pos || ($pos === 0)) {
                        $v = str_ireplace($n, $spec[$n], $v);
                        break;
                    }
                }
                $specs[$k] = $v;
            }
            $specs = implode(',', $specs);
            if (!empty($specs)){
                $property[] = array(
                    'name' => '规格',
                    'value' => str_ireplace('"', '英寸', $specs),
                );
            }
        }
        // feature
        $mapping = array(
            'Dyed' => '扎染/染色',
            'Print' => '印花',
            'Embroider' => '绣花/刺绣',
            'Regular Fit' => '标准版型',
            'Loose Fit' => '宽松版型',
            'Tight Fit' => '紧身版型',
            'Skinny Fit' => '贴身版型',
            'Lace' => '蕾丝',
	    'Woven' => '梭织',
        );
        $feature = $this->findKey($des, $mapping);
        if (!empty($feature)){
            $property[] = array(
                'name' => '特性',
                'value' => $feature,
            );
        }

        $property = json_encode($property);
        $property = addslashes($property);

        return $property;
    }

    private function esDoc($id, $data, $type='update')
    {
        $redata = array(
            'type' => $type,
            'condition' => $id,
            'data' => $data
        );
        $this->curl_es($redata);
    }

    private function curl_es($data, $url = 'api/coru') // test server
    {
	if ($this->database == 'test') {
	    $url .= '_test';
 	}
        if (isset($data['data']['create_time'])) {
            $data['data']['create_time'] = time();
        }
        $data = array('data' => $data);
        $data = json_encode($data);
        $url = 'http://10.26.95.72/index.php/'.$url;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=utf-8',
            'Content-Length:'.strlen($data)
        ));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
    }

    private function findKey ($des, $mapping=array()) {
        $p = [".",",",":",";","!"," ","'",'"'];
        $keywords = '';
        $keys = array_keys($mapping);
        for ($i=0;$i<count($keys);$i++) {
                $str = $des;
                do {
                        $pos = stripos($str, $keys[$i]);
                        if ($pos || ($pos === 0)) {
                                $length = strlen($keys[$i]);
                                if ((($pos === 0) && in_array($str[$pos+$length], $p)) || (in_array(@$str[$pos-1], $p) && ($pos+$length) == strlen($str)) || (in_array(@$str[$pos-1], $p) && in_array($str[$pos+$length], $p))) {
                                        if (!strpos($keywords, $mapping[$keys[$i]])) {
                                                $keywords .= ' '.$mapping[$keys[$i]];
                                                break;
                                        }
                                }
                                $str = substr($str, $pos+$length);
                                $pos = true;
                        }
                } while ($pos && !empty($str));
        }

        return $keywords;
    }

    private function ex_album($id, $imgs)
    {
	$position = 0;
        $cover_img = '';
        $images = DB::connection($this->database)->table('product_album')->where('product_id', $id)->get(); // get old images
	if(isset($images[0])){
            $old_imgs = [];
            $album = [];
            $img_sum = count($imgs);
            foreach ($images as $k => $v) {
                $sum = 1;
                foreach ($imgs as $m => $n) {
                    if ($v->url != $n) {
                        if ($sum == $img_sum) {
                            $old_imgs[] = $v;
                        }
                        $sum++;
                    } else {
                        $album[] = array(
			   'id'       => $v->id,
                           'content'  => $v->content,
                           'url'      => $v->url,
                           'position' => $v->position,
		        );
                        break;
                    }
                }
            }
            $img_sum = count($images);
            foreach ($imgs as $m => $n) {
                $sum = 1;
                foreach ($images as $k => $v) {
                    if ($n != $v->url) {
                        if ($sum == $img_sum) {
                            $album[] = array(
                                'id'       => 0,
                                'content'  => '',
                                'url'      => $n,
                                'position' => 0,
			    );
                        }
                        $sum++;
                    } else {
                        if ($m == 0) {
                            $cover_img = $v->content;
                        }
                        break;
                    }
                }
            }
            if (is_array($old_imgs) && count($old_imgs)) {
                $oldids = array_column($old_imgs, 'id');
		DB::connection($this->database)->table('product_album')->whereIn('id', $oldids)->delete();
		foreach ($old_imgs as $k => $v) {
            	    $path = strchr($v->content, 'album/');
            	    if (!empty($path)) {
                	$oia[] = $path;
            	    }
        	}
        	if (isset($oia) && is_array($oia) && count($oia)) {
            	    Storage::disk('oss')->delete($oia);
        	}
            }
            $backup = '';
            if (is_array($album) && count($album)) {
		$albums = [];
                foreach ($album as $k => $v) {
                    if (empty($v['content']) || !strpos($v['content'], 'aliyuncs.com')) {
                        $ossurl = $this->up_img($v['url']);
                        if (!$ossurl) {
                            continue;
                        }
                        if (empty($cover_img)) {
			    $cover_img = $ossurl;
                        }
                        $albums[$position]['content'] = $ossurl;
                        $albums[$position]['type']       = 1;
                        $albums[$position]['position']   = $position;
                        $albums[$position]['product_id'] = $id;
                        $albums[$position]['url'] = $v['url'];
                        $position++;
                    } else {
                        if (empty($backup)) {
                            $backup = $v['content'];
                        }
                    }
                }
		if (is_array($albums) && count($albums)) {
                    DB::connection($this->database)->table('product_album')->insert($albums);
                }
            }
            if (empty($cover_img)) {
                $cover_img = $backup;
            }
        } else {
	    $album = array();
            foreach($imgs AS $img){
                if(!$img) continue;
                $url = $this->up_img($img);
            	if(!$url){
                    continue;
            	}
            	if($cover_img==''){
                    $cover_img = $url;
                }
            	$album[$position]['content']    = $url;
            	$album[$position]['type']       = 1;
            	$album[$position]['position']   = $position;
            	$album[$position]['product_id'] = $id;
            	$album[$position]['url'] = $img;
            	$position++;
            }
            if (is_array($album) && count($album)) {
                DB::connection($this->database)->table('product_album')->insert($album);
            }
        }
        return $cover_img;
    }

    private function up_img($url, $dir='album')
    {
        $uArr = explode('/', $url);
        $uArr = explode('.', $uArr[count($uArr)-1]);
        if( count($uArr)>1 ){
            $ext = $uArr[count($uArr)-1];
        }else{
            $ext = '';
        }
        $file_path = $dir.'/Y16'.md5($url) . ($ext?'.'.$ext:'');
        $url = $this->oss_up($url, $file_path);
        if($url){
            return $url;
        }
        return false;
    }

    private function oss_up($url, $file_path, $rep=0)
    {
	$default = 'http://product-album-n.oss-cn-hangzhou.aliyuncs.com/';
	$prefix = config('filesystems.disks.oss.path', $default);
	if ($rep == 0 && Storage::disk('oss')->exists($file_path)) {
	    return $prefix.$file_path;
	}
        $file_content = $this->down_url($url);
        if( $file_content!==false ){
	    $res = Storage::disk('oss')->put($file_path, $file_content);
	    if ($res) {
	        return $prefix.$file_path;
	    }
        }
        return false;
    }

    private function down_url($url) // download image
    {
	$proxy = $this->get_proxy();
        $ch = curl_init();
	if ($proxy) {
	    curl_setopt($ch, CURLOPT_PROXY, $proxy);
	}
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
	if ($proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
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

    private function get_proxy()
    {
	$proxy = DB::connection('mysql')->table('proxy')->select('id','ip','port','times')->where('status', 1)->orderby('get_at', 'desc')->first();
        DB::connection('mysql')->table('proxy')->where('id', $proxy->id)->update(['times' => $proxy->times + 1]);

	if (isset($proxy->ip)) {
            return $proxy->ip.':'.$proxy->port;
	} else {
	    return false;
	}
    }

    private function ex_category($category, $mapping)
    {
	if (is_array($mapping) && isset($mapping[$category])) {
	    return $mapping[$category];
	}
	
	return false;
    }

    private function getStoreCommondFee($shippings, $product, $rates, $weights)
    {
        foreach ($shippings as $x => $y) {
            $rate = $rates[$y->currency];
            if ($y->type == 1) {
                $direct_fee = $this->getStoreFee($y, $product, $rate, $weights); // direct mail Store fee
            }
            if ($y->type >= 2) {
                $fee = $this->getStoreFee($y, $product, $rate, $weights);
                if (isset($fee)) {
                    return ceil($fee);
                }
            }
        }
        if (isset($direct_fee)) {
            return ceil($direct_fee);
        }

        return 0;
    }

    private function getStoreFee($y, $product, $rate, $weights)
    {
        if ($y->count_type == 3) {
            $wei = $weights[$y->count_unit];
            if ((($y->high == 0) && (($product['weight']*$wei) >= $y->low)) || (($y->high != 0) && (($product['weight']*$wei) >= $y->low) && (($product['weight']*$wei) < $y->high))) {
                $store_fee  = $rate*$y->low_fee;
            }
        } elseif ($y->count_type == 2) {
            if ((($y->high == 0) && ($product['num'] >= $y->low)) || (($y->high != 0) && ($product['num'] >= $y->low) && ($product['num'] <= $y->high))) {
                $store_fee  = $rate*($y->base_fee + $y->low_fee * ($product['num'] - $y->low));
            }
        } else {
            if ((($y->high == 0) && ($product['price'] >= ($rate*$y->low))) || (($y->high != 0) && ($product['price'] >= ($rate*$y->low)) && ($product['price'] < ($rate*$y->high)))) {
                $store_fee  = $rate*$y->low_fee;
            }
        }
        if (isset($store_fee)) {
                return ceil($store_fee);
        }

        return 0;
    }

    private function desc_encode($desc_title, $desc_content)
    {
        $title_seperator = '[__T__]';
        $content_seperator = '[__C__]';
        $data = array();
        foreach ($desc_title as $key => $row) {
            $tarr = $title_seperator . $row . $content_seperator . $this->element($key, $desc_content, '');
            $data[] = $tarr;
        }
        return implode($data);
    }

    private function element($item, $array, $default = FALSE)
    {
        if ( ! isset($array[$item]) OR $array[$item] == "") {
            return $default;
        }

        return $array[$item];
    }

    private function curl_note($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $output = curl_exec($ch);
        curl_close($ch);
    }

    private function ex_color($name){
        $name = addslashes($name);
        $one = DB::connection($this->database)->select("SELECT color_id FROM color WHERE `name`= ?", [$name]);
        if (is_array($one) && count($one)) {
            return $one[0]->color_id;
        } else {
            $data['name'] = $name;
            $data['author'] = 0;
            return (int)$id = DB::connection($this->database)->table("color")->insertGetId($data);
        }
    }

    private function ex_size($name){
        $name = addslashes($name);
        $name = strtoupper($name);
        $one = DB::connection($this->database)->("SELECT size_id FROM size WHERE `name`= ?", [$name]);
        if (is_array($one) && count($one)) {
            return $one[0]->size_id;
        } else {
            $data['name'] = $name;
            return (int)$id = DB::connection($this->database)->table("size")->insertGetId($data);
        }
    }

    private function del_old_product_size($id, $sizes)
    {
	return DB::connection($this->database)->table('product_size')->where('product_id', '=', $id)->whereNotIn('size_id', $sizes)->delete();
    }

    private function add_product_size($id, $sizes)
    {
	$data = [];
	foreach ($sizes as $v) {
	    $data[] = ['product_id' => $id, 'size_id' => $v]; 
	}
	return DB::connection($this->database)->table('product_size')->insert($data);
    }

    private function del_old_product_color($id, $colors)
    {   
        return DB::connection($this->database)->table('product_color')->where('product_id', '=', $id)->whereNotIn('color_id', $sizes)->delete();
    }

    private function add_product_color($id, $colors)
    {
        $data = [];
        foreach ($colors as $v) {
            $data[] = ['product_id' => $id, 'color_id' => $v];
        }
        return DB::connection($this->database)->table('product_color')->insert($data);
    }
}
