<?php
namespace app\modules\api\controllers\v1;

use app\common\models\model\Order;
use app\components\Macro;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use app\modules\api\controllers\BaseController;
use app\modules\gateway\models\logic\LogicOrder;
use Yii;
use yii\data\ActiveDataProvider;

/*
 * 代理相关功能
 */
class AgentsController extends BaseController
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
     * 代理商列表
     *
     *
     */
    public function actionList()
    {

        $user = Yii::$app->user->identity;
        //允许的排序
        $sorts = [
            'created_at-'=>['created_at',SORT_DESC],
        ];

        $sort = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', 15, Macro::CONST_PARAM_TYPE_SORT, '分页参数错误',[1,100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,1000]);

        $orderNo = ControllerParameterValidator::getRequestParam($this->allParams, 'orderNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'平台订单号错误',[0,32]);
        $merchantOrderNo = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantOrderNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'商户订单号错误',[0,32]);

        $status = ControllerParameterValidator::getRequestParam($this->allParams, 'status','',Macro::CONST_PARAM_TYPE_INT,'订单状态错误',[0,100]);
        $notifyStatus = ControllerParameterValidator::getRequestParam($this->allParams, 'notifyStatus','',Macro::CONST_PARAM_TYPE_INT,'通知状态错误',[0,100]);
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');

        if(!empty($sorts[$sort])){
            $sort = $sorts[$sort];
        }else{
            $sort =['created_at',SORT_DESC];
        }

        //生成查询参数
        $filter = $this->baseFilter;
        $query = Order::find()->where($filter);
        if($dateStart){
            $query->andFilterCompare('created_at', '>='.strtotime($dateStart));
        }
        if($dateEnd){
            $query->andFilterCompare('created_at', '<'.strtotime($dateEnd));
        }
        if($orderNo){
            $query->andwhere(['order_no' => $orderNo]);
        }
        if($merchantOrderNo){
            $query->andwhere(['merchant_order_no' => $merchantOrderNo]);
        }
        $summeryQuery = $query;
        if($status!==''){
            $query->andwhere(['status' => $status]);
        }

        if($notifyStatus!==''){
            $query->andwhere(['notify_status' => $notifyStatus]);
        }

        //生成分页数据
        $p = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
            ],
            'sort' => [
                'defaultOrder' => [
                    $sort[0] => $sort[1],
                ]
            ],
        ]);

        //格式化返回记录数据
        $records=[];
        foreach ($p->getModels() as $i=>$d){
            $records[$i]['id'] = $d->id;
            $records[$i]['order_no'] = $d->order_no;
            $records[$i]['merchant_order_no'] = $d->merchant_order_no;
            $records[$i]['amount'] = $d->amount;
            $records[$i]['status'] = $d->status;
            $records[$i]['status_str'] = $d->getStatusStr();
            $records[$i]['notify_status'] = $d->notify_status;
            $records[$i]['notify_status_str'] = $d->getNotifyStatusStr();
            $records[$i]['created_at'] = date('Y-m-d H:i:s');
            $records[$i]['notify_ret'] = '';
            if($d->notify_status === Order::NOTICE_STATUS_FAIL) $records[$i]['notify_ret'] = $d->notify_ret;
        }

        //分页数据
        $pagination = $p->getPagination();
        $total = $pagination->totalCount;
        $lastPage = ceil($pagination->totalCount/$perPage);
        $from = ($page-1)*$perPage;
        $to = $page*$perPage;

        //表格底部合计
        $summery['total'] = $pagination->totalCount;
        $summery['amount'] = $query->sum('amount');
        $summery['paid_amount'] = $summeryQuery->andwhere(['status' => Order::STATUS_PAID])->sum('paid_amount');
        $summery['paid_count'] = $summeryQuery->andwhere(['status' => Order::STATUS_PAID])->count('paid_amount');

        //格式化返回json结构
        $data = [
            'data'=>$records,
            'summery'=>$summery,
            "pagination"=>[
                "total" =>  $total,
                "per_page" =>  $perPage,
                "current_page" =>  $page,
                "last_page" =>  $lastPage,
                "from" =>  $from,
                "to" =>  $to
            ]
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }

    /**
     * 发送异步通知
     */
    public function actionSendNotify()
    {
        $statusArr = array_keys(Order::ARR_STATUS);
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', null, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '订单ID错误');

        $user    = Yii::$app->user->identity;

        $filter = $this->baseFilter;
        $filter['id'] = $id;
//        $order = Order::findOne($filter);
        $order = Order::find()->where($filter)->limit(1)->one();
        if(!$order){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单不存在');
        }

        $ret = LogicOrder::notify($order);

        return ResponseHelper::formatOutput(Macro::SUCCESS, '通知发送成功');
    }
}
