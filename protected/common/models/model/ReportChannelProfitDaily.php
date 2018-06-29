<?php
namespace app\common\models\model;

use Yii;
use yii\db\ActiveRecord;

class ReportChannelProfitDaily extends BaseModel
{

    public static function getDb()
    {
        return \Yii::$app->db;
    }

    public static function tableName()
    {
        return '{{%report_channel_profilt_daily}}';
    }
}