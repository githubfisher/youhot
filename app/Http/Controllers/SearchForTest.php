<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Ixudra\Curl\Facades\Curl;

class SearchForTest extends Controller
{
    protected $url = 'http://localhost:9200/test/test';

    public function search(Request $request)
    {
        $q = $request->input('q');
        $category = json_decode($request->input('category'), true);
        $brand = json_decode($request->input('brand'), true);
        $discount = json_decode($request->input('discount'), true);
        $price = json_decode($request->input('price'), true);
        $store = json_decode($request->input('store'), true);
        $offset = $request->input('of');
        $limit = $request->input('lm');
        $sort = json_decode($request->input('sort'), true);
        $count = $request->input('count');
	$status = $request->input('status');
	//$range = json_decode($request->input('range'), true);

        if (empty($q)) {
            $data = [
                'query' => [
                    'bool' => []
                ]
            ];
        } else {
	    $splitWords = $this->scws($q);
            $data = [
                'query' => [
                    'bool' => [
                        'must' => [
                            'multi_match' => [
                                'fields' => [
                                    'title',
                                    'description',
				    'keywords',
				    'author_nickname',
                                ],
				'type' => 'best_fields',
                                'query' => $splitWords,
                            ]
                        ],
			'should' => [
			    'match' => [
                                'title' => [
                                    'query' => $q,
                                    'type' => 'phrase',
                                ]
                            ]
			]
                    ]
                ]
            ];
        }
        $data = $this->dealWith($data, 'category', $category);
        $data = $this->dealWith($data, 'author', $brand);
        $data = $this->dealWith($data, 'price_id', $price);
        $data = $this->dealwith($data, 'discount_id', $discount);
        $data = $this->dealWith($data, 'store', $store);
        if (!empty($offset)) {
            $data['from'] = $offset;
        }
        if (!empty($limit)) {
            $data['size'] = $limit;
        }
	//if (is_array($range) && count($range)) {
	    //$data['query']['bool']['filter']['range'][$range[0]][$range[1]] = $range[2];
  	//} 
        if (is_array($sort) && count($sort)) {
	    foreach ($sort as $s => $t) {
                $data['sort'][$t[0]]['order'] = $t[1]; 
	    }
            $data['sort']['_score'] = ['order' => 'desc'];
        }
        if (!empty($count)) {
            $data['aggs']['category_count']['terms']['field'] = 'cate3';
        }
	if (empty($status)) {
	    $data['query']['bool']['filter']['bool']['must'][]['term']['status'] = 1;
	} else {
	    $data['query']['bool']['filter']['bool']['must'][]['term']['status'] = $status;
        }

        //$data = json_encode($data);
	//echo $data;die;

        $url = $this->url."/_search?pretty";
        $response = Curl::to($url)
            ->withData($data)
            ->asJsonRequest()
            ->post();
        
        return $response;
    }

    public function coru(Request $request)
    {
        $result = '';
        $data = $request->input("data");
	if (!is_array($data)) {
	    $data = json_decode($data, true);
	}
        if (is_array($data)) {
            switch ($data['type']) {
                case 'create':
                    $result = $this->createDoc($data['condition'], $data['data']);
                    break;
                case 'update':
                    $result = $this->updateDoc($data['condition'], $data['data']);
                    break;
		case 'update_range':
		    $result = $this->updateRange($data['condition'], $data['data']);
		    break;
                case 'delete_range':
                    $result = $this->deleteRange($data['condition']);
                    break;
                case 'delete':
                    $result = $this->deleteDoc($data['condition']);
                    break;
                default:
                    break;
            }
        }
        return $result;
    }

    private function dealWith($data, $column, $param)
    {
        if (is_array($param) && !empty($param[0])) {
            if (count($param) > 1) {
                $data['query']['bool']['filter']['bool']['must'][]['terms'][$column] = $param;
            } else {
                $data['query']['bool']['filter']['bool']['must'][]['term'][$column] = $param[0];
            }
        } else if (!is_array($param) && !empty($param)) {
            $data['query']['bool']['filter']['bool']['must'][]['term'][$column] = $param;
        }

        return $data;
    }

