<?php
namespace App\Repository;

// mysqli 클래스를 읽다
use mysqli;
class ServiceRepository{

    // DB변수 생성
    private mysqli $db;
    // 생성자
    public function __construct(mysqli $db){
        $this->db = $db;
    }
    
    // Service 전체 정보 읽기
    public function index():array{

        $stmt = $this->db->prepare("SELECT * FROM Service");
        $stmt->execute();

        // 
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }


    // Service메뉴를 작성
    public function create (
        string $serviceName,
        string $price,
        int $duration
        ):bool {

        $stmt = $this->db->prepare("INSERT INTO Service
                                    (service_name, price, duration_min)
                                    VALUES (?,?,?)");
        $stmt->bind_param('ssi', $serviceName, $price, $duration);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }


    // Service메뉴 수정
    public function update (
        int $serevice_id,
        string $service_name,
        string $price,
        int $duration
        ):bool {

        $stmt = $this->db->prepare("UPDATE Service SET
                service_name = ?, price = ?, duration_min = ?
                WHERE service_id = ?");
        $stmt->bind_param('ssii', 
                    $service_name, $price, $duration, $serevice_id);
        $stmt->execute();
        
        return $stmt->affected_rows;
    }


    // service메뉴 삭제
    public function delete(
        int $service_id
    ):bool {

        $stmt = $this->db->prepare("DELETE FROM Service 
                                    WHERE service_id = ?");
        $stmt->bind_param('i', $service_id);
        $stmt->execute();

        return $stmt->affected_rows;
    }

}