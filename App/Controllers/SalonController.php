<?php

namespace App\Controllers;

use App\Errors\ErrorHandler;
use App\Services\SalonService;
use App\Services\ImageService;
use Throwable;

require_once __DIR__.'/../db.php';
require_once __DIR__.'/../http.php';

class SalonController {

    // 'GET' -> 정보 보기
    public function index():void{

        try {
            $db = get_db();
            $service = new SalonService($db);
            $row = $service->indexService();

            json_response([
                'success' => true,
                'data' => ['salon' => $row]
            ]);

        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[salon_index]'), 500);
        }    
}


    // 'PUT' -> salon정보 수정
    public function updateText():void{
        
        try{

            $data = read_json_body();

            if (!is_array($data)) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'INVALID_REQUEST_BODY',
                        'message' => 'JSON 형식의 요청 본문이 필요합니다.',
                    ],
                ], 400);
                return;
            }

            $intro         = $data['introduction'] ?? '';
            $info          = json_encode($data['information']) ?? '';
            $traffic       = json_encode($data['traffic']) ?? '';

            if ($intro === '' || $info === '' ||  $traffic === '') {
                json_response([
                    'success' => false,
                    'error' => ['code' => 'INVALID_REQUEST',
                                'message' => '유효하지 않은 요청입니다.']
                ], 400);
            return;
        }
 
            $db = get_db();
            $updateText = new SalonService($db);
            $updateText->updateTextService($intro, $info, $traffic);
           
            if (!$updateText) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'UPDATE_FAILED',
                        'message' => '수정에 실패했습니다.'
                    ]
                ], 500);
                return;
            }

            json_response([
                'success' => true,
                'message' => '수정 성공했습니다.'
            ]);


        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[salon_update]'), 500);
        }   
    }


    // ==========================
    // PUT /salon/image/:id — 이미지 변경
    // ==========================
    // --- updateImage 메서드는 뉴스 글의 '이미지만' 수정하는 기능을 담당합니다.
    // --- 클라이언트는 POST /salon/image 와 같은 요청을 보내며
    // --- 새로운 이미지를 업로드하면, 기존 이미지를 삭제하고 새 이미지로 교체합니다.
    public function updateImage ():void {

        try{
            // 이미지 파일이 요청에 포함되지 않은 경우
            if (!isset($_FILES['image'])) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'NO_FILE',
                        'message' => 'image 파일이 전달되지 않았습니다.',
                    ],
                ], 400);
                return;
            }

            $file = $_FILES['image'];

            $mine = mime_content_type($file['tmp_name']) ?: '';
            if (strpos($mine,'image/') !== 0) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'INVALID_MIME',
                        'message' => '이미지 파일만 업로드할 수 있습니다.'
                    ]
                ], 400);
                return;
            }

            // 이미지를 업로드 하기 위해 ImageService를 호출하기
            $imageService = new ImageService();
            
            // upload함수 호출 (파일 정보 넘기기)
            $uploadResult = $imageService->upload($file, 'salon'); 
            $newImageKey = $uploadResult['key']; // 새로운 image_key 받기
            $newImageUrl = $uploadResult['url']; // 새로운 image_url 받기

            $db = get_db();
            $imageUpdate = new SalonService($db);
            $row = $imageUpdate->indexService();

            // 기존 이미지 삭제하기
            $imageService->delete($row['image_key']);
            
            // imageUpdate
            $imageUpdate->updateImageService($newImageUrl, $newImageKey);

            if (!$imageUpdate) {
                json_response([
                    'success' => false,
                    'error' => [
                        'code' => 'UPDATE_FAILED',
                        'message' => '이미지 수정에 실패했습니다.'
                    ]
                ], 500);
                return;
            }

            json_response([
                'success' => true,
                'message' => '수정 성공했습니다.'
            ]);
            
        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[salon_updateImage]'),500);
        } 
    }
}

