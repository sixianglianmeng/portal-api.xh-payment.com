<?php
/**
 * Created by PhpStorm.
 * User: kk
 * Date: 2018/9/14
 * Time: 上午2:19
 */

namespace app\modules\api\controllers\v1\admin;


use app\common\models\model\ChannelAccount;
use app\common\models\model\Order;
use app\common\models\model\Remit;
use app\components\Macro;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use yii\db\Expression;
use yii\db\Query;

class EchartsController extends BaseController
{
    /**
     * 充值金额走势(按小时)
     * @return array
     * @throws \power\yii2\exceptions\ParameterValidationExpandException
     */
    public function actionChargeTrendHour()
    {
        $channel_merchant_id = ControllerParameterValidator::getRequestParam($this->allParams, 'channel_merchant_id', [],Macro::CONST_PARAM_TYPE_ARRAY,'渠道号错误');
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');
        $startTime = empty($dateStart) ? strtotime('-14 days',strtotime(date("Y-m-d 00:00:00"))) : strtotime(date("Y-m-d 00:00:00",strtotime($dateStart)));
        $endTime = empty($dateEnd) ? strtotime(date("Y-m-d 23:59:59")) : strtotime(date("Y-m-d 23:59:59",strtotime($dateEnd)));
        $days = (strtotime(date("Y-m-d",$endTime)) - strtotime(date("Y-m-d",$startTime))) / (24*3600) ;
        if($days > 14){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '时间筛选跨度不能超过15天',[]);
        }
        $query = Order::find();
        $query->andFilterCompare('settlement_at','>='.$startTime);
        $query->andFilterCompare('settlement_at','<='.$endTime);
        if(!empty($channel_merchant_id)){
            $query->andWhere(['channel_merchant_id'=>$channel_merchant_id]);
        }
        $query->andWhere(['status'=>Order::STATUS_SETTLEMENT]);
        $query->select(new Expression("sum(`paid_amount`) as amount,from_unixtime(`settlement_at`,'%y-%m-%d %H') as times"));
        $query->groupBy(new Expression("from_unixtime(`settlement_at`,'%Y%m%d%H')"));
        $list = $query->asArray()->all();
        $data['channelMerchantOptions'] = [];
        $channelMerchantList = ChannelAccount::find()->select('channel_name,merchant_id')->all();
        foreach ($channelMerchantList as $val){
            $tmp = [];
            $tmp['channel_merchant_id'] = $val->merchant_id;
            $tmp['channel_name'] = $val->channel_name;
            $data['channelMerchantOptions'][] = $tmp;
        }
        $data['chart'] = [];
        $data['hour'] = [];
        if (!$list) return ResponseHelper::formatOutput(Macro::SUCCESS,'未查询到充值数据，请检查查询条件',$data);
        $chartData = [];
        $tmp = ['00','01','02','03','04','05','06','07','08','09','10','11','12','13','14','15','16','17','18','19','20','21','22','23'];
        foreach ($list as $val){
            list($date,$time) = explode(" ",$val['times']);
            foreach ($tmp as $tmpVal){
                if (!isset($chartData[$date][$tmpVal])){
                    $chartData[$date][$tmpVal] = 0;
                }
                if($tmpVal == (string)$time){
                    $chartData[$date][(string)$tmpVal] = $val['amount'];
                }
            }
        }
        $data['hour'] = $tmp;
        foreach ($chartData as $key => $val){
            foreach ($val as $value){
                $data['chart'][$key][] = $value;
            }
        }
        return ResponseHelper::formatOutput(Macro::SUCCESS,'',$data);
    }

    /**
     * 充值金额走势(按天)
     * @return array
     * @throws \power\yii2\exceptions\ParameterValidationExpandException
     */
    public function actionRechargeTrendDaily()
    {
        $channel_merchant_id = ControllerParameterValidator::getRequestParam($this->allParams, 'channel_merchant_id', [],Macro::CONST_PARAM_TYPE_ARRAY,'渠道号错误');
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');
        $startTime = empty($dateStart) ? strtotime('-14 days',strtotime(date("Y-m-d 00:00:00"))) : strtotime(date("Y-m-d 00:00:00",strtotime($dateStart)));
        $endTime = empty($dateEnd) ? strtotime(date("Y-m-d 23:59:59")) : strtotime(date("Y-m-d 23:59:59",strtotime($dateEnd)));
        $days = (strtotime(date("Y-m-d",$endTime)) - strtotime(date("Y-m-d",$startTime))) / (24*3600) ;
        if($days > 14){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '时间筛选跨度不能超过15天',[]);
        }
        $query = Order::find();
        $query->andFilterCompare('settlement_at','>='.$startTime);
        $query->andFilterCompare('settlement_at','<='.$endTime);
        if(!empty($channel_merchant_id)){
            $query->andWhere(['channel_merchant_id'=>$channel_merchant_id]);
        }
        $query->andWhere(['status'=>Order::STATUS_SETTLEMENT]);
        $query->select(new Expression("sum(`paid_amount`) as amount,from_unixtime(`settlement_at`,'%Y-%m-%d') as times"));
        $query->groupBy(new Expression("from_unixtime(`settlement_at`,'%Y%m%d')"));
        $list = $query->asArray()->all();
        if (!$list) return ResponseHelper::formatOutput(Macro::SUCCESS,'未查询到充值数据，请检查查询条件',[]);
        $data = [];
        foreach ($list as $val){
            $data[$val['times']] = $val['amount'];
        }
        return ResponseHelper::formatOutput(Macro::SUCCESS,'',$data);
    }

    /**
     * 商户首页充值代付走势
     * @return array
     */
    public function actionChargeRemitTrendMerchant()
    {
        $user = \Yii::$app->user->identity;
        $data['isMainAccount'] =  $user->isMainAccount();
        $data['charts']['charge'] = [];
        $data['charts']['remit'] = [];
        $data['merchant']['charge'] = [];
        $data['merchant']['remit'] = [];
        if($data['isMainAccount']) {
            /********商户当天的充值代付走势开始*************/

            //商户当天的充值
            $queryOrder = Order::find();
            $queryOrder->andFilterCompare('settlement_at', '>=' . strtotime(date("Y-m-d 00:00:00")));
            $queryOrder->andFilterCompare('settlement_at', '<=' . strtotime(date("Y-m-d 23:59:59")));
            $queryOrder->andWhere(['status' => Order::STATUS_SETTLEMENT]);
            $queryOrder->andWhere(['merchant_id' => $user->id]);
            $queryOrder->select(new Expression("sum(`paid_amount`) as amount,from_unixtime(`settlement_at`,'%y-%m-%d %H') as times"));
            $queryOrder->groupBy(new Expression("from_unixtime(`settlement_at`,'%Y%m%d%H')"));
            $listOrder = $queryOrder->asArray()->all();
            $tmp = ['00', '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23'];
            if (!empty($listOrder)) {
                $chartData = [];
                foreach ($listOrder as $val) {
                    list($date, $time) = explode(" ", $val['times']);
                    foreach ($tmp as $tmpVal) {
                        if (!isset($chartData[$date][$tmpVal])) {
                            $chartData[$date][$tmpVal] = 0;
                        }
                        if ($tmpVal == (string)$time) {
                            $chartData[$date][(string)$tmpVal] = $val['amount'];
                        }
                    }
                }
                foreach ($chartData as $key => $val) {
                    foreach ($val as $k=>$value) {
                        $data['charts']['charge'][$k] = $value;
                    }
                }
            }
            //商户当天的代付
            $queryRemit = Remit::find();
            $queryRemit->andFilterCompare('remit_at', '>=' . strtotime(date("Y-m-d 00:00:00")));
            $queryRemit->andFilterCompare('remit_at', '<=' . strtotime(date("Y-m-d 23:59:59")));
            $queryRemit->andWhere(['status' => Remit::STATUS_SUCCESS]);
            $queryRemit->andWhere(['merchant_id' => $user->id]);
            $queryRemit->select(new Expression("sum(`amount`) as amount,from_unixtime(`remit_at`,'%y-%m-%d %H') as times"));
            $queryRemit->groupBy(new Expression("from_unixtime(`remit_at`,'%Y%m%d%H')"));
            $listRemit = $queryRemit->asArray()->all();
            if (!empty($listRemit)) {
                $chartData = [];
                foreach ($listRemit as $val) {
                    list($date, $time) = explode(" ", $val['times']);
                    foreach ($tmp as $tmpVal) {
                        if (!isset($chartData[$date][$tmpVal])) {
                            $chartData[$date][$tmpVal] = 0;
                        }
                        if ($tmpVal == (string)$time) {
                            $chartData[$date][(string)$tmpVal] = $val['amount'];
                        }
                    }
                }
                foreach ($chartData as $key => $val) {
                    foreach ($val as $k => $value) {
                        $data['charts']['remit'][$k] = $value;
                    }
                }
            }
            /********商户当天的充值代付走势结束*************/
            /********商户近7天的充值代付走势开始*************/
            //商户近7天的充值金额
            $queryWeekOrder = Order::find();
            $queryWeekOrder->andFilterCompare('settlement_at', '>=' . strtotime(date("Y-m-d 00:00:00",strtotime('-7 days',time()))));
            $queryWeekOrder->andFilterCompare('settlement_at', '<=' . strtotime(date("Y-m-d 23:59:59")));
            $queryWeekOrder->andWhere(['status' => Order::STATUS_SETTLEMENT]);
            $queryWeekOrder->andWhere(['merchant_id' => $user->id]);
            $queryWeekOrder->select(new Expression("sum(`paid_amount`) as amount,from_unixtime(`settlement_at`,'%y-%m-%d') as times"));
            $queryWeekOrder->groupBy(new Expression("from_unixtime(`settlement_at`,'%Y%m%d')"));
            $listWeekOrder = $queryWeekOrder->asArray()->all();
            if (!empty($listWeekOrder)) {
                foreach ($listWeekOrder as $val) {
                    $data['merchant']['charge'][$val['times']] = $val['amount'];
                }
            }
            //商户近7天的代付金额
            $queryWeekRemit = Remit::find();
            $queryWeekRemit->andFilterCompare('remit_at', '>=' . strtotime(date("Y-m-d 00:00:00",strtotime('-7 days',time()))));
            $queryWeekRemit->andFilterCompare('remit_at', '<=' . strtotime(date("Y-m-d 23:59:59")));
            $queryWeekRemit->andWhere(['status' => Remit::STATUS_SUCCESS]);
            $queryWeekRemit->andWhere(['merchant_id' => $user->id]);
            $queryWeekRemit->select(new Expression("sum(`amount`) as amount,from_unixtime(`remit_at`,'%y-%m-%d') as times"));
            $queryWeekRemit->groupBy(new Expression("from_unixtime(`remit_at`,'%Y%m%d')"));
            $listWeekRemit = $queryWeekRemit->asArray()->all();
            if (!empty($listWeekRemit)) {
                foreach ($listWeekRemit as $val) {
                    $data['merchant']['remit'][$val['times']] = $val['amount'];
                }
            }
            /********商户近7天的充值代付走势结束*************/
        }
        $data['hour'] = $tmp;
        return ResponseHelper::formatOutput(Macro::SUCCESS,'',$data);
    }
}