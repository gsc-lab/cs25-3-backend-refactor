<?php

namespace App\Services;

use App\Repository\SalonRepository;
use mysqli;

/**
 * SalonService
 *
 * 살롱(Salon) 정보와 관련된 비즈니스 로직을 담당하는 Service 클래스.
 * Controller로부터 요청을 받아 Repository를 통해 DB 접근을 수행한다.
 * 텍스트 정보 수정과 이미지 수정 기능을 분리하여 관리한다.
 */

class SalonService {
    
    /**
     * SalonRepository 인스턴스
     * 실제 데이터베이스 처리 로직은 Repository 계층에 위임한다.
     */
    private SalonRepository $repo;

    /**
     * 생성자
     *
     * @param mysqli $db 데이터베이스 연결 객체
     */
    public function __construct(mysqli $db)
    {
        // Repository 객체 생성 및 DB 연결 주입
        $this->repo = new SalonRepository($db);
    }


    /**
     * 살롱의 전체 정보를 조회한다.
     *
     * @return array 살롱 정보 목록
     */
    public function indexService(): array{

        return $this->repo->index();

    }

    /**
     * 텍스트 정보만 업데이트 (이미지 변경 없음)
     * @param string  $intro  　살롱 소개 문구
     * @param string  $info     매장 안내 정보
     * @param string  $traffic  오시는 길 안내 정보
     * @return bool 성공 true, 실페 false
    */
    public function updateTextService(
        string $intro,
        string $info,
        string $traffic
    ): bool {

        return $this->repo->updateTextOnly($intro, $info, $traffic);

    }


    /**
     * 살롱 이미지 수정
     * @param string  $newUrl 새 이미지의 URL 
     * @param string  $newKey 스토리지에 저장된 이미지 식별 키
     * @return bool 성공 true, 실페 false
     */
    public function updateImageService(
        string $newUrl,
        string $newKey
    ): bool {

        return $this->repo->updateImage($newUrl, $newKey);

    }

}