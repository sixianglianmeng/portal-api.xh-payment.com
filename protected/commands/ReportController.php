<?php

namespace app\commands;

use app\common\models\model\AuthItem;
use app\common\models\model\Channel;
use app\common\models\model\ChannelAccount;
use app\common\models\model\Financial;
use app\common\models\model\Order;
use app\common\models\model\Remit;
use app\common\models\model\ReportChannelProfitDaily;
use app\common\models\model\ReportFinancialDaily;
use app\common\models\model\User;
use app\components\Macro;
use app\lib\helpers\ControllerParameterValidator;
use Yii;
use yii\db\Query;
use yii\helpers\ArrayHelper;

/*
 * 商户张变日报
 *
 * ./protected/yii scan-all-actions/init-sys-role
 */
class ReportController extends \yii\console\Controller
{
    public $day='';

    public function options($actionId)
    {
        return ['day'];
    }
    
    public function init()
    {
        parent::init();

        ini_set("display_errors", 1);
        ini_set('memory_limit', '2048M');
    }

    public function beforeAction($event)
    {
        Yii::info('console process: ' . implode(' ', $_SERVER['argv']));
        return parent::beforeAction($event);
    }

    /**
     * 商户帐变日报
     *
     * ./protected/yii report/daily-financial
     */
    public function actionDailyFinancial()
    {
        //收款总额/结算总额/提成总额/增金总额/减金总额,收款手续费,结算手续费总额,转账手续费总额,失败返还总额,失败返还手续费总额,平台转出总额,平台转入总额,今日余额
        $fields = ['recharge'=>'收款总额','remit'=>'结算总额','bonus'=>'提成总额','total_income'=>'增金总额','total_cost'=>'减金总额','recharge_fee'=>'收款手续费',
        'remit_fee'=>'结算手续费总额','transfer_fee'=>'转账手续费总额','remit_refund'=>'失败返还总额','remit_fee_refund'=>'失败返还手续费总额','transfer_in'=>'平台转出总额','transfer_out'=>'平台转入总额','balance'=>'今日余额(收入-支持)'];

        $day = $this->day ? date('Ymd', strtotime($this->day)) : date('Ymd',strtotime('-1 day'));
        $tsStart = strtotime($day);
        $tsEnd = $tsStart+86400;
        bcscale(6);

        //先查询uid,然后再查询
        //对应分类汇总的amount数据不能中switch之外初始化,防止循环只获取到商户部分帐变记录,初始化会覆盖写入之前的.
        $query = (new \yii\db\Query())
            ->andWhere(['status'=>Financial::STATUS_FINISHED])
            ->andFilterCompare('created_at', '>=' . $tsStart)
            ->andFilterCompare('created_at', '<' .$tsEnd)
            ->select(['uid'])
            ->from(Financial::tableName())
            ->groupBy('uid');

        $reportModel = new ReportFinancialDaily();
        foreach ($query->batch() as $batUids) {
            $uids = [];
            foreach ($batUids as $bu){
                $uids[] = $bu['uid'];
            }

            $latestBalanceQuery = (new Query())
                ->andWhere(['status'=>Financial::STATUS_FINISHED])
                ->andFilterCompare('created_at', '>=' . $tsStart)
                ->andFilterCompare('created_at', '<' .$tsEnd)
                ->select(['MAX(id) AS id'])
                ->from(Financial::tableName())
                ->groupBy('uid');

            $subQuery = (new Query())->select(['ff.id','ff.uid','balance','frozen_balance'])
                ->andWhere(['ff.status'=>Financial::STATUS_FINISHED])
                ->from(Financial::tableName().' AS ff')
                ->leftJoin(['l' => $latestBalanceQuery], 'l.id = ff.id');

            $rows = (new Query())
                ->where(['f.uid'=>$uids])
                ->andWhere(['f.status'=>Financial::STATUS_FINISHED])
                ->andFilterCompare('f.created_at', '>=' . $tsStart)
                ->andFilterCompare('f.created_at', '<' .$tsEnd)
                ->select(['f.uid','f.username','f.event_type','SUM(f.amount) AS amount','u.all_parent_agent_id','f2.balance as account_balance','f2.frozen_balance as account_frozen_balance'])
                ->from(Financial::tableName().' as f')
                ->leftJoin(User::tableName().' AS u', 'f.uid = u.id')
                ->leftJoin(['f2' => $subQuery], 'f.id = f2.id')
                ->groupBy('f.uid,f.event_type')
                ->all();

            $reportLogs = [];
            foreach ($rows as $row){
                if(empty($reportLogs[$row['uid']])){
                    $reportLogs[$row['uid']] = [
                        'user_id'             => $row['uid'],
                        'username'            => $row['username'],
                        'all_parent_agent_id' => $row['all_parent_agent_id'],
                        'account_balance' => $row['account_balance'],
                        'account_frozen_balance' => $row['account_frozen_balance'],
                        'date'                => $day,
                        'recharge'            => 0,
                        'remit'               => 0,
                        'bonus'               => 0,
                        'total_income'        => 0,
                        'total_cost'          => 0,
                        'recharge_fee'        => 0,
                        'remit_fee'           => 0,
                        'transfer_fee'        => 0,
                        'remit_refund'        => 0,
                        'remit_fee_refund'    => 0,
                        'transfer_in'         => 0,
                        'transfer_out'        => 0,
                        'balance'             => 0,
                    ];
                }

                switch ($row['event_type']) {
                    case Financial::EVENT_TYPE_RECHARGE:
                        $reportLogs[$row['uid']]['recharge']     = bcadd($reportLogs[$row['uid']]['recharge'] , $row['amount']);
                        $reportLogs[$row['uid']]['total_income'] = bcadd($reportLogs[$row['uid']]['total_income'], $row['amount']);
                        break;
                    case Financial::EVENT_TYPE_RECHARGE_FEE:
                        $reportLogs[$row['uid']]['recharge_fee'] = bcadd($reportLogs[$row['uid']]['recharge_fee'], $row['amount']);
                        $reportLogs[$row['uid']]['total_cost']   = bcadd($reportLogs[$row['uid']]['total_cost'], $row['amount']);
                        break;
                    case Financial::EVENT_TYPE_BONUS:
                    case Financial::EVENT_TYPE_REMIT_BONUS:
                        $reportLogs[$row['uid']]['bonus']        = bcadd($reportLogs[$row['uid']]['bonus'], $row['amount']);
                        $reportLogs[$row['uid']]['total_income'] = bcadd($reportLogs[$row['uid']]['total_income'], $row['amount']);
                        break;
                    case Financial::EVENT_TYPE_REMIT:
                        $reportLogs[$row['uid']]['remit']      = bcadd($reportLogs[$row['uid']]['remit'], $row['amount']);
                        $reportLogs[$row['uid']]['total_cost'] = bcadd($reportLogs[$row['uid']]['total_cost'], $row['amount']);
                        break;
                    case Financial::EVENT_TYPE_REMIT_FEE:
                        $reportLogs[$row['uid']]['remit_fee']  = bcadd($reportLogs[$row['uid']]['remit_fee'], $row['amount']);
                        $reportLogs[$row['uid']]['total_cost'] = bcadd($reportLogs[$row['uid']]['total_cost'], $row['amount']);
                        break;
                    case Financial::EVENT_TYPE_REFUND_REMIT:
                        $reportLogs[$row['uid']]['remit_refund'] = bcadd($reportLogs[$row['uid']]['remit_refund'], $row['amount']);
                        $reportLogs[$row['uid']]['total_income'] = bcadd($reportLogs[$row['uid']]['total_income'], $row['amount']);
                        break;
                    case Financial::EVENT_TYPE_REFUND_REMIT_FEE:
                        $reportLogs[$row['uid']]['remit_fee_refund'] = bcadd($reportLogs[$row['uid']]['remit_fee_refund'], $row['amount']);
                        $reportLogs[$row['uid']]['total_income']     = bcadd($reportLogs[$row['uid']]['total_income'], $row['amount']);
                        break;
                    case Financial::EVENT_TYPE_REFUND_REMIT_BONUS:
                        $reportLogs[$row['uid']]['total_cost'] = bcadd($reportLogs[$row['uid']]['total_cost'], $row['amount']);
                        break;
                    case Financial::EVENT_TYPE_TRANSFER_IN:
                        $reportLogs[$row['uid']]['transfer_in']  = bcadd($reportLogs[$row['uid']]['transfer_in'], $row['amount']);
                        $reportLogs[$row['uid']]['total_income'] = bcadd($reportLogs[$row['uid']]['total_income'], $row['amount']);
                    case Financial::EVENT_TYPE_TRANSFER_OUT:
                        $reportLogs[$row['uid']]['transfer_out'] = bcadd($reportLogs[$row['uid']]['transfer_out'], $row['amount']);
                        $reportLogs[$row['uid']]['total_cost']   = bcadd($reportLogs[$row['uid']]['total_cost'], $row['amount']);
                        break;
                    case Financial::EVENT_TYPE_TRANSFER_FEE:
                        $reportLogs[$row['uid']]['transfer_fee'] = bcadd($reportLogs[$row['uid']]['transfer_fee'], $row['amount']);
                        $reportLogs[$row['uid']]['total_income'] = bcadd($reportLogs[$row['uid']]['total_income'], $row['amount']);
                        break;
                    case Financial::EVENT_TYPE_SYSTEM_MINUS:
                        $reportLogs[$row['uid']]['total_cost'] = bcadd($reportLogs[$row['uid']]['total_cost'], $row['amount']);
                    case Financial::EVENT_TYPE_SYSTEM_PLUS:
                        $reportLogs[$row['uid']]['total_income'] = bcadd($reportLogs[$row['uid']]['total_income'], $row['amount']);
                        break;
                    case Financial::EVENT_TYPE_RECHARGE_FROZEN:
                        $reportLogs[$row['uid']]['total_cost'] = bcadd($reportLogs[$row['uid']]['total_cost'], $row['amount']);
                        break;
                    case Financial::EVENT_TYPE_RECHARGE_UNFROZEN:
                        $reportLogs[$row['uid']]['total_income'] = bcadd($reportLogs[$row['uid']]['total_income'], $row['amount']);
                        break;
                    default:
                        break;

                }
            }

            foreach ($reportLogs as $reportArr){
//                echo json_encode($reportArr).PHP_EOL;

                $reportArr['balance'] = bcadd($reportArr['total_income'],$reportArr['total_cost']);
                Yii::info("DailyFinancial: {$reportArr['user_id']},{$reportArr['username']},{$reportArr['total_income']},{$reportArr['total_cost']},{$reportArr['balance']}");
                $report = ReportFinancialDaily::findOne(['user_id'=>$reportArr['user_id'],'date'=>$reportArr['date']]);
                if(!$report) $report = clone $reportModel;

                $report->setAttributes($reportArr,false);
                $report->save(false);
            }
        }
    }

