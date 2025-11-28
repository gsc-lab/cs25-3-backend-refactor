<?php
    
    namespace App\Controllers;

use Throwable;
use ValueError;

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

                // Service테이블의 전체 내용 가져오기
                // sql문
                $stmt = $db->prepare("SELECT 
                                            service_id, service_name, price, duration_min 
                                            FROM Service");
                // 실행
                $stmt->execute();
                // 결과 받기
                $result = $stmt->get_result();
                
                // 모둔 Service 정보를 넣는 리스터
                $services = [];
                
                // 반복문을 사용해서 모든 레코드를 리스트에 넣기
                while($row = $result->fetch_assoc()){
                    array_push($services, $row);
                }
                
                // 프런트엔드에 리스터를 반환
                json_response([
                    "success" => true,
                    "data" => ['service' => $services] 
                ]);

            } catch (Throwable $e) {
                error_log('[service_index]' . $e->getMessage());
                json_response([
                    "success" => false,
                    "error" => ['code' => 'INTERNAL_SERVER_ERROR',
                                'message' => '서버 내부 오류가 발생했습니다.']
                ], 500);
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
            
                // sql문
                $stmt = $db->prepare("INSERT INTO Service 
                                        (service_name, price, duration_min) 
                                    VALUES(?,?,?)");
                // binding
                $stmt->bind_param('ssi', $service_name, $price, $duration_min);
                // 실행
                $stmt->execute();

                json_response([
                    "success" => true
                ], 201);
                
                $stmt->close(); 

            } catch (Throwable $e) {
                error_log('[service_create]'.$e->getMessage());
                json_response([
                    "success" => false,
                    "error" => ['code' => 'INTERNAL_SERVER_ERROR',
                                'message' => '서버 내부 오류가 발생했습니다.']
                ], 500);
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

                $stmt = $db->prepare("
                    UPDATE Service
                    SET service_name = ?,
                        price        = ?,
                        duration_min = ?
                    WHERE service_id  = ?
                ");

                $stmt->bind_param('ssii',
                    $service_name,
                    $price,
                    $duration_min,
                    $service_id
                );

                $stmt->execute();

                if ($db->affected_rows === 0) {
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
                error_log('[service_update]'.$e->getMessage());
                json_response([
                    "success" => false,
                    "error" => [
                        'code' => 'INTERNAL_SERVER_ERROR',
                        'message' => '서버 내부 오류가 발생했습니다.'
                    ]
                ], 500);
            }
        }


        // =============================
        // 'DELETE' -> service 내용 삭제
        // =============================
        public function delete(string $serevice_id):void{
   
            // service_id검중
            $service_id = (int)$serevice_id ?? 0;

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
                
                // sql문 
                $stmt = $db->prepare("DELETE FROM Service WHERE service_id=?");
                // 실행
                $stmt->bind_param('i', $service_id);
                $stmt->execute();

                if ($db->affected_rows === 0){
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
                error_log('[service_delete]' .$e->getMessage());
                json_response([
                    "success" => false,
                    "error" => ['code' => 'INTERNAL_SERVER_ERROR',
                                'message' => '서버 내부 오류가 발생했습니다.']
                ], 500);
                return;
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
                error_log('[service_index]' . $e->getMessage());
                json_response([
                    "success" => false,
                    "error" => ['code' => 'INTERNAL_SERVER_ERROR',
                                'message' => '서버 내부 오류가 발생했습니다.']
                ], 500);
                throw($e);
            }
        }
    }
    

?>