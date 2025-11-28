<?php

function require_guest(): void {
    if (!empty($_SESSION['user'])) {
        // 1) 로그인 상태면 바로 로그아웃 처리
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']
            );
        }
        session_destroy();

        // 2) 그리고 나서 그냥 통과시키거나,
        // 또는 프론트에서만 쓸 수 있게 메시지 리턴
        json_response([
            'success' => true,
            'message' => '이미 로그인 상태였으나 로그아웃 후 계속 진행합니다.'
        ], 200);
        exit;
    }
}

?>
