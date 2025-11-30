<?php

namespace App\Repository;

use mysqli;

class TimeoffRepository {

    private mysqli $db;

    // DB값을 생성
    public function __construct(mysqli $db) {     
        $this->db = $db;
    } 


    // 디자이너 전체 휴무 출력
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


    // designer 휴무 작성
    public function create(
        int $user_id,
        string $start_at,
        string $end_at 
    ):bool {

        $stmt = $this->db->prepare("INSERT INTO 
                                    TimeOff (user_id, start_at, end_at) 
                                    VALUES (?,?,?)");
        $stmt->bind_param('iss', $user_id, $start_at, $end_at);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }


    // designer 휴무 수정
    public function update(
        int $to_id,
        string $start_at,
        string $end_at
    ):int {

        // to_id가 DB에 있는지 check
        $stmt = $this->db->prepare("SELECT 1 FROM TimeOff WHERE to_id = ? LIMIT 1");
        $stmt->bind_param('i', $to_id);
        $stmt->execute();
        $result = $stmt->get_result();

        // 해당하지 않는 to_id가 넘어 왔을 때
        if ($result->num_rows === 0) {
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'TIMEOFF_NOT_FOUND',
                    'message' => '해당 휴무 정보가 존재하지 않습니다.'
                    ]
            ], 400);
        }

        $stmt2 = $this->db->prepare("UPDATE TimeOff SET 
                                start_at = ?, end_at = ?
                                WHERE to_id=?");               
        $stmt2->bind_param('ssi', $start_at, $end_at, $to_id);
        $stmt2->execute();

        return $stmt2->affected_rows;
    }


    // designer 휴무 삭제
    public function delete(
        int $to_id
    ):int {

        // to_id가 DB에 있는지 check
        $stmt = $this->db->prepare("SELECT 1 FROM TimeOff WHERE to_id = ? LIMIT 1");
        $stmt->bind_param('i', $to_id);
        $stmt->execute();
        $result = $stmt->get_result();

        // 해당하지 않는 to_id가 넘어 왔을 때
        if ($result->num_rows === 0) {
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'TIMEOFF_NOT_FOUND',
                    'message' => '해당 휴무 정보가 존재하지 않습니다.'
                    ]
            ], 400);
        }

        $stmt2 = $this->db->prepare("DELETE FROM TimeOff WHERE to_id=?");
        $stmt2->bind_param('i',$to_id);
            
        $stmt2->execute();

        return $stmt2->affected_rows;
    }
}