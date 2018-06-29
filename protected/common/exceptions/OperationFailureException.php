<?php
namespace app\common\exceptions;
use app\components\Macro;

/**
 * 操作失败异常
 * @author booter<booter.ui@gmail.com>
 *
 */
class OperationFailureException extends \Exception
{
    protected $code = Macro::ERR_UNKNOWN;
    public function __construct($message = "", $code = Macro::ERR_UNKNOWN, Throwable $previous = null) {
        parent::__construct($message,$code,$previous);
    }

}
