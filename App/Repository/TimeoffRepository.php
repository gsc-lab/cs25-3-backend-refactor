<?php

namespace App\Repository;

use mysqli;

class TimeoffRepository {

    private mysqli $db;

    // DB값을 생성
    public function __construct(mysqli $db) {     
        $this->db = $db;
    } 


    /**
     *  디자이너 전체 휴무 출력
     * @return array  휴무 정보 전체 출력
     */
    public function index():array {
        
        // 휴무(TimeOff) 데이터를 사용자 정보와 함께 조회하고, 시작 시간 → 사용자 ID 순으로 정렬하는 쿼리
        $stmt = $this->db->prepare("SELECT 
                                            t.to_id,
                                            u.user_name as designer_name,
                                            t.start_at,
                                            t.end_at
                                            FROM TimeOff AS t
                                            JOIN Users AS u
                                                ON t.user_id = u.user_id
                                            ORDER BY t.start_at ASC, t.user_id ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }


    /** 
     * designer 휴무 작성
     * @param int $userId designer의 user_id
     * @return int 성공 1 / 실패 0
     */ 
    public function create(
        int $userId,
        string $startAt,
        string $endAt 
    ):bool {

        $stmt = $this->db->prepare("INSERT INTO 
                                    TimeOff (user_id, start_at, end_at) 
                                    VALUES (?,?,?)");
        $stmt->bind_param('iss', $userId, $startAt, $endAt);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }


    /** 
     * designer 휴무 수정
     * @param int    $toId    휴무 ID
     * @param string $startAt 휴무 시작 일시
     * @param string $endAt   휴무 종료 일시
     * @return int 성공 1 / 실패 0
     */ 
    
    public function update(
        int $toId,
        string $startAt,
        string $endAt
    ):int {

        $stmt = $this->db->prepare("UPDATE TimeOff SET 
                                start_at = ?, end_at = ?
                                WHERE to_id=?");               
        $stmt->bind_param('ssi', $startAt, $endAt, $toId);
        $stmt->execute();

        return $stmt->affected_rows;
    }


    /** 
     * designer 휴무 삭제
     * @param int    $toId    휴무 ID
     * @return int 성공 1 / 실패 0
     */ 
    public function delete(
        int $toId
    ):int {

        $stmt = $this->db->prepare("DELETE FROM TimeOff WHERE to_id=?");
        $stmt->bind_param('i',$toId);
            
        $stmt->execute();

        return $stmt->affected_rows;
    }


    /**
     * 특정 휴무 정보를 조회한다.
     *
     * @param int $toId 휴무 ID
     * @return int 조회 결과 (존재 여부 확인 )
     */
    public function show(
        int $toId
    ): int {

        $stmt = $this->db->prepare("SELECT 1 FROM TimeOff WHERE to_id = ? LIMIT 1");
        $stmt->bind_param('i', $toId);
        $stmt->execute();

        return $stmt->affected_rows;
    }
}