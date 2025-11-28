<?php
declare(strict_types=1);

// 쿠키 만료시간 1시간
$sessionLifetime = 3600;

// 세션 만료 정리 시간 설정
ini_set('session.gc_maxlifetime', (string)$sessionLifetime);

// 세션 쿠키 설정
session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'path'     => '/',
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

// Composer 오토로드 불러오기
require_once __DIR__ . '/../vendor/autoload.php';

// .env 환경 변수 로드
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// 공통 HTTP 응답 함수 등
require_once __DIR__ . '/../App/http.php';

// 라우터 설정 (registerAllRoutes 함수 포함)
require_once __DIR__ . '/../routes/router.php';

// 미들웨어 파일 로드 (run_middlewares, require_login 등)
require_once __DIR__ . '/../App/middleware/AuthMiddleware.php';

// 프리플라이트 요청(OPTIONS)은 여기서 바로 종료 → 실제 라우터까지 보내지 않음
// CORS 설정 시 브라우저가 먼저 OPTIONS로 물어볼 때 사용
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // 내용 없는 정상 응답
    exit;
}

// 라우터 인스턴스 생성
$router = new AltoRouter();

// API의 기본 경로 설정 (예: http://example.com/v1/…)
$router->setBasePath('/v1');

// 라우트 일괄 등록 (GET/POST/PUT/DELETE 등)
registerAllRoutes($router);

// 현재 요청 URL과 등록된 라우트 매칭 시도
$match = $router->match();

// 매칭 실패 시 404 응답
if (!$match) {
    echo json_response(['error' => '404 Not Found'], 404);
    exit;
}

// 매칭된 라우트의 타깃(콜백/컨트롤러 정보 등)
$target = $match['target'];

// URL 경로에서 추출된 파라미터들 (예: /users/[i:id] 의 id 값)
$params = array_values($match['params']);

/**
 * 1. $target이 단순 콜러블(익명함수, 함수 이름 등)인 경우 처리
 */
if (is_callable($target)) {

    // 1-1. 콜러블이면서, 배열 형태로 미들웨어 정보가 포함된 경우
    if (is_array($target) && isset($target['middleware'])) {
        // 우선 미들웨어 실행 (로그인 여부, 권한 체크 등)
        run_middlewares($target['middleware']);

        // 실제 실행할 함수(컨트롤러 메서드 등)
        $callable = $target['callable'];

        // 라우트 파라미터를 인수로 넘겨서 실행
        echo call_user_func_array($callable, $params);
    } else {
        // 1-2. 미들웨어 설정 없이 단순 콜러블인 경우 바로 실행
        $result = call_user_func_array($target, $params);

        // 문자열을 반환했다면 JSON 포맷으로 감싸서 응답
        if (is_string($result)) {
            echo json_response([$result]);
        }
    }
    exit;
}

// /**
//  * 2. $target이 "Controller#method" 형태의 문자열인 경우
//  *    예: "UserController#index"
//  */
// if (is_string($target) && strpos($target, '#') !== false) {

//     // "컨트롤러명#메서드명" 분리
//     [$controllerName, $method] = explode('#', $target, 2);

//     // 완전 수식 클래스명(FQCN) 생성 (네임스페이스 포함)
//     $fqcn = "\\App\\Controllers\\{$controllerName}";

//     // 컨트롤러 인스턴스 생성 후 메서드 실행
//     (new $fqcn())->{$method}(...$params);
//     exit;
// }

/**
 * 3. $target이 배열이며, controller/action/middleware 정보를 따로 담는 방식인 경우
 *    예:
 *    [
 *      'controller' => 'UserController',
 *      'action'     => 'index',
 *      'middleware' => ['login', 'client']
 *    ]
 */
if (is_array($target)) {

    // 컨트롤러, 액션, 미들웨어 정보 분리
    $controllerName = $target['controller'];
    $actionName     = $target['action'];
    $middlewares    = $target['middleware'] ?? [];

    // 미들웨어 실행 (로그인, 권한, 기타 검사)
    run_middlewares($middlewares);

    // 컨트롤러 완전 수식 클래스명
    $fqcn = "\\App\\Controllers\\{$controllerName}";

    // 컨트롤러 인스턴스를 생성하고, 액션 메서드 실행
    (new $fqcn())->{$actionName}(...$params);
    exit;
}

// ---------------------------------------
// 예외: 위의 어떤 경우에도 해당하지 않는 잘못된 라우트 설정
// ---------------------------------------
echo json_response(['error' => 'Bad route target'], 500);
exit;


