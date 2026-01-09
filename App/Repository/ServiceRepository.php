<?php
namespace App\Repository;

// mysqli 클래스를 읽다
use mysqli;
class ServiceRepository{

    // DB 연결 인스턴스를 저장하는 멤버 변수
    private mysqli $db;
    // 생성자 : 외부에서 주입한 mysqli 연결 객체를 받아 저장
    public function __construct(mysqli $db){
        $this->db = $db;
    }
    
    // ================================
    // 전체 Service 목록 조회 (SELECT *)
    // ================================
    public function index():array{

        $stmt = $this->db->prepare("SELECT * FROM Service");
        $stmt->execute();

        // → 전체 결과를 연관배열 형태로 반환
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }


    // ==============================
    // Service 메뉴 생성하기 (INSERT)
    // ==============================
    public function create (
        string $serviceName,
        string $price,
        int    $duration
        ):bool {

        $stmt = $this->db->prepare("INSERT INTO Service
                                    (service_name, price, duration_min)
                                    VALUES (?,?,?)");
        $stmt->bind_param('ssi', $serviceName, $price, $duration);
        $stmt->execute();

        // INSERT 성공 여부 반환
        // affected_rows > 0 이면 정상 INSERT 됨
        return $stmt->affected_rows > 0;
    }


    // =============================
    // Service 메뉴 수정하기 (UPDATE)
    // =============================
    public function update (
        int $sereviceId,
        string $serviceName,
        string $price,
        int $duration
        ):int {

        $stmt = $this->db->prepare("UPDATE Service SET
                service_name = ?, price = ?, duration_min = ?
                WHERE service_id = ?");
        $stmt->bind_param('ssii', 
                    $serviceName, $price, $duration, $sereviceId);
        $stmt->execute();
        
        // 수정할 데이터 예부를 controller에서 check하기 위해 결과 숫자를 반환
        return $stmt->affected_rows;
    }


    // =========================
    // Service 삭제 (DELETE)
    // ==========================
    public function delete(
        int $serviceId
    ):int {

        $stmt = $this->db->prepare("DELETE FROM Service 
                                    WHERE service_id = ?");
        $stmt->bind_param('i', $serviceId);
        $stmt->execute();

        // 삭제할 데이터 예부를 controller에서 check하기 위해 결과 숫자를 반환
        return $stmt->affected_rows;
    }


    /**
     * 특정 service_id의 Service 정보 조회
     * @param int $serviceId 시술의 ID
     * @return array|bool|null falsy：false, 0, "0", "", null, [] 의 경우 null를 반환
     */
    public function show(
        int $serviceId
    ):?array{

        $stmt = $this->db->prepare("SELECT * FROM Service WHERE service_id=?");
        $stmt->bind_param('i', $serviceId);
        $stmt->execute(); 
        $result = $stmt->get_result()->fetch_assoc(); 
        
        // fetch_assoc() → 단일 레코드 반환
        return $result ?: null;
    }
}