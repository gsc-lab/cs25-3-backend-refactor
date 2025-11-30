<?php

namespace App\Controllers;

use App\Repository\TimeoffRepository;
use App\Errors\ErrorHandler;
use Throwable;

require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../http.php";

class TimeoffController {

    // =============================
    // 'GET' -> 디자이너 전체 휴무 출력
    // =============================
    public function index():void {
        
        try {
            $db = get_db(); // DB 연결
            $repo = new TimeoffRepository($db);
            $result = $repo->index();

            // 성공 응답 반환
            json_response([
                'success' => true,
                'data' => ['timeoff' => $result]
            ]);
        
        // 예외 처리 (서버내 오류 발생지) 
        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[timeoff_index]'), 500);
        }
    }


    // ============================
    // 'POST' => designer 휴무 작성
    // ============================
    public function create():void {
        
        $data = read_json_body(); // JSON 요청 파싱
        
        // 필드 값 받기 (형변환 포함)
        $user_id   = isset($data['user_id']) ? trim((int)$data['user_id']) : '';
        $start_at  = isset($data['start_at']) ? trim((string)$data['start_at']) : '';
        $end_at    = isset($data['end_at']) ? trim((string)$data['end_at']) : '';

        // 필드 검증
        if ($user_id === '' || $start_at == ''|| $end_at === '') {
            json_response([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR',
                            'message' => '필수 필드가 비었습니다.']
            ], 400);
            return;
        }

        try {
            
            $db = get_db(); // DB 연결
            $repo = new TimeoffRepository($db);
            $repo->create($user_id, $start_at, $end_at);

            // 성공 응답
            json_response([
                'success' => true,
                'message' => '작성 성공했습니다.'
            ],201);

        // 예외 처리 (서버내 오류 발생지) 
        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[timeoff_create]'), 500);
        }
    }


    // =============================
    // 'PUT' -> designer 휴무 수정
    // =============================
    public function update(string $to_id) :void {
        
        # "10", "7", "5" -> ok, int형으로 바꿈 ,  "abc"、""、"0"、"-3" -> fals
        $to_id = filter_var($to_id, FILTER_VALIDATE_INT);

        if ($to_id === false || $to_id <= 0) {
            json_response([
                'success' => false,
                'error' => ['code' => 'RESOURCE_NOT_FOUND',
                            'message' => '요청한 리소스를 찾을 수 없습니다.']
            ], 404);
            return;
        }

        $to_id = (int)$to_id; // 형변환 확정
        
        // 프론트에서 데이터를 받는다
        $data = read_json_body();
    
        $start_at  = isset($data['start_at']) ? trim((string)$data['start_at']) : '';
        $end_at    = isset($data['end_at']) ? trim((string)$data['end_at']) : '';
        
        // 유호성 확인
        if ($start_at === '' || $end_at === '' ) {
            json_response([
                    'success' => false,
                    'error' => ['code' => 'VALIDATION_ERROR',
                                'message' => '필수 필드가 비었습니다.']
                ], 400);
            return;
        }

        try {
            // DB 접속
            $db = get_db();
            $repo = new TimeoffRepository($db);
            $result = $repo->update($to_id, $start_at, $end_at);

            // 수정된 행이 없다면 데이터 없음 처리
            if ($result <= 0) {
                json_response([                  
                     "success" => false,
                     "error" => ['code' => 'NO_CHANGES_APPLIED',
                                'message' => '수정된 내용이 없습니다.']
                ], 409);
                return;
            } 
            
            // 성공 응답
            json_response([
                'success' => true,
                'message' => '수정 성공했습니다.'
            ]);
        
        // 예외 처리 (서버내 오류 발생지)
        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[timeoff_update]'), 500);
        }
    }


    // ==============================
    // 'DELETE' -> designer 휴무 삭제
    // ==============================
    public function delete(string $to_id):void{

        // ID 정수 검증
        $to_id = filter_var($to_id, FILTER_VALIDATE_INT);
        
        if ($to_id === false || $to_id <= 0) {
            json_response([
                'success' => false,
                'error' => ['code' => 'RESOURCE_NOT_FOUND',
                            'message' => '요청한 리소스를 찾을 수 없습니다.']
            ], 404);
            return;
        }

        try {

            $db = get_db(); // DB 연결
            $repo = new TimeoffRepository($db);
            $result = $repo->delete($to_id);

            // 삭제된 행이 없으면 오류
            if ($result <= 0){
                    json_response([
                     "success" => false,
                     "error" => ['code' => 'RESOURCE_NOT_FOUND',
                                'message' => '삭제할 데이터를 찾을 수 없습니다.']
                ], 404);
                return;
            }

            // 성공 응답
            json_response([
                'success' => true
            ], 204);

        // 예외 처리 (서버내 오류 발생지)
        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[timeoff_delete]'), 500);
        }      
    }
}



