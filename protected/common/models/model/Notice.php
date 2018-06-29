<?php
namespace app\common\models\model;

/*
 * 公告表
 */
class Notice extends BaseModel
{
    //公告状态
    const STATUS_NOTPAY= 0;
    const STATUS_PAID = 10;


    //公告状态
    const ARR_STATUS = [
        self::STATUS_NOTPAY=>'不显示',
        self::STATUS_PAID=>'显示',
    ];
    public static function tableName()
    {
        return '{{%notice}}';
    }

}