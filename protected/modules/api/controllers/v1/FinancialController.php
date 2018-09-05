<?php
namespace app\modules\api\controllers\v1;

use app\common\models\logic\LogicElementPagination;
use app\common\models\model\Financial;
use app\components\Macro;
use app\components\Util;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use app\modules\api\controllers\BaseController;
use Yii;

class FinancialController extends BaseController
{
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
            $this->baseFilter['uid'] = Yii::$app->user->identity->getMainAccount()->id;
        }

        return $parentBeforeAction;
    }

    /**
     * 收支明细
     * @roles admin
     */
    public function actionList()
    {
        $user = Yii::$app->user->identity;
        //允许的排序
        $sorts = [
            'created_at-'=>['created_at'=>SORT_DESC],
        ];

        $sort = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', 15, Macro::CONST_PARAM_TYPE_SORT, '分页参数错误',[1,100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,1000]);

        $orderNo = ControllerParameterValidator::getRequestParam($this->allParams, 'orderNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'平台订单号错误',[0,32]);

        $eventType = ControllerParameterValidator::getRequestParam($this->allParams, 'eventType','',Macro::CONST_PARAM_TYPE_ARRAY,'订单状态错误',[0,100]);
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');

        $uid = ControllerParameterValidator::getRequestParam($this->allParams, 'uid','',Macro::CONST_PARAM_TYPE_INT,'商户号错误');

        $username = ControllerParameterValidator::getRequestParam($this->allParams, 'username','',Macro::CONST_PARAM_TYPE_STRING,'商户账号号错误',[6,16]);

        $export = ControllerParameterValidator::getRequestParam($this->allParams, 'export',0,Macro::CONST_PARAM_TYPE_INT,'导出参数错误');
        $exportType = ControllerParameterValidator::getRequestParam($this->allParams, 'exportType','',Macro::CONST_PARAM_TYPE_ENUM,'导出类型错误商户账号号错误',['csv','txt']);
        if(!empty($sorts[$sort])){
            $sort = $sorts[$sort];
        }else{
            $sort = ['id'=>SORT_DESC];
        }

        //生成查询参数
        $filter = $this->baseFilter;
        $query = Financial::find()->where($filter);
        $query->andWhere(['status'=>Financial::STATUS_FINISHED]);

        $dateStart = strtotime($dateStart);
        $dateEnd = strtotime($dateEnd);
        if(($dateEnd-$dateStart)>86400*15){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '时间筛选跨度不能超过15天');
            $dateStart=$dateEnd-86400*15;
        }
        if($dateStart){
            $query->andFilterCompare('created_at', '>='.strtotime(date("Y-m-d 00:00:00",$dateStart)));
        }
        if($dateEnd){
            $query->andFilterCompare('created_at', '<'.strtotime(date("Y-m-d 23:59:59",$dateEnd)));
        }
        if($orderNo){
            $query->andwhere(['event_id' => $orderNo]);
        }

        if($uid){
            $query->andwhere(['uid' => $uid]);
        }
        if($username){
            $query->andwhere(['username' => $username]);
        }
        if($eventType){
            $query->andwhere(['event_type' => $eventType]);
        }

        if($export==1 && $exportType){
            $fieldLabel = ["订单号","商户号","商户账户","项目类型","变动前余额","金额","当前余额","状态","时间","备注"];
            foreach ($fieldLabel as $fi=>&$fk){
                $fk = mb_convert_encoding($fk,'GBK');
            }
            $records = [];
            $records[] = $fieldLabel;
            $rows = $query->limit(5000)->all();
            foreach ($rows as $i=>$d){
                $record['order_no'] = "'".$d->event_id;
                $record['uid'] = $d->uid;
                $record['username'] = mb_convert_encoding($d->username,'GBK');
                $record['event_type_str'] = mb_convert_encoding(Financial::getEventTypeStr($d->event_type),'GBK');
                $record['balance_before'] = $d->balance_before;
                $record['amount'] = $d->amount;
                $record['balance'] = $d->balance;
                $record['status_str'] = mb_convert_encoding(Financial::getStatusStr($d->status),'GBK');
                $record['created_at'] = date('Y-m-d H:i:s',$d->created_at);
                $record['bak'] = $d->bak;
                $records[] = $record;
            }

            $outFilename='收支明细-'.date('YmdHi').'.'.$exportType;
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

        //生成分页数据
        $fields = ['id','uid','username','event_id','amount','balance_before','balance','bak','event_id','status','event_type','created_at'];
        $paginationData = LogicElementPagination::getPagination($query,$fields,$page-1,$perPage,$sort);
        $records=[];
        foreach ($paginationData['data'] as $i=>$d){
            $records[$i] = $d;
            $records[$i]['event_type_str'] = Financial::getEventTypeStr($d['event_type']);
            $records[$i]['created_at'] = date('Y-m-d H:i:s',$d['created_at']);
            $records[$i]['status'] = $d['status'];
            $records[$i]['status_str'] = Financial::getStatusStr($d['status']);
        }

        //表格底部合计
        $summery = [];
        $sum = $query->select('event_type, sum(amount) as amount')->groupBy('event_type')->all();
        foreach ($sum as $s){
            $s = $s->toArray();
            $s['event_type_str'] = Financial::getEventTypeStr($s['event_type']);
            $summery[] = $s;
        }

        //格式化返回json结构
        $data = [
            'options'=>[
                'typeOptions'=> Util::addAllLabelToOptionList(Financial::ARR_EVENT_TYPES, true),
            ],
            'data'=>$records,
            'summery'=>$summery,
            "pagination"=>$paginationData['pagination'],
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }

    /**
     * 我的收支明细
     * @roles agent,merchant
     */
    public function actionMyList()
    {
//        $user = Yii::$app->user->identity;
        $userObj = Yii::$app->user->identity;
        $user = $userObj->getMainAccount();
        //允许的排序
        $sorts = [
            'created_at-'=>['created_at'=>SORT_DESC],
        ];

        $sort = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', 15, Macro::CONST_PARAM_TYPE_SORT, '分页参数错误',[1,100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,1000]);

        $orderNo = ControllerParameterValidator::getRequestParam($this->allParams, 'orderNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'平台订单号错误',[0,32]);

        $eventType = ControllerParameterValidator::getRequestParam($this->allParams, 'eventType','',Macro::CONST_PARAM_TYPE_INT,'订单状态错误',[0,100]);
        $notifyStatus = ControllerParameterValidator::getRequestParam($this->allParams, 'notifyStatus','',Macro::CONST_PARAM_TYPE_INT,'通知状态错误',[0,100]);
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');
//        $uid = ControllerParameterValidator::getRequestParam($this->allParams, 'uid','',Macro::CONST_PARAM_TYPE_INT,'商户号错误',[0,100]);
//        $username = ControllerParameterValidator::getRequestParam($this->allParams, 'username','',Macro::CONST_PARAM_TYPE_STRING,'商户账号号错误',[6,16]);
        $export = ControllerParameterValidator::getRequestParam($this->allParams, 'export',0,Macro::CONST_PARAM_TYPE_INT,'导出参数错误');
        $exportType = ControllerParameterValidator::getRequestParam($this->allParams, 'exportType','',Macro::CONST_PARAM_TYPE_ENUM,'导出类型错误',['csv','txt']);

        if(!empty($sorts[$sort])){
            $sort = $sorts[$sort];
        }else{
            $sort = ['id'=>SORT_DESC];
        }

        //生成查询参数
        $filter = $this->baseFilter;
        $query = Financial::find()->where($filter);
        $query->andWhere(['status'=>Financial::STATUS_FINISHED]);
        $query->andWhere(['uid'=>$user->id]);
        $dateStart = strtotime($dateStart);
        $dateEnd = strtotime($dateEnd);
        if(($dateEnd-$dateStart)>86400*15){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '时间筛选跨度不能超过15天');
            $dateStart=$dateEnd-86400*15;
        }
        if($dateStart){
            $query->andFilterCompare('created_at', '>='.strtotime(date("Y-m-d 00:00:00",$dateStart)));
        }
        if($dateEnd){
            $query->andFilterCompare('created_at', '<'.strtotime(date("Y-m-d 23:59:59",$dateEnd)));
        }
        if($orderNo){
            $query->andwhere(['event_id' => $orderNo]);
        }

//        if($uid){
//            $query->andwhere(['uid' => $uid]);
//        }
//        if($username){
//            $query->andwhere(['username' => $username]);
//        }
        $summeryQuery = $query;
        if($eventType){
            $query->andwhere(['event_type' => $eventType]);
        }

        if($notifyStatus!==''){
            $query->andwhere(['notify_status' => $notifyStatus]);
        }


        if($export==1 && $exportType){
            $fieldLabel = ["订单号","商户号","商户账户","项目类型","变动前余额","金额","当前余额","状态","时间","备注"];
            foreach ($fieldLabel as $fi=>&$fk){
                $fk = mb_convert_encoding($fk,'GBK');
            }
            $records = [];
            $records[] = $fieldLabel;
            $rows = $query->limit(5000)->all();
            foreach ($rows as $i=>$d){
                $record['order_no'] = "'".$d->event_id;
                $record['uid'] = $d->uid;
                $record['username'] = mb_convert_encoding($d->username,'GBK');
                $record['event_type_str'] = mb_convert_encoding(Financial::getEventTypeStr($d->event_type),'GBK');
                $record['balance_before'] = $d->balance_before;
                $record['amount'] = $d->amount;
                $record['balance'] = $d->balance;
                $record['status_str'] = mb_convert_encoding(Financial::getStatusStr($d->status),'GBK');
                $record['created_at'] = date('Y-m-d H:i:s',$d->created_at);
                $record['bak'] = $d->bak;
                $records[] = $record;
            }

            $outFilename='收支明细-'.$user->username.'-'.date('YmdHi').'.'.$exportType;
            header('Content-type: application/octet-stream; charset=GBK');
            Header("Accept-Ranges: bytes");
            header('Content-Disposition: attachment; filename='.$outFilename);
            $fp = fopen('php://output', 'w');
            foreach ($records as $record){
                if(!is_array($record) || !$record){
                    continue;
                }
                fputcsv($fp, $record);
            }
            fclose($fp);

            exit;
        }

        //生成分页数据
        $fields = ['id','uid','username','event_id','amount','balance_before','balance','bak','event_id','status','event_type','created_at','to_username'];
        $paginationData = LogicElementPagination::getPagination($query,$fields,$page-1,$perPage,$sort);
        $records=[];
        foreach ($paginationData['data'] as $i=>$d){
            $records[$i]                   = $d;
            $records[$i]['event_type_str'] = Financial::getEventTypeStr($d['event_type']);
            $records[$i]['created_at']     = date('Y-m-d H:i:s', $d['created_at']);
            $records[$i]['status']         = $d['status'];
            $records[$i]['status_str']     = Financial::getStatusStr($d['status']);
        }

        //表格底部合计
        $summery = [];
        $sum = $query->select('event_type, sum(amount) as amount')->groupBy('event_type')->all();
        foreach ($sum as $s){
            $s = $s->toArray();
            $s['event_type_str'] = Financial::ARR_EVENT_TYPES[$s['event_type']]??'-';
            $summery[] = $s;
        }

        //格式化返回json结构
        $typeOptions = Util::addAllLabelToOptionList(Financial::ARR_EVENT_TYPES, true);
        //普通用户不显示分润类型
        if($userObj->isMerchant()){
            $typeOptionsDisableArr = [Financial::EVENT_TYPE_RECHARGE_BONUS,Financial::EVENT_TYPE_REMIT_BONUS,Financial::EVENT_TYPE_RECHARGE_BONUS_REFUND,Financial::EVENT_TYPE_REFUND_REMIT_BONUS];
            foreach ($typeOptions as $tk=>$tv){
                if(in_array($tv['id'],$typeOptionsDisableArr)){
                    unset($typeOptions[$tk]);
                }
            }
        }
        $data = [
            'options'=>[
                'typeOptions'=> $typeOptions,
            ],
            'data'=>$records,
            'summery'=>$summery,
            "pagination"=>$paginationData['pagination'],
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }


}