    private function updateDoc($id, $data)
    {
	if (!isset($data['doc'])) {
	    $new_data['doc'] = $data;
	    $data = $new_data;
	}
    	$url = $this->url."/$id/_update?pretty";
    	$response = Curl::to($url)
    		->withData($data)
    		->asJsonRequest()
    		->post();
    	$response = json_decode($response, true);

    	return $response;
    }

    private function createDoc($id, $data)
    {
    	$url = $this->url."/$id/?pretty";
        $response = Curl::to($url)
            ->withData($data)
            ->asJsonRequest()
            ->put();
        $response = json_decode($response, true);

        return $response;
    }

    private function deleteDoc($id)
    {
        $url = $this->url."/$id/?pretty";
        $reponse = Curl::to($url)->delete();

        return $reponse;
    }

    public function getSum()
    {
        $url = $this->url."/_search?q=*&pretty";
        $response = Curl::to($url)
            ->post();

        return $response;
    }

    public function getIndices()
    {
        $url = "127.0.0.1:9200/_cat/indices?v";
        $response = Curl::to($url)
            ->get();

        return $response;
    }
    
    public function update(Request $request)
    {
    	$id = $request->input('id');
    	$data = json_decode($request->input('data'));
        $data = array(
            'doc' => $data
        );
        $result = $this->updateDoc($id, $data);

    	return $result;
    }

    public function create(Request $request)
    {
    	$data = $request->input('data');
    	$data = json_decode($data, true);
    	$id = $data['id'];
    	$result = $this->createDoc($id, $data);

        return $result;
    }

    public function delete(Request $request)
    {
    	$id = $request->input('id');
        $result = $this->deleteDoc($id);

        return $result;
    }

    public function getDocument(Request $request)
    {
        $id = $request->input('id');
        $result = $this->getDoc($id);

        return $result;
    }

    private function getDoc($id)
    {
        $url = $this->url."/$id?pretty";
        $response = Curl::to($url)->get();

        return $response;
    }

    private function updateRange($condition, $update_data)
    {
        $data['query']['bool']['must'][]['term']['category'] = $condition['category'];
        $data['query']['bool']['must'][]['term']['author'] = $condition['author'];
        $data['query']['bool']['filter']['range']['rank']['lte'] = $condition['rank'];
        $data['query']['bool']['filter']['range']['rank']['gte'] = 0;
        $data['script']['inline'] = 'ctx._source.status += '.$update_data['status'];
        $url = $this->url."/_update_by_query?conflicts=proceed";
        $response = Curl::to($url)
            ->withData($data)
            ->asJsonRequest()
            ->post();

        return $response;
    }

    private function scws($word)
    {
        $so = scws_new();         //创建对象
        $so->set_charset('utf8'); //设定UTF8
        $so->set_ignore(true);   //过滤符号
        $so->set_duality(true);  //设定是否将闲散文字自动以二字分词法聚合
        $so->send_text($word);
        $words = [];
        while ($tmp = $so->get_result()){
            $words[] = $tmp;
        }
        $so->close();
        if (is_array($words) && count($words)) {
            $word = '';
            foreach ($words as $key => $value) {
                foreach ($value as $x => $y) {
                    $word .= $y['word'].' ';
                }
            }
            $word = rtrim($word, ' ');
        }
        
        return $word;
    }
    
    private function deleteRange($condition)
    {
	$data['query']['match']['category'] = $condition;
	$url = $this->url."/_delete_by_query";
	$response = Curl::to($url)
	    ->withData($data)
	    ->asJsonRequest()
	    ->post();

	return $response;
    }

    public function mapping()
    {
	$url = $this->url.'/_mapping?pretty';
	$response = Curl::to($url)
	    ->get();

	return $response;
     }

     public function splitWords(Request $request)
     {
	$q = $request->input('q');
	$words = $this->scws($q);

	return ['words' => $words];
     }
}
