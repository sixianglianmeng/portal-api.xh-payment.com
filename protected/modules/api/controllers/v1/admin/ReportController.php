<?php

namespace app\modules\api\controllers\v1\admin;

use app\common\models\model\AccountOpenFee;
use app\common\models\model\Channel;
use app\common\models\model\ChannelAccount;
use app\common\models\model\Order;
use app\common\models\model\ReportChannelProfitDaily;
use app\common\models\model\ReportFinancialDaily;
use app\common\models\model\ReportRechargeDaily;
use app\common\models\model\User;
use app\components\Macro;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use app\modules\api\controllers\BaseController;
use app\modules\gateway\models\logic\LogicOrder;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Expression;
use yii\db\Query;

class ReportController extends BaseController
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
    public function beforeAction($action)
    {
        $ret = parent::beforeAction($action);

        return $ret;
    }

    /**
     * 日收支统计管理
     *
     * @roles admin
     */
    public function actionDailyFinancial()
    {
        $user = Yii::$app->user->identity;

        $userId    = ControllerParameterValidator::getRequestParam($this->allParams, 'user_id', '', Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '商户ID错误', [5]);
        $username  = ControllerParameterValidator::getRequestParam($this->allParams, 'username', '', Macro::CONST_PARAM_TYPE_USERNAME, '商户名错误', [0, 32]);
        $parentUserId    = ControllerParameterValidator::getRequestParam($this->allParams, 'parent_user_id', '', Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '父账户编号错误', [5]);
        $parentUsername  = ControllerParameterValidator::getRequestParam($this->allParams, 'parent_username', '', Macro::CONST_PARAM_TYPE_USERNAME, '父账户名错误', [0, 32]);
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',
            Macro::CONST_PARAM_TYPE_DATE, '开始日期错误');
        $dateEnd   = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',
            Macro::CONST_PARAM_TYPE_DATE, '结束日期错误');

        $sort    = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', '', Macro::CONST_PARAM_TYPE_SORT, '分页参数错误', [1, 100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 100]);
        $page    = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 1000]);

        $query = ReportFinancialDaily::find();
        $query =(new \yii\db\Query())
            ->select(['id','date','user_id','username','recharge','ABS(remit) AS remit','bonus','total_income','ABS(total_cost) AS total_cost','ABS(recharge_fee) AS recharge_fee','ABS(remit_fee) AS remit_fee','ABS(transfer_fee) AS transfer_fee','remit_refund','remit_fee_refund','transfer_in','ABS(transfer_out) AS transfer_out','balance','account_balance','ABS(account_frozen_balance) AS account_frozen_balance','all_parent_agent_id','updated_at','created_at','plat_sum'])
            ->from(ReportFinancialDaily::tableName());
        if ($user->isAdmin()) {
            if(!empty($parentUserId)){
                $agentWhere = [
                    'or',
                    ['like', 'all_parent_agent_id', ',' . $parentUserId . ','],
                    ['like', 'all_parent_agent_id', '[' . $parentUserId . ']'],
                    ['like', 'all_parent_agent_id', '[' . $parentUserId . ','],
                    ['like', 'all_parent_agent_id', ',' . $parentUserId . ']'],
                ];
                $query->andWhere($agentWhere);
            }
            if(!empty($parentUsername)){
                $parentUser = User::find()->select('id')->where(['username'=>$parentUsername])->one();
                if(!empty($parentUser)){
                    $agentWhere = [
                        'or',
                        ['like', 'all_parent_agent_id', ',' . $parentUser->id . ','],
                        ['like', 'all_parent_agent_id', '[' . $parentUser->id . ']'],
                        ['like', 'all_parent_agent_id', '[' . $parentUser->id . ','],
                        ['like', 'all_parent_agent_id', ',' . $parentUser->id . ']'],
                    ];
                    $query->andWhere($agentWhere);
                }
            }
        } elseif ($user->isAgent()) {
            $agentWhere = [
                'or',
                ['like', 'all_parent_agent_id', ',' . $user->id . ','],
                ['like', 'all_parent_agent_id', '[' . $user->id . ']'],
                ['like', 'all_parent_agent_id', '[' . $user->id . ','],
                ['like', 'all_parent_agent_id', ',' . $user->id . ']'],
                ['user_id', $user->id]
            ];
            $query->andWhere($agentWhere);
        } else {
            $query->andWhere(['user_id' => $user->id]);
        }

        if ($username) {
            $query->andWhere(['like', 'username', $username]);
        }
        if ($userId) {
            $query->andWhere(['user_id' => $userId]);
        }

        if ($dateStart) {
            $query->andFilterCompare('date', '>=' . date('Ymd', strtotime($dateStart)));
        }
        if ($dateEnd) {
            $query->andFilterCompare('date', '<=' . date('Ymd', strtotime($dateEnd) + 86400));
        }

        //允许的排序
