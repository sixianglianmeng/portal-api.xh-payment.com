<?php

namespace app\common\models\model;

use app\components\Macro;
use Yii;
use app\common\models\model\BaseModel;

class LogSystem extends BaseModel
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%system_log}}';
    }

    public function attributeLabels()
    {
        return [
            'id'=>'自增ID',
            'level'=>'等级',
            'category'=>'分类',
            'log_time'=>'记录时间',
            'prefix'=>'前缀',
            'message'=>'详情',
        ];
    }
}
