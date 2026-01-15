<?php

namespace App\Controllers;

use App\Services\ReservationService;
use App\Errors\ErrorHandler;
use DateTime;
use Throwable;

require_once __DIR__. "/../db.php";
require_once __DIR__. "/../http.php";


class ReservationController {

    // -----------------------------------
    // 현재 로그인한 사용자 정보(Session 기반)
    // -----------------------------------
    private int $userId;
    private string $role;
    public function __construct()
    {
        $this->userId = $_SESSION['user']['user_id'];
        $this->role    = $_SESSION['user']['role'];
    }


    // ================================================
    // 자신의 예약 정보 조회 (client 또는 designer 공용)
    // - 로그인된 계정의 role에 따라 조회 조건 변경
    // ================================================
    public function show():void{

        $userId = $this->userId;
        $role   = $this->role; 
        

        try {
            $db = get_db();

            $today  = date('Y-m-d');

            // 과거의 예약인지 아니면 미래의 예약인지 카리키는 표시 
            $time = $_GET['time'] ?? null;

            // Repository 호출하여 JOIN된 예약 + 서비스 목록 조회
            $service = new ReservationService($db);
            $result = $service->getMyReservations($userId, $role, $time, $today);
            
            // 하나의 예약(reservation_id)에 여러 서비스가 연결되므로
            // reservation_id 기준으로 데이터를 그룹핑해서 JSON 출력 형태 조정
            $reservations = [];
                                            
            while ($row = $result->fetch_assoc()) {
                $rid = $row['reservation_id'];

                // 예약 데이터 초기화（처음에 한번 만）
                if (!isset($reservations[$rid])) {
                    $reservations[$rid] = [
                        'reservation_id' => $rid,
                        'client_name'    => $row['client_name'],
                        'designer_name'  => $row['designer_name'],
                        'requirement'    => $row['requirement'],
                        'day'            => $row['day'],
                        'start_at'       => $row['start_at'],
                        'end_at'         => $row['end_at'],
                        'status'         => $row['status'],
                        'cancelled_at'   => $row['cancelled_at'],
                        'cancel_reason'  => $row['cancel_reason'],
                        'created_at'     => $row['created_at'],
                        'updated_at'     => $row['updated_at'],
                        'services'       => [],     // 서비스 배열
                        'total_price'    => 0
                    ];
                }

                // service이름을 배열에 추가
                $reservations[$rid]['services'][] = $row['service_name'];
                // service가격을 배열에 추가
                $reservations[$rid]['total_price'] += $row['price'];
            }

            // JSON으로 내보내는 형태로 변환
            $reservations = array_values($reservations);

            json_response([
                'success' => true,
                'data' => ['reservation' => $reservations]
            ]);

        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[Reservation_create]'),500);
        }
    } 


