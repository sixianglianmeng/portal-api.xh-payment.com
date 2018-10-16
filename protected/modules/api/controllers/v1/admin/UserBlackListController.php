<?php
/**
 * Created by PhpStorm.
 * User: kk
 * Date: 2018/10/15
 * Time: 下午9:10
 */

namespace app\modules\api\controllers\v1\admin;


use app\common\models\model\UserBlacklist;
use app\components\Macro;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use yii\data\ActiveDataProvider;

class UserBlackListController extends BaseController
{
    /**
     * 黑名单管理
     * @return array
     * @throws \power\yii2\exceptions\ParameterValidationExpandException
     */
    public function actionList()
    {
        $type = ControllerParameterValidator::getRequestParam($this->allParams,'type',[],Macro::CONST_PARAM_TYPE_ARRAY,'封禁类型错误');
        $val = ControllerParameterValidator::getRequestParam($this->allParams,'val','',Macro::CONST_PARAM_TYPE_STRING,'封禁内容错误');
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,1000]);
        $query = UserBlacklist::find();
        if(!empty($type)){
            $query->andWhere(['type'=>$type]);
        }
        if(!empty($val)){
            $query->andWhere(['val'=>$val]);
        }
        if(!empty($dateStart)){
            $query->andWhere(['>=','created_at',strtotime($dateStart)]);
        }
        if(!empty($dateEnd)){
            $query->andWhere(['<=','created_at',strtotime($dateEnd)]);
        }
        $query->orderBy('created_at desc');
        //生成分页数据
        $p = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page' => $page-1,
            ],
        ]);
        $data['list'] = [];
        $data['typeOptions'] = UserBlacklist::ARR_TYPES;
        $data['total'] = 0;
        if(empty($p->getModels())){
            return ResponseHelper::formatOutput(Macro::SUCCESS,'',$data);
        }
        //分页数据
        $pagination = $p->getPagination();
        $data['total'] = $pagination->totalCount;
        foreach ($p->getModels() as $val){
            $tmp = [];
            $tmp['type'] = $val['type'];
            $tmp['type_str'] = UserBlacklist::ARR_TYPES[$val['type']];
            $tmp['val'] = $val['val'];
            $tmp['merchant_id'] = $val['merchant_id'] ?? '';
            $tmp['merchant_name'] = $val['merchant_id'] ?? '';
            $tmp['op_uid'] = $val['op_uid'] ?? '';
            $tmp['op_username'] = $val['op_username'] ?? '';
            $tmp['created_at'] = date("Y-m-d H:i:s" ,$val['created_at']);
            $data['list'][] = $tmp;
        }
        return ResponseHelper::formatOutput(Macro::SUCCESS,'',$data);
    }

    /**
     * 添加黑名单
     * @return array
     * @throws \power\yii2\exceptions\ParameterValidationExpandException
     */
    public function actionAdd()
    {
        $user = \Yii::$app->user->identity;
        $type = ControllerParameterValidator::getRequestParam($this->allParams,'type',null,Macro::CONST_PARAM_TYPE_INT,'封禁类型错误');
        $val = ControllerParameterValidator::getRequestParam($this->allParams,'val',null,Macro::CONST_PARAM_TYPE_STRING,'封禁内容错误');
        $merchant_id = ControllerParameterValidator::getRequestParam($this->allParams, 'merchant_id', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'商户编号错误',[0,32]);
        $merchant_name = ControllerParameterValidator::getRequestParam($this->allParams, 'merchant_name', '',Macro::CONST_PARAM_TYPE_USERNAME,'商户账号错误',[2,16]);
        $userBlack = UserBlacklist::find()->where(['val'=>$val])->one();
        if(!empty($userBlack)){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'封禁内容已存在');
        }
        $userBlackObj = new UserBlacklist();
        $userBlackObj->type = $type;
        $userBlackObj->val = $val;
        if(!empty($merchant_id)) $userBlackObj->merchant_id = $merchant_id;
        if(!empty($merchant_name)) $userBlackObj->merchant_name = $merchant_name;
        $userBlackObj->op_uid = $user->id;
        $userBlackObj->op_username = $user->username;
        $userBlackObj->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS,'添加成功');
    }

}