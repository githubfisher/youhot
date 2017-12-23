<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mongo;

class DataTest extends Controller
{
    public function get(Request $request)
    {
	$where = json_decode($request->input('where'), true);
	if (is_array($where) && count($where)) {
            if (is_array($where[0])) {
                foreach ($where as $k => $v) {
                    if (count($v) == 2) {
                        list($a, $b) = $v;
                        $res = Mongo::where($a, $b);
                    } else {
                        list($a, $b, $c) = $v;
                        $res = Mongo::where($a, $b, $c);
                    }
                }
            } else {
                if (count($where) == 2) {
                    list($a, $b) = $where;
                    $res = Mongo::where($a, $b);
                } else {
                    list($a, $b, $c) = $where;
                    $res = Mongo::where($a, $b, $c);
                }
            }
            $res = $res->get();
        } else {
	    $res = Mongo::get();
        }
	$res = ['res' => 0, 'hit' => 'success', 'list' => $res];

	return $res;
    }

    public function create(Request $request)
    {
	$res['res'] = 0;
	$res['hit'] = 'success';

        return $res;
    }

    public function update(Request $request)
    {
	$where = json_decode($request->input('where'), true);
	$data = json_decode($request->input('data'), true);
	if (is_array($where) && count($where) && is_array($data) && count($data)) {
	    if (is_array($where[0])) {
		foreach ($where as $k => $v) {
		    if (count($v) == 2) {
			list($a, $b) = $v;
		        $res = Mongo::where($a, $b);
		    } else {
			list($a, $b, $c) = $v;
			$res = Mongo::where($a, $b, $c);
		    }
		}
	    } else {
		if (count($where) == 2) {
		    list($a, $b) = $where;
		    $res = Mongo::where($a, $b);
		} else {
		    list($a, $b, $c) = $where;
		    $res = Mongo::where($a, $b, $c);
		}
	    }
	    $res = $res->update($data);
	    $res = ['res' => 0, 'hit' => 'success', 'update_sum' => $res];	
	} else {
	    $res = ['res' => 1, 'hit' => 'fail'];
	}

	return $res;
    }

    public function delete(Request $request)
    {
	$where = json_decode($request->input('where'), true);
        if (is_array($where) && count($where)) {
            if (is_array($where[0])) {
                foreach ($where as $k => $v) {
                    if (count($v) == 2) {
                        list($a, $b) = $v;
                        $res = Mongo::where($a, $b);
                    } else {
                        list($a, $b, $c) = $v;
                        $res = Mongo::where($a, $b, $c);
                    }
                }
            } else {
                if (count($where) == 2) {
                    list($a, $b) = $where;
                    $res = Mongo::where($a, $b);
                } else {
                    list($a, $b, $c) = $where;
                    $res = Mongo::where($a, $b, $c);
                }
            }
            $res = $res->delete();
            $res = ['res' => 0, 'hit' => 'success', 'delete_sum' => $res];
        } else {
            $res = ['res' => 1, 'hit' => 'fail'];
        }

        return $res;
    }
}
