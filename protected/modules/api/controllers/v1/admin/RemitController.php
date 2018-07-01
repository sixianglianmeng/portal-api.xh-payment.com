<?php
namespace app\modules\api\controllers\v1\admin;

use app\common\models\model\LogOperation;
use app\common\models\model\Remit;
use app\components\Macro;
use app\components\RpcPaymentGateway;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use app\modules\api\controllers\BaseController;
use app\modules\gateway\models\logic\LogicRemit;
use Yii;

class RemitController extends BaseController
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
        $parentBeforeAction =  parent::beforeAction($action);

        return $parentBeforeAction;
    }

    /**
     * 设置提款为成功
     */
    public function actionSetSuccess()
    {
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', null, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '订单ID错误');

        $filter = $this->baseFilter;
        $filter['id'] = $id;
        $order = Remit::find()->where($filter)->limit(1)->one();
        if(!$order){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单不存在');
        }

        //接口日志埋点
        Yii::$app->params['operationLogFields'] = [
            'table'=>'p_remit',
            'pk'=>$order->id,
            'order_no'=>$order->order_no,
        ];

        if(!in_array($order->status,[Remit::STATUS_BANK_PROCESSING, Remit::STATUS_DEDUCT, Remit::STATUS_NOT_REFUND])){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单状态必须是已扣款|处理中|失败未退款');
        }

        $orderOpList = [];
        $orderOpList[] = ['order_no'=>$order->order_no];
        RpcPaymentGateway::setRemitSuccess($orderOpList);

        return ResponseHelper::formatOutput(Macro::SUCCESS, '订单已更新为成功状态');
    }

    /**
     * 管理员同步提款状态
     * @role admin
     */
    public function actionSyncStatus()
    {
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', null, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '订单ID错误');

        $filter = $this->baseFilter;
        $filter['id'] = $id;
        $order = Remit::find()->where($filter)->limit(1)->one();
        if(!$order){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单不存在');
        }

        //接口日志埋点
        Yii::$app->params['operationLogFields'] = [
            'table'=>'p_remit',
            'pk'=>$order->id,
            'order_no'=>$order->order_no,
        ];

        $orderOpList = [];
        $orderOpList[] = ['order_no'=>$order->order_no];
        RpcPaymentGateway::syncRemitStatus(0, $orderOpList);

        return ResponseHelper::formatOutput(Macro::SUCCESS, '订单已同步');
    }

    /**
     * 设置提款为失败
     */
    public function actionSetFail()
    {
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', null, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '订单ID错误');

        $filter = $this->baseFilter;
        $filter['id'] = $id;
//        $order = Remit::findOne($filter);
        $order = Remit::findOne($filter);
        if(!$order){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单不存在');
        }

        //接口日志埋点
        Yii::$app->params['operationLogFields'] = [
            'table'=>'p_remit',
            'pk'=>$order->id,
            'order_no'=>$order->order_no,
        ];

        if(!in_array($order->status,[Remit::STATUS_BANK_PROCESSING, Remit::STATUS_DEDUCT, Remit::STATUS_NOT_REFUND])){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单状态必须是已扣款|处理中|失败未退款');
        }

        $orderOpList = [];
        $orderOpList[] = ['order_no'=>$order->order_no];
        RpcPaymentGateway::setRemitFail($orderOpList);

        return ResponseHelper::formatOutput(Macro::SUCCESS, '订单已更新为失败状态');
    }

    /**
     * 设置出款为已审核
     */
    public function actionSetChecked()
    {
        $idList = ControllerParameterValidator::getRequestParam($this->allParams, 'idList', null, Macro::CONST_PARAM_TYPE_ARRAY, '订单ID错误');

        $filter = $this->baseFilter;
        $filter['id'] = $idList;
        $filter['status'] = Remit::STATUS_DEDUCT;
        $maxNum = 100;
        if(count($idList)>$maxNum){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, "单次最多设置{$maxNum}个订单");
        }
        $rawOrders = (new \yii\db\Query())
            ->select(['id','order_no'])
            ->from(Remit::tableName())
            ->where($filter)
            ->limit(100)
            ->all();

        if(!$rawOrders){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单不存在');
        }

        $orders = [];
        foreach ($rawOrders as $o){
            $orders[] = [
                'order_no'=>$o['order_no']
            ];

            //接口日志埋点
            Yii::$app->params['operationLogFields'] = [
                'table'=>'p_remit',
                'pk'=>$o['id'],
                'order_no'=>$o['order_no'],
            ];
            LogOperation::inLog('ok');
        }

        RpcPaymentGateway::setRemitChecked($orders);

        return ResponseHelper::formatOutput(Macro::SUCCESS, '订单已更新为已审核');
    }
    /**
     * 出款待审核提醒
     * @roles admin,admin_operator,admin_service_lv1,admin_service_lv2
     */
    public function actionRemind()
    {
        $user = Yii::$app->user->identity;
        if($user->group_id == 10){
            $remit = Remit::find()->where(['status'=>0])->limit(1)->one();
            if(!empty($remit)){
                return ResponseHelper::formatOutput(Macro::SUCCESS);
            }
        }
        return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN);
    }
}
