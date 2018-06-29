<?php
namespace app\jobs;

use app\components\Macro;
use app\modules\gateway\models\logic\LogicOrder;
use yii\base\BaseObject;
use yii\queue\JobInterface;
use yii\queue\RetryableJobInterface;
use app\common\models\model\Order;

class PaymentNotifyJob extends BaseObject implements RetryableJobInterface
{
    public $orderNo;
    public $url;
    public $data;

    public function execute($queue)
    {
        $urlInfo = parse_url($this->url);

        \Swoole\Async::dnsLookup($urlInfo['host'], function ($domainName, $ip) use($urlInfo,$queue) {
            $cli = new \swoole_http_client($ip, 80);
            $cli->set([ 'timeout' => 10]);
            $cli->setHeaders([
                'Host' => $domainName,
                "User-Agent" => 'Chrome/49.0.2587.3',
                'Accept' => 'text/html,application/xhtml+xml,application/xml',
                'Accept-Encoding' => 'gzip',
            ]);
            $urlInfo['path'] = $urlInfo['path']??'/';
            $cli->post($urlInfo['path'], $this->data, function ($cli) use ($queue) {
                \Yii::debug(['PaymentNotifyJob ret',$this->orderNo,$cli->statusCode]);
                $noticeOk = Order::NOTICE_STATUS_NONE;
                if($cli->statusCode == 200){
                    $noticeOk = Order::NOTICE_STATUS_SUCCESS;
                }else{
                    $noticeOk = Order::NOTICE_STATUS_FAIL;
                }
                LogicOrder::updateNotifyResult($this->orderNo,$noticeOk,$cli->body);
            });
        });
    }

    public function getRetryDelay($attempt, $error)
    {
        return $attempt * 30;
    }

    public function getTtr()
    {
        return 100;
    }

    public function canRetry($attempt, $error)
    {
        return ($attempt < 10);// && ($error instanceof TemporaryException)
    }
}