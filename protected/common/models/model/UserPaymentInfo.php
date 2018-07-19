<?php
namespace app\common\models\model;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/*
 * 商户支付配置信息
 */
class UserPaymentInfo extends BaseModel
{
    const ALLOW_API_RECHARGE_NO=0;
    const ALLOW_API_RECHARGE_YES=1;
    const ALLOW_API_REMIT_NO=0;
    const ALLOW_API_REMIT_YES=1;
    const ALLOW_MANUAL_RECHARGE_NO=0;
    const ALLOW_MANUAL_RECHARGE_YES=1;
    const ALLOW_MANUAL_REMIT_NO=0;
    const ALLOW_MANUAL_REMIT_YES=1;
    const ALLOW_API_FAST_REMIT_YES=1;
    const ALLOW_API_FAST_REMIT_NO=1;

    public static function tableName()
    {
        return '{{%user_payment_info}}';
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

    public function getRemitChannel()
    {
        return $this->hasOne(ChannelAccount::className(), ['id'=>'remit_channel_account_id']);
    }

    /**
     * 获取appId对应的所有支付方式数组
     *
     * @return array
     */
    public function getPayMethodsArr()
    {
        $raWmethods = $this->getPayMethods();
        $methods = [];
        foreach ($this->payMethods as $m){
            $methods[] = [
                'id'=>$m->method_id,
                'rate'=>$m->fee_rate,
                'name'=>$m->method_name,
                'status'=>$m->status,
            ];
        }

        return $methods;
    }

    /**
     * 根据appId获取对应的支付方式列表，目前appId=merchantId
     *
     * @return string
     */
    public static function getPayMethodsArrByAppId($appId)
    {
        $raWmethods = MerchantRechargeMethod::findAll(['app_id'=>$appId]);
        $methods = [];
        foreach ($raWmethods as $m){
            $methods[] = [
                'id'=>$m->method_id,
                'rate'=>$m->fee_rate,
                'name'=>$m->method_name,
                'channel_account_name'=>$m->channel_account_name,
                'channel_account_id'=>$m->channel_account_id,
                'status' => $m->status,
                'manual_status' => $m->manual_status,
                'settlement_type' => $m->settlement_type,
            ];
        }

        return $methods;
    }

    /**
     * 充值渠道是否支持某个支付方式
     */
    public function hasPaymentMethod($methodId)
    {
        $has = $this->getPayMethodById($methodId);
        return $has;
    }

    /**
     * 根据支付方式id获取支付方式信息
     *
     * @param int $id 支付方式id
     * @return ActiveRecord
     */
    public function getPayMethodById($id)
    {
        return $this->hasOne(MerchantRechargeMethod::className(), ['payment_info_id' => 'id'])
            ->where(['method_id' => $id])->one();
    }

    /**
     * 支付方式列表
     *
     * @param int $id 支付方式id
     * @return array
     */
    public function getPayMethods()
    {
        return $this->hasMany(MerchantRechargeMethod::className(), ['payment_info_id' => 'id']);
    }

    /*
     * 更新用户的支付配置
     *
     * @param User $parentAccount 父级账户,若传值则更新对应的父级信息及利润差额
     * @param array $methods 支付配置MerchantRechargeMethod list,若传值则更新对应配置信息
     */
    public function updatePayMethods($parentAccount, $methods=[])
    {
        if(empty($methods)){
            $methods = $this->getPayMethodsArr();
        }
        $parentMinRate = $parentAccount?$parentAccount->paymentInfo->payMethods:[];

        foreach ($methods as $i => $pm) {
            $methods[$i]['parent_method_config_id']     = $methods[$i]['parent_method_config_id'] ?? 0;
            $methods[$i]['parent_recharge_rebate_rate'] = $methods[$i]['parent_recharge_rebate_rate'] ?? 0;
            $methods[$i]['all_parent_method_config_id'] = $methods[$i]['all_parent_method_config_id'] ?? [];
            $methods[$i]['status']                      = $methods[$i]['status'] ?? MerchantRechargeMethod::STATUS_ACTIVE;

            if ($parentMinRate) {
                foreach ($parentMinRate as $k => $cmr) {

                    if ($pm['id'] == $cmr->method_id && $pm['status'] == '1') {
                        if ($pm['rate'] < $cmr->fee_rate) {
                            throw new \app\common\exceptions\OperationFailureException("收款渠道费率不能低于上级费率(" . Channel::ARR_METHOD[$pm['id']] . ":{$cmr->fee_rate})");
                        }
                        //提前计算好需要给上级的分润比例
                        $allMids = [];
                        $methods[$i]['parent_method_config_id']     = $cmr->id;
                        $methods[$i]['parent_recharge_rebate_rate'] = bcsub($pm['rate'], $cmr->fee_rate, 9);
//                        echo "{$parentAccount->username},{$cmr->fee_rate},{$this->username},{$pm['rate']},{$methods[$i]['parent_recharge_rebate_rate']}\n";
                        if($cmr->all_parent_method_config_id && $cmr->all_parent_method_config_id != 'null'){
                            $allMids = json_decode($cmr->all_parent_method_config_id, true);
                        }
                        array_push($allMids, $cmr->id);
                        $methods[$i]['all_parent_method_config_id'] = $allMids;
                    }
                }
            }
            $methods[$i]['all_parent_method_config_id'] = json_encode($methods[$i]['all_parent_method_config_id']);
        }

        //批量写入每种支付类型配置
        foreach ($methods as $i=>$pm){
            $methodConfig = MerchantRechargeMethod::find()->where(['method_id'=>$pm['id'],'app_id'=>$this->app_id])->limit(1)->one();

            if(!$methodConfig){
                $methodConfig = new MerchantRechargeMethod();

                $methodConfig->method_id = $pm['id'];
                $methodConfig->app_id = $this->app_id;
                $methodConfig->merchant_id = $this->user_id;
                $methodConfig->merchant_account = $this->username;

                $methodConfig->payment_info_id = $this->id;
                $methodConfig->parent_method_config_id = $pm['parent_method_config_id'];
                $methodConfig->all_parent_method_config_id = $pm['all_parent_method_config_id'];
            }

            $methodConfig->parent_recharge_rebate_rate = $pm['parent_recharge_rebate_rate'];
            $methodConfig->status = ($pm['status']==MerchantRechargeMethod::STATUS_ACTIVE)?MerchantRechargeMethod::STATUS_ACTIVE:MerchantRechargeMethod::STATUS_INACTIVE;
            $methodConfig->fee_rate = $pm['rate'];
            $methodConfig->settlement_type = $pm['settlement_type']??SiteConfig::cacheGetContent('default_settlement_type');
            $methodConfig->save();
        }
    }
    
    
    /**
     * 获取所有上级支付方式配置
     *
     * @return array|mixed
     */
    public function getAllParentRemitChannelAccount()
    {
        $pids = $this->getAllParentAgentId();
        return self::find(['id'=>$pids])->all();
    }

    /**
     * 获取某支付方式所有上级支付方式配置
     *
     * @return array|mixed
     */
    public function getMethodAllParentAgentConfig($pids)
    {
        $pids = $this->getAllParentAgentId();
        return UserPaymentInfo::findAll(['app_id'=>$pids,'method_id'=>$mid]);
    }

    /**
     * 检测当前请求ip是否中商户配置的白名单中
     * @return bool
     */
    public function checkAppServerIp()
    {
        if($this->app_server_ips){
            $ip = Yii::$app->request->remoteIP;
            $allowIps = json_decode($this->app_server_ips);

            if(!$allowIps || !in_array($ip,$allowIps)){
                return false;
            }
        }

        return true;
    }

    /**
     * 获取用户默认的支付配置
     * 一个用户理论上可以有多个支付配置,目前每个用户只有一个.这个时候app_id=user_id
     *
     * @param int $userId 商户id
     * @return ActiveRecord
     */
    public static function getUserDefaultPaymentInfo($userId)
    {
        return self::findOne(['user_id'=>$userId,'app_id'=>$userId]);
    }

}