//        $sorts = [
//            'created_at-'=>['created_at'=>SORT_DESC],
//            'balance-'=>['u.balance'=>SORT_DESC],
//            'balance+'=>['u.balance'=>SORT_ASC],
//        ];
//        if (!empty($sorts[$sort])) {
//            $sort = $sorts[$sort];
//        } else {
//            $sort = ['created_at'=>SORT_DESC];
//        }
        if($sort){
            $sortFiled = substr($sort,0,-1);
            $sortAd = substr($sort,-1)=='-'?SORT_DESC:SORT_ASC;
            $sort = [$sortFiled=>$sortAd];
        }

        $query->orderBy($sort);

        //生成分页数据
        $p = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page' => $page - 1,
            ],
            'sort' => [
//                'defaultOrder' => $sort
            ],
        ]);

        $records = [];
        foreach ($p->getModels() as $i => $d) {
            $records[$i]               = $d;
            $records[$i]['created_at'] = date('Ymd H:i:s', $d['created_at']);
        }
        //分页数据
        $pagination = $p->getPagination();
        $total      = $pagination->totalCount;
        $lastPage   = ceil($pagination->totalCount / $perPage);
        $from       = ($page - 1) * $perPage;
        $to         = $page * $perPage;
        $data       = [
            'data' => $records,
            "pagination" => [
                "total" => $total,
                "per_page" => $perPage,
                "current_page" => $page,
                "last_page" => $lastPage,
                "from" => $from,
                "to" => $to
            ]
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }

    /**
     * 代理交易(收款)明细
     *
     * @roles admin
     */
    public function actionAgentDailyRecharge()
    {
        $user = Yii::$app->user->identity;

        $userId    = ControllerParameterValidator::getRequestParam($this->allParams, 'user_id', '', Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '商户ID错误', [5]);
        $username  = ControllerParameterValidator::getRequestParam($this->allParams, 'username', '',
            Macro::CONST_PARAM_TYPE_USERNAME, '商户名错误', [0, 32]);
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',
            Macro::CONST_PARAM_TYPE_DATE, '开始日期错误');
        $dateEnd   = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',
            Macro::CONST_PARAM_TYPE_DATE, '结束日期错误');

        $sort    = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', '', Macro::CONST_PARAM_TYPE_SORT, '分页参数错误', [1, 100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 100]);
        $page    = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 1000]);

        $query = ReportRechargeDaily::find();
        $query->andWhere(['user_group_id' => User::GROUP_AGENT]);

        if ($username) {
            $query->andWhere(['like', 'username', $username]);
        }
        if ($userId) {
            $query->andWhere(['user_id' => $userId]);
        }

        if ($dateStart) {
            $query->andFilterCompare('date', '>=' . date('Ymd', strtotime($dateStart)));
        }
        if ($dateEnd) {
            $query->andFilterCompare('date', '<' . date('Ymd', strtotime($dateEnd) + 86400));
        }

        $sorts = [
            'date-' => ['date', SORT_DESC],
        ];
        if (!empty($sorts[$sort])) {
            $sort = $sorts[$sort];
        } else {
            $sort = ['created_at', SORT_DESC];
        }
        //生成分页数据
        $p = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page' => $page - 1,
            ],
            'sort' => [
                'defaultOrder' => [
                    $sort[0] => $sort[1],
                ]
            ],
        ]);

        $records = [];
        foreach ($p->getModels() as $i => $d) {
            $records[$i]               = $d->toArray();
            $records[$i]['created_at'] = date('Ymd H:i:s', $d->created_at);
        }
        //分页数据
        $pagination = $p->getPagination();
        $total      = $pagination->totalCount;
        $lastPage   = ceil($pagination->totalCount / $perPage);
        $from       = ($page - 1) * $perPage;
        $to         = $page * $perPage;
        $data       = [
            'data' => $records,
            "pagination" => [
                "total" => $total,
                "per_page" => $perPage,
                "current_page" => $page,
                "last_page" => $lastPage,
                "from" => $from,
                "to" => $to
            ]
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }


    /**
     * 通道交易量
     *
     * @roles admin
     */
    public function actionChannelRecharge()
    {
        $user = Yii::$app->user->identity;

        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',
            Macro::CONST_PARAM_TYPE_DATE, '开始日期错误');
        $dateEnd   = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',
            Macro::CONST_PARAM_TYPE_DATE, '结束日期错误');

        $sort    = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', '', Macro::CONST_PARAM_TYPE_SORT, '分页参数错误', [1, 100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 100]);
        $page    = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 1000]);

        $subQuery = (new \yii\db\Query())
            ->from(Order::tableName())
            ->andWhere(['status' => [Order::STATUS_SETTLEMENT, Order::STATUS_PAID]]);

        if ($dateStart) {
            $subQuery->andFilterCompare('paid_at', '>=' .  strtotime($dateStart));
        }
        if ($dateEnd) {
            $subQuery->andFilterCompare('paid_at', '<' . (strtotime($dateEnd) + 86400));
        }

        $subQuery->select(['channel_account_id,pay_method_code,SUM(amount) AS amount'])
                ->groupBy('channel_account_id,pay_method_code');

        $query = (new \yii\db\Query())
            ->select(['o.channel_account_id', 'c.channel_name', 'o.amount', 'o.pay_method_code'])
            ->from(['o' => $subQuery])
            ->leftJoin(ChannelAccount::tableName() . ' AS c', 'c.id = o.channel_account_id');

        $records = [];
        foreach ($query->all() as $i => $d) {
            if (empty($records[$d['channel_account_id']]['methods'][$d['pay_method_code']])) {
                $records[$d['channel_account_id']]['methods'][$d['pay_method_code']] = [];
            }
            $records[$d['channel_account_id']]['methods'][$d['pay_method_code']]['amount'] = $d['amount'];
            $records[$d['channel_account_id']]['channel_name']                             = $d['channel_name'];
            $records[$d['channel_account_id']]['channel_account_id']                       = $d['channel_account_id'];
        }
        //分页数据