    /**
     * 商户代理收款订单日报
     *
     * ./protected/yii report/daily-recharge
     */
    public function actionDailyRecharge()
    {
        $day = $this->day ? date('Ymd', strtotime($this->day)) : date('Ymd',strtotime('-1 day'));
        $tsStart = strtotime($day);
        $tsEnd = $tsStart+86400;
        $allOrderfilter = "paid_at>={$tsStart} AND paid_at<{$tsEnd}";
        $successOrderfilter = "status IN (".implode(",",[Order::STATUS_SETTLEMENT,Order::STATUS_PAID]).") AND paid_at>={$tsStart} AND paid_at<{$tsEnd}";

        //写入所有代理所有订单汇总
        $sql = "REPLACE INTO p_report_recharge_daily (date, user_id, username,user_goup_id, total_amount, total_count, avg_amount,created_at)
	SELECT {$day} AS date, u.id AS user_id, u.username AS user_name,u.group_id AS user_goup_id, SUM(amount) AS total_amount,COUNT(*) AS total_count, AVG(amount) AS 
	avg_amount,UNIX_TIMESTAMP() AS created_at
	FROM (
		SELECT o.id, SUBSTRING_INDEX(SUBSTRING_INDEX(o.pids, ',', numbers.n), ',', -1) AS pid
		FROM p_temp_parent_uids_extract numbers
		INNER JOIN (
			SELECT id,REPLACE(REPLACE(p_orders.all_parent_agent_id, ']', ''), '[', '') AS pids
			FROM p_orders
			WHERE {$allOrderfilter}
		) o
		ON CHAR_LENGTH(o.pids) - CHAR_LENGTH(REPLACE(o.pids, ',', '')) >= numbers.n - 1
		ORDER BY id, n
	) pu
	LEFT JOIN p_orders o ON o.id = pu.id
	LEFT JOIN p_users u ON u.id = pu.pid
	GROUP BY pu.pid";

        Yii::$app->db->createCommand($sql)->execute();

        //更新所有代理成功订单汇总
        $sql = "UPDATE p_report_recharge_daily d, (
		SELECT {$day} AS date, pu.pid AS user_id, SUM(amount) AS success_amount,COUNT(*) AS success_count, AVG(amount) AS success_avg_amount
		FROM p_orders o
		RIGHT JOIN (
			SELECT o.id,SUBSTRING_INDEX(SUBSTRING_INDEX(o.pids, ',', numbers.n), ',', -1) AS pid
			FROM p_temp_parent_uids_extract numbers
			INNER JOIN (
				SELECT id,REPLACE(REPLACE(p_orders.all_parent_agent_id, ']', ''), '[', '') AS pids
				FROM p_orders
				WHERE {$successOrderfilter}
			) o
			ON CHAR_LENGTH(o.pids) - CHAR_LENGTH(REPLACE(o.pids, ',', '')) >= numbers.n - 1
			ORDER BY id, n
		) pu
		ON o.id = pu.id
		GROUP BY pu.pid
	) os
SET d.success_amount = os.success_amount, d.success_count = os.success_count, d.success_avg_amount = os.success_avg_amount
WHERE d.user_id = os.user_id
AND d.date = os.date";
        Yii::$app->db->createCommand($sql)->execute();


