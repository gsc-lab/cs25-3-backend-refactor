<?php

namespace App\Services;

use App\Repository\TimeoffRepository;
use mysqli;

class TimeOffService {

    // TimeoffRepository 인스턴스
    // 실제 DB 접근 로직은 Repository 계층에 위임한다.
    private TimeoffRepository $repo;

    /**
     * 생성자
     *
     * @param mysqli $db 데이터베이스 연결 객체
     */
    public function __construct(mysqli $db) {
        // Repository 객체를 생성하고 DB 연결을 주입
        $this->repo = new TimeoffRepository($db);
    }


    /**
     * 모든 디자이너의 휴무 정보를 조회한다.
     *
     * @return array 휴무 목록 데이터
     */
    public function indexService(): array {
        
        return $this->repo->index(); 
    
    }


    /**
     * 특정 휴무 정보를 조회한다.
     *
     * @param int $toId 휴무 ID
     * @return int 조회 결과 (존재 여부 확인 등)
     */
    public function showService(
        int $toId
    ): int {

        return $this->repo->show($toId) ;

    }

    /**
     * 디자이너의 휴무 정보를 새로 등록한다.
     *
     * @param int    $userId  디자이너 사용자 ID
     * @param string $startAt 휴무 시작 일시
     * @param string $endAt   휴무 종료 일시
     * @return bool 등록 성공 시 true, 실패 시 false
     */
    public function createService(
        int    $userId,
        string $startAt,
        string $endAt
        ): bool {

        return $this->repo->create($userId, $startAt, $endAt);

    }

   
    /**
     * 기존 휴무 정보를 수정한다.
     *
     * @param int    $toId    휴무 ID
     * @param string $startAt 휴무 시작 일시
     * @param string $endAt   휴무 종료 일시
     * @return bool 수정된 행이 1건 이상일 경우 true
     */
    public function updateService(
        int    $toId,
        string $startAt,
        string $endAt
    ): bool {

        return $this->repo->update($toId, $startAt, $endAt) > 0;
    
    }
    

    /**
     * 지정한 휴무 정보를 삭제한다.
     *
     * @param int $toId 휴무 ID
     * @return bool 삭제된 행이 1건 이상일 경우 true
     */
    public function deleteService(
       int $toId 
    ): bool {

        return $this->repo->delete($toId) > 0;

    }
}
