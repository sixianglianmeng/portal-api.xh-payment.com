<?php
namespace app\common\models\model;

use Yii;
use yii\db\ActiveRecord;

class ReportRechargeDaily extends BaseModel
{

    public static function getDb()
    {
        return \Yii::$app->db;
    }

    public static function tableName()
    {
        return '{{%report_recharge_daily}}';
    }
}