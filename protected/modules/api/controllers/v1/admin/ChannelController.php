<?php
namespace app\modules\api\controllers\v1\admin;

use app\common\exceptions\OperationFailureException;
use app\common\models\model\Channel;
use app\common\models\model\ChannelAccount;
use app\common\models\model\ChannelAccountRechargeMethod;
use app\common\models\model\MerchantRechargeMethod;
use app\common\models\model\User;
use app\common\models\model\UserPaymentInfo;
use app\components\Macro;
use app\components\RpcPaymentGateway;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use app\modules\api\controllers\BaseController;
use yii\db\Query;
use yii\helpers\ArrayHelper;

class ChannelController extends BaseController
{
    //基础查询参数，例如查询订单只能查询自己的
    protected $baseFilter = [];

    public function behaviors()
    {
        $parentBehaviors = parent::behaviors();
        //验证码不需要token验证
        $behaviors = [];
        $behaviors = \yii\helpers\ArrayHelper::merge($parentBehaviors, $behaviors);

        return $behaviors;
    }

    /**
     * 前置action
     *
     * @author bootmall@gmail.com
     */
    public function beforeAction($action){
        $ret =  parent::beforeAction($action);

        return $ret;
    }

    /**
     * 渠道列表
     */
    public function actionList(){
        $name = ControllerParameterValidator::getRequestParam($this->allParams, 'name','',Macro::CONST_PARAM_TYPE_STRING,'渠道名称错误');
        $query = Channel::find();
        if($name){
            $query->andWhere(['like','name',$name]);
        }
        $list = $query->asArray()->all();
        foreach ($list as $key=>$val){
            if($val['pay_methods']){
                $tmp = json_decode($val['pay_methods'],true);
                foreach ($tmp as $k => $v){
                    $val['pay_methods_ids'][]= $k;
                }
            }
            $list[$key] = $val;
        }
        $payMethodsOptions = Channel::ARR_METHOD;
        $data = [
            'data' => $list,
            'payMethodsOptions' => $payMethodsOptions
        ];
        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }

    /**
     * 支付渠道账户列表
     *
     */
    public function actionAccountList()
    {
        $query = ChannelAccount::find();
        $accounts = $query->where( ['deleted_at'=>0])->all();

        //格式化返回记录数据
        $records=[];
        $channelAccountStatusArr = ChannelAccount::ARR_STATUS;
        $channelSecretTemplates = ArrayHelper::map(Channel::find()->select('id,app_secrets_template')->asArray()->all(), 'id', 'app_secrets_template');

        foreach ($accounts as $i=>$a){
            $records[$i] = $a->toArray();
            $records[$i]['id'] = $a->id;
            $records[$i]['channel_name'] = $a->channel_name;
            $records[$i]['parent_name'] = $a->channel->name;
            $records[$i]['channel_id'] = $a->channel_id;
            $records[$i]['merchant_id'] = $a->merchant_id;
            $records[$i]['merchant_account'] = $a->merchant_account;
            $records[$i]['remit_fee'] = $a->remit_fee;
            $records[$i]['app_secrets'] = json_decode($a->app_secrets,true);
            $records[$i]['app_secrets_template'] = json_decode($channelSecretTemplates[$a->channel_id],true);
            $records[$i]['recharge_quota_pertime'] = $a->recharge_quota_pertime;
            $records[$i]['remit_quota_pertime'] = $a->remit_quota_pertime;
            $records[$i]['remit_quota_perday'] = $a->remit_quota_perday;
            $records[$i]['recharge_quota_perday'] = $a->recharge_quota_perday;
            $records[$i]['min_recharge_pertime'] = $a->min_recharge_pertime;
            $records[$i]['min_remit_pertime'] = $a->min_remit_pertime;
            $records[$i]['pay_methods'] = $a->getChannelMethodsArr();
            $records[$i]['status'] = $a->status;
            $records[$i]['created_at'] = date('Y-m-d H:i:s',$a->created_at);
            $records[$i]['app_id'] = $a->app_id;
            $records[$i]['statusName'] = $channelAccountStatusArr[$a->status];
            $records[$i]['visible'] = $a->visible;
            $records[$i]['visible_str'] = ChannelAccount::ARR_VISIBLE[$a->visible]??'-';
            $records[$i]['balance_alert_threshold'] = $a->balance_alert_threshold;
        }
        $data['list'] = $records;
        $data['channelAccountStatusOptions'] = ChannelAccount::ARR_STATUS;
        $channelOptions = ArrayHelper::map(Channel::getALLChannel(), 'id', 'name');
        $data['channelOptions'] = $channelOptions;
        $data['methodsOptions'] = Channel::ARR_METHOD;
        $data['channelSecretTemplates'] = $channelSecretTemplates;
        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }

