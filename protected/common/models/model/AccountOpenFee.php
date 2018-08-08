<?php
namespace app\common\models\model;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/*
 * 商户开户费表
 */
class AccountOpenFee extends BaseModel
{
    const STATUS_UNPAID=0;
    const STATUS_PAID=1;
    const ARR_STATUS = [
        self::STATUS_UNPAID => '未缴纳',
        self::STATUS_PAID => '已缴纳',
    ];

    public static function tableName()
    {
        return '{{%account_open_fee}}';
    }

    public function behaviors() {
        return [TimestampBehavior::class];
    }


    /**
     * 是否需要支付开户费
     */
    public function needPay()
    {
        $paid = false;
        if($this->fee>0
            && $this->status != self::STATUS_PAID
        ){
            $paid = true;
        }

        return $paid;
    }
}