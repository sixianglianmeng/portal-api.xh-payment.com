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
        $startTime = empty($dateStart) ? strtotime('-4 days',strtotime(date("Y-m-d"))) : strtotime(date("Y-m-d",strtotime($dateStart)));
        $endTime = empty($dateEnd) ? strtotime(date("Y-m-d")) : strtotime(date("Y-m-d",strtotime($dateEnd)));
        $days = ($endTime - $startTime) / (24*3600) ;
        if($days > 4){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '时间筛选跨度不能超过4天');
        }
        $timeArray = [
            0=>['date_start'=>'00:00:00','date_end'=>'00:59:59'],
            1=>['date_start'=>'01:00:00','date_end'=>'01:59:59'],
            2=>['date_start'=>'02:00:00','date_end'=>'02:59:59'],
            3=>['date_start'=>'03:00:00','date_end'=>'03:59:59'],
            4=>['date_start'=>'04:00:00','date_end'=>'04:59:59'],
            5=>['date_start'=>'05:00:00','date_end'=>'05:59:59'],
            6=>['date_start'=>'06:00:00','date_end'=>'06:59:59'],
            7=>['date_start'=>'07:00:00','date_end'=>'07:59:59'],
            8=>['date_start'=>'08:00:00','date_end'=>'08:59:59'],
            9=>['date_start'=>'09:00:00','date_end'=>'09:59:59'],
            10=>['date_start'=>'10:00:00','date_end'=>'10:59:59'],
            11=>['date_start'=>'11:00:00','date_end'=>'11:59:59'],
            12=>['date_start'=>'12:00:00','date_end'=>'12:59:59'],
            13=>['date_start'=>'13:00:00','date_end'=>'13:59:59'],
            14=>['date_start'=>'14:00:00','date_end'=>'14:59:59'],
            15=>['date_start'=>'15:00:00','date_end'=>'15:59:59'],
            16=>['date_start'=>'16:00:00','date_end'=>'16:59:59'],
            17=>['date_start'=>'17:00:00','date_end'=>'17:59:59'],
            18=>['date_start'=>'18:00:00','date_end'=>'18:59:59'],
            19=>['date_start'=>'19:00:00','date_end'=>'19:59:59'],
            20=>['date_start'=>'20:00:00','date_end'=>'20:59:59'],
            21=>['date_start'=>'21:00:00','date_end'=>'21:59:59'],
            22=>['date_start'=>'22:00:00','date_end'=>'22:59:59'],
            23=>['date_start'=>'23:00:00','date_end'=>'23:59:59']
        ];
        $data = [];
        for($i = 0 ;$i < $days ;$i++){
            $dayTime = date("Y-m-d",strtotime("-{$i} days"));
            foreach ($timeArray as $key => $val){
                $where['start_time'] = $dayTime.' '.$val['date_start'];
                $where['end_time'] = $dayTime.' '.$val['date_end'];
                $tmp = Order::totalChargeAmount($where);
                $data[$dayTime][] = $tmp;
            }
        }
        return ResponseHelper::formatOutput(Macro::SUCCESS,'',$data);
    }
}