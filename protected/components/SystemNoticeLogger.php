<?php
/**
 * 基于Yii2 yii\log\EmailTarget，满足日志规范
 *
 * @author booter<booter.ui@gmail.com>
 * @copyright None
 */

namespace app\components;

use app\common\models\model\SiteConfig;
use power\yii2\log\Logger;
use Yii;
use yii\base\InvalidConfigException;
use yii\di\Instance;
use yii\mail\MailerInterface;
use yii\log\Target;

/**
 * Email路由规则
 * EmailTarget sends selected log messages to the specified email addresses.
 *
 * You may configure the email to be sent by setting the [[message]] property, through which
 * you can set the target email addresses, subject, etc.:
 *
 * ```php
 * 'components' => [
 *     'log' => [
 *          'targets' => [
 *              [
 *                  'class' => 'app\components\SystemNoticeLogger',
 *                  'levels' => ['error', 'warning'],
 *                   'telegram'=>[
 *                        'api_uri'=>'https://t1-portal.huaruipay.com/telgram/msg.php',
 *                   ],
 *
 *                  'email' => [
 *                      'from' => ['log@example.com'],
 *                      'to' => ['developer1@example.com', 'developer2@example.com'],
 *                      'subject' => 'Log message',
 *                  ],
 *              ],
 *          ],
 *     ],
 * ],
 * ```
 *
 * In the above `mailer` is ID of the component that sends email and should be already configured.
 *
 * @author booter<booter.ui@gmail.com>
 * @since 2.0
 */
class SystemNoticeLogger extends Target
{
    /**
     * @var array the configuration array for creating a [[\yii\mail\MessageInterface|message]] object.
     * Note that the "to" option must be set, which specifies the destination email address(es).
     */
    public $message = [];
    public $email = [];
    public $telegram = [];


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
    }

    /**
     * Sends log messages to specified email addresses.
     */
    public function export()
    {
        $messages = array_map([$this, 'formatMessage'], $this->messages);
        $messages = wordwrap(implode("\n", $messages), 70);

        //404错误不发送警报
        if(strpos($messages,'NotFoundHttpException')){
            return true;
        }

        $title = gethostname();
        if(Yii::$app->request->getIsConsoleRequest()){
            $title.=" ".pathinfo(WWW_DIR)['basename'];
        }else{
            $title.=" ".Yii::$app->request->hostName;
        }

        $telgramKey = SiteConfig::cacheGetContent('sys_notice_tegram_key');
        $telgramUrl = SiteConfig::cacheGetContent('sys_notice_tegram_url');
        $telgramChatId = SiteConfig::cacheGetContent('sys_notice_tegram_chatid');
        if($telgramKey && $telgramUrl && $telgramChatId){
            $data = [
                'msg'=> $title."\n".$messages,
                'key'=> $telgramKey,
                'chatId'=> $telgramChatId,
            ];
            $ret = Util::curlPost($telgramUrl,$data);
            if($ret!='ok'){

            }
        }

        $mailAddr = SiteConfig::cacheGetContent('sys_notice_mail_to');
        if(!empty($mailAddr)){
            Yii::$app->mailer->compose()
                ->setTo($mailAddr)
                ->setSubject($title)
                ->setTextBody($messages)
                ->send();
        }
    }

    /**
     * Composes a mail message with the given body content.
     * @param string $body the body content
     * @return \yii\mail\MessageInterface $message
     */
    protected function composeMessage($body)
    {
        $message = $this->mailer->compose();
        Yii::configure($message, $this->message);
        $message->setTextBody($body);

        return $message;
    }

    /**
     * Formats a log message for display as a string.
     * @param array $message the log message to be formatted.
     * The message structure follows that in [[Logger::messages]].
     * @return string the formatted message
     */
    public function formatMessage($message)
    {
        list($text, $level, $category, $timestamp) = $message;
        $level = Logger::getLevelName($level);
        if (!is_string($text)) {
            // exceptions may not be serializable if in the call stack somewhere is a Closure
            if ($text instanceof \Throwable || $text instanceof \Exception) {
                $text = (string) $text;
            } else {
                $text = VarDumper::export($text);
            }
        }
        $traces = [];
        if (isset($message[4])) {
            foreach ($message[4] as $trace) {
                $traces[] = "in {$trace['file']}:{$trace['line']}";
            }
        }

        $prefix = $this->getMessagePrefix($message);
        $message =  $this->getTime($timestamp) . " {$prefix}[$level][$category] $text"
            . (empty($traces) ? '' : "\n    " . implode("\n    ", $traces));

        return $message;
    }


}
