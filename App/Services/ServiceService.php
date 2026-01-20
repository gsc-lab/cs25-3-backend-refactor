<?php

namespace App\Services;

use App\Repository\ServiceRepository;
use App\Exceptions\NoChangesException;
use mysqli;
use RuntimeException;
use Throwable;

class ServiceService {

    // ServiceRepository 인스턴스 생성
    private ServiceRepository $repo;

    // 생성자
    public function __construct(mysqli $db)
    {
        // Repository 객체를 생성해 DB연결을 전해 준다 
        $this->repo = new ServiceRepository($db);
    }


    /**
     * service내용 전체 반환하기
     * @return array Service 내용을 전체 반환
     */
    public function indexService(): array {

        return $this->repo->index();

    }


    /**
     * service 메뉴 작성
     * @param string $serviceName 시술 명
     * @param string $price 시술 가격
     * @param int $duration 시술 시간
     * @return bool 작성 성공 여부 반환
     */
    public function createService(
        string $serviceName,
        string $price,
        int    $duration
    ): bool {

        $affectedRows = $this->repo->create(
            $serviceName,
            $price,
            $duration
        );

        return $affectedRows > 0;
    }


    /**
     * service내용 수정
     * @param int $sereviceId 시술의 ID
     * @param string $serviceName 시술 명
     * @param string $price 시술의 가격
     * @param int $duration 시술 시간
     * @return bool 수정할 데이터가 있으면 True , 없으면 False 반환
     */
    public function updateService(
        int $sereviceId,
        string $serviceName,
        string $price,
        int $duration
    ): bool {

        $affectedRows = $this->repo->update($sereviceId, $serviceName,
                        $price, $duration );


        if ($affectedRows === 1) {
            return true;
        }

        if ($affectedRows === 0){
            throw new NoChangesException('수정된 내용이 없습니다.');
        }

        throw new RuntimeException('DB update failed');
 
    }

    
    /**
     * Service 삭제 
     * @param int $serviceId 시술 ID
     * @return bool 성공 여부를 반환
     */
    public function deleteService(
        int $serviceId
    ) : bool {
        
        $affectedRows = $this->repo->delete($serviceId);

        return $affectedRows > 0;

    }


    /**
     * 특정 service_id의 Service 정보 조회
     * @param int $serviceId 시술의 ID
     * @return array 특정 Service 정보를 반환
     */
    public function showService(
        int $serviceId
    ): ?array {

        $result = $this->repo->show($serviceId);

        return $result?:null;

    }


}
