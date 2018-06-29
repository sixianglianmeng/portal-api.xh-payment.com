<?php
namespace app\common\models\form;

use Yii;
use yii\base\Model;

/**
 * BasePayment form
 */
class BaseForm extends Model
{
    /**
     * 获取所有错误字符串
     *
     * @return string
     * @author booter.ui@gmail.com
     */
    public function getErrorsString(){

        $rawErrors = $this->getFirstErrors();

        $errors = [];
        foreach ($rawErrors as $el=>$err){
            if(is_array($err)){
                $errors[] = implode(';',$err);
            }
            elseif(is_string($err)){
                $errors[] = $err;
            }else{
                $errors[] = json_encode($err,JSON_UNESCAPED_UNICODE);
            }
        }

        $errString =  implode(';',$errors);
        return $errString;
    }
}