    /**
     * 通道余额管理
     * @role admin
     */
    public function actionBalanceList()
    {

        $ret = RpcPaymentGateway::call('/channel/update-all-account-balance',[]);

        if($ret['code']==0){
            $msg ="查询成功! 目前查询的为" . date('Ymd H:i:s', $ret['data']['lastUpdate']) . "更新的余额";
        }else{
            $msg ="更新失败! 目前查询的为" . date('Ymd H:i:s', $ret['data']['lastUpdate']) . "更新的余额";
        }

        $query = (new Query())
            ->from(ChannelAccount::tableName())
            ->select(['id','channel_id','channel_name','balance','frozen_balance']);

        $accounts = $query->all();

        $data['channel_total_available_balance'] = 0;
        $data['channel_total_frozen_balance'] = 0;
        //格式化返回记录数据
        $records=[];
        foreach ($accounts as $i=>$a){
            $records[$i] = $a;
            $records[$i]['total_balance'] = bcadd( $records[$i]['balance'], $records[$i]['frozen_balance'],2);
            $data['channel_total_available_balance'] = bcadd($data['channel_total_available_balance'],$a['balance'],6);
            $data['channel_total_frozen_balance'] = bcadd($data['channel_total_balance'],$a['frozen_balance'],6);
        }
        $data['channel_total_balance']  = bcsub($data['channel_total_available_balance'],$data['channel_total_frozen_balance'],2);
        $data['list'] = $records;

        //冻结总余额
        $data['merchant_total_balance']['frozen_balance'] = (new \yii\db\Query())
            ->select(['SUM(frozen_balance) AS frozen_balance'])
            ->from(User::tableName())
            ->scalar();
        //大于0的真实总余额 xinhuipay不统计(sh倒账到新汇
        $data['merchant_total_balance']['balance'] = (new \yii\db\Query())
            ->select(['SUM(balance) AS balance'])
            ->from(User::tableName())
            ->where(['>','balance',0])
            ->where(['!=','id',3011804])
            ->scalar();
        //负总余额
        $data['merchant_total_negative_balance'] = (new \yii\db\Query())
            ->select(['SUM(balance) AS balance'])
            ->from(User::tableName())
            ->where(['<','balance',0])
            ->scalar();

        $data['total_profit'] = bcsub($data['channel_total_balance'],$data['merchant_total_balance']['balance'],2);

        return ResponseHelper::formatOutput(Macro::SUCCESS, $msg, $data);
    }

    /**
     * 渠道帐号列表
     */
    public function actionGetAccountList()
    {
        $payType = 0;//ControllerParameterValidator::getRequestParam($this->allParams, 'pay_type',1,Macro::CONST_PARAM_TYPE_INT,'pay_type错误');
        $filter = ['status'=>ChannelAccount::STATUS_ACTIVE];
        $query = ChannelAccount::find()->where($filter);
        $query->andwhere(['deleted_at'=>0]);
        if($payType){
            $query->andwhere(['like', 'methods', '"id":'.$payType.',']);
        }
        $accounts = $query->all();
        //格式化返回记录数据
        $records=[];
        foreach ($accounts as $i=>$a){
            $records[$i]['id'] = $a->id;
            $records[$i]['name'] = $a->channel_name.'-'.$a->merchant_id;
            $records[$i]['merchant_id'] = $a->merchant_id;
            $records[$i]['app_id'] = $a->app_id;
            $records[$i]['methods'] = $a->getPayMethodsArr();
        }

        return ResponseHelper::formatOutput(Macro::SUCCESS, '',$records);
    }

