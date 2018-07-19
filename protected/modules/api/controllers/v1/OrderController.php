<?php
namespace app\modules\api\controllers\v1;

use app\common\models\model\BankCodes;
use app\common\models\model\Channel;
use app\common\models\model\ChannelAccount;
use app\common\models\model\Order;
use app\common\models\model\Track;
use app\common\models\model\User;
use app\components\Macro;
use app\components\RpcPaymentGateway;
use app\components\Util;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use app\modules\api\controllers\BaseController;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;

class OrderController extends BaseController
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

        //生成查询参数
        if(Yii::$app->user->identity && !Yii::$app->user->identity->isAdmin()){
            $this->baseFilter['merchant_id'] = Yii::$app->user->identity->id;
        }

        return $parentBeforeAction;
    }

    /**
    * 手工充值
    */
    public function actionAdd()
    {
        $payeeName = ControllerParameterValidator::getRequestParam($this->allParams, 'merchant_username', '',Macro::CONST_PARAM_TYPE_USERNAME,'充值账户错误');
        $amount = ControllerParameterValidator::getRequestParam($this->allParams, 'amount', null,Macro::CONST_PARAM_TYPE_DECIMAL,'充值金额错误');
        $payType = ControllerParameterValidator::getRequestParam($this->allParams, 'pay_type', Channel::METHOD_WEBBANK,Macro::CONST_PARAM_TYPE_INT,'充值渠道错误');
        $bankCode = ControllerParameterValidator::getRequestParam($this->allParams, 'bank_code', '',Macro::CONST_PARAM_TYPE_INT,'银行代码错误');

        if($payeeName){
            $merchant = User::findOne(['username'=>$payeeName]);
            if(empty($merchant)){
                Util::throwException(Macro::ERR_USER_NOT_FOUND);
            }
        }else{
            $merchant = Yii::$app->user->identity;
        }

        $ret = RpcPaymentGateway::recharge($amount,$payType,$bankCode,$merchant->username);

        return ResponseHelper::formatOutput(Macro::SUCCESS, '订单生成成功', $ret['data']);

    }

    /**
     * 收款订单
     * @roles admin,admin_operator
     */
    public function actionList()
    {
        $userObj = Yii::$app->user->identity;
        //允许的排序
        $sorts = [
            'created_at-'=>['created_at',SORT_DESC],
        ];

        $sort = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', 15, Macro::CONST_PARAM_TYPE_SORT, '分页参数错误',[1,100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,1000]);

        $orderNo = ControllerParameterValidator::getRequestParam($this->allParams, 'orderNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'平台订单号错误',[0,32]);
        $merchantOrderNo = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantOrderNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'商户订单号错误',[0,32]);
        $merchantUsername = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantUserName', '',Macro::CONST_PARAM_TYPE_STRING,'用户名错误',[0,32]);
        $merchantNo = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'商户编号错误',[0,32]);

        $status = ControllerParameterValidator::getRequestParam($this->allParams, 'status','',Macro::CONST_PARAM_TYPE_INT,'订单状态错误',[0,100]);

        $method = ControllerParameterValidator::getRequestParam($this->allParams, 'method','',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'支付类型错误',[0,100]);

        $channelAccount = ControllerParameterValidator::getRequestParam($this->allParams, 'channelAccount','',Macro::CONST_PARAM_TYPE_INT,'通道号错误',[0,100]);


        $notifyStatus = ControllerParameterValidator::getRequestParam($this->allParams, 'notifyStatus','',Macro::CONST_PARAM_TYPE_INT,'通知状态错误',[0,100]);
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');

        $minMoney = ControllerParameterValidator::getRequestParam($this->allParams, 'minMoney', '',Macro::CONST_PARAM_TYPE_DECIMAL,'最小金额输入错误');

        $maxMoney = ControllerParameterValidator::getRequestParam($this->allParams, 'maxMoney', '',Macro::CONST_PARAM_TYPE_DECIMAL,'最大金额输入错误');

        $export = ControllerParameterValidator::getRequestParam($this->allParams, 'export',0,Macro::CONST_PARAM_TYPE_INT,'导出参数错误');
        $exportType = ControllerParameterValidator::getRequestParam($this->allParams, 'exportType','',Macro::CONST_PARAM_TYPE_ENUM,'导出类型错误',['csv','txt']);

        if(!empty($sorts[$sort])){
            $sort = $sorts[$sort];
        }else{
            $sort =['created_at',SORT_DESC];
        }

        //生成查询参数
        $filter = $this->baseFilter;
        $query = Order::find()->where($filter);
        $dateStart = strtotime($dateStart);
        $dateEnd = strtotime($dateEnd);
        if(($dateEnd-$dateStart)>86400*31){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '时间筛选跨度不能超过31天');
            $dateStart=$dateEnd-86400*31;
        }

        if($dateStart){
            $query->andFilterCompare('created_at', '>='.$dateStart);
        }
        if($dateEnd){
            $query->andFilterCompare('created_at', '<'.$dateEnd);
        }
        if($minMoney){
            $query->andFilterCompare('amount', '>='.$minMoney);
        }
        if($maxMoney){
            $query->andFilterCompare('amount', '=<'.$maxMoney);
        }
        if($orderNo){
            $query->andwhere(['order_no' => $orderNo]);
        }
        if($merchantOrderNo){
            $query->andwhere(['merchant_order_no' => $merchantOrderNo]);
        }
        if($merchantUsername){
            $query->andwhere(['merchant_account' => $merchantUsername]);
        }
        if($merchantNo){
            $query->andwhere(['merchant_id' => $merchantNo]);
        }
        $summeryQuery = $query;
        if($status!==''){
            $query->andwhere(['status' => $status]);
        }

        if(!empty($channelAccount)){
            $query->andwhere(['channel_account_id' => $channelAccount]);
        }

        if(!empty($method)){
            $query->andwhere(['pay_method_code' => $method]);
        }

        if(!empty($notifyStatus)){
            $query->andwhere(['notify_status' => $notifyStatus]);
        }
