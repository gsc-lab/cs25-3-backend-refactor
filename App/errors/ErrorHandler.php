<?php

namespace App\Errors;

use Throwable;

class ErrorHandler{

    // 서버 오류 (500)
    public static function server (Throwable $e, string $tag = 'server_error') :array
    {
        error_log("$tag". $e->getMessage());

        return[
            'success' => false,
            'error'   => [
                'code'    => 'INTERNAL_SERVER_ERROR',
                'message' => '서버 내부 오류가 발생했습니다.'
                ]
            ];
    }

}