    // =======================================================
    // 'GET' -> 특정 디자이너 예약 정보 보기 (client, designer)
    // =======================================================
    public function designerDetail():void{

        $designerId = isset($_GET['designer_id']) ? $_GET['designer_id'] : '';
        $today = date('Y-m-d');

        try {

            $db = get_db();

            $service = new ReservationService($db);
            $result = $service->getDesignerUpcomingReservations($designerId, $today);

            $reservations = [];
                                            
            while ($row = $result->fetch_assoc()) {
                $rid = $row['reservation_id'];

                // 예약 데이터의 초기화（멘 처음에 한 번 만）
                if (!isset($reservations[$rid])) {
                    $reservations[$rid] = [
                        'reservation_id' => $rid,
                        'designer_name'  => $row['designer_name'],
                        'day'            => $row['day'],
                        'start_at'       => $row['start_at'],
                        'end_at'         => $row['end_at'],
                        'status'         => $row['status'],
                    ];
                }
            }

            // 연관 배열의 key를 제거하고 JSON 배열 형태로 맞춤
            $reservations = array_values($reservations);

            json_response([
                'success' => true,
                'data' => [
                    'reservation' => $reservations
                ]
            ]);

        } catch (Throwable $e) {
            error_log('[reservation_show]'.$e->getMessage());
            json_response([
                "success" => false,
                "error" => ['code' => 'INTERNAL_SERVER_ERROR', 
                            'message' => '서버 오류가 발생했습니다.'
                ]],500);
                return;
        }
    }

    
    // ===================================================================
    // 'POST' => 예약 작성
    // - service_id(배열) 기반으로 예약 총 소요 시간을 계산한 뒤 end_at 자동 생성
    // - 디자이너 휴무와 기존 예약 시간과의 충돌 여부 검사 필수
    // ===================================================================
    public function create():void {
        
        $userId = $this->userId;

        // 프론트에서 데이터 받기
        $data = read_json_body();

        $designerId  = filter_var($data['designer_id'], FILTER_VALIDATE_INT);
        $requirement = isset($data['requirement']) ? (string)$data['requirement'] : '' ;
        $serviceId   = isset($data['service_id'])  ? $data['service_id'] : '' ;
        $day         = isset($data['day'])         ? (string)$data['day'] : '' ;
        $startAt     = isset($data['start_at'])    ? (string)$data['start_at'] : '' ;

        // 필수 입력 검증
        if ($designerId === '' || $requirement === '' || $serviceId === '' || 
            $day === '' || $startAt === '' ) {
                json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => '필수 필드가 비었습니다.'
                    ]
            ], 400);
            return;
        }

        // 여러계 서비스가 들어갈 수 있으니 ID를 CSV 형태로 변환 (DB 저장용)
        $serviceIds = implode(",", $serviceId);
        
        try {
            $db = get_db();
            $service = new ReservationService($db);
            
            // 서비스 총 소요 시간 계산
            $totalMin = $service->calculateTotalDuration($serviceId);

            // start_at + totalMin → end_at 계산
            $start = new DateTime($startAt);
            $end = clone $start;
            $end->modify("+{$totalMin} minutes");
            $endAt = $end->format("H:i");

            // designer 휴무과 여약시간 중복 여부를 확인
            $checkTimeoff = $service->checkDesignerTimeOff($designerId, $day);

            // 같은 디자이너의 동일 날짜/시간대 예약과 충돌 여부 검사
            $reservationTimeCheck = $service->checkReservationTimeConflict(
                $designerId, $day, $endAt, $startAt);
            
            // 어느 쪽 하나라도 중복이 되면 오류 표시
            if (!$checkTimeoff || !$reservationTimeCheck ) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'TIME_CONFLICT',
                        'message' => '선택한 시간은 예약 불가능합니다.'
                        ]
                ],409);
                return;
            }

            // 중복이 없으면 예약내용을 INSERT하기
            $service->createReservation($userId, $designerId, $requirement,
             $day, $startAt, $endAt, $serviceIds);
            
            json_response([
                'success' => true,
                'message' => '작성 성공했습니다.'
            ],201);
        
        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[Reservation_create]'),500);
        }
    }


    // ==============================================
    // 'PUT' -> 예약 취소 또는 상태 변경
    // - client → cancel_reason 작성 → 예약 취소 처리
    // - designer → status 변경(check-in, confirmed 등)
    // ==============================================
    public function update(string $reservationId):void {
        
        $role = $this->role;

        // reservation_id 유효성 검사
        $reservationId = filter_var($reservationId, FILTER_VALIDATE_INT);
        if ($reservationId === false || $reservationId <= 0) {
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'RESOURCE_NOT_FOUND',
                    'message' => '요청한 리소스를 찾을 수 없습니다.'
                    ]
            ], 404);
            return;
        }            

        $data = read_json_body();
        
        try {

            $db = get_db();
            $service = new ReservationService($db);

            // client 예약 취소
            if ($role === 'client') {

                // 값 검증
                $cancelReason = isset($data['cancel_reason']) ? (string)$data['cancel_reason'] : '';

                if ($cancelReason === '') {
                    json_response([
                        'success' => false,
                        'error'   => [
                            'code'    => 'VALIDATION_ERROR',
                            'message' => '필수 필드가 비었습니다.'
                            ]
                    ], 400);
                    return;
                }

                // reservation_id로 예약내용을 Update하기 (cancel_reason, status,cancelled_at) 
                $clientCancel = $service->cancelReservationByClient($cancelReason, $reservationId);
                
                if ($clientCancel === 0) {
                    json_response([
                        "success" => false,
                        "error"   => [
                            'code'    => 'NO_CHANGES_APPLIED',
                            'message' => '수정된 내용이 없습니다.'
                            ]
                    ], 409);
                    return;
                }
            
            // designer의 예약 상태 변경
            } elseif ($role === 'designer') {
                
                // 값 검증
                $status = isset($data['status']) ? (string)$data['status'] : '';

                if ($status === '') {
                    json_response([
                        'success' => false,
                        'error'   => [
                            'code'    => 'VALIDATION_ERROR',
                            'message' => '필수 필드가 비었습니다.'
                        ]
                    ], 400);
                    return;
                }

                $statusChenge = $service->changeReservationStatus($status, $reservationId);
                
                if ($statusChenge === 0) {
                    json_response([
                        'success' => false,
                        'error'   => [
                            'code'    => 'NO_CHANGES_APPLIED',
                            'message' => '수정된 내용이 없습니다.'
                        ]
                    ], 409);
                    return;
                }            
            }
                json_response([
                    'success' => true,
                    'message' => '수정 성공 했습니다.'
                ]);   

        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[Reservation_create]'),500);
        }
    }
}
