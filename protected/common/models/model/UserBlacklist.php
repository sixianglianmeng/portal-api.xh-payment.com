<?php
namespace app\common\models\model;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
/*
 * 黑名单用户
 */
class UserBlacklist extends BaseModel
{
    //黑名单类型
    const ARR_TYPES = [
        1=>'IP',
        2=>'客户端id',
        3=>'银行卡号',
    ];

    public static function tableName()
    {
        return '{{%user_blacklist}}';
    }

    public function behaviors() {
        return [TimestampBehavior::class];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [];
    }


    /**
     * 检查订单是否在黑名单
     */
    public static function checkOrderNoInBalcklist($orderNoList,$orderType){
        $filter = ['order_no'=>$orderNoList,'order_type'=>$orderType];
        $query = self::find()->where($filter);
        $query->groupBy('order_no');
        return $query->select('order_no,count(id) as num')->asArray()->all();
    }
}