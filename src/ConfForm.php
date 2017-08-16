<?php
/**
 * Created by PhpStorm.
 * User: zhuxiaofeng
 * Date: 2017/7/6
 * Time: 18:34
 */

namespace Optionalform;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class ConfForm
{

    public static function pp_test()
    {
        Route::any('form/get');
        echo __METHOD__ . 'test';
    }

    public static function createOptionalForm($dbConfig)
    {
        if (!Schema::connection($dbConfig)->hasTable('optional_form')) {
            Schema::connection($dbConfig)->create('optional_form', function (Blueprint $table) {
                $table->increments('id');// 主键 自增
                $table->string('title');//标题
                $table->text('describe');//描述
                $table->string('uid');
                $table->string('email');
                $table->integer('status');//状态
                $table->text('info');//配置数据
                $table->integer('version');//版本
                $table->timestamps(); // 自动创建的两个字段：created_at 和 updated_at
            });
        } else {
        }

        echo __METHOD__ . 'test';
    }

    /**
     * @param $data
     */
    public static function saveForm($data)
    {
        if (empty($data)) {
            return;
        }
        if ($data['title']) {
            $list['title'] = trim($data['title']);
        }
        if ($data['describe']) {

        }

    }

    /**
     * @param $str
     * @param $params
     *
     * 解析sql 并查询返回数据
     *
     */
    public static function analysis($str, $params)
    {
//        $str = '{"name":"DSP列表","path":"dsp/list","type":"pageable","sql":"select wk_plan.name,wk_plan.status from wk_plan join wk_unit on wk_plan.id=wk_unit.plan_id where 1 and (wk_plan.created_at >= {startDate} or wk_plan.created_at <= {endDate}) and ((wk_plan.user_id = {userId}) or (wk_plan.status = {status} and wk_plan.id in ({id})) ) order by wk_plan.created_at desc limit {pageNo}, {pageSize}","params":{"startDate":{"type":"string","rules":[["required"],["max","2017-07-12"],["min","2015-08-01"]]},"endDate":{"type":"string","rules":[["required"],["max","2017-07-12"],["min","2015-08-01"]]},"userId":{"type":"string","rules":[]},"status":{"type":"integer","rules":[]},"id":{"type":"integer","rules":[]},"srcId":{"type":"integer","rules":[]},"pageId":{"type":"integer","rules":[]},"dspId":{"type":"integer","rules":[]},"pageSize":{"type":"integer","rules":[["required"]]},"pageNo":{"type":"integer","rules":[["required"]]}}}';
        $info = json_decode($str, true);
        if (isset($info['sql'])) {
            $chart_sql = $info['sql'];
//            print_r($chart_sql);
//            echo "<pre>";

            $msg = self::params_validate($info['params'], $params);
            if (count($msg) > 0) {
                return $msg;
            }
            foreach ($info['params'] as $k => $v) {
                if (strpos($chart_sql, '{' . $k . '}')) {
                    if (isset($params[$k])) {
                        if ($v['type'] === 'string') {
                            $paramsCharts = "'" . $params[$k] . "'";
                        } else {
                            $paramsCharts = $params[$k];
                        }
                        $chart_sql = str_replace('{' . $k . '}', $paramsCharts, $chart_sql);
                    } else {
                        $split = preg_split('/(\bwhere\b|\bgroup\b|\border\b|\blimit\b)/i', $chart_sql, -1,
                            PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
                        $t = preg_split('/(\band\b|\bor\b|\b\(\b)/i', $split[2], -1,
                            PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

                        //and 和 or 分割之后的条件
                        foreach ($t as $match_k => $match_v) {
                            //处理没传参数的这个where 条件
                            if (strpos($match_v, '{' . $k . '}')) {
                                $match_v = str_replace(" ", '', $match_v);
                                if (substr(trim($match_v), 0, 1) != '(') { //第一个字符不是(
                                    if (isset($t[$match_k - 1])) {
                                        $t[$match_k] = self::params_not_exist_string($t[$match_k], $t[$match_k - 1]);
                                    } else {
                                        $t[$match_k] = self::params_not_exist_string($t[$match_k], $t[$match_k + 1]);
                                    }
                                } else {
                                    if (isset($t[$match_k + 1])) {
                                        $t[$match_k] = self::params_not_exist_string($t[$match_k], $t[$match_k + 1]);
                                    } else {
                                        $t[$match_k] = self::params_not_exist_string($t[$match_k], $t[$match_k - 1]);
                                    }
                                }
                            }
                        }

                        $split[2] = implode('', $t);
                        $chart_sql = implode('', $split);

                    }
                }
            }
//            echo "<pre>";
//            print_r($t);
//            echo "</pre>";
        }

//        echo "<pre>";
//        print_r($chart_sql);
//        echo "</pre>";

        $data = DB::connection($info['connection'])->select($chart_sql);
        return $data;
//        echo "<pre>";
//        print_r(adapt_from_db_array($data));
//        echo "</pre>";
//        return $data;

    }

    /**
     * @param $str
     * @param $last_key
     * @return string
     * 参数不存在  根据前后的 连接符 和 括号 替换where部分
     */
    public static function params_not_exist_string($str, $last_key)
    {
        switch ($last_key) {
            case 'and':
                $status = 'true';
                break;
            case 'or':
                $status = 'false';
                break;
        }
//        $str3= preg_replace('/\(.*?\)/', '', $str); // 去除单个条件的()
        $str3 = preg_replace('/(\([^\(\)]*\))/', '', $str); // 去除最小的()
//        echo"<pre>";
//        var_dump($str3);

        $right_str = preg_replace('/[^\)]/', '', $str3);  // 获取右括号的集合，需要保留
        $left_str = preg_replace('/[^\(]/', '', $str3);// // 获取左括号的集合，需要保留
        $where_str = '';
        if (!empty($left_str)) {
            $where_str .= ' ' . $left_str;
        }
        $where_str .= ' ' . $status . ' ';
        if (!empty($right_str)) {
            $where_str .= $right_str . ' ';
        }

        return $where_str;
    }


    /**
     * @param $params 参数验证rules
     * @param $data 参数数据
     * @return array
     */
    static function params_validate($params, $data)
    {
        $msg = [];
        foreach ($params as $k => $v) {
            if (!isset($v['rules']) || empty($v['rules'])) {
                continue;
            }
            foreach ($v['rules'] as $rule_k => $rule_v) {
                if (in_array('required', $rule_v) && !isset($data[$k])) {
                    array_push($msg, $k . '不能为空');
                }
            }
        }

        return $msg;
    }


//$a = 1;
//print($a);
//$s = "select wk_plan.name,wk_plan.status from wk_plan join wk_unit on wk_plan.id=wk_unit.plan_id where 1 and (wk_plan.created_at >= {startDate} or wk_plan.created_at <= {endDate}) and ((wk_plan.user_id = {userId}) or (wk_plan.status = {status} and wk_plan.id in ( select id from wk_unit where id={palnId})) ) order by wk_plan.created_at desc limit {pageNo}, {pageSize}";
//$split = preg_split('/(\bwhere\b|\bgroup\b|\border\b|\blimit\b)/i', $s, -1,
//PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
//echo "<pre>";
//print_r($split);
//echo "</pre>";
//$t = preg_split('/(\band\b|\bor\b|\b\(\b)/i', $split[2], -1,
//PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
//echo "<pre>";
//print_r($t);
//echo "</pre>";
//
//$params['startDate'] = "2017-07-01";
//$params['endDate'] = "2017-07-02";
//$params['pageSize'] = 50;
//$params['pageNo'] = 1;
}
