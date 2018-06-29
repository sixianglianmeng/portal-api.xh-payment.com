<?php
namespace app\common\exceptions;
use app\components\Macro;

/**
 * 服务器失败
 * @author booter<booter.ui@gmail.com>
 *
 */
class UnKnownErrorException extends \Exception
{
    protected $code = Macro::ERR_UNKNOWN;
    public function __construct($message = "", $code = Macro::ERR_UNKNOWN, Throwable $previous = null) {

    }

}
