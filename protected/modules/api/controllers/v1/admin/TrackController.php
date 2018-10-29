<?php
/**
 * Created by PhpStorm.
 * User: kk
 * Date: 2018/5/5
 * Time: 下午10:25
 */

namespace app\modules\api\controllers\v1\admin;
use app\common\models\model\ChannelAccount;
use app\common\models\model\Order;
use app\common\models\model\Remit;
use app\common\models\model\Track;
use app\components\Macro;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use app\modules\api\controllers\BaseController;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;


class TrackController extends BaseController
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
     * 调单追踪列表
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
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100000]);

        $orderNo = ControllerParameterValidator::getRequestParam($this->allParams, 'orderNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'结算订单号错误',[0,32]);

        $merchantOrderNo = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantOrderNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'商户订单号错误',[0,32]);

        $merchantNo = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'商户编号错误',[0,32]);

        $merchantAccount = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantAccount', '',Macro::CONST_PARAM_TYPE_CHINESE,'商户账号错误',[2,16]);

        $channelAccount = ControllerParameterValidator::getRequestParam($this->allParams, 'channelAccountOptions','',Macro::CONST_PARAM_TYPE_INT,'通道号错误',[0,100]);

        $statusOrder = ControllerParameterValidator::getRequestParam($this->allParams, 'statusOrder','',Macro::CONST_PARAM_TYPE_INT,'订单状态错误',[0,100]);
        $statusRemit = ControllerParameterValidator::getRequestParam($this->allParams, 'statusRemit','',Macro::CONST_PARAM_TYPE_INT,'订单状态错误',[0,100]);

        $typeTrack = ControllerParameterValidator::getRequestParam($this->allParams, 'typeTrack','',Macro::CONST_PARAM_TYPE_INT,'调单类型错误',[0,100]);

        $statusTrack = ControllerParameterValidator::getRequestParam($this->allParams, 'statusTrack','',Macro::CONST_PARAM_TYPE_INT,'调单状态错误',[0,100]);

        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');
        if(!empty($sorts[$sort])){
            $sort = $sorts[$sort];
        }else{
            $sort =['created_at',SORT_DESC];
        }
        $field = 'pt.*,po.order_no as po_order_no,po.merchant_order_no as po_merchant_order_no,po.status as po_status,po.channel_account_id as po_channel_account_id,po.amount as po_amount,po.merchant_id as po_merchant_id,po.merchant_account as po_merchant_account,';
        $field .= 'pr.order_no as pr_order_no,pr.merchant_order_no as pr_merchant_order_no,pr.status as pr_status,pr.channel_account_id as pr_channel_account_id,pr.amount as pr_amount,pr.merchant_id as pr_merchant_id,pr.merchant_account as pr_merchant_account ';
        $grouyBy = "pt.parent_id,pt.parent_type";
        $query = (new \yii\db\Query())
            ->select($field)
            ->from('p_track AS pt')
            ->leftJoin('p_orders AS po',"pt.parent_id = po.id AND pt.parent_type = 'order'")
            ->leftJoin('p_remit AS pr',"pt.parent_id = pr.id AND pt.parent_type = 'remit'")
            ->groupBy($grouyBy);

        $dateStart = strtotime($dateStart);
        $dateEnd = strtotime($dateEnd);
        if(($dateEnd-$dateStart)>86400*31){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '时间筛选跨度不能超过31天');
            $dateStart=$dateEnd-86400*31;
        }
        if($dateStart){
            $query->andFilterCompare('pt.created_at', '>='.$dateStart);
        }
        if($dateEnd){
            $query->andFilterCompare('pt.created_at', '<='.$dateEnd);
        }
        if($orderNo){
            $query->andWhere(['or','po.order_no="'.$orderNo.'"','pr.order_no="'.$orderNo.'"']);
        }
        if($merchantOrderNo){
            $query->andWhere(['or','po.merchant_order_no="'.$merchantOrderNo.'"','pr.merchant_order_no="'.$merchantOrderNo.'"']);
        }
        if($merchantNo){
            $query->andWhere(['or','po.merchant_id="'.$merchantNo.'"','pr.merchant_id="'.$merchantNo.'"']);
        }
        if($merchantAccount){
            $query->andWhere(['or','po.merchant_account="'.$merchantAccount.'"','pr.merchant_account="'.$merchantAccount.'"']);
        }
        if($channelAccount){
            $query->andWhere(['or','po.channel_account_id="'.$channelAccount.'"','pr.channel_account_id="'.$channelAccount.'"']);
        }
        if($statusOrder){
            $query->andWhere(['po.status' => $statusOrder]);
        }
        if($statusRemit){
            $query->andWhere(['pr.status' => $statusRemit]);
        }
        if($typeTrack!==''){
            $query->andwhere(['pt.type' => $typeTrack]);
        }
        if($statusTrack!==''){
            $query->andwhere(['pt.status' => $statusTrack]);
        }
        $query->orderBy('pt.created_at desc');
        $p = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page' => $page-1,
            ],
        ]);
        //        获取渠道号 为筛选和订单详情准备数据
        $channelAccountOptions = ArrayHelper::map(ChannelAccount::getALLChannelAccount(), 'id', 'channel_name');
        $channelAccountOptions[0] = '全部';

        $records=[];
        foreach ($p->getModels() as $i=>$d){
            $records[$i]['id'] = $d['id'];
            $records[$i]['parentName'] = Track::ARR_PARENTTYPE[$d['parent_type']];
            $records[$i]['orderNo'] = $d['parent_type'] =='order' ? $d['po_order_no'] : $d['pr_order_no'];
            $records[$i]['merchantOrderNo'] = $d['parent_type'] =='order' ? $d['po_merchant_order_no'] : $d['pr_merchant_order_no'];
            $records[$i]['orderStatus'] = $d['parent_type'] =='order' ? Order::ARR_STATUS[$d['po_status']]??$d['po_status'] : Remit::ARR_STATUS[$d['pr_status']]??$d['po_status'];
            $records[$i]['channelAccount'] = $d['parent_type'] =='order' ? $channelAccountOptions[$d['po_channel_account_id']]??$d['po_channel_account_id'] :
                $channelAccountOptions[$d['pr_channel_account_id']??$d['po_channel_account_id']];
            $records[$i]['orderAmount'] = $d['parent_type'] =='order' ? $d['po_amount'] : $d['pr_amount'];
            $records[$i]['merchantNo'] = $d['parent_type'] =='order' ? $d['po_merchant_id'] : $d['pr_merchant_id'];
            $records[$i]['merchantAccount'] = $d['parent_type'] =='order' ? $d['po_merchant_account'] : $d['pr_merchant_account'];
            $records[$i]['trackStatus'] =Track::ARR_STATUS[$d['status']];
            $records[$i]['trackType'] = Track::ARR_TPYE[$d['type']];
            $records[$i]['status'] = $d['status'];
            $records[$i]['type'] = $d['type'];
            $records[$i]['created_at'] = date('Y-m-d H:i:s',$d['created_at']);
            $records[$i]['uploadUrl'] = json_decode($d['upload'],true);
            $records[$i]['parentId'] = $d['parent_id'];
            $records[$i]['parentType'] = $d['parent_type'];
            $records[$i]['op_uid'] = $d['op_uid'];
            $records[$i]['op_username'] = $d['op_username'];
        }
        //分页数据
        $pagination = $p->getPagination();
        $total = $pagination->totalCount;
        $lastPage = ceil($pagination->totalCount/$perPage);
        $from = ($page-1)*$perPage;
        $to = $page*$perPage;

        $data = [
            'data'=>$records,
            'condition'=>array(
                'statusOrderOptions'=> Order::ARR_STATUS,
                'statusRemitOptions'=> Remit::ARR_STATUS,
                'statusTrackOptions'=> Track::ARR_STATUS,
                'typeTrackOptions' => Track::ARR_TPYE,
                'channelAccountOptions'=>$channelAccountOptions,
            ),
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
     * 添加调单追踪
     */
    public function actionAdd()
    {
        $user = Yii::$app->user->identity;
        $parentId = ControllerParameterValidator::getRequestParam($this->allParams, 'parentId', null, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '订单ID错误');
        $type = ControllerParameterValidator::getRequestParam($this->allParams, 'type','',Macro::CONST_PARAM_TYPE_INT,'追号类型错误',[0,100]);
        $parentType = ControllerParameterValidator::getRequestParam($this->allParams,'parentType','',Macro::CONST_PARAM_TYPE_STRING,'调单源错误',[0,100]);
        $upload = ControllerParameterValidator::getRequestParam($this->allParams,'upload','',Macro::CONST_PARAM_TYPE_ARRAY,'上传文件路径错误',[0,100]);
        $note = ControllerParameterValidator::getRequestParam($this->allParams,'note','',Macro::CONST_PARAM_TYPE_STRING,'备注错误',[0,100]);
//        Yii::$app->db->schema->refreshTableSchema(Track::tableName());
        $trackObj = new Track();
        $trackObj->parent_id = $parentId;
        $trackObj->parent_type = $parentType;
        $trackObj->type = $type;
        $trackObj->upload = json_encode($upload);
        $trackObj->note = $note;
        $trackObj->op_uid = $user->id;
        $trackObj->op_username = $user->username;
        $trackObj->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }
    /**
     * 调单详情
     */
    public function actionDetail()
    {
        $parentId = ControllerParameterValidator::getRequestParam($this->allParams, 'parentId', null, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '订单ID错误');
        $parentType = ControllerParameterValidator::getRequestParam($this->allParams,'parentType','',Macro::CONST_PARAM_TYPE_STRING,'调单源错误',[0,100]);
        $filter = ['parent_id'=>$parentId,'parent_type'=>$parentType];
        $query = Track::find()->where($filter)->orderBy('created_at desc');
        $list = $query->asArray()->all();
        foreach ($list as $key => $val){
            $val['status_name'] = Track::ARR_STATUS[$val['status']];
            $val['type_name'] = Track::ARR_TPYE[$val['type']];
            $val['uploadUrl'] = [];
            if($val['upload']){
                $tmp = json_decode($val['upload'],true);
                foreach ($tmp as $k => $v){
                    $val['uploadUrl'][$k] = Yii::$app->request->hostInfo.$v;
                }
            }
            $list[$key] = $val;
        }
        return ResponseHelper::formatOutput(Macro::SUCCESS, '', $list);
    }
    /**
     * 调单编辑
     */
    public function actionEdit()
    {
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', 0, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '调单ID错误');
        $parentId = ControllerParameterValidator::getRequestParam($this->allParams, 'parentId', 0, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '上级ID错误');
        $parentType = ControllerParameterValidator::getRequestParam($this->allParams, 'parentType', '', Macro::CONST_PARAM_TYPE_STRING, '上级类型错误');
        $status = ControllerParameterValidator::getRequestParam($this->allParams,'status',0,Macro::CONST_PARAM_TYPE_INT,'调单状态错误');
        $note = ControllerParameterValidator::getRequestParam($this->allParams,'note','',Macro::CONST_PARAM_TYPE_STRING,'调单备注错误');
//        $track = Track::findOne(['id'=>$id]);
        $track = Track::find()->where(['id'=>$id])->limit(1)->one();
        if(!$track){
            return ResponseHelper::formatOutput(Macro::ERR_TRACK_NON, '编辑的调单记录不存在');
        }

        Track::updateAll(['status'=>$status,'note'=>$note],['parent_id'=>$parentId,'parent_type'=>$parentType]);
        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

}