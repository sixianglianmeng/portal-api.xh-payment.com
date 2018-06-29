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
    const types = [
        1=>'IP',
        2=>'客户端id',
        3=>'商户下uid',
    ];

    public static function tableName()
    {
        return '{{%user_blacklist}}';
    }

    public function behaviors() {
        return [TimestampBehavior::className(),];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [];
    }
}