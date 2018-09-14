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
        $startTime = empty($dateStart) ? strtotime('-3 days',strtotime(date("Y-m-d 00:00:00"))) : strtotime(date("Y-m-d 00:00:00",strtotime($dateStart)));
        $endTime = empty($dateEnd) ? strtotime(date("Y-m-d 23:59:59")) : strtotime(date("Y-m-d 23:59:59",strtotime($dateEnd)));
        $days = (strtotime(date("Y-m-d",$endTime)) - strtotime(date("Y-m-d",$startTime))) / (24*3600) ;
        if($days > 4){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '时间筛选跨度不能超过4天');
        }
        $query = Order::find();
        $query->andFilterCompare('settlement_at','>='.$startTime);
        $query->andFilterCompare('settlement_at','<='.$endTime);
        $query->andWhere(['status'=>Order::STATUS_SETTLEMENT]);
        $query->select(new Expression("sum(`paid_amount`) as amount,from_unixtime(`settlement_at`,'%Y-%m-%d %H') as times"));
        $query->groupBy(new Expression("from_unixtime(`settlement_at`,'%Y%m%d%H')"));
        $list = $query->asArray()->all();
        if (!$list) return ResponseHelper::formatOutput(Macro::SUCCESS,'',[]);
        $chartData = [];
        foreach ($list as $val){
            $tmp = ['00' => 0 , '01' => 0 , '02' => 0 , '03' => 0 , '04' => 0 , '05' => 0 , '06' => 0 , '07' => 0 , '08' => 0 , '09' => 0 , '10' => 0 , '11' => 0 , '12' => 0 , '13' => 0,'14' => 0 ,'15' => 0 ,'16' => 0 ,'17' => 0 ,'18' => 0 ,'19' => 0 ,'20' => 0,'21' => 0 ,'22' => 0 ,'23' => 0];
            list($date,$time) = explode(" ",$val['times']);
            $tmp[(string)$time] = $val['amount'];
            $chartData[$date] = $tmp;
        }
        $data = [];
        foreach ($chartData as $key => $val){
            foreach ($val as $value){
                $data[$key][] = $value;
            }
        }

//        $data['2018-09-11'] = ["543113.69", "673987.85", "466886.15", "475646.00", "189140.00", "67255.92", "180326.07", "1178641.40", "2450301.12", "3045451.78", "3452237.29", "3708345.14", "2919407.97", "2357965.43", "3117303.52", "3537998.93", "3218793.33", "2107883.30", "2294047.92", "2206863.96", "2150130.50", "2142737.45", "1507022.58", "1157057.98"];
//        $data['2018-09-12'] = ["1024946.62", "913214.68", "786174.50", "177659.00", "268266.00", "61409.00", "186080.46", "945199.99", "2331624.77", "3165531.71", "3981066.28", "2976927.33", "2565486.38", "3743021.67", "2904412.55", "3080567.71", "3212351.72", "2853842.80", "3158698.65", "3526467.97", "2048941.09", "1786266.44", "1304818.20", "997392.97"];
//        $data['2018-09-13'] = ["1042847.62", "324156.39", "324878.52", "180085.00", "214001.75", "177087.90", "259895.04", "1330923.61", "3049007.72", "2956231.97", "3075395.27", "3778228.01", "3341839.14", "4529695.05", "4024294.83", "4185795.08", "3356373.75", "3356332.78", "3016910.81", "3017963.43", "2795871.18", "1320512.08", "1887223.95", "1524706.97"];
//        $data['2018-09-14'] = ["1017278.55", "42579.12", "0", "0", "0", "0", "0", "0", "0", "0", "0", "0", "0", "0", "0", "0", "0", "0", "0", "0", "0", "0", "0", "0"];

        return ResponseHelper::formatOutput(Macro::SUCCESS,'',$data);
    }
}