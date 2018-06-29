<?php

namespace app\components;
/*
 * 系统常量定义
 */
class Macro
{
    const FAIL = -1;
    const SUCCESS = 0;
    const ERR_UNKNOWN = 1;
    //通用消息：操作成功
    const SUCCESS_MESSAGE = [
        'code'    => self::SUCCESS,
        'data'    => [],
        'message' => '操作成功'
    ];

    //通用消息：操作失败
    const FAILED_MESSAGE = [
        'code'    => self::FAIL,
        'data'    => [],
        'message' => '操作失败'
    ];


    const USER_LOGIN_REQUIRED = 401;      // 需要登录
    const PARAMETER_VALIDATION_FAILED = 400;
    const PRIVILIEGE_NOTPASS = 403;      //权限不足
    const ERROR_NOT_FOUND = 404;      //找不到
    const INTERNAL_SERVER_ERROR = 500;      // 内部错误
    const SIGN_ERROR = 10002;    // 签名错误
    const ERROR_LOGIN_INFO = 10003;    //登录错误
    const ERR_REFERRER = 10004;
    const ERR_API_IP_DENIED = 10005;
    const ERR_ACCESS_TOKEN = 10006;
    const ERR_PAGE_NOT_FOUND= 404;

    const ERR_NEED_LOGIN = 401;
    const ERR_LOGIN_FAIL = 1001;
    const ERR_NEED_SOME_PARAM = 1011;
    const ERR_PARAM_SIGN= 1012;
    const ERR_PARAM_FORMAT= 1013;

    const ERR_PERMISSION= 1021;

    const ERR_SMS_SEND_FAIL= 2021;
    const ERR_SMS_REACH_MAX= 2022;
    const ERR_SMS_VERIFY_CODE= 2023;

    const ERR_USERNAME_FORMAT = 10011;
    const ERR_USER_EXISTS = 10012;
    const ERR_USER_NOT_FOUND = 10013;
    const ERR_USER_BAN = 10014;
    const ERR_BALANCE_NOT_ENOUGH = 10015;
    const ERR_USER_PASSWORD = 10016;
    const ERR_USER_PASSWORD_CONFIRM = 10017;
    const ERR_USER_GOOGLE_CODE = 10027;
    const ERR_USER_MASTER = 10037;
    const ERR_USER_CHILD_USERNAME = 10087;
    const ERR_USER_CHILD_NON = 10067;
    const ERR_USER_FINANCIAL_EMPTY = 10068;
    const ERR_USER_2FA_EMPTY = 10069;

    const ERR_USER_PAYMENT_INFO_CHANNLE_ACCOUNT_ID = 10501;
    const ERR_USER_PAYMENT_INFO_REMIT_QUOTA_PEDAY = 10502;

    const ERR_PHONE_FORMAT = 10021;
    const ERR_PHONE_EXISTS = 10022;
    const ERR_PHONE_NOT_FOUND = 10023;
    const ERR_PHONE_NEED_BIND = 10024;

    const ERR_PWD_FORMAT = 10031;
    const ERR_PWD_NOT_MATCH = 10032;
    const ERR_LOGIN_FAIL_TOO_MANY_TIMES = 10033;

    const ERR_PAYMENT_CHANNEL_ACCOUNT = 10100;
    const ERR_PAYMENT_CHANNEL_ID = 10101;
    const ERR_PAYMENT_BANK_CODE = 10102;
    const ERR_PAYMENT_CALLBACK_SIGN = 10103;
    const ERR_PAYMENT_PROCESSING = 10104;
    const ERR_PAYMENT_NOTICE_RESULT_OBJECT = 10105;
    const ERR_PAYMENT_ALREADY_DONE = 10106;
    const ERR_PAYMENT_CHANNEL_CONFIG = 10107;
    const ERR_PAYMENT_REACH_ACCOUNT_QUOTA_PER_DAY = 10111;
    const ERR_PAYMENT_REACH_ACCOUNT_QUOTA_PER_TIME = 10112;
    const ERR_PAYMENT_REACH_CHANNEL_QUOTA_PER_DAY = 10113;
    const ERR_PAYMENT_REACH_CHANNEL_QUOTA_PER_TIME = 10114;
    const ERR_PAYMENT_API_NOT_ALLOWED = 10115;
    const ERR_PAYMENT_MANUAL_NOT_ALLOWED  = 10116;
    const ERR_PAYMENT_TYPE_NOT_ALLOWED  = 10117;
    const ERR_MERCHANT_FEE_CONFIG  = 10118;
    const ERR_CHANNEL_FEE_CONFIG  = 10119;
    const ERR_PAYMENT_CHANNEL_TYPE_NOT_ALLOWED  = 10120;

