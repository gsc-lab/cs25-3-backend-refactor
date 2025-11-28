<?php

// RESTful API 응답을 JSON 형식으로 출력하는 함수
// 응답 데이터를 JSON으로 변환해서, 클라이언트가 보기 좋게 전달하는 역할
function json_response($data, $code = 200): void
{
    // HTTP 응답의 body가 JSON 형식이라는 것을 명시
    header('Content-Type: application/json; charset=utf-8');
    // HTTP 응답 코드
    http_response_code($code);
    // HTTP 응답의 body를 클라이언트가 읽을 수 있도록 인코딩
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// HTTP 요청의 body에 담긴 JSON 데이터를 읽어서 PHP 배열로 변환해 주는 함수 정의
function read_json_body(): array
{
    // HTTP 요청의 body에 담긴 데이터를 읽어와서 raw 변수에 저장
    $raw = file_get_contents('php://input');

    // file_get_contents()가 실패하거나 body가 비어 있는 경우
    // 빈 배열 반환
    if ($raw === false || $raw === '')
        return [];

    // 가져온 데이터를 PHP 내부 데이터로 변환한 후 연관 배열 형태로 data 변수에 저장
    $data = json_decode($raw, true);

    // data가 배열이면 그대로 반환 / 배열이 아니면 빈 배열 반환
    return is_array($data) ? $data : [];

}