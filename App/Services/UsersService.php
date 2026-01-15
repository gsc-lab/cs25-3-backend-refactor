<?php

namespace App\Services;

use App\Repository\UsersRepository;
use App\Exceptions\NoChangesException;
use mysqli;

class UsersService {

    // UsersRepository인스턴스 멘버 변수
    private UsersRepository $repo;

    /**
     * 생성자
     * UserRepository를 생성해 DB접속을 전달한다
     * @param mysqli $db DB접속 dbject
     */
    public function __construct(mysqli $db)
    {
        $this->repo = new UsersRepository($db);
    }


    /**
     * 회원 정보 단일 조회
     * @return ?array 데이터가 있으면 배열으로, 없으면 null 반환
     */
    public function showService(
        int $userId
    ): ?array{

        // UsersRepository show메서드에 접속
        $result = $this->repo->show($userId);

        return $result;
    }


    /**
     * account 중복 검사
     * @param string $account ID
     * @return bool  존재의 유무를 반환
     */
    public function accountChackService(
        string $account
    ) : bool {
        
        $result = $this->repo->accountCheck($account);
        
        return $result;
    }


    /**
     * 회원 가입
     * @param string $account account ID
     * @param string $passwordHash 비밀 번호
     * @param string $userName 사용자 이름
     * @param string $role 역할
     * @param string $gender 성별
     * @param string $phone 전화번호
     * @param string $birth 생일
     * @return bool 성공 여부를 반환
     */
    public function createService(
        string $account,
        string $passwordHash,
        string $userName,
        string $role,
        string $gender,
        string $phone,
        string $birth
    ):bool {

        $result = $this->repo->create($account, $passwordHash, $userName,
                             $role, $gender, $phone, $birth);
        
        return $result;
    }


    /**
     * 회원 정보 수정 (UPDATE)
     * @param int $userId PRYMARY KEY
     * @param string $account account ID
     * @param string $passwordHash 비밀번호
     * @param string $userName 사용자 이름
     * @param string $phone 전화 번호
     * @throws NoChangesException 수정된 내용이 없었을 때 오류 메시지
     * @return int 수정된 내용의 유무를 반환
     */
    public function updateService(
        int $userId,
        string $account,
        string $passwordHash,
        string $userName,
        string $phone
    ):int {

        
        $result = $this->repo->update($userId, $account, 
                                $passwordHash, $userName, $phone);
        if ($result === 1) {
            throw new NoChangesException('수정된 내용이 없습니다.');
        }

        return $result;
    }


    /**
     * 회원 탈퇴 (DELETE)
     * @param int $userId PRYMARY KEY
     * @return int  삭제된 내용의 유무를 반환
     */
    public function deleteService(
        int $userId
    ):int {
        
        $result = $this->repo->delete($userId);

        return $result;
    }


    /**
     * 로그인 (SELECT)
     * @param string $account account ID
     * @param string $role 역할
     * @return array|null account ID가 존재하지않음 -> null , 존재함 -> 배열 반환 
     */
    public function loginService(
        string $account,
        string $role
    ):?array{

        $result = $this->repo->login($account, $role);

        // 처리 종류 , null를 반환
        if ($result === 0) {
            return null;
        }

        return $result;
    }

}