    const ERR_REMIT_REACH_ACCOUNT_QUOTA_PER_DAY = 10201;
    const ERR_REMIT_REACH_ACCOUNT_QUOTA_PER_TIME = 10202;
    const ERR_REMIT_REACH_CHANNEL_QUOTA_PER_DAY = 10203;
    const ERR_REMIT_REACH_CHANNEL_QUOTA_PER_TIME = 10204;
    const ERR_REMIT_API_NOT_ALLOWED = 10205;
    const ERR_REMIT_MANUAL_NOT_ALLOWED = 10206;
    const ERR_REMIT_NOT_FOUND = 10207;

    //下级管理
    const ERR_ACCOUNT_ORDER = 10301;

    //批量excel处理错误
    const ERR_EXCEL_BATCH_REMIT = 10311;
    const ERR_REMIT_BANK_CONFIG = 10312;
    const ERR_USER_FINANCIAL_PASSWORD = 10313;
    const ERR_USER_KEY_FA = 10314;
    const ERR_EXCEL_BATCH_REMIT_NUMBERS = 10315;
    const ERR_REMIT_CHANNEL_NOT_ENOUGH = 10316;
    const ERR_EXCEL_BATCH_REMIT_TOTAL_AMOUNT = 10317;
    const ERR_EXCEL_BATCH_REMIT_AMOUNT = 10318;
    const ERR_ALLOW_MANUAL_REMIT = 10319;


    //调单记录
    const ERR_TRACK_NON = 10320;

    const CONST_PAYMENT_GETWAY_SIGN_TYPE = 'MD5';
    const CONST_JSON = 'json';
    const CONST_HTML = 'html';
    const CONST_PARAM_TYPE_STRING = 'string';
    const CONST_PARAM_TYPE_NUMERIC_STRING = 'numberic_string';
    const CONST_PARAM_TYPE_ARRAY = 'array';
    const CONST_PARAM_TYPE_ARRAY_HAS_KEY = 'array_has_key';
    const CONST_PARAM_TYPE_EMAIL = 'email';
    const CONST_PARAM_TYPE_USERNAME = 'username';
    const CONST_PARAM_TYPE_MOBILE = 'mobile';
    const CONST_PARAM_TYPE_TEL = 'tel';
    const CONST_PARAM_TYPE_IDCARD = 'idcard';
    const CONST_PARAM_TYPE_PASSWORD = 'password';
    const CONST_PARAM_TYPE_INT = 'int';
    const CONST_PARAM_TYPE_INT_GT_ZERO = 'int_gt_zero';
    const CONST_PARAM_TYPE_DECIMAL = 'decimal';
    const CONST_PARAM_TYPE_MD5 = 'md5';
    const CONST_PARAM_TYPE_DATE = 'date';
    const CONST_PARAM_TYPE_DATETIME = 'datetime';
    const CONST_PARAM_TYPE_ORDER_NO = 'order_no';
    const CONST_PARAM_TYPE_ALNUM = 'alnum';
    const CONST_PARAM_TYPE_ENUM = 'enum';
    const CONST_PARAM_TYPE_IP = 'ip';
    const CONST_PARAM_TYPE_IPv4 = 'ipv4';
    const CONST_PARAM_TYPE_IPv6 = 'ipv6';
    const CONST_PARAM_TYPE_BANKCODE = 'bankcode';
    const CONST_PARAM_TYPE_PAYTYPE = 'paytype';
    const CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE = 'alnum_dash_underline';
    const CONST_PARAM_TYPE_DEV_PLATFORM = 'mobile_platform';
    //分页参数类型，数字字母
    const CONST_PARAM_TYPE_SORT = 'sort';
    const CONST_PARAM_TYPE_CHINESE = 'chinese';
    const CONST_PARAM_TYPE_BANK_NO = 'bank_no';