    /**
     * 渠道号-状态修改
     * @return array
     * @throws \power\yii2\exceptions\ParameterValidationExpandException
     */
    public function actionAccountStatus()
    {
        $accounID = ControllerParameterValidator::getRequestParam($this->allParams, 'id',1,Macro::CONST_PARAM_TYPE_INT,'ID错误');
        $accounStatus = ControllerParameterValidator::getRequestParam($this->allParams, 'status',1,Macro::CONST_PARAM_TYPE_INT,'状态码错误');
//        $channelAccount = ChannelAccount::findOne(['id'=>$accounID]);
        $channelAccount = ChannelAccount::find()->where(['id'=>$accounID])->limit(1)->one();
        $channelAccount->status = $accounStatus;
        $channelAccount->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS);

    }
    /**
     * 渠道号添加/编辑
     */
    public function actionAccountEdit()
    {
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id',0,Macro::CONST_PARAM_TYPE_INT,'ID错误');
        $channelId = ControllerParameterValidator::getRequestParam($this->allParams, 'channelId','',Macro::CONST_PARAM_TYPE_STRING,'渠道编号错误');
        $appId = ControllerParameterValidator::getRequestParam($this->allParams, 'appId','',Macro::CONST_PARAM_TYPE_STRING,'渠道appId错误');
        $channelName = ControllerParameterValidator::getRequestParam($this->allParams, 'channelName','',Macro::CONST_PARAM_TYPE_STRING,'渠道名称错误');
        $merchantId = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantId','',Macro::CONST_PARAM_TYPE_STRING,'渠道商户ID错误');
        $merchantAccount = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantAccount','',Macro::CONST_PARAM_TYPE_STRING,'渠道商户账户错误');
        $appSecrets = ControllerParameterValidator::getRequestParam($this->allParams, 'appSecrets','',Macro::CONST_PARAM_TYPE_ARRAY,'渠道密钥配置错误');
        $gatewayUri = ControllerParameterValidator::getRequestParam($this->allParams, 'gatewayUri','',Macro::CONST_PARAM_TYPE_STRING,'渠道接口地址错误');
        $remitFee = ControllerParameterValidator::getRequestParam($this->allParams, 'remitFee','',Macro::CONST_PARAM_TYPE_DECIMAL,'出款费率错误');
        $pay_methods = ControllerParameterValidator::getRequestParam($this->allParams, 'pay_methods','',Macro::CONST_PARAM_TYPE_ARRAY,'收款续费错误');

        $remitQuotaPerday = ControllerParameterValidator::getRequestParam($this->allParams, 'remitQuotaPerday','',Macro::CONST_PARAM_TYPE_DECIMAL,'单日提款限额错误');
        $rechargeQuotaPerday = ControllerParameterValidator::getRequestParam($this->allParams, 'rechargeQuotaPerday','',Macro::CONST_PARAM_TYPE_DECIMAL,'单日充值限额错误');
        $rechargeQuotaPertime = ControllerParameterValidator::getRequestParam($this->allParams, 'rechargeQuotaPertime','',Macro::CONST_PARAM_TYPE_DECIMAL,'单次提款限额错误');
        $remitQuotaPertime = ControllerParameterValidator::getRequestParam($this->allParams, 'remitQuotaPertime','',Macro::CONST_PARAM_TYPE_DECIMAL,'单次充值限额称错误');
        $status = ControllerParameterValidator::getRequestParam($this->allParams, 'status','',Macro::CONST_PARAM_TYPE_INT,'状态错误');
        $minRechargePertime = ControllerParameterValidator::getRequestParam($this->allParams, 'minRechargePertime',0,Macro::CONST_PARAM_TYPE_DECIMAL,'单次最低充值错误');
        $minRemitPertime = ControllerParameterValidator::getRequestParam($this->allParams, 'minRemitPertime',0,Macro::CONST_PARAM_TYPE_DECIMAL,'单次最低出款错误');
        $visible = ControllerParameterValidator::getRequestParam($this->allParams, 'visible',1,Macro::CONST_PARAM_TYPE_INT,'显示状态错误');
        $balanceAlertThreshold = ControllerParameterValidator::getRequestParam($this->allParams, 'balanceAlertThreshold',0,Macro::CONST_PARAM_TYPE_INT,'余额报警阀值错误');

        $channel = null;
        if($id){
            $channelAccount = ChannelAccount::findOne(['id'=>$id]);
            $channel = $channelAccount->channel;
        }else{
            $channelAccount = new ChannelAccount();
            $channel = Channel::findOne($channelId);
        }

        if($channelId!=''){
            $channelAccount->channel_id = $channelId;
        }
        $channelNameChanged = false;
        if($channelName){
            if($id && $channelAccount->channel_name!=$channelName){
                $channelNameChanged = true;
            }
            $channelAccount->channel_name = $channelName;
        }
        if($merchantId!=''){
            $channelAccount->merchant_id = $merchantId;
            $channelAccount->app_id = $merchantId;
        }
        if($appId!='') {
            $channelAccount->app_id = $appId;
        }
        $exisitAccount = ChannelAccount::findOne(['app_id'=>$channelAccount->app_id,'channel_id'=>$channelAccount->channel_id]);
        if($exisitAccount){
            if(($id && $exisitAccount->id!=$id) || !$id){
                return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'渠道号已存在!');
            }
        }

        if($merchantAccount!=''){
            $channelAccount->merchant_account = $merchantAccount;
        }
        if($appSecrets!=''){
            $channelAccount->app_secrets = json_encode($appSecrets,JSON_UNESCAPED_SLASHES);
        }
        if($remitFee!=''){
            $channelAccount->remit_fee = $remitFee;
        }
        if($remitQuotaPerday!=''){
            $channelAccount->remit_quota_perday = $remitQuotaPerday;
        }
        if($rechargeQuotaPerday!=''){
            $channelAccount->recharge_quota_perday = $rechargeQuotaPerday;
        }
        if($rechargeQuotaPertime!=''){
            $channelAccount->recharge_quota_pertime = $rechargeQuotaPertime;
        }
        if($remitQuotaPertime!=''){
            $channelAccount->remit_quota_pertime = $remitQuotaPertime;
        }
        if($channel && !$gatewayUri){
            $channelAccount->gateway_base_uri = $channel->gateway_base_uri;
        }elseif($gatewayUri){
            $channelAccount->gateway_base_uri = $gatewayUri;
        }
        if($status!=''){
            $channelAccount->status = $status;
        }
        $channelAccount->min_recharge_pertime = $minRechargePertime;
        $channelAccount->min_remit_pertime = $minRemitPertime;
        $channelAccount->balance_alert_threshold = $balanceAlertThreshold;
        $channelAccount->visible = $visible;

        $channelAccount->save();
        foreach ($pay_methods as $key=>$val){
            $methodObj = ChannelAccountRechargeMethod::find()
                ->where(['method_id'=>$val['id'],'channel_account_id'=>$channelAccount->id,'channel_id'=>$channelAccount->channel_id])
                ->limit(1)
                ->one();
            if(!$methodObj){
                $methodObj = new ChannelAccountRechargeMethod();
                $methodObj->method_id = $val['id'];
                $methodObj->method_name = Channel::getPayMethodsStr($val['id']);;
                $methodObj->channel_id = $channelAccount->channel_id;
                $methodObj->channel_account_id = $channelAccount->id;
                $methodObj->channel_account_name = $channelAccount->channel_name;
            }

            $methodObj->fee_rate = empty($val['rate'])?0:$val['rate'];
            //$methodObj->status = $val['status'];
            $methodObj->save();
        }

        if($channelNameChanged){
            ChannelAccountRechargeMethod::updateAll(['channel_account_name'=>$channelName],['channel_account_id'=>$id]);
            MerchantRechargeMethod::updateAll(['channel_account_name'=>$channelName],['channel_account_id'=>$id]);
            UserPaymentInfo::updateAll(['remit_channel_account_name'=>$channelName],['remit_channel_account_id'=>$id]);
        }

        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

    /**
     * 三方渠道编辑
     *
     * @role admin
     */
    public function actionEdit()
    {
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id',null,Macro::CONST_PARAM_TYPE_INT,'ID错误',[0]);
        $data['server_ips'] = ControllerParameterValidator::getRequestParam($this->allParams, 'server_ips','',Macro::CONST_PARAM_TYPE_STRING,'server_ips错误');

        $channel = null;
        if($id){
            $channel = Channel::findOne(['id'=>$id]);
        }
        if(!$channel){
            throw new OperationFailureException("渠道:{$id}不存在");
        }

        $channel->setAttributes($data,false);
        $channel->save(false);

        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

    /**
     * 渠道号软删除
     */
    public function actionAccountDelete()
    {
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', 0, Macro::CONST_PARAM_TYPE_INT, 'ID错误');
        $account = ChannelAccount::findOne(['id'=>$id]);
        if(!$account){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'渠道号不存在!');
        }
        $account->visible = 0;
        $account->status = ChannelAccount::STATUS_BANED;
        $account->deleted_at = time();
        $account->save();

        return ResponseHelper::formatOutput(Macro::SUCCESS,"删除成功");
    }
}
