<?php

// 데이터베이스 정보 및 mysqli 예외 모드 설정 불러오기
require_once __DIR__ . '/config.php';

// 데이터베이스 연결 함수 정의
function get_db(): mysqli
{
    try {
        // 데이터베이스 연결 및 반환
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $db->set_charset('utf8mb4');  // UTF-8 설정
        return $db;

    } catch (Throwable $e) {
        // 세션 활성화는 되어있지만, 세션을 시작하지 않았을 때
        // 세션 시작
        if (session_status() === PHP_SESSION_NONE)
            session_start();

        // 세션 변수 'error'에 오류 메시지 저장
        $_SESSION['error'] = 'DB 연결 오류';

        // PHP 서버 로그에 실제 오류 메시지 저장
        error_log('[DB] ' . $e->getMessage());

        // 예외를 다시 상위로 던져 500 JSON 응답 처리
        throw $e;
    }
}
?>