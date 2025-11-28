<?php

namespace App\Controllers;

use App\Services\ImageService;
use RuntimeException;
use Throwable;

    require_once __DIR__ . "/../db.php";
    require_once __DIR__ . "/../http.php";

    class DesignerController{


        // 로그인한 사용자 ID 저장
        private ?int $user_id = null;

        public function __construct(){

            // 세션 값이 존재하면 user_id 저장
            if (isset($_SESSION['user'])){
                $this->user_id = $_SESSION['user']['user_id'];
            }

        }


        // ===============================
        // 'GET' -> Designer정보 전체 보기
        // ===============================
        public function index():void {

            try {
                // DB접속
                $db = get_db();
                // Designer + Users JOIN해서 전체 정보 조회
                $stmt = $db->prepare("SELECT 
                                            d.designer_id,
                                            d.user_id,
                                            u.user_name,
                                            d.image,
                                            d.image_key,
                                            d.experience,
                                            d.good_at,
                                            d.personality,
                                            d.message
                                            FROM Designer AS d
                                            JOIN Users AS u
                                                ON d.user_id = u.user_id
                                            ORDER BY designer_id DESC");
                // 실행
                $stmt->execute();
                // SELECT 결과 가져오기
                $result = $stmt->get_result();
                
                $designers = [];

                // 리스터에 저장
                while($row = $result->fetch_assoc()){
                    array_push($designers, $row);
                }
                
                // 프론트에 반환
                json_response([
                    'success' => true,
                    'data' => ['designer' => $designers]
                ]);
            
            // 예외 처리 (서버내 오류 발생지)
            } catch (Throwable $e) {
                // 에러 로그 기록
                error_log('[designer_index]'. $e->getMessage());
                // 에러 응답 반환
                json_response([
                    "success" => false,
                    "error" => ['code' => 'INTERNAL_SERVER_ERROR', 
                                'message' => '서버 오류가 발생했습니다.'
                ]],500);
                return;
            }  
        }


        // ===============================
        // 'GET' -> 해당 Designer정보 보기
        // ===============================
        public function show(string $designer_id):void {
            
            // designer_id 유호성 검사  
            $designer_id = filter_var($designer_id, FILTER_VALIDATE_INT);

            if ($designer_id === false || $designer_id <= 0) {
                    json_response([
                    'success' => false,
                    'error' => ['code' => 'INVALID_ID',
                                'message' => 'ID가 잘못되었습니다. 올바른 숫자 ID를 지정하십시오.'
                            ]
                ], 400);
                return;
            }

            try{
                $db = get_db(); // DB접속
                
                // JOIN으로 디자이너 상세 정보 조회
                $stmt = $db->prepare("SELECT
                                    d.designer_id,
                                    u.user_name,
                                    d.image,
                                    d.image_key,
                                    d.experience,
                                    d.good_at,
                                    d.personality,
                                    d.message
                                    FROM Designer AS d
                                    JOIN Users AS u
                                        ON d.user_id = u.user_id
                                    WHERE d.designer_id=?");
                $stmt->bind_param('i',$designer_id);
                // 실행
                $stmt->execute();
                $result = $stmt->get_result();

                // 결과가 없는 경우 오류  
                if ($result->num_rows === 0) {
                    json_response([
                        'success' => false,
                        'error' =>['code' => 'RESOURCE_NOT_FOUND',
                                    'message' => '해당 디자이너를 찾을 수 없습니다.'
                                ]
                    ], 404);
                    return;
                }
                
                $row = $result->fetch_assoc();

                // JSON 응답
                json_response([
                    'success' => true,
                    'data' => ['designer' => $row]
                ]);

            // 예외 처리 (서버내 오류 발생지)
            } catch (Throwable $e) {
                error_log('[designer_show]'.$e->getMessage());
                json_response([
                    "success" => false,
                    "error" => ['code' => 'INTERNAL_SERVER_ERROR', 
                                'message' => '서버 오류가 발생했습니다.'
                    ]
                ],500);
                return;
            }
        }


        // ===============================
        // 'POST' -> Designer정보 작성
        // ===============================
        public function create():void {

            $user_id = $this->user_id;
            
            try {
                // 1) 필수 필드 확인
                $experience  = filter_var($_POST['experience'], FILTER_VALIDATE_INT);
                $good_at     = isset($_POST['good_at']) ? (string)$_POST['good_at'] : '';
                $personality = isset($_POST['personality']) ? (string)$_POST['personality'] : '';
                $message     = isset($_POST['message']) ? (string)$_POST['message'] : '';
            
                 // 필수 값 검증
                if ($experience === false || $experience <= 0 || $good_at === '' ||
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
                $stmt = $db->prepare("INSERT INTO Designer
                                    (user_id, image, image_key, experience, good_at, personality, message)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                                    );
                $stmt->bind_param('ississs', 
                            $user_id, $imageUrl, $imageKey, $experience, $good_at, $personality, $message);
                $stmt->execute();

                if ($db->affected_rows === 0) {
                    json_response([
                        'success' => false,
                        'error'   => [
                            'code'    => 'NO_RECORD_INSERTED',
                            'message' => '삽입 처리가 수행되지 않았습니다.',
                        ],
                    ], 400);
                    return;
                }

                json_response([
                    'success' => true 
                ], 201);
        
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
                error_log('[designer_create]'.$e->getMessage());
                json_response([
                    "success" => false,
                    "error"   => [
                        'code'    => 'INTERNAL_SERVER_ERROR', 
                        'message' => '서버 오류가 발생했습니다.'
                    ]
                ],500);
                return;
            }
        }


        
        
        /**
        * ===============================
        * 'PUT' -> Designer정보 수정
        * ===============================
        * 텍스트 정보 수정 (good_at, personality, message)
        *  - body: JSON
        *  - 이미지는 그대로 두고 싶을 때 사용
        *  ※ 이미지까지 변경하고 싶으면 아래 updateImage() 같은 별도 엔드포인트 쓰는 게 깔끔함
        */
        public function update(string $designer_id):void {
            
            // ID 정수 유효성 검사
            $designer_id = filter_var($designer_id, FILTER_VALIDATE_INT);
        
            if ($designer_id === false || $designer_id <= 0) {
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

                $fields = [];
                $params = [];
                $types  = '';

                // 수정 가능 필드만 허용
                $allowed = ['experience', 'good_at', 'personality', 'message'];

                // Body에 포함된 필드만 수정 대상으로 적용
                foreach ($allowed as $field) {
                    if (array_key_exists($field, $data)) {
                        $value = trim((string)$data[$field]);
                        if ($value === '') {
                            json_response([
                                'success' => false,
                                'error'   => [
                                    'code'    => 'VALIDATION_ERROR',
                                    'message' => '필수 필드가 비었습니다.',
                                ],
                            ], 400);
                            return;
                        }

                        $fields[] = $field . ' = ?';
                        $params[] = $value;
                        $types    .= 's';
                    }
                }

                // 수정할 필드가 없을 때
                if (empty($fields)) {
                    json_response([
                        'success' => false,
                        'error'   => [
                            'code'    => 'NO_FIELDS_TO_UPDATE',
                            'message' => '수정할 필드가 없습니다.',
                        ],
                    ], 400);
                    return;
                }

                $db = get_db();

                // UPDATE문 조립
                $stmt = $db->prepare("UPDATE Designer SET "
                                    .implode("," , $fields) 
                                    ." WHERE designer_id=?");
                $types .= 'i';
                $params[] = $designer_id;
                $stmt->bind_param($types, ...$params);
                $stmt->execute();

                json_response([
                    'success' => true
                ]);
            
            // 예외 처리 (서버내 오류 발생지)
            } catch (Throwable $e) {
                error_log('[designer_update]'.$e->getMessage());
                json_response([
                    "success" => false,
                    "error"   => [
                        'code'    => 'INTERNAL_SERVER_ERROR', 
                        'message' => '서버 오류가 발생했습니다.'
                    ]
                ],500);
                return;
            }     
        }



        // ===============================================
        // 'POST' -> 디자이너 이미지 수정
        // ===============================================
        public function updateImage(string $designer_id):void {

            // ID 유효성 검사
            $designer_id = filter_var($designer_id, FILTER_VALIDATE_INT);

            if ($designer_id === false || $designer_id <= 0) {
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

                // 0) 기존 데이터 조회 (image_key 포함)
                $stmt = $db->prepare("SELECT * FROM Designer WHERE designer_id = ?");
                $stmt->bind_param('i', $designer_id);
                $stmt->execute();
                $result  = $stmt->get_result();
                $current = $result->fetch_assoc();

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
                $stmt = $db->prepare("UPDATE Designer SET 
                                            image = ?, image_key = ? 
                                            WHERE designer_id = ?");
                $stmt->bind_param('ssi', $newUrl, $newKey, $designer_id);
                $stmt->execute();

                json_response([
                    'success' => true
                ], 201);

            } catch (Throwable $e) {
                error_log('[hairstyle_updateImage] ' . $e->getMessage());

                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'INTERNAL_SERVER_ERROR',
                        'message' => '서버 오류가 발생했습니다.',
                    ],
                ], 500);
            }
        }



        // ===============================
        // 'DELETE' -> Designer정보 삭제
        // ===============================
        // DB 레코드 + R2 이미지 같이 삭제
        public function delete(string $designer_id):void {
            
            $designer_id = filter_var($designer_id, FILTER_VALIDATE_INT);

            //검증
            if ($designer_id === false || $designer_id <= 0) {
                json_response([
                    'success' => false,
                    'error' => ['code' => 'INVALID_ID',
                                'message' => 'ID가 잘못되었습니다. 올바른 숫자 ID를 지정하십시오..']
                ], 400);
                return;
            }

            try {
                
                $db = get_db(); // DB접속

                // 0) 먼저 image_key 조회
                $stmt = $db->prepare("SELECT image_key FROM Designer WHERE designer_id = ?");
                $stmt->bind_param('i', $designer_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();

                if (!$row) {
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
                $stmt2 = $db->prepare("DELETE FROM Designer WHERE designer_id=?");
                $stmt2->bind_param('i', $designer_id);
                // 실행
                $stmt2->execute();

                // 삭제된 행이 없는 경우 오류 표시
                if ($stmt2->affected_rows === 0){
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
                error_log('[designer_delete]'.$e->getMessage());
                json_response([
                    "success" => false,
                    "error" => [
                        'code'    => 'INTERNAL_SERVER_ERROR', 
                        'message' => '서버 오류가 발생했습니다.'
                    ]
                ],500);
                return;
            }   
        }
    }

?>