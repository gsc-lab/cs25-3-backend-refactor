<?php
    
namespace App\Controllers;

use App\Repository\ServiceRepository;
use App\Errors\ErrorHandler;
use Throwable;

require_once __DIR__. '/../db.php';
require_once __DIR__. '/../http.php';

class ServiceController{

    // =================================
    // 'GET' -> service내용 전체 반환하기
    // =================================
    public function index() :void {
        
        try{
            // DB접속
            $db = get_db();

            // ServiceRepositry의 인스탄스를 생성
            $repo = new ServiceRepository($db); // DB를 인자 값으로 보내기
            $services = $repo->index();
            
            // 프런트엔드에 리스터를 반환
            json_response([
                "success" => true,
                "data" => ['service' => $services] 
            ]);

        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[service_index]'), 500);
        }
    }


    // ===========================
    // 'POST' -> service 메뉴 작성
    // ===========================
    public function create():void{
        
        // 프론트에서 데이터를 받는다
        $data = read_json_body();

        // 하나씩 꺼내기
        $service_name = isset($data['service_name']) ? trim((string)$data['service_name']) : '';
        $price        = isset($data['price']) ? trim((string)$data['price']) : '';
        $duration_min = isset($data['duration_min']) ? trim((int)$data['duration_min']) : '';

        // 유호성 검사
        if ($service_name === '' || $price === '' || $duration_min === '') {
            json_response([
                "success" => false,
                "error" => ['code' => 'VALIDATION_ERROR', 
                            'message' => '필수 필드가 비었습니다.']
            ], 400);
            return;
        }

        try {
            // DB연결
            $db = get_db();
        
            $repo = new ServiceRepository($db);
            $repo->create($service_name, $price, $duration_min);

            json_response([
                "success" => true
            ], 201);
            
        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[service_create]'), 500);
        }
    } 


    // ========================
    // 'PUT' -> service내용 수정
    // ========================
    public function update(string $service_id) : void
    {
        // ID 정수 변환
        $service_id = filter_var($service_id, FILTER_VALIDATE_INT);

        if ($service_id === false || $service_id <= 0) {
            json_response([
                "success" => false,
                "error" => [
                    "code" => "RESOURCE_NOT_FOUND",
                    "message" => "요청한 리소스를 찾을 수 없습니다."
                ]
            ], 404);
            return;
        }

        // 프론트에서 입력 정보 받기
        $data = read_json_body();

        $service_name = isset($data['service_name']) ? trim((string)$data['service_name']) : '';
        $price        = isset($data['price']) ? trim((string)$data['price']) : '';
        $duration_min = isset($data['duration_min']) ? (int)$data['duration_min'] : 0;

        // 유효성 검사
        if ($service_name === '' || $price === '' || $duration_min <= 0) {
            json_response([
                "success" => false,
                "error" => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => '필수 필드가 비었습니다.'
                ]
            ], 400);
            return;
        }

        try {
            $db = get_db();

            $repo = new ServiceRepository($db);
            $services = $repo->update($service_id, $service_name,
                                            $price, $duration_min);

            if ($services <= 0) {
                // 없는 ID이거나 값이 완전히 동일한 경우
                json_response([
                    "success" => false,
                    "error" => [
                        "code" => "RESOURCE_NOT_FOUND",
                        "message" => "수정할 데이터를 찾을 수 없습니다."
                    ]
                ], 404);
                return;
            }

            json_response([
                "success" => true
            ], 201); // 프론트가 201을 기대하고 있으니 그대로

        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[service_update]'), 500);
        }
    }


    // =============================
    // 'DELETE' -> service 내용 삭제
    // =============================
    public function delete(string $serevice_id):void{

        // service_id검중
        $service_id = filter_var($serevice_id, FILTER_VALIDATE_INT);

        if ($service_id === false || $service_id <= 0) {
            json_response([
                "success" => false,
                "error" => ['code' => 'INVALID_REQUEST',
                            'message' => '유효하지 않은 요청입니다.']
            ], 400);
            return;
        }

        try {
            
            // DB접속
            $db = get_db();
            
            $repo = new ServiceRepository($db);
            $delete = $repo->delete($serevice_id);

            if ($delete <= 0){
                json_response([
                    "success" => false,
                    "error" => ['code' => 'RESOURCE_NOT_FOUND',
                                'message' => '삭제할 데이터를 찾을 수 없습니다.']
                ], 404);
                return;
            }

            // 성공
            json_response([
                "success" => true,
            ], 204);   

        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[service_delete]'), 500);
        }
    }

    // 'GET' -> service내용 전체 반환하기
    public function show(string $service_id) :void {
        
        try{
            // DB접속
            $db = get_db();

            // Service테이블의 세부 내용 가져오기
            // sql문
            $stmt = $db->prepare("SELECT service_id, service_name, price, duration_min FROM Service WHERE service_id=?");
            $stmt->bind_param('i', $service_id);
            // 실행
            $stmt->execute();
            // 결과 받기
            $result = $stmt->get_result();
            
            // 반복문을 사용해서 모든 레코드를 리스트에 넣기
            $row = $result->fetch_assoc();
            
            // 프런트엔드에 리스터를 반환
            json_response([
                "success" => true,
                "data" => [
                            'service_name' => $row['service_name'],
                            'price' => $row['price'],
                            'duration_min' => $row['duration_min']
                            ] 
            ]);

       } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[service_show]'), 500);
        }
    }
}
