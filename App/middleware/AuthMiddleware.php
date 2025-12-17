<?php
// 로그인 체크

require_once __DIR__. "/GuestMiddleware.php";
require_once __DIR__. "/RoleMiddleware.php";

function require_login():void {

    if (empty($_SESSION['user'])) {
        json_response([
            'success' => false,
            'error' => ['code' => 'UNAUTHENTICATED',
                        'message' => '인증 정보가 유효하지 않습니다.로그인하세요.']
        ], 401);
        exit;
    } 
}

function run_middlewares(array $middlewares): void
{
    foreach ($middlewares as $mw) {
        switch ($mw) {
            case 'login':
                require_login();
                break;

            case 'client':
                require_role(['client']);
                break;

            case 'designer':
                require_role(['designer']);
                break;

            case 'manager':
                require_role(['manager']);
                break;
            
            case 'guest':
                require_guest();
                break;
        }
    }
}



?>