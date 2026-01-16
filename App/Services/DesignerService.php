<?php

namespace App\Services;

use App\Repository\DesignerRepository;
use mysqli;

class DesignerService {

    // DesignerRepository클래스의 인스턴스 멘버 변수
    private DesignerRepository $repo;

    /**
     * __construct
     * @param mysqli $db
     */
    public function __construct(mysqli $db)
    {
        $this->repo = new DesignerRepository($db);
    }


    /**
     * Designer정보 전체 보기
     * @return array 
     */
    public function listDesigners():array {

        return $this->repo->index();
    
    }


    /**
     * 해당 Designer정보 보기
     * @param int $designerId designer의 userID
     * @return array
     */
    public function getDesigner(
        int $designerId
        ):array {

            return $this->repo->show($designerId);
        
        }

    
    /**
     * Designer정보 작성
     * @param int $userId         PRYMARY KEY
     * @param string $imageUrl    이미지의 URL
     * @param string $imageKey    이미지의 이름
     * @param int $experience     결력
     * @param string $good_at     잘하는 기술
     * @param string $personality 분의기
     * @param string $message     메시지
     * @return bool
     */
    public function createDesigner(
        int    $userId, 
        string $imageUrl,
        string $imageKey,
        int    $experience, 
        string $good_at,
        string $personality,
        string $message
    ): bool {

        $result = $this->repo->create($userId, $imageUrl, $imageKey, $experience,
                            $good_at, $personality, $message);
        return $result;
    }


    /**
     * 텍스트 정보만 수정
     * @param int $designerId     designer의 userID
     * @param int $experience     결력
     * @param string $good_at     잘하는 기술
     * @param string $personality 분의기
     * @param string $message     메시지
     * @return bool
     */
    public function updateDesignerProfile(
        int    $designerId,
        int    $experience,
        string $goodAt,
        string $personality,
        string $message
    ) : bool {
        
        $result = $this->repo->updateTextOnly( $designerId, $experience, $goodAt, $personality, $message);

        return $result;
    }




    /**
     * 이미지만 교체하는 엔드포인트 
     * @param int $designerId     designer의 userID
     * @param string $imageUrl    이미지의 URL
     * @param string $imageKey    이미지의 이름
     * @return bool
     */
    public function updateDesignerImage(
        int $designerId,
        string $imageUrl,
        string $imageKey
        ): bool {

            $result = $this->repo->updateImageOnly($designerId, $imageUrl, $imageKey);

            return $result;
    }


    /**
     * 파일 키 조회 — 삭제 전에 R2 파일 삭제용으로 사용
     * @param int $designerId designer의 userID
     * @return string|null
     */
    public function getDesignerImageKey(
        int $designerId
        ): ?string {
    
            return $result = $this->repo->findFileKey($designerId);
    }


    /**
     * Designer 프로필 삭제
     * @param int $designerId designer의 userID
     * @return int
     */
    public function deleteDesigner(
        int $designerId
    ): int {

        return $this->repo->delete($designerId);
        
    }

}