    const CONST_DEV_OS_ALL = ['android', 'ios', 'win', 'wp', 'linux'];
    const CONST_DEV_OS_ANDROID = 'android';
    const CONST_DEV_OS_IOS = 'ios';
    const CONST_DEV_OS_WIN = 'win';
    const CONST_DEV_OS_WP = 'wp';
    const CONST_DEV_OS_LINUX = 'linux';
    const CONST_DEV_TYPE_PHONE = 1;
    const CONST_DEV_TYPE_PAD = 2;
    const CONST_DEV_TYPE_LAPTOP = 3;

    const CLIENT_ID_IN_COOKIE = 'x-client-id';

    const FORMAT_RAW = 'raw';
    const FORMAT_HTML = 'html';
    const FORMAT_JSON = 'json';
    const FORMAT_JSONP = 'jsonp';
    const FORMAT_XML = 'xml';
    const FORMAT_PAYMENT_GATEWAY_JSON = 'payment_json';

    const SELECT_OPTION_ALL = '__ALL__';
    const PAGINATION_DEFAULT_PAGE_SIZE = 10;

    const CACHE_HSET_USER_PERMISSION = "user_permission";
    const CACHE_HSET_SITE_CONFIG = "site_config";

    const MSG_LIST = [
        self::USER_LOGIN_REQUIRED => '系统需要登录',
        self::PARAMETER_VALIDATION_FAILED => '参数验证失败',
        self::PRIVILIEGE_NOTPASS => '权限校验失败',
        self::ERROR_NOT_FOUND => '位置错误',
        self::INTERNAL_SERVER_ERROR => '服务器内部错误',
        self::SIGN_ERROR => '参数签名错误',
        self::ERROR_LOGIN_INFO => '登录信息错误',
        self::ERR_REFERRER => '访问来源错误',
        self::ERR_PAGE_NOT_FOUND => '访问的内容不存在',
        self::ERR_API_IP_DENIED => '接口请求不在允许的IP范围',
        self::ERR_ACCESS_TOKEN => '非法的token令牌',

        self::ERR_NEED_LOGIN => '系统需要登录',
        self::ERR_LOGIN_FAIL => '登录失败',
        self::ERR_NEED_SOME_PARAM => '请求参数不足',
        self::ERR_PARAM_SIGN => '参数签名错误',
        self::ERR_PARAM_FORMAT => '参数格式错误',

        self::ERR_PERMISSION => '权限错误',

        self::ERR_SMS_SEND_FAIL => '短信发送失败',
        self::ERR_SMS_REACH_MAX => '短信超过额度',
        self::ERR_SMS_VERIFY_CODE => '短信验证码错误',

        self::ERR_USERNAME_FORMAT => '用户名错误',
        self::ERR_USER_EXISTS => '用户已存在',
        self::ERR_USER_NOT_FOUND => '用户不存在',
        self::ERR_USER_BAN => '用户已被禁用',
        self::ERR_BALANCE_NOT_ENOUGH => '余额不足',
        self::ERR_USER_FINANCIAL_EMPTY => '资金密码未设置',
        self::ERR_USER_2FA_EMPTY => '手机令牌为设置',
        self::ERR_REMIT_CHANNEL_NOT_ENOUGH => '出款渠道未设置',


        self::ERR_PHONE_FORMAT => '手机号错误',
        self::ERR_PHONE_EXISTS => '手机号已存在',
        self::ERR_PHONE_NOT_FOUND => '手机号不存在',
        self::ERR_PHONE_NEED_BIND => '需要绑定手机号',

        self::ERR_PWD_FORMAT => '密码格式错误',
        self::ERR_PWD_NOT_MATCH => '密码正确',
        self::ERR_LOGIN_FAIL_TOO_MANY_TIMES => '登录失败次数过多',

        self::ERR_PAYMENT_CHANNEL_ACCOUNT => '充值渠道账户错误',
        self::ERR_PAYMENT_CHANNEL_ID => '充值渠道账户ID错误',
        self::ERR_PAYMENT_BANK_CODE => '充值银行代码错误',
        self::ERR_PAYMENT_CALLBACK_SIGN => '充值回调签名错误',
        self::ERR_PAYMENT_PROCESSING => '充值已经处于处理状态',
        self::ERR_PAYMENT_NOTICE_RESULT_OBJECT => '充值结果对象不存在',
        self::ERR_PAYMENT_ALREADY_DONE => '充值已处理完毕',
        self::ERR_PAYMENT_CHANNEL_CONFIG => '充值渠道账户配置错误',
        self::ERR_PAYMENT_REACH_ACCOUNT_QUOTA_PER_DAY => '已达到账户单日充值最大额度',
        self::ERR_PAYMENT_REACH_ACCOUNT_QUOTA_PER_TIME => '已达到账户单次充值最大额度',
        self::ERR_PAYMENT_REACH_CHANNEL_QUOTA_PER_DAY => '已达到渠道单日充值最大额度',
        self::ERR_PAYMENT_REACH_CHANNEL_QUOTA_PER_TIME => '已达到渠道单日充值最大额度',
        self::ERR_PAYMENT_API_NOT_ALLOWED => '商户不支持API充值',
        self::ERR_PAYMENT_MANUAL_NOT_ALLOWED => '商户不支持手工充值',
        self::ERR_PAYMENT_TYPE_NOT_ALLOWED => '商户不支持此充值方式',
        self::ERR_MERCHANT_FEE_CONFIG => '商户费率配置错误',
        self::ERR_CHANNEL_FEE_CONFIG => '渠道账户费率配置错误',
        self::ERR_PAYMENT_CHANNEL_TYPE_NOT_ALLOWED => '渠道不支持此充值方式',

        self::ERR_REMIT_REACH_ACCOUNT_QUOTA_PER_DAY => '已达到账户单日提款最大额度',
        self::ERR_REMIT_REACH_ACCOUNT_QUOTA_PER_TIME => '已达到账户单次提款最大额度',
        self::ERR_REMIT_REACH_CHANNEL_QUOTA_PER_DAY => '已达到渠道单日提款最大额度',
        self::ERR_REMIT_REACH_CHANNEL_QUOTA_PER_TIME => '已达到渠道单次提款最大额度',
        self::ERR_REMIT_API_NOT_ALLOWED => '商户帐号不支持API出款',
        self::ERR_REMIT_MANUAL_NOT_ALLOWED => '商户帐号不支持手工出款',
        self::ERR_REMIT_NOT_FOUND => '提款记录不存在',
    ];

    const BANK_LIST = [
        'ABC'     => '中国农业银行',
        'BOC'     => '中国银行',
        'BOCOM'   => '交通银行',
        'CCB'     => '中国建设银行',
        'ICBC'    => '中国工商银行',
        'PSBC'    => '中国邮政储蓄银行',
        'CMBC'    => '招商银行',
        'SPDB'    => '浦发银行',
        'CEBBANK' => '中国光大银行',
        'ECITIC'  => '中信银行',
        'PINGAN'  => '平安银行',
        'CMBCS'   => '中国民生银行',
        'HXB'     => '华夏银行',
        'CGB'     => '广发银行',
        'BJBANK'  => '北京银行',
        'BOS'     => '上海银行',
        'CIB'     => '兴业银行',
    ];

    public static function getAllBankCode()
    {
        return array_keys(BANK_LIST);
    }
}
