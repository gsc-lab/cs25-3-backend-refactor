<?php

namespace App\Services;

use App\Repository\HairstyleRepository;
use mysqli;

class HairstyleService {


    /**
    * HairstyleRepository 인스턴스
    */
    private HairstyleRepository $repo;

    public function __construct(mysqli $db)
    {
        $this->repo = new HairstyleRepository($db);
    }
    


    /**
     * Summary of listHairstyles
     * @param mixed $limit
     * @return array
     */
    public function listHairstyles(
        ?int $limit = null
    ): array{

        $result = $this->repo->index($limit); 
        return $result;
    }



    /**
     * 특정 HairStyle 조회
     * @param int $hairId
     * @return array
     */
    public function getHairstyle(
        int $hairId
    ):array {

            $result = $this->repo->show($hairId);

            return $result;
    }


    /**
     * 새 헤어스타일 등록 (이미지 업로드 포함)
     * @param string $title       헤어스타일의 title
     * @param string $imageUrl    헤어스타일 image의 URL
     * @param string $imageKey    헤어스타일 image의 imagekey 
     * @param string $description 헤어스타일 image의 설명
     * @return int
     */
    public function createHairstyle(        
        string $title, 
        string $imageUrl,
        string $imageKey,
        string $description
    ): int {
        
        $result = $this->repo->create($title, $imageUrl, $imageKey, $description);

        return $result;                
    }



    /**
     * Summary of updateHairstyleText
     * @param int $hairId         hairID
     * @param string $title       헤어스타일의 title
     * @param string $description 헤어스타일 image의 설명
     * @return bool
     */
    public function updateHairstyleText(
        int    $hairId, 
        string $title,
        string $description
    ):bool {
        
        $result = $this->repo->updateTextOnly($hairId, $title, $description);

        return $result;
    }


    /**
     * 이미지만 교체하는 엔드포인트
     * @param int $hairId    hairID
     * @param string $newUrl 새로운 image의 URL
     * @param string $newKey 새로운 image의 imagekey 
     * @return bool
     */
    public function updateHairstyleImage(
        int    $hairId,
        string $newUrl,
        string $newKey
    ): bool {

        $result = $this->repo->updateImageOnly($hairId, $newUrl, $newKey);

        return $result;
    }


    /**
     * 파일 키 조회 — 삭제 전에 R2 파일 삭제용으로 사용
     * @param int $hairId   hairID
     * @return string|null  있으면 문자열으로, 없으면 null를 반환
     */
    public function getHairstyleImageKey(
        int $hairId
    ): ?string {
        
        $result = $this->repo->findFileKey($hairId);

        return $result;
    }


    /**
     * DELETE — HairStyle 삭제
     * @param int $hairId   hairID
     * @return int
     */
    public function deleteHairstyle(
        int $hairId
    ): int {

        $result = $this->repo->delete($hairId);

        return $result; 
    }
        
}