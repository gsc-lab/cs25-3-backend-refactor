<?php

function require_role(array $roles):void {
    if (!in_array($_SESSION['user']['role'], $roles)) {
        json_response([
            'success' => false,
            'error'   => ['code'    => 'FORBIDDEN',
                          'message' => '이 작업을 수행할 권한이 없습니다.']
        ], 403);
        exit;
    }
}



?>