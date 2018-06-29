<?php
/**
 * Created by PhpStorm.
 * User: kk
 * Date: 2018/5/5
 * Time: 下午10:28
 */
namespace app\common\models\model;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
/*
 * 调单追踪
 */
class Track extends BaseModel
{
    //调单类型
    const TYPE_COMPLAIN= 1;
    const TYPE_ASSIST = 2;
    const TYPE_OTHER = 3;

//    调单类型
    const ARR_TPYE = [
        self::TYPE_COMPLAIN=>'投诉',
        self::TYPE_ASSIST=>'协查',
        self::TYPE_OTHER=>'其他',
    ];

    //调单状态
    const STATUS_NOTHANDLE = 1;
    const STATUS_HANDLE = 2;
    const STATUS_HANDLED = 3;

    const ARR_STATUS =[
        self::STATUS_HANDLE => '处理中',
        self::STATUS_NOTHANDLE => '未处理',
        self::STATUS_HANDLED => '已处理',
    ];

    const ARR_PARENTTYPE = [
        'order' => '收款订单',
        'remit' => '结算订单',
    ];

    public static function getDb()
    {
        return \Yii::$app->db;
    }

    public static function tableName()
    {
        return '{{%track}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [];
    }
    /**
     * 检查订单是否有调单
     */
    public static function checkTrack($parentIds,$parentType){
        $filter = ['parent_type'=>$parentType];
        $query = self::find()->where($filter);
        $query->andWhere(['in','parent_id',$parentIds]);
        $query->groupBy('parent_id');
        return $query->select('parent_id,count(id) as num')->asArray()->all();
    }

}