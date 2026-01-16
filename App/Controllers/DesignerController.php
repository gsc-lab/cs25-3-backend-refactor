<?php

namespace App\Controllers;

use App\Services\DesignerService;
use App\Errors\ErrorHandler;
use App\Services\ImageService;
use RuntimeException;
use Throwable;

require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../http.php";

class DesignerController{

    // 로그인한 사용자 ID 저장
    private ?int $userId = null;

    public function __construct(){

        // 세션 값이 존재하면 user_id 저장
        if (isset($_SESSION['user'])){
            $this->userId = $_SESSION['user']['user_id'];
        }
    }


    // ===============================
    // 'GET' -> Designer정보 전체 보기
    // ===============================
    public function index():void {

        try {
            // DB접속
            $db = get_db();
            $repo = new DesignerService($db);
            $designers = $repo->listDesigners();

            // 프론트에 반환
            json_response([
                'success' => true,
                'data'    => ['designer' => $designers]
            ]);
        
        // 예외 처리 (서버내 오류 발생지)
        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e,'[designer_index]'), 500);
        }       
    }


    // ===============================
    // 'GET' -> 해당 Designer정보 보기
    // ===============================
    public function show(string $designerId):void {
        
        // designer_id 유호성 검사  
        $designerId = filter_var($designerId, FILTER_VALIDATE_INT);

        if ($designerId === false || $designerId <= 0) {
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'INVALID_ID',
                    'message' => 'ID가 잘못되었습니다. 올바른 숫자 ID를 지정하십시오.'
                ]
            ], 400);
            return;
        }

        try{
            $db = get_db(); // DB접속
            $repo = new DesignerService($db);
            $row = $repo->getDesigner($designerId);
 
            // 결과가 없는 경우 오류  
            if (!$row) {
                json_response([
                    'success' => false,
                    'error'   =>[
                        'code'    => 'RESOURCE_NOT_FOUND',
                        'message' => '해당 디자이너를 찾을 수 없습니다.'
                        ]
                ], 404);
                return;
            }
            
            // JSON 응답
            json_response([
                'success' => true,
                'data'    => ['designer' => $row]
            ]);

        // 예외 처리 (서버내 오류 발생지)
        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e,'[designer_show]'), 500);
        }
    }


    // ===============================
    // 'POST' -> Designer정보 작성
    // ===============================
    public function create():void {

        $userId = $this->userId;
        
        try {
            // 1) 필수 필드 확인
            $experience  = filter_var($_POST['experience'], FILTER_VALIDATE_INT);
            $goodAt     = isset($_POST['good_at']) ? (string)$_POST['good_at'] : '';
            $personality = isset($_POST['personality']) ? (string)$_POST['personality'] : '';
            $message     = isset($_POST['message']) ? (string)$_POST['message'] : '';
        
            // 필수 값 검증
            if ($experience === false || $experience <= 0 || $goodAt === '' ||
                    $personality === '' || $message === '') {
                    json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'VALIDATION_ERROR',
                        'message' => '필수 필드가 비었습니다.'
                        ]
                ], 400);
                return;
            }

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

            // (선택) MIME 검사
            $mime = mime_content_type($file['tmp_name']) ?: '';
            if (strpos($mime, 'image/') !== 0) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'INVALID_MIME',
                        'message' => '이미지 파일만 업로드할 수 있습니다.',
                    ],
                ], 400);
                return;
            }

            // 2) 이미지 R2 업로드
            $imageService = new ImageService();
            // → ['key' => '폴더/파일명.png', 'url' => 'https://...r2.dev/...']
            $uploadResult = $imageService->upload($file, 'designer');
            $imageKey     = $uploadResult['key'];
            $imageUrl     = $uploadResult['url'];

            $db = get_db(); // DB접속
            $repo = new DesignerService($db);
            $result = $repo->createDesigner($userId, $imageUrl, $imageKey, $experience,
                                     $goodAt, $personality, $message);
    
            if (!$result) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'NO_RECORD_INSERTED',
                        'message' => '삽입 처리가 수행되지 않았습니다.',
                    ],
                ], 400);
                return;
            }

            // 성공 200 코드
            json_response([
                'success' => true,
                'message' => '작성 성공 했습니다.' 
            ]);
    
        // 예외 처리 (서버내 오류 발생지)
        } catch (RuntimeException $e) {
            error_log('[designer_create_runtime] ' . $e->getMessage());
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'UPLOAD_FAILED',
                    'message' => '업로드에 실패했습니다.',
                ],
            ], 500);
        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[designer_create]'),500);
        }
    }

   
    
   
    // ===============================
    // 'PUT' -> Designer정보 수정
    // ===============================
    // 텍스트 정보 수정 (good_at, personality, message)
    //  - body: JSON
    //  - 이미지는 그대로 두고 싶을 때 사용
    //  ※ 이미지까지 변경하고 싶으면 아래 updateImage() 같은 별도 엔드포인트 쓰는 게 깔끔함
    public function update(string $designerId):void {
        
        // ID 정수 유효성 검사
        $designerId = filter_var($designerId, FILTER_VALIDATE_INT);
    
        if ($designerId === false || $designerId <= 0) {
            json_response([
                'success' => false,
                'error' => [
                    'code'    => 'INVALID_REQUEST',
                    'message' => '유효하지 않은 ID입니다.'
                ]
            ], 400);
            return;
        }
        
        try{
            // 프론트에서 받은 데이터
            $data = read_json_body();

            // 필수 값 검증
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

            $experience  = filter_var($data['experience'], FILTER_VALIDATE_INT);
            $goodAt     = isset($data['good_at']) ? (string)$data['good_at'] : '';
            $personality = isset($data['personality']) ? (string)$data['personality'] : '';
            $message     = isset($data['message']) ? (string)$data['message'] : '';

            // 필수 값 검증
            if ($experience === false || $experience <= 0 || $goodAt === '' ||
                    $personality === '' || $message === '') {
                    json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'VALIDATION_ERROR',
                        'message' => '필수 필드가 비었습니다.'
                        ]
                ], 400);
                return;
            }

            $db = get_db();
            $repo = new DesignerService($db);
            $repo->updateDesignerProfile($designerId, $experience, 
                                        $goodAt,$personality, $message); 

            // 성공 200 코드
            json_response([
                'success' => true,
                'message' => 'update성공 했습니다.'
            ],200);
        
        // 예외 처리 (서버내 오류 발생지)
        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[designer_update]'),500);
        }     
    }



    // ===============================================
    // 'POST' -> 디자이너 이미지 수정
    // ===============================================
    public function updateImage(string $designerId):void {

        // ID 유효성 검사
        $designerId = filter_var($designerId, FILTER_VALIDATE_INT);

        if ($designerId === false || $designerId <= 0) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'RESOURCE_NOT_FOUND',
                        'message' => '요청한 리소스를 찾을 수 없습니다.'
                        ]
                ], 404);
            return;
        }

        // 파일 유무 확인
        if (empty($_FILES['image'])){
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'NO_FILE',
                    'message' => 'image 파일이 전달되지 않았습니다.'
                ]
            ], 400);
            return;
        }    

        try {

            $db = get_db();
            $repo = new DesignerService($db);
            $current = $repo->getDesigner($designerId);

            if (!$current) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'RESOURCE_NOT_FOUND',
                        'message' => '수정할 데이터를 찾을 수 없습니다.',
                    ],
                ], 404);
                return;
            }
            
            $file = $_FILES['image'];

            $mime = mime_content_type($file['tmp_name']) ?: '';
            if (strpos($mime, 'image/') !== 0) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'INVALID_MIME',
                        'message' => '이미지 파일만 업로드할 수 있습니다.',
                    ],
                ], 400);
                return;
            }

            $imageService = new ImageService();

            // 새로 업로드
            $uploadResult = $imageService->upload($file, 'designer');
            $newKey       = $uploadResult['key'];
            $newUrl       = $uploadResult['url'];

            // 기존 이미지 삭제 (실패하더라도 서비스 자체는 계속)
            try {
                if (!empty($current['image_key'])) {
                    $imageService->delete($current['image_key']);
                } 
            } catch (Throwable $e) {
                error_log('[designer_updateImage_delete_old] ' . $e->getMessage());   
            }

            // DB 수정
            $repo->updateDesignerImage($designerId, $newUrl, $newKey);
            
            // 성공 200 코드
            json_response([
                'success' => true,
                'message' => 'update성공 했습니다.'
            ]);

        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[designer_updateImage]'),500);
        }
    }



    // ===============================
    // 'DELETE' -> Designer정보 삭제
    // ===============================
    // DB 레코드 + R2 이미지 같이 삭제
    public function delete(string $designerId):void {
        
        $designerId = filter_var($designerId, FILTER_VALIDATE_INT);

        //검증
        if ($designerId === false || $designerId <= 0) {
            json_response([
                'success' => false,
                'error' => ['code' => 'INVALID_ID',
                            'message' => 'ID가 잘못되었습니다. 올바른 숫자 ID를 지정하십시오..']
            ], 400);
            return;
        }

        try {
            
            $db = get_db(); // DB접속
            $repo = new DesignerService($db);
            // 0) 먼저 image_key 조회
            $row = $repo->getDesigner($designerId);
            if (!$row['image_key']) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'RESOURCE_NOT_FOUND',
                        'message' => '삭제할 데이터를 찾을 수 없습니다.',
                    ],
                ], 404);
                return;
            }

            $imageKey = $row['image_key'] ?? null;

            // 1) R2 이미지 삭제
            if ($imageKey) {
                $imageService = new ImageService();
                try {
                    $imageService->delete($imageKey);
                } catch (Throwable $e) {
                    error_log('[designer_delete_image] ' . $e->getMessage());
                    // 정책에 따라 여기서 바로 500을 줄 수도 있고,
                    // 일단 레코드는 삭제하고 나중에 orphan 정리하는 식으로 갈 수도 있음
                }
            }

            // 2) DB 삭제
            $delete = $repo->deleteDesigner($designerId);

            // 삭제된 행이 없는 경우 오류 표시
            if ($delete === 0){
                json_response([
                    "success" => false,
                    "error" => [
                        'code' => 'RESOURCE_NOT_FOUND',
                        'message' => '삭제할 데이터를 찾을 수 없습니다.'
                        ]
                ], 404);
                return;
            }
        
            json_response([
                'success' => true
            ], 204);
        
        // 예외 처리 (서버내 오류 발생지)
        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[designer_delete]'),500);
        }   
    }
}
