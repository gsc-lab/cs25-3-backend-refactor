<?php

namespace App\Services;

use App\Repository\ReservationRepository;
use mysqli;

class ReservationService {


    /**
     * ReservationRepository클래스의 인스턴스 멘버 변수
     */
    private ReservationRepository $repo;

    /**
     * 생성자
     * @param mysqli $db 데이터베이스 연결 객체
     */
    public function __construct(mysqli $db)
    {
        // Repository 객체 생성 및 DB 연결 주입
        $this->repo = new ReservationRepository($db);
    }

    
    /**
     * 자기 예약 정보 보기 (client, designer)
     * @param int    $userId     User ID
     * @param string $where      역할(client/designer)에 따라 WHERE 문이 달라짐
     * @param string $condition  과거/미래 조건 (r.day > today 또는 < today)
     * @param string $today      오늘 날짜
     * @return object
     */
    public function getMyReservations(
        int     $userId,
        string  $role,     
        ?string $time,  
        string  $today       
    ):object {
    
        return $this->repo->show($userId, $role,  $time, $today);
    }


    /**
     * 특정 디자이너 예약 정보 보기 (client, designer)
     * @param string $designerId  designer의 userID
     * @param string $today       오늘 날짜
     * @return object
     */
    public function getDesignerUpcomingReservations(
        string $designerId,
        string $today
    ): object {

        $result = $this->repo->findUpcomingReservationsByDesigner($designerId, $today);

        return $result;
    }


    /**
     * 선택한 서비스들의 duration_min 합계를 계산
     * @param array $serviceId 시술의 ID
     * @return int
     */
    public function calculateTotalDuration(
        array $serviceId
    ):int {

        return $this->repo->totalMin($serviceId);
    }


    /**
     * 디자이너 휴무 / 예약 불가능 시간 중복 체크
     * @param int    $designerId     designer의 userID
     * @param string $reservationDay 예약 날 짜
     * @return bool
     */
    public function checkDesignerTimeOff(
        int    $designerId,
        string $reservationDay
    ):bool{
        
        return $this->repo->checkTimeoff($designerId, $reservationDay);
    }


    /**
     * 선택한 디자이너의 기존 예약과 시간 중복 여부 확인
     * @param int    $designerId     designer의 userID
     * @param string $reservationDay 예약 날 짜
     * @param string $endAt          예약 종료 시간
     * @param string $startAt        예약 시작 시간
     * @return bool
     */
    public function checkReservationTimeConflict(
        int    $designerId,
        string $reservationDay,
        string $endAt,
        string $startAt  
    ):bool {

        $result = $this->repo->reservationTimeCheck($designerId, $reservationDay,
                                             $endAt, $startAt );

        return $result;
     }


    /**
     * 예약 작성
     * @param int    $clientId     client의 userID
     * @param int    $designerId   designer의 userID
     * @param string $requirement  comment
     * @param string $day          예약 날 짜
     * @param string $endAt        예약 종료 시간
     * @param string $startAt      예약 시작 시간
     * @param string $service_ids  선텍된 시술의 ID
     * @return bool
     */
    public function createReservation(
        int    $clientId,
        int    $designerId,
        string $requirement,
        string $day,
        string $startAt,
        string $endAt,
        string $service_ids 
    ):bool {

        $result = $this->repo->create($clientId, $designerId, $requirement, 
                            $day, $startAt, $endAt, $service_ids );
        
        return $result; 
    }


    /**
     * client → 예약 취소
     * @param string $cancelReason   calsel 이유
     * @param int    $reservationId  예약 ID
     * @return int
     */
    public function cancelReservationByClient(
        string $cancelReason,
        int    $reservationId
    ): int {

        $result = $this->repo->clientCancel($cancelReason, $reservationId);

        return $result;
    }
    
    
    
    /**
     * designer → status 변경
     * @param string $status         예약 상태
     * @param int    $reservationId  예약 ID
     * @return int
     */
    public function changeReservationStatus(
        string $status,
        int    $reservationId
    ):int {
     
        return $this->repo->statusChenge($status, $reservationId);
    } 

}