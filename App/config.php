<?php

// 데이터베이스 정보 상수 정의
define('DB_HOST', 'db');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'backend');

// mysqli 예외 모드 설정
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

?>