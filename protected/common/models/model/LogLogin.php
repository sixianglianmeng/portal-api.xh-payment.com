<?php

    namespace app\common\models\model;

    use app\components\Macro;
    use Yii;
    use app\common\models\model\BaseModel;

    class LogLogin extends BaseModel
    {
        /**
         * @inheritdoc
         */
        public static function tableName()
        {
            return '{{%log_login}}';
        }

    }
