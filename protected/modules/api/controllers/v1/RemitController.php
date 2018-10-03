<?php
namespace app\modules\api\controllers\v1;

use app\common\models\model\BankCodes;
use app\common\models\model\ChannelAccount;
use app\common\models\model\LogOperation;
use app\common\models\model\Remit;
use app\common\models\model\SiteConfig;
use app\common\models\model\Track;
use app\common\models\model\UploadedFile;
use app\common\models\model\UserBlacklist;
use app\common\models\model\UserPaymentInfo;
use app\components\Macro;
use app\components\RpcPaymentGateway;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use app\modules\api\controllers\BaseController;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;

class RemitController extends BaseController
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
            $this->baseFilter['merchant_id'] = Yii::$app->user->identity->getMainAccount()->id;
        }

        return $parentBeforeAction;
    }

    /**
     * 出款订单
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

        $merchantNo = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'商户编号错误',[0,32]);
        $merchantAccount = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantAccount', '',Macro::CONST_PARAM_TYPE_USERNAME,'商户账号错误',[2,16]);
        $orderNo = ControllerParameterValidator::getRequestParam($this->allParams, 'orderNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'结算订单号错误',[0,32]);
        $merchantOrderNo = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantOrderNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'商户订单号错误',[0,32]);
        $channelOrderNo = ControllerParameterValidator::getRequestParam($this->allParams, 'channelOrderNo', '',Macro::CONST_PARAM_TYPE_STRING,'渠道订单号错误');
//        $bankAccount = ControllerParameterValidator::getRequestParam($this->allParams, 'backAccount', '',Macro::CONST_PARAM_TYPE_CHINESE,'持卡人错误',[2,8]);
        $bankNo = ControllerParameterValidator::getRequestParam($this->allParams, 'bankNo', '',Macro::CONST_PARAM_TYPE_INT,'卡号错误');
        $channelAccount = ControllerParameterValidator::getRequestParam($this->allParams, 'channelAccount','',Macro::CONST_PARAM_TYPE_ARRAY,'通道号错误',[0,100]);

        $status = ControllerParameterValidator::getRequestParam($this->allParams, 'status','',Macro::CONST_PARAM_TYPE_ARRAY,'订单状态错误',[0,100]);
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
        $query = Remit::find()->where($filter);
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
            $query->andFilterCompare('amount', '<='.$maxMoney);
        }
        if($merchantNo){
            $query->andwhere(['merchant_id' => $merchantNo]);
        }
        if($merchantAccount){
            $query->andwhere(['merchant_account' => $merchantAccount]);
        }

        if(!empty($channelAccount)){
            $query->andwhere(['channel_account_id' => $channelAccount]);
        }
//        if($bankAccount!==''){
//            $query->andwhere(['bank_account' => $bankAccount]);
//        }
        if($bankNo!==''){
            $query->andwhere(['bank_no' => $bankNo]);
        }
        $summeryQuery = $query;

        if($status){
            $query->andwhere(['status' => $status]);
        }
        //订单号查询情况下忽略其他条件
        if($orderNo || $merchantOrderNo || $channelOrderNo) {
            $query->where=[];
            if($orderNo){
                $query->andwhere(['order_no' => $orderNo]);
            }
            if($merchantOrderNo){
                $query->andwhere(['merchant_order_no' => $merchantOrderNo]);
            }
            if($channelOrderNo){
                $query->andwhere(['channel_order_no' => $channelOrderNo]);
            }
        }

        if($export==1 && $exportType){
            $fieldLabel = ["订单号","商户订单号","商户号","商户账户","金额","银行","姓名","卡号","状态","时间","备注"];
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
                $record['bank_name']           = mb_convert_encoding($d->bank_name, 'GBK');
                $record['bank_account']           = mb_convert_encoding($d->bank_account, 'GBK');
                $record['bank_no']           = mb_convert_encoding($d->bank_no, 'GBK');
                $record['status_str'] = $d->showStatusStr();
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
        $parentIds = $pageOrderNoList= [];
        foreach ($p->getModels() as $i=>$d){
            $parentIds[$i] = $d->id;
            $records[$i]['id'] = $d->id;
            $pageOrderNoList[] = $d->order_no;
            $records[$i]['inBlackList'] = 0;
            $records[$i]['order_no'] = $d->order_no;
            $records[$i]['merchant_id'] = $d->merchant_id;
            $records[$i]['merchant_account'] = $d->merchant_account;
            $records[$i]['merchant_order_no'] = $d->merchant_order_no;
            $records[$i]['channel_order_no'] = $d->channel_order_no;
            $records[$i]['channel_account_name'] = $channelAccountOptions[$d->channel_account_id]??'' ;
            $records[$i]['amount'] = $d->amount;
            $records[$i]['remited_amount'] = $d->remited_amount;
            $records[$i]['merchant_check_status'] = $d->merchant_check_status;
            $records[$i]['need_merchant_check'] = $d->need_merchant_check;
            $records[$i]['merchant_check_status_str'] = '-';
            if($d->need_merchant_check){
                $records[$i]['merchant_check_status_str'] = Remit::ARR_MERCHANT_CHECK_STATUS[$d->merchant_check_status]??'-';
            }
            $records[$i]['status'] = $d->status;
            $records[$i]['bank_ret'] = str_replace("\n",'<br />', $d->bank_ret);
            $records[$i]['status_str'] = Remit::ARR_STATUS[$d->status]??'-';//$d->showStatusStr();
            $records[$i]['bank_no'] = $d->bank_no;
            $records[$i]['bank_account'] = $d->bank_account;
            $records[$i]['bank_code'] = $d->bank_code;
            $records[$i]['bank_name'] = !empty($d->bank_name)?$d->bank_name:BankCodes::getBankNameByCode($d->bank_code);
            $records[$i]['bak'] = str_replace("\n",'<br />', $d->bak);
            $records[$i]['created_at'] = date('Y-m-d H:i:s',$d->created_at);
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

        $summery['all_status_list'] = [
            [
            'status'=>"_all_",
            'status_str'=>'总计',
            'amount'=>$summery['amount']?$summery['amount']:0,
            'nums'=>$summery['total'],
            ]
        ];
        $allStatusList = $summeryQuery->select('status,sum(amount) as amount,count(amount) as nums')->groupBy('status')->asArray()->all();
        foreach ($allStatusList as $k=>$d){
            $d['status_str'] = Remit::ARR_STATUS[$d['status']]??'-';
            $summery['all_status_list'][] = $d;
        }

        //查询订单是否有调单记录
        $trackOptions = [];
        if(count($parentIds) > 0)
        $trackOptions = ArrayHelper::map(Track::checkTrack($parentIds,'remit'),'parent_id','num');

        foreach($records as $key => $val){
            if (isset($trackOptions[$val['id']])){
                $records[$key]['track'] = 1;
            }else{
                $records[$key]['track'] = 0;
            }
        }

        if(count($pageOrderNoList) > 0){
            $rawBlacklist = UserBlacklist::checkOrderNoInBalcklist($pageOrderNoList,2);
            $blackList = ArrayHelper::map($rawBlacklist,'order_no','num');

            foreach($records as $key => $val){
                if (isset($blackList[$val['order_no']])){
                    $records[$key]['inBlackList'] = 1;
                }
            }
        }

        //格式化返回json结构
        $data = [
            'data'=>$records,
            'condition'=>array(
                'statusOptions'=> ArrayHelper::merge([Macro::SELECT_OPTION_ALL=>'全部'],Remit::ARR_STATUS),
                'channelAccountOptions'=>$channelAccountOptions,
            ),
            'summery' => $summery,
            'remitAutoCommitStatus' => SiteConfig::findOne(['title'=>'enable_remit_commit'])->toArray(),
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
     * 显示批量提款信息
     */
    public function actionBatchRemit()
    {

        $uploadId = ControllerParameterValidator::getRequestParam($this->allParams, 'upload_id',0,Macro::CONST_PARAM_TYPE_INT,'上传文件ID错误');
//        $uploadInfo = UploadedFile::findOne(['id'=>$uploadId]);
        $uploadInfo = UploadedFile::find()->where(['id'=>$uploadId])->limit(1)->one();
        $filename = Yii::getAlias('@webroot').$uploadInfo->filename;
        $data = [];
        $fileInfo = [];
        $user = Yii::$app->user->identity->getMainAccount();
        $paymentInfo = UserPaymentInfo::find()->where(['user_id'=>$user->id])->select('remit_channel_id')->asArray()->all();
        if(!$paymentInfo){
            return ResponseHelper::formatOutput(Macro::ERR_REMIT_BANK_CONFIG, '没有配置提款银行');
        }
        $channelIds = [];
        foreach ($paymentInfo as $key =>$val){
            $channelIds[$key] = $val['remit_channel_id'];
        }
        $bankOptions = ArrayHelper::map(BankCodes::getBankList($channelIds), 'bank_name','platform_bank_code');
        $user->paymentInfo->getRemitChannel();
        $remit_quota_pertime = $user->paymentInfo->remit_quota_pertime;
        $channelAccount = ChannelAccount::findOne(['id'=>$user->paymentInfo->remit_channel_account_id]);
        $channel_account_remit_quota_pertime = $channelAccount->remit_quota_pertime;
        if($uploadInfo->type == 'text/plain'){
            $file = fopen($filename,'r');
            $i=0;
            //输出文本中所有的行，直到文件结束为止。
            while(! feof($file))
            {
                $list= explode("\t", fgets($file));//fgets()函数从文件指针中读取一行
                $fileInfo[$i] = @iconv('gb2312','utf-8',$list[0]);
                $i++;
            }
            fclose($file);
            $j = 0;
            foreach ($fileInfo as $key => $val){
                if($val && $key > 0){
                    $tmp = explode(',',$val);
                    $data[$j]['bank_code'] = '';
                    $data[$j]['status'] = 0;
                    if($bankOptions[$tmp[0]]){
                        $data[$j]['bank_code'] = $bankOptions[$tmp[0]];
                        $data[$j]['bank_name'] = $tmp[0];
                    }else{
                        $data[$j]['bank_name'] = $tmp[0].'--不支持该银行';
                        $data[$j]['status'] = 1;
                    }
                    $data[$j]['bank_number'] = $tmp[1];
                    if($data[$j]['bank_number'] && (strlen($data[$j]['bank_number']) < 16 || strlen($data[$j]['bank_number']) < 19)){
                        $data[$j]['status'] = 1;
                        $data[$j]['bank_number'] .= '--银行卡号不正确';
                    }
                    $data[$j]['real_name'] = $tmp[2];
                    $data[$j]['amount'] = $tmp[3];
                    if(
                        bccomp($tmp[3], $user->balance, 6)===1
//                        || $remit_quota_pertime && (bccomp($tmp[3], $remit_quota_pertime, 6)===1)
//                        || $channel_account_remit_quota_pertime && (bccomp($tmp[3], $channel_account_remit_quota_pertime, 6)===1)
                    ){
                        $data[$j]['status'] = 1;
//                        $data[$j]['amount'] .= "---单条提款金额（{$tmp[3]}）大于当前余额($user->balance), 或者大于单次提款限额({$remit_quota_pertime})，或者大于渠道单次提款限额({$channel_account_remit_quota_pertime})";
                        $data[$j]['amount'] .= "--单条提款金额（{$tmp[3]}）大于当前余额($user->balance)";
                    }
                    $j++;
                }
            }
        }else{
            $fileType   = \PHPExcel_IOFactory::identify($filename); //文件名自动判断文件类型
            $excelReader  = \PHPExcel_IOFactory::createReader($fileType);
            $phpexcel    = $excelReader->load($filename)->getSheet(0);//载入文件并获取第一个sheet
            $total_line  = $phpexcel->getHighestRow();//总行数
            $total_column= $phpexcel->getHighestColumn();//总列数
            if($total_line > 1){
                for($row = 2;$row <= $total_line; $row++){
                    for($column = 'A'; $column <= $total_column; $column++){
                        $fileInfo[$row][] = trim($phpexcel->getCell($column.$row)->getValue());
                    }
                }
                $i = 0;
                foreach ($fileInfo as $key=>$val){
                    $data[$i]['bank_code'] = '';
                    $data[$i]['status'] = 0;
                    if($bankOptions[$val[0]]){
                        $data[$i]['bank_code'] = $bankOptions[$val[0]];
                        $data[$i]['bank_name'] = $val[0];
                    }else{
                        $data[$i]['bank_name'] = $val[0].'--'.'不支持该银行';
                        $data[$i]['status'] = 1;
                    }
                    $data[$i]['bank_number'] = $val[1];
                    if($data[$i]['bank_number'] && (strlen($data[$i]['bank_number']) < 16 || strlen($data[$i]['bank_number']) < 19)){
                        $data[$i]['status'] = 1;
                        $data[$i]['bank_number'] .= '--银行卡号不正确';
                    }
                    $data[$i]['real_name'] = $val[2];
                    $data[$i]['amount'] = $val[3];
                    if(
                        bccomp($val[3], $user->balance, 6)===1
//                        || $remit_quota_pertime && (bccomp($val[3], $remit_quota_pertime, 6)===1)
//                        || $channel_account_remit_quota_pertime && (bccomp($val[3], $channel_account_remit_quota_pertime, 6)===1)
                        ){
                        $data[$i]['status'] = 1;
                        $data[$i]['amount'] .= "--单条提款金额（{$val[3]}）大于当前余额($user->balance)";
//                        $data[$i]['amount'] .= "---单条提款金额（{$val[3]}）大于当前余额($user->balance), 或者大于单次提款限额({$remit_quota_pertime})，或者大于渠道单次提款限额({$channel_account_remit_quota_pertime})";
                    }
                    $i++;
                }
            }
        }
        if(!$data){
            return ResponseHelper::formatOutput(Macro::ERR_EXCEL_BATCH_REMIT, '没有可操作内容');
        }
        if(count($data) > 300 ){
            return ResponseHelper::formatOutput(Macro::ERR_EXCEL_BATCH_REMIT_NUMBERS, '批量提款一次最多300条');
        }
        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }
    /**
     * 获取提款的银行配置
     */
    public function actionGetBankList()
    {
        $user = Yii::$app->user->identity->getMainAccount();
        $paymentInfo = UserPaymentInfo::find()->where(['user_id'=>$user->id])->select('remit_channel_id')->asArray()->all();
        if(!$paymentInfo){
            return ResponseHelper::formatOutput(Macro::ERR_REMIT_BANK_CONFIG, '没有配置提款银行');
        }
        $channelIds = [];
        foreach ($paymentInfo as $key =>$val){
            $channelIds[$key] = $val['remit_channel_id'];
        }
        $data['bankOptions'] = ArrayHelper::map(BankCodes::getBankList($channelIds), 'platform_bank_code', 'bank_name');
        $data['balance'] = $user->balance;
        $user->paymentInfo->getRemitChannel();
        $data['remit_quota_pertime'] = $user->paymentInfo->remit_quota_pertime;

        $data['channel_account_remit_quota_pertime'] = 0;
        if($user->paymentInfo->remit_channel_id){
            $channelAccount = ChannelAccount::findOne(['id'=>$user->paymentInfo->remit_channel_account_id]);
            $data['channel_account_remit_quota_pertime'] = $channelAccount->remit_quota_pertime;
        }
        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }
    /**
     * 批量提款处理
     */
    public function actionSingleBatchRemit()
    {
        $user = Yii::$app->user->identity->getMainAccount();
        $userObj = Yii::$app->user->identity;
        if($user->paymentInfo->allow_manual_remit != 1){
            return ResponseHelper::formatOutput(Macro::ERR_ALLOW_MANUAL_REMIT, "账户未开通手工提款功能!");
        }

        $financial_password_hash = ControllerParameterValidator::getRequestParam($this->allParams, 'financial_password_hash','',Macro::CONST_PARAM_TYPE_STRING,'资金密码必须在8位以上');
        $key_2fa = ControllerParameterValidator::getRequestParam($this->allParams,'key_2fa','',Macro::CONST_PARAM_TYPE_INT,'验证码错误',[6]);
        $remitData = ControllerParameterValidator::getRequestParam($this->allParams, 'remitData',null,Macro::CONST_PARAM_TYPE_ARRAY,'提款数据错误');
        if(!$user->validateFinancialPassword($financial_password_hash)) {
            return ResponseHelper::formatOutput(Macro::ERR_USER_FINANCIAL_PASSWORD, '资金密码不正确');
        }
        $googleObj = new \PHPGangsta_GoogleAuthenticator();
        if(!$googleObj->verifyCode($userObj->key_2fa,$key_2fa,2)){
            return ResponseHelper::formatOutput(Macro::ERR_USER_KEY_FA, '安全令牌不正确');
        }
        if($remitData && count($remitData) < 300){
            $remitArr = [];
            $balance = $user->balance;
            //不用判断单笔限额,后端接口支持自动拆分
//            $user->paymentInfo->getRemitChannel();
//            $remit_quota_pertime = $user->paymentInfo->remit_quota_pertime;
//            $channelAccount = ChannelAccount::findOne(['id'=>$user->paymentInfo->remit_channel_account_id]);
//            $channel_account_remit_quota_pertime = $channelAccount->remit_quota_pertime;
            $totalAmount = 0;
            $i = 0;
            foreach ($remitData as $val){
                if($val['status'] == 0){
                    //['amount'=>'金额','bank_code'=>'银行代码','bank_no'=>'卡号','bank_account'=>'持卡人',
                    if(
                        bccomp($val['amount'], $balance, 6)===1
                        //不用判断单笔限额,后端接口支持自动拆分
//                        || $remit_quota_pertime && (bccomp($val['amount'], $remit_quota_pertime, 6)===1)
//                        || $channel_account_remit_quota_pertime && (bccomp($val['amount'], $channel_account_remit_quota_pertime, 6)===1)
                    ){
//                        return ResponseHelper::formatOutput(Macro::ERR_EXCEL_BATCH_REMIT_AMOUNT, "单条提款金额（{$val['amount']}）大于当前余额($balance), 或者大于单次提款限额({$remit_quota_pertime})，或者大于渠道单次提款限额({$channel_account_remit_quota_pertime})");
                        return ResponseHelper::formatOutput(Macro::ERR_EXCEL_BATCH_REMIT_AMOUNT, "单条提款金额（{$val['amount']}）大于当前余额($balance)");
                    }

                    $remitArr[$i]['amount'] = $val['amount'];
                    $remitArr[$i]['bank_code'] = $val['bank_code'];
                    $remitArr[$i]['bank_no'] = $val['bank_number'];
                    $remitArr[$i]['bank_account'] = $val['real_name'];
                    $totalAmount = bcadd($totalAmount,$val['amount'],2);
                    $i++;
                }
            }
            if($totalAmount > $balance){
                return ResponseHelper::formatOutput(Macro::ERR_EXCEL_BATCH_REMIT_TOTAL_AMOUNT, '总提款金额({$totalAmount})大于当前余额总提款金额({$balance})');
            }
            unset($remitData);
            RpcPaymentGateway::remit($user->username,$remitArr,md5(json_encode($remitArr)));
            return ResponseHelper::formatOutput(Macro::SUCCESS);
        }else{
            return ResponseHelper::formatOutput(Macro::ERR_EXCEL_BATCH_REMIT_NUMBERS, '提款笔数超过300条');
        }
    }

    /**
     * 同步出款状态
     */
    public function actionSyncStatus()
    {
        $idList = ControllerParameterValidator::getRequestParam($this->allParams, 'idList', null, Macro::CONST_PARAM_TYPE_ARRAY, '订单ID错误');

        $filter = $this->baseFilter;
        $filter['id'] = $idList;
        $filter['status'] = [Remit::STATUS_DEDUCT,Remit::STATUS_BANK_PROCESSING];

        $maxNum = 100;
        if(count($idList)>$maxNum){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, "单次最多同步{$maxNum}个订单");
        }
        $orders = [];
        $rawOrders = (new \yii\db\Query())
            ->select(['id','order_no'])
            ->from(Remit::tableName())
            ->where($filter)
            ->limit($maxNum)
            ->all();
        foreach ($rawOrders as $o){
            $orders[] = $o['order_no'];

            //接口日志埋点
            Yii::$app->params['operationLogFields'] = [
                'table'=>'p_remit',
                'pk'=>$o['id'],
                'order_no'=>$o['order_no'],
            ];
            LogOperation::inLog('ok');
        }

        if(!$orders){
//            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单不存在');
        }else{
            RpcPaymentGateway::syncRemitStatus(0, $orders);
        }

        return ResponseHelper::formatOutput(Macro::SUCCESS, '同步请求已提交');
    }

    /**
     * 我的出款订单
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

        $merchantNo = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'商户编号错误',[0,32]);
        $merchantAccount = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantAccount', '',Macro::CONST_PARAM_TYPE_USERNAME,'商户账号错误');
        $orderNo = ControllerParameterValidator::getRequestParam($this->allParams, 'orderNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'结算订单号错误',[0,32]);
        $merchantOrderNo = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantOrderNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'商户订单号错误',[0,32]);

        $bankNo = ControllerParameterValidator::getRequestParam($this->allParams, 'bankNo', '',Macro::CONST_PARAM_TYPE_INT,'卡号错误');

        $status = ControllerParameterValidator::getRequestParam($this->allParams, 'status','',Macro::CONST_PARAM_TYPE_INT,'订单状态错误',[0,100]);
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');
        $minMoney = ControllerParameterValidator::getRequestParam($this->allParams, 'minMoney', '',Macro::CONST_PARAM_TYPE_DECIMAL,'最小金额输入错误');
        $maxMoney = ControllerParameterValidator::getRequestParam($this->allParams, 'maxMoney', '',Macro::CONST_PARAM_TYPE_DECIMAL,'最大金额输入错误');
        $selfCheck = ControllerParameterValidator::getRequestParam($this->allParams, 'selfCheck', 0,Macro::CONST_PARAM_TYPE_INT,'审核参数错误');
        $checkStatus = ControllerParameterValidator::getRequestParam($this->allParams, 'checkStatus', 0,Macro::CONST_PARAM_TYPE_INT,'审核状态错误');
        $export = ControllerParameterValidator::getRequestParam($this->allParams, 'export',0,Macro::CONST_PARAM_TYPE_INT,'导出参数错误');
        $exportType = ControllerParameterValidator::getRequestParam($this->allParams, 'exportType','',Macro::CONST_PARAM_TYPE_ENUM,'导出类型错误',['csv','txt']);

        if(!empty($sorts[$sort])){
            $sort = $sorts[$sort];
        }else{
            $sort =['created_at',SORT_DESC];
        }

        //生成查询参数
        $filter = $this->baseFilter;
        $query = Remit::find()->where($filter);
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
        if($merchantNo){
            $query->andwhere(['merchant_id' => $merchantNo]);
        }
        if($merchantAccount){
            $query->andwhere(['merchant_account' => $merchantAccount]);
        }
        if($orderNo){
            $query->andwhere(['order_no' => $orderNo]);
        }
        if($merchantOrderNo){
            $query->andwhere(['merchant_order_no' => $merchantOrderNo]);
        }
        if($bankNo!==''){
            $query->andwhere(['bank_no' => $bankNo]);
        }
        $summeryQuery = $query;

        //商户审核订单列表请求
        if($selfCheck){
            $query->andwhere(['need_merchant_check' => $selfCheck]);
            $query->andwhere(['merchant_check_status' => $checkStatus]);
            $query->andwhere(['status' => Remit::STATUS_DEDUCT]);
        }
        //普通订单列表
        elseif($status!==''){
            $query->andwhere(['bank_status' => $status]);
        }

        if(!$selfCheck){
            //只查询不需要商户审核,或者通过了商户审核的
            $merchantCheckStatusCanBeShow = [Remit::MERCHANT_CHECK_STATUS_CHECKED,Remit::MERCHANT_CHECK_STATUS_DENIED];
            $query->andFilterWhere([
                    'or',
                    ['need_merchant_check'=> 0],
                    [
                        'and',
                        'need_merchant_check=1',
                        'merchant_check_status IN('.implode(',',$merchantCheckStatusCanBeShow).')'
                    ]
                ]
            );
        }


        if($export==1 && $exportType){
            $fieldLabel = ["订单号","商户订单号","商户号","卡号","姓名","银行","金额","状态","下单时间","成功时间"];
            foreach ($fieldLabel as $fi=>&$fk){
                $fk = mb_convert_encoding($fk,'GBK');
            }
            $records = [];
            $records[] = $fieldLabel;
            $rows = $query->limit(5000)->all();
            foreach ($rows as $i=>$d){
                $record['order_no'] = "'".$d->order_no;
                $record['merchant_order_no'] = "'".$d->merchant_order_no;
                $record['uid'] = $d->merchant_id;
                $record['bank_no'] = "'".$d->bank_no;
                $record['bank_account'] = mb_convert_encoding($d->bank_account,'GBK');
                $record['bank_name'] = mb_convert_encoding(!empty($d->bank_name)?$d->bank_name:BankCodes::getBankNameByCode($d->bank_code),'GBK');
                $record['amount'] = $d->amount;
                $record['status_str'] = mb_convert_encoding($d->showStatusStr($d->status),'GBK');
                $record['created_at'] = date('Y-m-d H:i:s',$d->created_at);
                $record['remit_at'] = $d->remit_at?date('Y-m-d H:i:s',$d->remit_at):'';
                $records[] = $record;
            }

            $outFilename='我的出款订单-'.$user->username.'-'.date('YmdHi').'.'.$exportType;
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

        //格式化返回记录数据
        $records=[];
        $parentIds = [];
        foreach ($p->getModels() as $i=>$d){
            $parentIds[$i] = $d->id;
            $records[$i]['id'] = $d->id;
            $records[$i]['order_no'] = $d->order_no;
            $records[$i]['merchant_id'] = $d->merchant_id;
            $records[$i]['merchant_account'] = $d->merchant_account;
            $records[$i]['merchant_order_no'] = $d->merchant_order_no;
            $records[$i]['amount'] = $d->amount;
            $records[$i]['remited_amount'] = $d->remited_amount;
            $records[$i]['status'] = $d->status;
            $records[$i]['check_status'] = $d->merchant_check_status;
            $records[$i]['check_status_str'] = $d->merchant_check_bak;
            $records[$i]['status_str'] = $d->showStatusStr();
            $records[$i]['bank_no'] = $d->bank_no;
            $records[$i]['bank_account'] = $d->bank_account;
            $records[$i]['bank_code'] = $d->bank_code;
            $records[$i]['bank_name'] = !empty($d->bank_name)?$d->bank_name:BankCodes::getBankNameByCode($d->bank_code);
            $records[$i]['created_at'] = date('Y-m-d H:i:s',$d->created_at);
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
        $summeryQuery = $summeryQuery->andwhere(['status' => Remit::STATUS_SUCCESS]);
        $summery['success_amount'] = $summeryQuery->sum('remited_amount');
        $summery['success_count'] = $summeryQuery->count('remited_amount');

        //格式化返回json结构
        $statusOptions = ArrayHelper::merge([Macro::SELECT_OPTION_ALL=>'全部'],Remit::ARR_BANK_STATUS);
        $data = [
            'data'=>$records,
            'condition'=>array(
                'statusOptions'=> $statusOptions,
            ),
            'summery'=>$summery,
            'canCheckRemitStatus'=>$user->paymentInfo->can_check_remit_status,
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
     * 商户待审核出款列表
     * @roles merchant
     */
    public function actionCheckList()
    {

    }

    /**
     * 商户审核出款
     * @roles merchant
     */
    public function actionCheck()
    {
        $remitIdList = ControllerParameterValidator::getRequestParam($this->allParams, 'remitIdList', null,Macro::CONST_PARAM_TYPE_ARRAY,'订单ID错误');
        $status = ControllerParameterValidator::getRequestParam($this->allParams, 'status', null,Macro::CONST_PARAM_TYPE_ENUM,'状态错误',[1,2]);
        $pwd = ControllerParameterValidator::getRequestParam($this->allParams, 'pwd','',Macro::CONST_PARAM_TYPE_STRING,'资金密码必须在8位以上');
        $key2fa = ControllerParameterValidator::getRequestParam($this->allParams,'t2fa','',Macro::CONST_PARAM_TYPE_INT,'验证码错误',[6]);

        if(empty($remitIdList)){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单ID错误,请重新选择');
        }

        $user = Yii::$app->user->identity;//->getMainAccount();
        //令牌校验操作人员的
        $googleObj = new \PHPGangsta_GoogleAuthenticator();
        if(!$googleObj->verifyCode($user->key_2fa,$key2fa,1)){
            return ResponseHelper::formatOutput(Macro::ERR_USER_KEY_FA, '安全令牌不正确');
        }

        $mainUser = $user->getMainAccount();

        //资金密码校验主账号的
        if(!$mainUser->validateFinancialPassword($pwd)) {
            return ResponseHelper::formatOutput(Macro::ERR_USER_FINANCIAL_PASSWORD, '资金密码不正确');
        }


        $ret = RpcPaymentGateway::call('/remit/merchant-check', ['remitIdList'=>$remitIdList,'status'=>$status]);

        return ResponseHelper::formatOutput(Macro::SUCCESS, '审核处理成功');
    }
}
