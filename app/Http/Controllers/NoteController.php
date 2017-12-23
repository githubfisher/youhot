<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\UserMsg;
use App\Models\Message;
use App\Models\ReturnBack;
use JPush\Client as JPush;
use DB;

class NoteController extends Controller
{
    protected $options = [
	'platform' => 'all',//['ios'],
	'audience' => 'all'
    ];

    public function postNote(Request $request)
    {
	$type = $request->input('type');
	switch ($type) {
	    case 1:
		$this->dealWithNote($request);
		break;
	    case 2:
		$this->dealWithPriceCut($request);
                break;
	    case 3:
		$this->dealWithCustomerService($request);	
                break;
	    case 4:
		$this->dealWithBack($request);
                break;
	    default:
                break;
	}
    }

    private function insertMsg($options)
    {
	$msg = new Message;
	$msg->ctype = empty($options['extras']['ctype']) ? 0 : $options['extras']['ctype'];
	$msg->tid = $options['extras']['id'];
	$msg->type = $options['extras']['type'];
	$msg->title = $options['title'];
	$msg->content = $options['content'];
	$msg->create_at = time();
	$msg->available_end = time()+7*86400;
	if (isset($options['extra'])) {
	    $msg->extra = $options['extra'];
	}
	$msg->save();

	return $msg->id;
    }

    private function insertUserMsg($users, $msgId, $type)
    {
	if (is_array($users) && count($users)) {
	    foreach ($users as $v) {
		$userMsgs[] = [
		    'userid' => $v,
		    'msgid' => $msgId,
		    'type' => $type,
		    'create_at' => time()
		];
	    }
	}
	DB::table('user_msg')->insert($userMsgs);
    }

    private function dealWithNote($request)
    {
	$option = $this->options;
        $option['title'] = $request->input('title');
        $option['content'] = $request->input('content');
        $option['extras'] = ['id' => $request->input('id'), 'type' => $request->input('type'), 'ctype' => $request->input('ctype')];
	$msgId = $this->insertMsg($option);
        $this->jpush($option);
    }

    private function dealWithPriceCut($request)
    {
	$id = $request->input('id');
	$users = Product::find($id)->users->toArray();
	$buyers = Product::find($id)->buyers->toArray();
	$ids = array_merge(array_column($users, 'userid'), array_column($buyers, 'userid'));
	$ids = array_unique($ids);
	foreach ($ids as $k => $i) {
	    $ids[$k] = (string)$i;
	}
	$product = Product::where('id','=',$id)->get();
	$option = $this->options;
        $option['title'] = '降价啦！手慢没哦！刚打折的'.$product[0]['title'];
        $option['content'] = '降价啦！手慢没哦！刚打折的'.$product[0]['title'];
        $option['extras'] = ['id' => $id, 'type' => $request->input('type')];
	$option['audience'] = false;
	$option['alias'] = $ids;
	$option['extra'] = '原：'.$request->input('presale_price').'，现：'.$request->input('price');
	$msgId = $this->insertMsg($option);
	if (count($ids)) {
	    $this->insertUserMsg($ids, $msgId, $request->input('type'));
            $this->jpush($option);
	}
    }

    private function dealWithCustomerService($request)
    {
	$id = $request->input('id');
	$msg = Message::where('type', $request->input('type'))->get()->toArray();
	$option = $this->options;
        $option['title'] = $msg[0]['title'];
        $option['content'] = $msg[0]['content'];
        $option['extras'] = ['id' => $msg[0]['id'], 'type' => $request->input('type')];
        $option['audience'] = false;
        $option['alias'] = (string)$id;
	$this->insertUserMsg([$id], $msg[0]['id'], $request->input('type'));
	$this->jpush($option);
    }

    public function dealWithBack($request)
    {
	$ctype = $request->input('ctype');
	$option = $this->options;
	if (isset($ctype) && ($ctype == 1)) { 
	    $userid = $request->input('userid');
	    $option['title'] = $request->input('title');
            $option['content'] = $request->input('content');
            $option['extras'] = ['id' => $request->input('tid'), 'type' => $request->input('type'), 'ctype' => $request->input('ctype')];
            $option['audience'] = false;
            $option['alias'] = (string)$userid;
	} else {
	    $id = $request->input('id');
	    $user = ReturnBack::find($id)->user;
	    $userid = $user->userid;
            $option['title'] = '售后处理消息';
            $option['content'] = '您有一笔售后单处理完成！';
            $option['extras'] = ['id' => $id, 'type' => $request->input('type')];
            $option['audience'] = false;
            $option['alias'] = (string)$userid;
	}
        $msgId = $this->insertMsg($option);
        $this->insertUserMsg([$userid], $msgId, $request->input('type'));
        $this->jpush($option);
    }

    private function jpush($options)
    {
        $appKey = 'e1a67c2a74ad820256bc70c5';
        $appSecret = 'f18daaaef877f238e41d0b28';
        $client = new Jpush($appKey, $appSecret);
	if ($options['audience'] == 'all') {
	    $push = $client->push()
		->setPlatform($options['platform'])
		->options(['apns_production'=>true])//苹果生产环境
		->addAllAudience()
		->iosNotification($options['title'], [
                        'sound' => 'sound',
                        'badge' => '+1',
                        'extras' => $options
                ])
		->send();
	} else {
	    $push = $client->push()
                ->setPlatform($options['platform'])
		->options(['apns_production'=>true])//苹果生产环境
                ->addAlias($options['alias'])
		->iosNotification($options['title'], [
      			'sound' => 'sound',
      			'badge' => '+1',
      			'extras' => $options
    		])
                ->send();
	}
    }

    public function push(Request $request)
    {
	$options['title'] = $request->input('title');
	$options['content'] = $request->input('content');
	$options['audience'] = $request->input('audience');
	$options['platform'] = $request->input('platform');
	$userids= json_decode($request->input('alias'), true);
	foreach ($userids as $k => $i) {
            $ids[$k] = (string)$i;
        }
	$options['alias'] = $ids;

	$this->jpush($options);
    }
}