//        var_dump($query->createCommand()->getRawSql());
        //生成分页数据
        $p = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page' => $page-1,
            ],
            'sort' => [
                'defaultOrder' => [
                    $sort[0] => $sort[1],
                ]
            ],
        ]);

        if($export==1 && $exportType){
            $fieldLabel = ["订单号","商户订单号","商户号","商户账户","金额","支付类型","银行","状态","时间","备注"];
            foreach ($fieldLabel as $fi=>&$fk){
                $fk = mb_convert_encoding($fk,'GBK');
            }
            $records = [];
            $records[] = $fieldLabel;
            $rows = $query->limit(5000)->all();
            foreach ($rows as $i => $d) {
                $record['order_no']          = "'" . $d->order_no;
                $record['merchant_order_no'] = "'" . $d->merchant_order_no;
//                $record['channel_order_no'] = $d->channel_order_no;
                $record['uid']                 = $d->merchant_id;
                $record['username']            = mb_convert_encoding($d->merchant_account, 'GBK');
                $record['amount']              = $d->amount;
                $record['pay_method_code_str'] = mb_convert_encoding(Channel::getPayMethodsStr($d->pay_method_code), 'GBK');
                $record['bank_name']           = mb_convert_encoding(BankCodes::getBankNameByCode($d->bank_code), 'GBK');
                $record['status_str']          = mb_convert_encoding($d->getStatusStr(), 'GBK');
                $record['created_at']          = date('Y-m-d H:i:s', $d->created_at);
                $record['bak']                 = $d->bak;
                $records[]                     = $record;
            }

            $outFilename='收款订单明细-'.date('YmdHi').'.'.$exportType;
            header('Content-type: application/octet-stream; charset=GBK');
            Header("Accept-Ranges: bytes");
            header('Content-Disposition: attachment; filename='.$outFilename);
            $fp = fopen('php://output', 'w');
            foreach ($records as $record){
                fputcsv($fp, $record);
            }
            fclose($fp);

            exit;
        }