        //写入所有商户的所有订单汇总
        $sql = "
REPLACE INTO p_report_recharge_daily(date,user_id,username,user_goup_id,total_amount,total_count,avg_amount,created_at) 
    SELECT {$day} AS date,merchant_id AS user_id,merchant_account AS user_name,30 AS user_goup_id,SUM(amount) AS total_amount,COUNT(*) AS total_count,avg(amount) AS 
    avg_amount,UNIX_TIMESTAMP() AS created_at 
    FROM p_orders o WHERE {$allOrderfilter}
    GROUP BY merchant_id";
        Yii::$app->db->createCommand($sql)->execute();

        //更新所有商户的所有成功订单汇总
        $sql = "UPDATE p_report_recharge_daily d, (
    SELECT {$day} AS date,merchant_id AS user_id, SUM(amount) AS success_amount,COUNT(*) AS success_count,avg(amount) AS success_avg_amount 
    FROM p_orders o WHERE {$successOrderfilter}
    GROUP BY merchant_id
    ) os 
SET d.success_amount=os.success_amount,d.success_count=os.success_count,d.success_avg_amount=os.success_avg_amount
WHERE d.user_id=os.user_id and d.date=os.date";
        Yii::$app->db->createCommand($sql)->execute();


    }

    /**
     * 渠道每日利润
     *
     * ./protected/yii report/channel-daily-profit
     */
    public function actionChannelDailyProfit()
    {
        $day = $this->day ? date('Ymd', strtotime($this->day)) : date('Ymd',strtotime('-1 day'));
        $tsStart = strtotime($day);
        $tsEnd = $tsStart+86400;

        //收款利润
        $subQuery = (new \yii\db\Query())
            ->from(Order::tableName())
            ->andWhere(['status'=>[Order::STATUS_SETTLEMENT,Order::STATUS_PAID]])
            ->andFilterCompare('paid_at', '>=' . $tsStart)
            ->andFilterCompare('paid_at', '<' .$tsEnd)
            ->select(['channel_account_id','channel_id','SUM(plat_fee_amount) AS channel_fee','SUM(plat_fee_profit) AS plat_fee_profit','SUM(amount) AS total','COUNT(*) AS count'])
            ->groupBy('channel_account_id');

        $query = (new \yii\db\Query())
            ->select(['o.channel_account_id','o.total','c.channel_id','c.channel_name AS channel_account_name','c1.name AS channel_name','o.plat_fee_profit','o.count','o.channel_fee'])
            ->from(['o'=>$subQuery])
            ->leftJoin(ChannelAccount::tableName().' AS c', 'c.id = o.channel_account_id')
            ->leftJoin(Channel::tableName().' AS c1', 'c1.id = o.channel_id');

        $reports=[];
        foreach ($query->all() as $i=>$d){
            if(empty($reports[$d['channel_account_id']])){
                $reports[$d['channel_account_id']] = [];
            }
            $reports[$d['channel_account_id']]['date'] = $day;
            $reports[$d['channel_account_id']]['recharge_total'] = $d['total'];
            $reports[$d['channel_account_id']]['recharge_plat_fee_profit'] = $d['plat_fee_profit'];
            $reports[$d['channel_account_id']]['recharge_count'] = $d['count'];
            $reports[$d['channel_account_id']]['channel_account_name'] = $d['channel_account_name'];
            $reports[$d['channel_account_id']]['channel_account_id'] = $d['channel_account_id'];
            $reports[$d['channel_account_id']]['channel_id'] = $d['channel_id'];
            $reports[$d['channel_account_id']]['channel_name'] = $d['channel_name'];
            $reports[$d['channel_account_id']]['remit_plat_fee_profit'] = 0;
            $reports[$d['channel_account_id']]['remit_count'] = 0;
            $reports[$d['channel_account_id']]['remit_total'] = 0;
            $reports[$d['channel_account_id']]['recharge_channel_fee'] = $d['channel_fee'];
            echo "{$i},{$d['channel_account_id']},{$d['channel_fee']},{$reports[$d['channel_account_id']]['recharge_channel_fee']}\n";
        }

        //出款利润
        $subQuery = (new \yii\db\Query())
            ->from(Remit::tableName())
            ->andWhere(['status'=>Remit::STATUS_SUCCESS])
            ->andFilterCompare('remit_at', '>=' . $tsStart)
            ->andFilterCompare('remit_at', '<' .$tsEnd)
            ->select(['channel_account_id','channel_id','SUM(plat_fee_amount) AS channel_fee','SUM(plat_fee_profit) AS plat_fee_profit','SUM(amount) AS total','COUNT(*) AS count'])
            ->groupBy('channel_account_id');

        $query = (new \yii\db\Query())
            ->select(['o.channel_account_id','c.channel_id','o.total','c.channel_name AS channel_account_name','total','c1.name AS channel_name','o.plat_fee_profit','o.count','o.channel_fee'])
            ->from(['o'=>$subQuery])
            ->leftJoin(ChannelAccount::tableName().' AS c', 'c.id = o.channel_account_id')
            ->leftJoin(Channel::tableName().' AS c1', 'c1.id = o.channel_id');

        foreach ($query->all() as $i=>$d){
            if(empty($reports[$d['channel_account_id']])){
                $reports[$d['channel_account_id']]['date'] = $day;
                $reports[$d['channel_account_id']]['recharge_total'] = 0;
                $reports[$d['channel_account_id']]['recharge_amount'] = 0;
                $reports[$d['channel_account_id']]['recharge_count'] = 0;
                $reports[$d['channel_account_id']]['channel_account_name'] = $d['channel_account_name'];
                $reports[$d['channel_account_id']]['channel_account_id'] = $d['channel_account_id'];
                $reports[$d['channel_account_id']]['channel_id'] = $d['channel_id'];
                $reports[$d['channel_account_id']]['channel_name'] = $d['channel_name'];
                $reports[$d['channel_account_id']]['recharge_channel_fee'] = 0;
                $reports[$d['channel_account_id']]['recharge_plat_fee_profit'] = 0;

            }
            $reports[$d['channel_account_id']]['remit_plat_fee_profit'] = $d['plat_fee_profit'];
            $reports[$d['channel_account_id']]['remit_count'] = $d['count'];
            $reports[$d['channel_account_id']]['remit_total'] = $d['total'];
            $reports[$d['channel_account_id']]['remit_channel_fee'] = $d['channel_fee'];
        }

        $fields = ['date','recharge_total','recharge_amount','recharge_count','channel_account_name','channel_account_id','channel_id','channel_name',
            'remit_amount','remit_count','remit_total','recharge_channel_fee','remit_channel_fee','plat_sum'
        ];

        foreach ($reports as $i=>$d){
            foreach ($fields as $f){
                if(!isset($reports[$i][$f])) $reports[$i][$f]=0;
            }

            //当天渠道结余
            $reports[$i]['plat_sum'] =  $reports[$i]['recharge_amount']-$reports[$i]['remit_amount']-$reports[$i]['recharge_channel_fee']-$reports[$i]['remit_channel_fee'];
        }

        Yii::$app->db->createCommand()->delete(ReportChannelProfitDaily::tableName(), "date={$day}")->execute();
        $okCount = Yii::$app->db->createCommand()->batchInsert(ReportChannelProfitDaily::tableName(),$fields,$reports)->execute();

        $this->actionReconciliation();
    }


    /**
     * 渠道对账
     *
     * ./protected/yii report/reconciliation
     */
    public function actionReconciliation()
    {   $day = $this->day ? date('Ymd', strtotime($this->day)) : date('Ymd',strtotime('-1 day'));
        $tsStart = strtotime($day);
        $tsEnd = $tsStart+86400;

        //找出当天0点和24点余额
        //0点余额
        $sql = "SELECT a.channel_account_id,balance,frozen_balance,a.created_at FROM p_channel_account_balance_snap a, 
(SELECT channel_account_id,MIN(created_at) AS created_at FROM `p_channel_account_balance_snap` WHERE created_at>={$tsStart} AND created_at<{$tsEnd} GROUP BY channel_account_id) b 
WHERE a.channel_account_id=b.channel_account_id AND a.created_at=b.created_at";
        $startBalance =  Yii::$app->db->createCommand($sql)->queryAll();
        foreach ($startBalance as $sb){
            $data = [
                'channel_balance_begin'=>$sb['balance'],
                'channel_balance_begin_ts'=>$sb['created_at'],
                'channel_frozen_balance_begin'=>$sb['frozen_balance'],
                'channel_frozen_balance_begin_ts'=>$sb['created_at'],
            ];
            echo json_encode($data).PHP_EOL;
            ReportChannelProfitDaily::updateAll($data,['date' => $day, 'channel_account_id'=>$sb['channel_account_id']]);
        }

        //24点余额
        $tomorrowTsStart = $tsStart+86400;
        $tomorrowTsEnd = $tsEnd+86400;
        $sql = "SELECT a.channel_account_id,balance,frozen_balance,a.created_at FROM p_channel_account_balance_snap a, 
(SELECT channel_account_id,MIN(created_at) AS created_at FROM `p_channel_account_balance_snap` WHERE created_at>={$tomorrowTsStart} AND created_at<{$tomorrowTsEnd} GROUP BY channel_account_id) b 
WHERE a.channel_account_id=b.channel_account_id AND a.created_at=b.created_at";
        $endBalance =  Yii::$app->db->createCommand($sql)->queryAll();
        foreach ($endBalance as $sb){
            $data = [
                'channel_balance_end'=>$sb['balance'],
                'channel_balance_end_ts'=>$sb['created_at'],
                'channel_frozen_balance_end'=>$sb['frozen_balance'],
                'channel_frozen_balance_end_ts'=>$sb['created_at'],
            ];
            ReportChannelProfitDaily::updateAll($data,['date' => $day, 'channel_account_id'=>$sb['channel_account_id']]);
        }
        //计算充值平台渠道方日结余
        $sql = "UPDATE p_report_channel_profilt_daily SET channel_sum=(channel_balance_end+channel_frozen_balance_end-channel_balance_begin-channel_frozen_balance_begin)
 WHERE created_at>={$tsStart} AND created_at<{$tsEnd}";
        Yii::$app->db->createCommand($sql)->execute();
    }

}
