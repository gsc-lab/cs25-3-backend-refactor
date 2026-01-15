<?php

namespace App\Errors;

use Throwable;

class ErrorHandler{


    // 리소스 잦을 수 없는 오류
    public static function notResouce (string $message = '데이터를 찾을 수 없습니다.') :array
    {
        return [
            'success' => false,
            'error'   => [
                'code' => 'RESOURCE_NOT_FOUND',
                'message' => $message
            ]
        ];
    }


    // 서버 오류 (500)
    public static function server (Throwable $e, string $tag = 'server_error') :array
    {
        error_log("$tag". $e->getMessage() . "\n" . $e->getTraceAsString());

        return[
            'success' => false,
            'error'   => [
                'code'    => 'INTERNAL_SERVER_ERROR',
                'message' => '서버 내부 오류가 발생했습니다.'
                ]
            ];
    }

}