//        获取渠道号 为筛选和订单详情准备数据
        $channelAccountOptions = ArrayHelper::map(ChannelAccount::getALLChannelAccount(), 'id', 'channel_name');
        $channelAccountOptions[0] = '全部';
        //格式化返回记录数据

        $records=[];
        $parentIds=[];
        foreach ($p->getModels() as $i=>$d){
            $records[$i]['id'] = $d->id;
            $parentIds[] = $d->id;
            $records[$i]['order_no'] = $d->order_no;
            $records[$i]['merchant_order_no'] = $d->merchant_order_no;
            $records[$i]['merchant_account'] = $d->merchant_account;
            $records[$i]['merchant_id'] = $d->merchant_id;
            $records[$i]['amount'] = $d->amount;
            $records[$i]['status'] = $d->status;
            $records[$i]['bank_code'] = $d->bank_code;
            $records[$i]['bank_name'] = BankCodes::getBankNameByCode($d->bank_code);
            $records[$i]['channel_account_name'] = $channelAccountOptions[$d->channel_account_id];
            $records[$i]['pay_method_code_str'] = Channel::getPayMethodsStr($d->pay_method_code);
            $records[$i]['status_str'] = $d->getStatusStr();
            $records[$i]['notify_status'] = $d->notify_status;
            $records[$i]['notify_status_str'] = $d->getNotifyStatusStr();
            $records[$i]['created_at'] = date('Y-m-d H:i:s',$d->created_at);
            $records[$i]['notify_ret'] = $d->notify_ret;
            $records[$i]['bak'] = str_replace("\n",'<br />', $d->bak);
            $records[$i]['settlement_type'] = $d->settlement_type;
            $records[$i]['expect_settlement_at'] = date('Y-m-d H:i:s',$d->expect_settlement_at);
            $records[$i]['settlement_at'] = $d->settlement_at?date('Y-m-d H:i:s',$d->settlement_at):'';
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

        //查询订单是否有调单记录
        $trackOptions = [];
        if(count($parentIds) > 0)
            $trackOptions = ArrayHelper::map(Track::checkTrack($parentIds,'order'),'parent_id','num');

        foreach($records as $key => $val){
            if (isset($trackOptions[$val['id']])){
                $records[$key]['track'] = 1;
            }else{
                $records[$key]['track'] = 0;
            }
        }
        //格式化返回json结构
        $data = [
            'data'=>$records,
            'condition'=>array(
                'statusOptions'=> Util::addAllLabelToOptionList(Order::ARR_STATUS, true),
                'notifyStatusOptions'=>Util::addAllLabelToOptionList(Order::ARR_NOTICE_STATUS,true),
                'channelAccountOptions'=>Util::addAllLabelToOptionList($channelAccountOptions,true),
                'methodOptions'=> Util::addAllLabelToOptionList(Channel::ARR_METHOD,true),
                'amount'=> $minMoney,
            ),
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
     * 重发订单通知
     */
    public function actionSendNotify()
    {
        $statusArr = array_keys(Order::ARR_STATUS);
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', null, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '订单ID错误');

        $filter = $this->baseFilter;
        $filter['id'] = $id;
//        $order = Order::findOne($filter);
        $order = Order::find()->where($filter)->limit(1)->one();
        if(!$order){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单不存在');
        }

        //接口日志埋点
        Yii::$app->params['operationLogFields'] = [
            'table'=>Order::tableName(),
            'pk'=>$order->id,
            'order_no'=>$order->order_no,
        ];

        RpcPaymentGateway::sendRechargeOrderNotify(0, [$order->order_no]);

        return ResponseHelper::formatOutput(Macro::SUCCESS, '通知发送成功');
    }

    /**
     * 到三方同步订单状态
     */
    public function actionSyncStatus()
    {
        $statusArr = array_keys(Order::ARR_STATUS);
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', null, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '订单ID错误');

        $filter = $this->baseFilter;
        $filter['id'] = $id;
//        $order = Order::findOne($filter);
        $order = Order::find()->where($filter)->limit(1)->one();
        if(!$order){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单不存在');
        }

        //接口日志埋点
        Yii::$app->params['operationLogFields'] = [
            'table'=>Order::tableName(),
            'pk'=>$order->id,
            'order_no'=>$order->order_no,
        ];

        RpcPaymentGateway::syncRechargeOrderStatus(0, [$order->order_no]);

        return ResponseHelper::formatOutput(Macro::SUCCESS, '订单状态同步成功');
    }

    /**
     * 订单详情
     */
    public function actionDetail()
    {
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', null, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '订单ID错误');
        $filter = $this->baseFilter;
        $filter['id'] = $id;
//        $order = Order::findOne($filter);
        $order = Order::find()->where($filter)->limit(1)->one();
        if(!$order){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单不存在');
        }else{
            $data['notify_url'] = $order->notify_url;
            $data['notify_times'] = $order->notify_times;
            $data['notify_ret'] = $order->notify_ret;
        }

        //接口日志埋点
        Yii::$app->params['operationLogFields'] = [
            'table'=>'p_orders',
            'pk'=>$order->id,
            'order_no'=>$order->order_no,
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }
    /**
     * 我的收款订单
     * @roles agent,merchant,merchant_financial,merchant_service
     */
    public function actionMyList()
    {
        $userObj = Yii::$app->user->identity;
        $user = $userObj->getMainAccount();
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
        $method = ControllerParameterValidator::getRequestParam($this->allParams, 'methodOptions','',Macro::CONST_PARAM_TYPE_INT,'支付类型错误',[0,100]);

        $notifyStatus = ControllerParameterValidator::getRequestParam($this->allParams, 'notifyStatus','',Macro::CONST_PARAM_TYPE_INT,'通知状态错误',[0,100]);
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');

        $minMoney = ControllerParameterValidator::getRequestParam($this->allParams, 'minMoney', '',Macro::CONST_PARAM_TYPE_DECIMAL,'最小金额输入错误');

        $maxMoney = ControllerParameterValidator::getRequestParam($this->allParams, 'maxMoney', '',Macro::CONST_PARAM_TYPE_DECIMAL,'最大金额输入错误');


        if(!empty($sorts[$sort])){
            $sort = $sorts[$sort];
        }else{
            $sort =['created_at',SORT_DESC];
        }

        //生成查询参数
        $filter = $this->baseFilter;
        $query = Order::find()->where($filter);
        $query->andWhere(['merchant_id'=>$user->id]);
        $dateStart = strtotime($dateStart);
        $dateEnd = strtotime($dateEnd);
        if(($dateEnd-$dateStart)>86400*31){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '时间筛选跨度不能超过31天');
            $dateStart=$dateEnd-86400*31;
        }
        if($dateStart){
            $query->andFilterCompare('created_at', '>='.$dateStart);
        }
        if($dateEnd){
            $query->andFilterCompare('created_at', '<'.$dateEnd);
        }
        if($minMoney){
            $query->andFilterCompare('amount', '>='.$minMoney);
        }
        if($maxMoney){
            $query->andFilterCompare('amount', '=<'.$maxMoney);
        }
        if($orderNo){
            $query->andwhere(['order_no' => $orderNo]);
        }
        if($merchantOrderNo){
            $query->andwhere(['merchant_order_no' => $merchantOrderNo]);
        }
        $summeryQuery = $query;
        if(!empty($status)){
            $query->andwhere(['status' => $status]);
        }

        if(!empty($method)){
            $query->andwhere(['pay_method_code' => $method]);
        }

        if(!empty($notifyStatus)){
            $query->andwhere(['notify_status' => $notifyStatus]);
        }
//        var_dump($query->createCommand()->getRawSql());
        //生成分页数据
        $p = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page' => $page-1,
            ],
            'sort' => [
                'defaultOrder' => [
                    $sort[0] => $sort[1],
                ]
            ],
        ]);