//        $pagination = $p->getPagination();
        $total    = 1;//$pagination->totalCount;
        $lastPage = 1;//ceil($pagination->totalCount/$perPage);
        $from     = ($page - 1) * $perPage;
        $to       = $page * $perPage;
        $data     = [
            'data' => array_values($records),
            'pay_methods' => empty($all) ? Channel::ARR_METHOD : ArrayHelper::merge([Macro::SELECT_OPTION_ALL => '全部'], Channel::ARR_METHOD),
            "pagination" => [
                "total" => $total,
                "per_page" => $perPage,
                "current_page" => $page,
                "last_page" => $lastPage,
                "from" => $from,
                "to" => $to
            ]
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }


    /**
     * 通道利润
     *
     * @roles admin
     */
    public function actionChannelDailyProfit()
    {
//        $user = Yii::$app->user->identity;
//        $channelAccountId = ControllerParameterValidator::getRequestParam($this->allParams, 'channelAccountId', '', Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '通道账户ID错误', [5]);
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '', Macro::CONST_PARAM_TYPE_DATE, '开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '', Macro::CONST_PARAM_TYPE_DATE, '结束日期错误');
        $month = ControllerParameterValidator::getRequestParam($this->allParams, 'month', null, Macro::CONST_PARAM_TYPE_INT, '月份错误');
//        $sort    = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', '', Macro::CONST_PARAM_TYPE_SORT, '分页参数错误', [1, 100]);
//        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 100]);
//        $page    = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 1000]);

        $monthList = ['01','02','03','04','05','06','07','08','09','10','11','12'];
        $dateStart = date('Y'.$monthList[$month].'01');
        $dateEnd = date('Ymd', strtotime("$dateStart +1 month -1 day"));
        $query = ReportChannelProfitDaily::find();
//        if ($channelAccountId) {
//            $query->andWhere(['channel_account_id' => $channelAccountId]);
//        }
        if ($dateStart) {
            $query->andFilterCompare('date', '>=' . $dateStart);
        }
        if ($dateEnd) {
            $query->andFilterCompare('date', '<=' . $dateEnd);
        }
        $query->orderBy('date desc');
        $list = $query->asArray()->all();
        $data = [];
        $resData = [];
        $sumFields = ['recharge_count','remit_count','recharge_total','recharge_plat_fee_profit','remit_total','remit_plat_fee_profit'];
        if(!empty($list)){
            foreach ($list as $value){
                $tmp = [];
                $tmp['channel_account_name'] = $value['channel_account_name'];
                $tmp['recharge_plat_fee_profit'] = $value['recharge_plat_fee_profit'];
                $tmp['recharge_count'] = $value['recharge_count'];
                $tmp['recharge_total'] = $value['recharge_total'];
                $tmp['remit_plat_fee_profit'] = $value['remit_plat_fee_profit'];
                $tmp['remit_count'] = $value['remit_count'];
                $tmp['remit_total'] = $value['remit_total'];
                foreach ($sumFields as $val){
                    if(!isset($data[$value['date']][$val])){
                        $data[$value['date']][$val] = 0;
                    }
                    $data[$value['date']][$val] = bcadd($data[$value['date']][$val],$value[$val],3);
                }
                if (!isset($data[$value['date']]['total_profit'])){
                    $data[$value['date']]['total_profit'] = 0;
                }
                $data[$value['date']]['total_profit'] =bcadd($data[$value['date']]['total_profit'],bcadd($value['recharge_plat_fee_profit'],$value['remit_plat_fee_profit'],3)) ;
                $data[$value['date']]['date'] = $value['date'];
                $data[$value['date']]['list'][] = $tmp;
            }
            $queryTotal = ReportChannelProfitDaily::find();
            if ($dateStart) {
                $queryTotal->andFilterCompare('date', '>=' . $dateStart);
            }
            if ($dateEnd) {
                $queryTotal->andFilterCompare('date', '<=' . $dateEnd);
            }
            $fields = "channel_account_name,sum(recharge_count) as recharge_count,sum(recharge_total) as recharge_total,sum(recharge_plat_fee_profit) as recharge_plat_fee_profit,sum(remit_count) as remit_count,sum(remit_total) as remit_total,sum(remit_plat_fee_profit) as remit_plat_fee_profit";
            $queryTotal->select($fields);
            $listTotal = $queryTotal->groupBy('channel_account_id')->asArray()->all();
            $dataTotal = [];
            if(!empty($listTotal)){
                foreach ($listTotal as $value){
                    $tmp = [];
                    $tmp['channel_account_name'] = $value['channel_account_name'];
                    $tmp['recharge_plat_fee_profit'] = $value['recharge_plat_fee_profit'];
                    $tmp['recharge_count'] = $value['recharge_count'];
                    $tmp['recharge_total'] = $value['recharge_total'];
                    $tmp['remit_plat_fee_profit'] = $value['remit_plat_fee_profit'];
                    $tmp['remit_count'] = $value['remit_count'];
                    $tmp['remit_total'] = $value['remit_total'];
                    foreach ($sumFields as $val){
                        if(!isset($dataTotal['total'][$val])){
                            $dataTotal['total'][$val] = 0;
                        }
                        $dataTotal['total'][$val] = bcadd($dataTotal['total'][$val],$value[$val],3);
                    }
                    if (!isset($dataTotal['total']['total_profit'])){
                        $dataTotal['total']['total_profit'] = 0;
                    }
                    $dataTotal['total']['total_profit'] =bcadd($dataTotal['total']['total_profit'],bcadd($value['recharge_plat_fee_profit'],$value['remit_plat_fee_profit'],3)) ;
                    $dataTotal['total']['date'] = '总计';
                    $dataTotal['total']['list'][] = $tmp;
                }
            }
            $arr = array_merge($data,$dataTotal);
            foreach ($arr as $val){
                $resData[] = $val;
            }

        }
//        $sorts = [
//            'date-' => ['date', SORT_DESC],
//        ];
//
//        if (!empty($sorts[$sort])) {
//            $sort = $sorts[$sort];
//        } else {
//            $sort = ['date'=>SORT_DESC];
//        }
//        //生成分页数据
//        $p = new ActiveDataProvider([
//            'query' => $query,
//            'pagination' => [
//                'pageSize' => $perPage,
//                'page' => $page - 1,
//            ],
//            'sort' => [
//                'defaultOrder' => $sort
//            ],
//        ]);
//
//        $records = [];
//        $sumFields = ['recharge_count','remit_count','recharge_total','recharge_plat_fee_profit','remit_total','remit_plat_fee_profit','total_profit'];
//        $summary = [];
//        $summary['date'] = '/';
//        $summary['channel_account_name'] = '当页总计';
//        foreach ($p->getModels() as $i => $d) {
//            $records[$i]               = $d->toArray();
//            $records[$i]['created_at'] = date('Ymd H:i:s', $d->created_at);
//            $records[$i]['total_profit'] = bcadd($d->recharge_plat_fee_profit,$d->remit_plat_fee_profit,3);
//            foreach ($sumFields as $f){
//                if(empty($summary[$f])) $summary[$f] = '';
//                $summary[$f] = bcadd($summary[$f],$records[$i][$f],3);
//            }
//            $tmp = [];
//            $tmp['channel_account_name'] = $d->channel_account_name;
//            $tmp['recharge_plat_fee_profit'] = $d->recharge_plat_fee_profit;
//            $tmp['recharge_count'] = $d->recharge_count;
//            $tmp['recharge_total'] = $d->recharge_total;
//            $tmp['remit_plat_fee_profit'] = $d->remit_plat_fee_profit;
//            $tmp['remit_count'] = $d->remit_count;
//            $tmp['remit_total'] = $d->remit_total;
//            if(!isset($records[$d->date]['recharge_count'])){
//                $records[$d->date]['recharge_count'] = 0;
//            }
//            $records[$d->date]['recharge_count'] = $d->recharge_count;
//
//        }
//        $records[] = $summary;
//        //分页数据
//        $pagination = $p->getPagination();
//        $total      = $pagination->totalCount;
//        $lastPage   = ceil($pagination->totalCount / $perPage);
//        $from       = ($page - 1) * $perPage;
//        $to         = $page * $perPage;
//        $data       = [
//            'data' => $records,
//            'summary' => $summary,
//            "pagination" => [
//                "total" => $total,
//                "per_page" => $perPage,
//                "current_page" => $page,
//                "last_page" => $lastPage,
//                "from" => $from,
//                "to" => $to
//            ]
//        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $resData);
    }

    /**
     * 通道日对账
     *
     * @roles admin
     */
    public function actionChannelDailyReconciliations()
    {
        $user = Yii::$app->user->identity;

        $channelAccountId = ControllerParameterValidator::getRequestParam($this->allParams, 'channelAccountId', '', Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '通道账户ID错误', [5]);
        $dateStart        = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',
            Macro::CONST_PARAM_TYPE_DATE, '开始日期错误');
        $dateEnd          = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',
            Macro::CONST_PARAM_TYPE_DATE, '结束日期错误');

        $sort    = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', '', Macro::CONST_PARAM_TYPE_SORT, '分页参数错误', [1, 100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 100]);
        $page    = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 1000]);

        $query = ReportChannelProfitDaily::find();
        if ($channelAccountId) {
            $query->andWhere(['channel_account_id' => $channelAccountId]);
        }

        if ($dateStart) {
            $query->andFilterCompare('date', '>=' . date('Ymd', strtotime($dateStart)));
        }
        if ($dateEnd) {
            $query->andFilterCompare('date', '<=' . date('Ymd', strtotime($dateEnd) + 86400));
        }

        $sorts = [
            'created_at-' => ['created_at', SORT_DESC],
        ];
        if (!empty($sorts[$sort])) {
            $sort = $sorts[$sort];
        } else {
            $sort = ['created_at', SORT_DESC];
        }
        //生成分页数据
        $p = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page' => $page - 1,
            ],
            'sort' => [
                'defaultOrder' => [
                    $sort[0] => $sort[1],
                ]
            ],
        ]);

        $records = [];
        foreach ($p->getModels() as $i => $d) {
            $records[$i]               = $d->toArray();
            $records[$i]['created_at'] = date('Ymd H:i:s', $d->created_at);
        }
        //分页数据
        $pagination = $p->getPagination();
        $total      = $pagination->totalCount;
        $lastPage   = ceil($pagination->totalCount / $perPage);
        $from       = ($page - 1) * $perPage;
        $to         = $page * $perPage;
        $data       = [
            'data' => $records,
            "pagination" => [
                "total" => $total,
                "per_page" => $perPage,
                "current_page" => $page,
                "last_page" => $lastPage,
                "from" => $from,
                "to" => $to
            ]
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }

    /**
     * 开户费统计
     * @return array
     * @throws \power\yii2\exceptions\ParameterValidationExpandException
     */
    public function actionAccountOpenFee()
    {
        $month = ControllerParameterValidator::getRequestParam($this->allParams, 'month', null, Macro::CONST_PARAM_TYPE_INT, '月份错误');
        $monthList = ['01','02','03','04','05','06','07','08','09','10','11','12'];
        $dateStart = date('Y-'.$monthList[$month].'-01 00:00:00');
        $dateEnd = date('Y-m-d 23:59:59', strtotime("$dateStart +1 month -1 day"));
        $query = (new Query())
            ->select('sum(pa.fee) as fee,sum(pa.fee_paid) as fee_paid,count(pa.id) as total,pu.parent_agent_id')
            ->from('p_account_open_fee AS pa')
            ->leftJoin('p_users AS pu',"pa.user_id = pu.id");
        $query->andWhere(['>=','pa.created_at',strtotime($dateStart)]);
        $query->andWhere(['<=','pa.created_at',strtotime($dateEnd)]);
        $query->groupBy('pu.parent_agent_id');
        $query->orderBy('sum(pa.fee) desc');
        $openFeeList = $query->all();
        $userList = User::find()->where(['group_id'=>User::GROUP_AGENT])->asArray()->all();
        $agentList = [];
        foreach ($userList as $val){
            $agentList[$val['id']]['username'] = $val['username'];
            $agentList[$val['id']]['fee_paid'] = 0;
            $agentList[$val['id']]['fee'] = 0;
            $agentList[$val['id']]['total'] = 0;
            $agentList[$val['id']]['merchant_id'] = $val['id'];
        }
        if(!empty($openFeeList)) {
            foreach ($openFeeList as $val) {
                $agentList[$val['parent_agent_id']]['fee_paid'] = $val['fee_paid'];
                $agentList[$val['parent_agent_id']]['total'] = $val['total'];
                $agentList[$val['parent_agent_id']]['fee'] = $val['fee'];
            }
        }
        $data = [];
        foreach ($agentList as $val){
            $data[] = $val;
        }
        return ResponseHelper::formatOutput(Macro::SUCCESS,'',$data);
    }
    /**
     * 开户费明细
     * @return array
     * @throws \power\yii2\exceptions\ParameterValidationExpandException
     */
    public function actionAccountOpenFeeInfo()
    {
        $month = ControllerParameterValidator::getRequestParam($this->allParams, 'month', null, Macro::CONST_PARAM_TYPE_INT, '月份错误');
        $agent_merchant_id = ControllerParameterValidator::getRequestParam($this->allParams, 'agent_merchant_id', null,Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'商户编号错误',[0,32]);
        $agent_merchant_name = ControllerParameterValidator::getRequestParam($this->allParams, 'agent_merchant_name', null,Macro::CONST_PARAM_TYPE_STRING,'商户名称错误',[0,32]);
        $monthList = ['01','02','03','04','05','06','07','08','09','10','11','12'];
        $dateStart = date('Y-'.$monthList[$month].'-01 00:00:00');
        $dateEnd = date('Y-m-d 23:59:59', strtotime("$dateStart +1 month -1 day"));
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,1000]);
        $field = "pa.user_id,pa.username,pa.status,pa.fee,pa.fee_paid,pa.paid_at,pa.order_no,pa.order_created_at,pa.created_at,pu.parent_agent_id";
        $query = (new Query())
            ->select($field)
            ->from('p_account_open_fee AS pa')
            ->leftJoin('p_users AS pu',"pa.user_id = pu.id");
        $query->andWhere(['>=','pa.created_at',strtotime($dateStart)]);
        $query->andWhere(['<=','pa.created_at',strtotime($dateEnd)]);
        $query->andWhere(['pu.parent_agent_id' => $agent_merchant_id]);
        $query->orderBy('pa.created_at desc');
        $pageObj = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page' => $page-1,
            ],
        ]);
        $tmpList = [];
        foreach ($pageObj->getModels() as $key=>$val){
            $val['status_str'] = AccountOpenFee::ARR_STATUS[$val['status']];
            $val['paid_at'] = date('Y-m-d H:i:s',$val['paid_at']);
            $val['order_created_at'] = date('Y-m-d H:i:s',$val['order_created_at']);
            $val['created_at'] = date('Y-m-d H:i:s',$val['created_at']);
            $val['parent_agent_name'] = $agent_merchant_name;
            $tmpList[] = $val;

        }
        //分页数据
        $pagination = $pageObj->getPagination();
        $total = $pagination->totalCount;
        $lastPage = ceil($pagination->totalCount/$perPage);
        $from = ($page-1)*$perPage;
        $to = $page*$perPage;
        $data = [
            'list'=>$tmpList,
            "total" =>  $total,
            "per_page" =>  $perPage,
            "current_page" =>  $page,
            "last_page" =>  $lastPage,
            "from" =>  $from,
            "to" =>  $to
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }

    /**
     * 商户充值统计
     * @return array
     * @throws \power\yii2\exceptions\ParameterValidationExpandException
     */
    public function actionRechargeGroupMerchant()
    {
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');
        $merchant_id = ControllerParameterValidator::getRequestParam($this->allParams, 'merchant_id', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'商户编号错误',[0,32]);
        $merchant_account = ControllerParameterValidator::getRequestParam($this->allParams, 'merchant_account', '',Macro::CONST_PARAM_TYPE_STRING,'商户名称错误',[0,32]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,1000]);
        $query = Order::find();
        $query->andFilterCompare('created_at','>='.strtotime($dateStart));
        $query->andFilterCompare('created_at','<='.strtotime($dateEnd));
        if(!empty($merchant_id)){
            $query->andWhere(['merchant_id'=>$merchant_id]);
        }
        if(!empty($merchant_account)){
            $query->andWhere(['merchant_account'=>$merchant_account]);
        }
        $query->select(new Expression('sum(amount) as amount,merchant_id,merchant_account,count(`merchant_id`) as total,status'));
        $query->groupBy('status,merchant_id');
        $pageObj = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page' => $page-1,
            ],
        ]);
        $data['list'] = [];
        $data['total'] = 0;
        $data['statusOptions'] = Order::ARR_STATUS;
        $data['statusOptions']['all'] = '总计';
        if (empty($pageObj->getModels())) return ResponseHelper::formatOutput(Macro::SUCCESS,'未查询到充值数据，请检查查询条件',$data);
        $list = [];
        foreach ($pageObj->getModels() as $k=>$val){
            $tmp = $val->toArray();
//            var_dump($tmp);
            $list[$tmp['merchant_id']]['merchant_id'] = $tmp['merchant_id'];
            $list[$tmp['merchant_id']]['merchant_account'] = $tmp['merchant_account'];
            foreach (Order::ARR_STATUS as $key=>$value){
                if(!isset($list[$tmp['merchant_id']]['status'][$key])){
                    $list[$tmp['merchant_id']]['status'][$key] = 0;
//                    $list[$tmp['merchant_id']]['status'][$key]['total'] = 0;
                }
                if($tmp['status'] == $key){
                    $list[$tmp['merchant_id']]['status'][$tmp['status']] = $tmp['amount'];
//                    $list[$tmp['merchant_id']]['status'][$tmp['status']]['total'] = $tmp['total'];
                }
            }
            if(!isset($list[$tmp['merchant_id']]['status']['all'])){
                $list[$tmp['merchant_id']]['status']['all'] = 0;
//                $list[$tmp['merchant_id']]['status']['all']['total'] = 0;
            }
            $list[$tmp['merchant_id']]['status']['all'] = bcadd($list[$tmp['merchant_id']]['status']['all']['amount'],$tmp['amount'],2);
//            $list[$tmp['merchant_id']]['status']['all']['total'] += $tmp['total'];
        }
//        die;
        foreach ($list as $val){
            $data['list'][] = $val;
        }
        //分页数据
        $pagination = $pageObj->getPagination();
        $data['total'] = $pagination->totalCount;
        return ResponseHelper::formatOutput(Macro::SUCCESS,'',$data);
    }
}
