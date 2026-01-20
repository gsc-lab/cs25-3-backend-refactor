<?php

namespace App\Repository;;

use mysqli;
use Throwable;

class ReservationRepository {

    private mysqli $db;
    // 생성자: DB 연결 객체를 받아 Repository 내부에서 사용
    public function __construct($db)
    {
        $this->db = $db;
    }


    // ======================================
    //  자기 예약 정보 보기 (client, designer)
    // ======================================
    public function show(
        int    $userId, 
        string $role,     // 역할(client/designer)에 따라 WHERE 문이 달라짐
        ?string $time,    // 과거/미래 조건 (r.day > today 또는 < today)
        string $today     // 오늘 날짜
    ):object {

        $condition = "";
        $where     = "";

        if ((int)$time === -1) {
                $condition = " r.day < ?";
            } else {
                $condition = " r.day >= ?";
            }

            // designer의 경우
            if ($role === 'designer') {
                $where = " WHERE r.designer_id = ?";
            } 
            // client의 경우 
            elseif ($role === 'client') {
                $where = " WHERE r.client_id = ?";
            }

        $stmt = $this->db->prepare("SELECT 
                                        r.reservation_id,
                                        uc.user_name AS client_name,
                                        ud.user_name AS designer_name,
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
                                        rs.unit_price AS price
                                        FROM Reservation AS r
                                        JOIN Users AS uc 
                                            ON uc.user_id = r.client_id   -- client name
                                        JOIN Users AS ud 
                                            ON ud.user_id = r.designer_id -- designer name
                                        JOIN ReservationService AS rs 
                                            ON rs.reservation_id = r.reservation_id
                                        JOIN Service AS s
                                            ON s.service_id = rs.service_id
                                        $where AND $condition 
                                        ORDER BY r.day ASC, r.start_at ASC");
        $stmt->bind_param('is', $userId, $today);
        $stmt->execute();

        // 여러 row가 반환되므로 get_result() 그대로 반환
        return $stmt->get_result();
    }


    // =======================================================
    // 'GET' -> 특정 디자이너 예약 정보 보기 (client, designer)
    // =======================================================
    public function findUpcomingReservationsByDesigner(
        string $designerId,
        string $today
    ): object {

         // where 조건
        $where = " WHERE r.designer_id = ?";
        
        // day
        $day = " r.day >= ? ";

        $stmt = $this->db->prepare("SELECT 
                                    r.reservation_id,
                                    ud.user_name as designer_name,
                                    r.day,
                                    r.start_at,
                                    r.end_at,
                                    r.status
                                    FROM Reservation AS r
                                    JOIN Users AS ud -- designer
                                        ON r.designer_id = ud.user_id
                                    JOIN ReservationService AS rs
                                        ON r.reservation_id = rs.reservation_id
                                    JOIN Service AS s
                                        ON rs.service_id = s.service_id
                                    $where AND $day
                                    ORDER BY r.day, start_at");

        $stmt->bind_param('is', $designerId, $today);
        $stmt->execute();
        return $stmt->get_result();

    }

    // ========================================
    //  선택한 서비스들의 duration_min 합계를 계산
    //  (총 소요 시간 계산용)
    // ========================================
    public function totalMin(
        array $serviceId
    ):int {

        $totalMin = 0;
        
        // 선택한 service_id 배열을 돌면서 duration_min을 하나씩 가져오기
        foreach ($serviceId as $sid) {

            $stmt = $this->db->prepare("SELECT duration_min 
                                        FROM Service WHERE service_id = ?");
            $stmt->bind_param('i', $sid);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // mysqli::fetch_column() — duration_min 값 하나만 가져오기
            $min = (int)$result->fetch_column();
            $totalMin += $min; // $totalMin에 더해서 걸리는 전체 시간을 계산하기
        }

        return $totalMin;
    }


    // =========================================================
    //  디자이너 휴무 / 예약 불가능 시간 중복 체크
    //  특정 날짜가 TimeOff의 start_at~end_at 범위에 포함되는지 확인
    // =========================================================
    public function checkTimeoff(
        int    $designerId,
        string $reservationDay
    ):bool{
        // designer 휴무과 여약시간 중복 여부를 확인
        $stmt = $this->db->prepare("SELECT 1 
                                FROM TimeOff
                                WHERE user_id = ?
                                AND start_at <= ?
                                AND end_at   >= ?
                                    LIMIT 1");
        $stmt->bind_param('iss', 
        $designerId,$reservationDay, $reservationDay);
        $stmt->execute();
        
        // 1건이라도 있으면 중복 → 예약 불가
        return $stmt->get_result()->num_rows <= 0;
    }

    // ============================================
    // 선택한 디자이너의 기존 예약과 시간 중복 여부 확인
    //  시간이 겹치는 조건:
    //      기존.start_at < 새.endAt
    //      AND
    //      새.startAt < 기존.end_at
    // ============================================
    public function reservationTimeCheck(
        int    $designerId,
        string $reservationDay,
        string $endAt,
        string $startAt  
    ):bool {

        $stmt = $this->db->prepare("SELECT 1
                                        FROM Reservation
                                        WHERE designer_id = ?
                                            AND day = ?
                                            AND status NOT IN ('cancelled', 'no_show')
                                            AND start_at < ?
                                            AND ? < end_at
                                            LIMIT 1");
        $stmt->bind_param('isss', 
                    $designerId, $reservationDay, $endAt, $startAt);
        $stmt->execute();

        // 1건이라도 있으면 중복 → 예약 불가
        return $stmt->get_result()->num_rows <= 0;
     }


    // =============================================
    //  예약 작성
    //  - Reservation 저장
    //  - 이어서 ReservationService에 서비스 목록 저장
    //  - 트랜잭션 처리 (둘 중 하나라도 실패하면 롤백) 
    // =============================================
    public function create(
        int $clientId,
        int $designerId,
        string $requirement,
        string $day,
        string $startAt,
        string $endAt,
        string $service_ids  // "2,3,7" 형태로 전달
    ):bool {

        $this->db->begin_transaction();
        
        try {

            // Reservation 테이블에 예약 정보를 INSERT하기
            $stmt = $this->db->prepare("INSERT INTO Reservation
                                        (client_id,  designer_id, requirement, day, start_at, end_at)
                                        VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('iissss', 
            $clientId, $designerId, $requirement, $day, $startAt, $endAt);
            $stmt->execute();
            // 생성된 reservation_id 가져오기
            $reservationId = $stmt->insert_id;

            // ReservationService 테이불에 서비스별 상세 정보 INSERT
            // 가격은 SELECT로 자동 불러오기
            $stmt2 = $this->db->prepare("INSERT INTO ReservationService
                                        (reservation_id, service_id, qty, unit_price)
                                        SELECT ?, s.service_id, 1, s.price
                                        FROM Service AS s
                                        WHERE service_id IN ($service_ids)");
            $stmt2->bind_param('i', $reservationId);
            $stmt2->execute();

            // 모든 작업 성공 → 커밋
            $this->db->commit();

            return $stmt2->affected_rows > 0;

        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }


    // ======================
    //  client → 예약 취소
    // ======================
    public function clientCancel(
        string $cancelReason,
        int    $reservationId
    ): int {

        $stmt = $this->db->prepare("UPDATE Reservation 
                                        SET cancel_reason = ?, status='cancelled', cancelled_at=NOW()
                                        WHERE reservation_id = ?");
        $stmt->bind_param('si', 
                $cancelReason, $reservationId);
        $stmt->execute();

        return $stmt->affected_rows;
    }
    
    
    // ==============================
    //  designer → status 변경
    // ==============================
    public function statusChenge(
        string $status,
        int    $reservationId
    ):int {
     
        $stmt = $this->db->prepare("UPDATE Reservation 
                                    SET status=? WHERE reservation_id=?");
        $stmt->bind_param('si', $status, $reservationId);
        $stmt->execute();

        return $stmt->affected_rows;
    }
}