//        获取渠道号 为筛选和订单详情准备数据
        $channelAccountOptions = ArrayHelper::map(ChannelAccount::getALLChannelAccount(), 'id', 'channel_name');
        $channelAccountOptions[0] = '全部';
        //格式化返回记录数据

        $records=[];
        $parentIds=[];
        foreach ($p->getModels() as $i=>$d){
            $records[$i]['id'] = $d->id;
            $parentIds[] = $d->id;
            $records[$i]['order_no'] = $d->order_no;
            $records[$i]['merchant_order_no'] = $d->merchant_order_no;
            $records[$i]['merchant_account'] = $d->merchant_account;
            $records[$i]['merchant_id'] = $d->merchant_id;
            $records[$i]['amount'] = $d->amount;
            $records[$i]['status'] = $d->status;
            $records[$i]['bank_code'] = $d->bank_code;
            $records[$i]['bank_name'] = BankCodes::getBankNameByCode($d->bank_code);
            $records[$i]['channel_account_name'] = $channelAccountOptions[$d->channel_account_id] ;
            $records[$i]['pay_method_code_str'] = Channel::getPayMethodsStr($d->pay_method_code);
            $records[$i]['status_str'] = $d->getStatusStr();
            $records[$i]['notify_status'] = $d->notify_status;
            $records[$i]['notify_status_str'] = $d->getNotifyStatusStr();
            $records[$i]['created_at'] = date('Y-m-d H:i:s',$d->created_at);
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

        //查询订单是否有调单记录
        $trackOptions = [];
        if(count($parentIds) > 0)
            $trackOptions = ArrayHelper::map(Track::checkTrack($parentIds,'order'),'parent_id','num');

        foreach($records as $key => $val){
            if (isset($trackOptions[$val['id']])){
                $records[$key]['track'] = 1;
            }else{
                $records[$key]['track'] = 0;
            }
        }
        //格式化返回json结构

        $data = [
            'data'=>$records,
            'condition'=>array(
                'statusOptions'=> Order::ARR_STATUS,
                'notifyStatusOptions'=>Order::ARR_NOTICE_STATUS,
                'channelAccountOptions'=>$channelAccountOptions,
                'methodOptions'=> Channel::ARR_METHOD,
                'amount'=> $minMoney,
            ),
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

}
