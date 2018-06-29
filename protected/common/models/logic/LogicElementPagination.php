<?php

namespace app\common\models\logic;

use Yii;
use yii\data\ActiveDataProvider;

/*
 * 饿了么element UI的分页数据
 */
class LogicElementPagination
{
    /**
     */
    public function __construct()
    {
    }

    /**
     * 构造饿了么element UI的分页数据
     *
     * @param yii\db\Query $query 查询对象，如Product::find()->where($filter)
     * @param array $fields 返回的数据项
     * @param int $currentPage 当前分页数
     * @param int $perPage 每页记录数量
     * @param array $sort 排序规则
     *
     * @return array ['data'=>数组类型的数据, recordModels=>ActiveModel对象类型数据，pagination=>分页数据]
     *
     */
    public static function getPagination($query, $fields='', $currentPage=1, $perPage=15, $sort=['id'=>'DESC'])
    {
        $p = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page' => $currentPage,
            ],
            'sort' => [
                'defaultOrder' => $sort
            ],
        ]);

        $records = [];
        $recordModels = $p->getModels();
        if($fields){
            foreach ($recordModels as $k=>$d){
                $r = [];
                foreach ($fields as $f){
                    if(isset($d->$f)){
                        $r[$f] = $d->$f;
                    }
                }
                $records[$k] = $r;
            }
        }

        $pagination = $p->getPagination();
        $total = $pagination->totalCount;
        $lastPage = ceil($pagination->totalCount/$perPage);
        $from = ($currentPage-1)*$perPage;
        $to = $currentPage*$perPage;

        $data = [
            'data'=>$records,
            'recordModels'=>$recordModels,
            "pagination"=>[
                "total" =>  $total,
                "per_page" =>  $perPage,
                "current_page" =>  $currentPage,
                "last_page" =>  $lastPage,
                "from" =>  $from,
                "to" =>  $to
            ]
        ];

        return $data;
    }
}