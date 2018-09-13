<?php
/**
 * Created by PhpStorm.
 * User: kk
 * Date: 2018/9/14
 * Time: 上午2:19
 */

namespace app\modules\api\controllers\v1\admin;


use app\common\models\model\Order;
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
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');
        $startTime = empty($dateStart) ? strtotime('-4 days',strtotime(date("Y-m-d 00:00:00"))) : strtotime(date("Y-m-d 00:00:00",strtotime($dateStart)));
        $endTime = empty($dateEnd) ? strtotime(date("Y-m-d 23:59:59")) : strtotime(date("Y-m-d 23:59:59",strtotime($dateEnd)));
        $days = ($endTime - $startTime) / (24*3600) ;
        if($days > 5){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '时间筛选跨度不能超过4天');
        }
        $query = Order::find();
        $query->andFilterCompare('settlement_at','>='.$startTime);
        $query->andFilterCompare('settlement_at','<='.$endTime);
        $query->andWhere(['status'=>Order::STATUS_SETTLEMENT]);
        $query->select(new Expression("sum(`paid_amount`) as amount,from_unixtime(`settlement_at`,'%Y-%m-%d %H') as times"));
//        $query->groupBy("from_unixtime(`settlement_at`,'%Y%m%d%H')");
        $query->groupBy(new Expression("from_unixtime(`settlement_at`,'%Y%m%d%H')"));
        $list = $query->asArray()->all();
        if (!$list) return ResponseHelper::formatOutput(Macro::SUCCESS,'',[]);
        $data = [];
        foreach ($list as $val){
            list($date,$time) = $val['times'];
            $data[$date][$time] = $val['amount'];
        }
        return ResponseHelper::formatOutput(Macro::SUCCESS,'',$data);
    }
}