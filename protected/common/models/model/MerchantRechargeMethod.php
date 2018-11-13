<?php
namespace app\common\models\model;

/*
 * 商户支付方式表
 */
use yii\db\ActiveRecord;

class MerchantRechargeMethod extends BaseModel
{
    const STATUS_INACTIVE=0;
    const STATUS_ACTIVE=1;

    const ARR_STATUS = [
        self::STATUS_ACTIVE => '启用',
        self::STATUS_INACTIVE => '停用',
    ];

    public static function tableName()
    {
        return '{{%merchant_recharge_methods}}';
    }

    public function getChannel()
    {
        return $this->hasOne(Channel::class, ['id'=>'channel_id']);
    }

    public function getChannelAccount()
    {
        return $this->hasOne(ChannelAccount::class, ['id'=>'channel_account_id']);
    }


    /**
     * 获取渠道账户支付方式配置信息
     *
     * @param string $appId 商户应用ID
     * @param string $methodId 支付方式ID
     * @return ActiveRecord
     */
    public function getChannelAccountMethodConfig()
    {
        return $this->channelAccount->getPayMethodById($this->method_id);
    }

    /**
     * 获取用户支付方式配置信息
     *
     * @param string $appId 商户应用ID
     * @return array
     */
    public static function getMethodConfigByAppId(string $appId)
    {
        return self::findAll(['app_id'=>$appId]);
    }

    /**
     * 获取支付方式配置信息
     *
     * @param string $appId 商户应用ID
     * @param string $methodId 支付方式ID
     * @return ActiveRecord
     */
    public static function getMethodConfigByAppIdAndMethodId(string $appId, string $methodId)
    {
        return self::findOne(['app_id'=>$appId,'method_id'=>$methodId]);
    }

    /**
     * 获取所有上级支付方式配置ID
     *
     * @return array|mixed
     */
    public function getAllParentAgentId()
    {
        return empty($this->all_parent_method_config_id)?[]:json_decode($this->all_parent_method_config_id,true);
    }

    /**
     * 获取所有上级支付方式配置
     *
     * @return array|mixed
     */
    public function getAllParentAgentConfig()
    {
        $pids = $this->getAllParentAgentId();
        return self::findAll(['id'=>$pids]);
    }

    /**
     * 获取某支付方式所有上级支付方式配置
     *
     * @return array|mixed
     */
    public function getMethodAllParentAgentConfig($mid)
    {
        $pids = $this->getAllParentAgentId();
        return self::findAll(['id'=>$pids,'method_id'=>$mid]);
    }
    
    /**
     * 获取当前商户上下级收款费率区间
     */
    public static function getPayMethodsRateSectionAppId($parent_agent_id = '',$lower_level = array()){
        $rateSection = [
            'parent_rate' => [],
            'lower_rate' => [],
        ];
        if($parent_agent_id){
            $parentRate = self::find()->where(['merchant_id'=>$parent_agent_id])->select('method_id,fee_rate')->asArray()->all();
            if($parentRate){
                foreach ($parentRate as $key => $val){
                    $rateSection['parent_rate'][$val['method_id']] = $val['fee_rate'];
                }
            }
        }
        if($lower_level){
            $lowerRate = self::find()->where(['in','merchant_id',$lower_level])->select('method_id,min(fee_rate) as fee_rate')->groupBy('method_id')->asArray()->all();
            if($lowerRate){
                foreach ($lowerRate as $key => $val){
                    $rateSection['lower_rate'][$val['method_id']] = $val['fee_rate'];
                }
            }
        }
        return $rateSection;
    }

    /**
     * 根据账期类型获取预计到账时间戳
     *
     * @param string $type
     */
    public static function getExpectSettlementTime(string $type){

        $type = strtoupper($type);
        if(!in_array($type, self::getAllSettlementType())){
            $type = 'T1';
        }
        $timeType = substr($type,0,1);
        $days = substr($type,1);

        $nowTs = time();
        $settlementTime = $nowTs;
        if($days==0){
            return $nowTs;
        }

        if($timeType=='D'){
            $settlementTime += intval($days)*86400;
        }if($timeType=='T'){
            $wDay = date('w');
            if($wDay>=4){
                $settlementTime += ((6-$wDay)+$days)*86400;
            }else{
                $settlementTime += intval($days)*86400;
            }
        }

        return $settlementTime;
    }

    /**
     * 获取所有导致类型
     *
     * @return array
     */
    public static function getAllSettlementType()
    {
        $typeStr =  SiteConfig::cacheGetContent('all_settlement_type');
        $types = explode(',', $typeStr);
        return $types;
    }

}