<?php

    namespace App\Controllers;

use DateTime;
use Throwable;

    require_once __DIR__. "/../db.php";
    require_once __DIR__. "/../http.php";

    $user_id = $_SESSION['user']['user_id'];

    class ReservationController {

        private int $user_id;
        private string $role;
        public function __construct()
        {
            $this->user_id = $_SESSION['user']['user_id'];
            $this->role    = $_SESSION['user']['role'];
        }


        // =============================================
        // 'GET' -> 자기 예약 정보 보기 (client, designer)
        // =============================================
        public function show():void{

            $user_id = $this->user_id;
            $role    = $this->role; 

            try {

                $db = get_db();

                // designer의 경우
                if ($role === 'designer') {
                    $where = " WHERE r.designer_id = ?";
                } 
                // client의 경우 
                elseif ($role === 'client') {
                    $where = " WHERE r.client_id = ?";
                }

                $stmt = $db->prepare("SELECT 
                                            r.reservation_id,
                                            ud.user_name as designer_name,
                                            uc.user_name as client_name,
                                            s.service_name,
                                            r.requirement,
                                            r.day,
                                            r.start_at,
                                            r.end_at,
                                            r.status,
                                            r.cancelled_at,
                                            r.cancel_reason,
                                            r.created_at,
                                            r.updated_at,
                                            s.price
                                            FROM Reservation AS r
                                            JOIN Users AS ud -- designer
                                                ON r.designer_id = ud.user_id
                                            JOIN Users AS uc -- client
                                                ON r.client_id = uc.user_id
                                            JOIN ReservationService AS rs
                                                ON r.reservation_id = rs.reservation_id
                                            JOIN Service AS s
                                                ON rs.service_id = s.service_id
                                            $where");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $reservations = [];
                                                
                while ($row = $result->fetch_assoc()) {
                    $rid = $row['reservation_id'];

                    // 予約データの初期化（最初の1回だけ）
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
                            'services'       => [],     // サービス一覧
                            'total_price'    => 0
                        ];
                    }

                    // サービス名を配列に追加
                    $reservations[$rid]['services'][] = $row['service_name'];

                    $reservations[$rid]['total_price'] += $row['price'];
                }

                // JSON に出す形へ変換
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

        // ==================================
        // 'POST' => 예약 작성
        // ==================================
    
        public function create():void {
            
            $user_id = $this->user_id;

            // 프론트에서 데이터 받기
            $data = read_json_body();

            $designer_id = filter_var($data['designer_id'], FILTER_VALIDATE_INT);
            $requirement = isset($data['requirement']) ? (string)$data['requirement'] : '' ;
            $service_id = isset($data['service_id']) ? $data['service_id'] : '' ;
            $day = isset($data['day']) ? (string)$data['day'] : '' ;
            $start_at = isset($data['start_at']) ? (string)$data['start_at'] : '' ;

            if ($designer_id === '' || $requirement === '' || $service_id === '' || 
                $day === '' || $start_at === '' ) {
                    json_response([
                    'success' => false,
                    'error' => ['code' => 'VALIDATION_ERROR',
                                'message' => '필수 필드가 비었습니다.']
                ], 400);
                return;
            }

            $service_ids = implode(",", $service_id);
         
            try {
                $db = get_db();

                $total_min = 0;
                // end_at 시간 계산
                foreach ($data['service_id'] as $sid) {
                    $end_at_stmt = $db->prepare("SELECT duration_min FROM Service WHERE service_id=?");
                    $end_at_stmt->bind_param('i', $sid);
                    $end_at_stmt->execute();
                    $result = $end_at_stmt->get_result();
                    $duration_min = (int)$result->fetch_column();
                    $total_min += $duration_min;
                }


                $start = new DateTime($start_at);
                $end = clone $start;
                $end->modify("+{$total_min} minutes");
                $end_at = $end->format("H:i");
 
                // designer 휴무과 여약시간 중복 여부를 확인
                $timeoff_stmt = $db->prepare("SELECT 1 
                                                    FROM TimeOff 
                                                    WHERE user_id = ?
                                                    AND start_at <= ?
                                                    AND end_at >= ?
                                                    LIMIT 1");
                $timeoff_stmt->bind_param('iss', $designer_id, $day, $day );
                $timeoff_stmt->execute();
                $timeoff_result = $timeoff_stmt->get_result();                                  

                // 선택한 designer와 예약 시간, 날짜가 이미 있는 예약이랑 중복이 있는지 확인
                $check_stmt = $db->prepare("SELECT 1 
                                            FROM Reservation 
                                            WHERE designer_id = ?
                                                AND start_at  
                                                AND day=? 
                                                AND status NOT IN ('cancelled', 'no_show')
                                                AND start_at < ?
                                                AND ? < end_at 
                                                LIMIT 1");
                $check_stmt->bind_param('isss', $designer_id, $day, $end_at, $start_at);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                // 어느 쪽 하나라도 중복이 되면 오류 표시
                if ($check_result->num_rows === 1 || $timeoff_result->num_rows === 1) {
                    json_response([
                        'success' => false,
                        'error' => ['code' => 'TIME_CONFLICT',
                                    'message' => '선택한 시간은 예약 불가능합니다.']
                    ],409);
                    return;
                }

                // 중복이 없으면 예약내용을 INSERT하기
                $rv_stmt = $db->prepare("INSERT INTO Reservation 
                                        (client_id, designer_id, requirement,
                                        day, start_at, end_at)
                                        VALUES (?,?,?,?,?,?)");
                $rv_stmt->bind_param('iissss', 
                                $user_id, $designer_id, $requirement, 
                                    $day, $start_at, $end_at);
                $rv_stmt->execute();
                $rv_id = $rv_stmt->insert_id;
                $rv_service_stmt = $db->prepare("INSERT INTO ReservationService
                                                    (reservation_id, service_id, qty, unit_price)
                                                    SELECT ?, s.service_id, 1, s.price
                                                    FROM Service AS s
                                                    WHERE service_id IN ($service_ids)");
                $rv_service_stmt->bind_param('i', $rv_id);
                $rv_service_stmt->execute();

                json_response([
                    'success' => true
                ]);
            

            } catch (Throwable $e) {
                error_log('[Reservation_create]'.$e->getMessage());
                json_response([
                    "success" => false,
                    "error" => ['code' => 'INTERNAL_SERVER_ERROR', 
                                'message' => '서버 오류가 발생했습니다.'
                ]],500);
                return;
                }
        }


        // ======================================
        // 'PUT' ->  (클라이언트) 예약 cancel
        // ======================================
        public function update(string $reservation_id):void {
            
            $role    = $this->role;

            $reservation_id = filter_var($reservation_id, FILTER_VALIDATE_INT);
            if ($reservation_id === false || $reservation_id <= 0) {
                json_response([
                    'success' => false,
                    'error' => ['code' => 'RESOURCE_NOT_FOUND',
                                'message' => '요청한 리소스를 찾을 수 없습니다.']
                ], 404);
                return;
            }            

            $data = read_json_body();
            
            try {

                $db = get_db();

                if ($role === 'client') {

                    $cancel_reason = isset($data['cancel_reason']) ? (string)$data['cancel_reason'] : '';

                    if ($cancel_reason === '') {
                        json_response([
                            'success' => false,
                            'error' => ['code' => 'VALIDATION_ERROR',
                                        'message' => '필수 필드가 비었습니다.']
                        ], 400);
                        return;
                    }

                    // reservation_id로 예약내용을 Update하기 (cancel_reason, status,cancelled_at) 
                    $stmt = $db->prepare("UPDATE Reservation 
                                                SET cancel_reason = ?, status='cancelled', cancelled_at=NOW()
                                                WHERE reservation_id = ?");
                    $stmt->bind_param('si', $cancel_reason, $reservation_id);
                    $stmt->execute();

                    if ($stmt->affected_rows === 0) {
                        json_response([
                        "success" => false,
                        "error" => ['code' => 'NO_CHANGES_APPLIED',
                                    'message' => '수정된 내용이 없습니다.']
                        ], 409);
                        return;
                    }

                    json_response([
                        'success' => true
                    ]);
                
                // designer 수정
                } elseif ($role === 'designer') {
                    
                    $status = isset($data['status']) ? (string)$data['status'] : '';

                    if ($status === '') {
                        json_response([
                            'success' => false,
                            'error' => ['code' => 'VALIDATION_ERROR',
                                        'message' => '필수 필드가 비었습니다..']
                        ], 400);
                        return;
                    }

                    $stmt = $db->prepare("UPDATE Reservation 
                                        SET status=? WHERE reservation_id=?");
                    $stmt->bind_param('si', $status, $reservation_id);
                    $stmt->execute();
                    if ($stmt->affected_rows === 0) {
                        json_response([
                            "success" => false,
                            "error" => ['code' => 'NO_CHANGES_APPLIED',
                                        'message' => '수정된 내용이 없습니다.']
                        ], 409);
                        return;
                    }

                    json_response([
                        'success' => true
                    ]);             
                }  

                

            } catch (Throwable $e) {
                error_log('[reservation_update]'.$e->getMessage());
                    json_response([
                        "success" => false,
                        "error" => ['code' => 'INTERNAL_SERVER_ERROR', 
                                    'message' => '서버 오류가 발생했습니다.'
                ]],500);
            return;
            }

        }
    }
    

?>