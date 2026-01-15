<?php
    
namespace App\Controllers;

use App\Services\ServiceService;
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
            $service = new ServiceService($db); // DB를 인자 값으로 보내기
            $result = $service->indexService();
            
            // 프런트엔드에 리스터를 반환
            json_response([
                "success" => true,
                "data" => ['service' => $result] 
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
        $serviceName = isset($data['service_name']) ? trim((string)$data['service_name']) : '';
        $price        = isset($data['price']) ? trim((string)$data['price']) : '';
        $durationMin = filter_var(
            $data['duration_min'] ?? null, 
            FILTER_VALIDATE_INT,
        ['option' => ['min_range' => 1]]
                );

        // 유호성 검사
        if ($serviceName === '' || $price === '' || $durationMin === false ) {
            json_response([
                "success" => false,
                "error"   => [
                    'code' => 'VALIDATION_ERROR', 
                    'message' => '필수 필드가 비었습니다.'
                    ]
            ], 400);
            return;
        }

        try {
            // DB연결
            $db = get_db();
        
            $service = new ServiceService($db);
            $service->createService(
                $serviceName, $price, $durationMin);

            json_response([
                'success' => true,
                'message' => '작성 성공했습니다.'
            ], 201);
            
        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[service_create]'), 500);
        }
    } 


    // ========================
    // 'PUT' -> service내용 수정
    // ========================
    public function update(string $serviceId) : void
    {
        // ID 정수 변환
        $serviceId = filter_var($serviceId, 
                                FILTER_VALIDATE_INT,
                            ['options' => ['min_range' => 1]]
                        );

        if ($serviceId === false) {
            json_response([
                "success" => false,
                "error"   => [
                    "code"    => "RESOURCE_NOT_FOUND",
                    "message" => "요청한 리소스를 찾을 수 없습니다."
                ]
            ], 404);
            return;
        }

        // 프론트에서 입력 정보 받기
        $data = read_json_body();

        $serviceName = isset($data['service_name']) ? trim((string)$data['service_name']) : '';
        $price        = isset($data['price']) ? trim((string)$data['price']) : '';
        $durationMin = filter_var($data['duration_min'],
                                 FILTER_VALIDATE_INT,
                                ['options' => ['min_range' => 1]]
                            );

        // 유효성 검사
        if ($serviceName === '' || $price === '' || $durationMin === false) {
            json_response([
                "success" => false,
                "error"   => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => '필수 필드가 비었습니다.'
                ]
            ], 400);
            return;
        }

        try {
            $db = get_db();

            $service = new ServiceService($db);
            $update = $service->updateService($serviceId, $serviceName,
                                            $price, $durationMin);

            if (!$update) {
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
    public function delete(string $sereviceId):void{

        // service_id검중
        $serviceId = filter_var($sereviceId, 
                                FILTER_VALIDATE_INT,
                            ['options' => ['min_range' => 1]]
                        );

        if ($serviceId === false) {
            json_response([
                "success" => false,
                "error"   => [
                    'code'    => 'INVALID_REQUEST',
                    'message' => '유효하지 않은 요청입니다.'
                    ]
            ], 400);
            return;
        }

        try {
            
            // DB접속
            $db = get_db();
            
            $service = new ServiceService($db);
            $delete = $service->deleteService($sereviceId);

            if (!$delete){
                json_response([
                    "success" => false,
                    "error"   => [
                        'code'    => 'RESOURCE_NOT_FOUND',
                        'message' => '삭제할 데이터를 찾을 수 없습니다.'
                        ]
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
    public function show(string $serviceId) :void {
        
        try{
            // DB접속
            $db = get_db();
            $service = new ServiceService($db);
            $row = $service->showService($serviceId);

            // 데이터가 존재하지 않으면 오류 표시
            if ($row === null){
                json_response(ErrorHandler::notResouce(), 404);
                return;
            }
            
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
