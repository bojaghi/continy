# 유닛 테스트하기

유닛 테스트는 로컬에서 직접 실행하거나 wp-env를 사용해 진행할 수 있습니다.

## 로컬에서 직접 테스트하기

### 테스트 데이터베이스와 테스트 계정 생성

워드프레스 테스트용 데이터베이스가 필요합니다. 보통 로컬에 설치된 MySQL 또는 MariaDB 데이터베이스를 사용하므로,
테스트 사용자, 테스트 데이터베이스를 생성하고 해당 데이터베이스에 모든 권한을 줍니다.

직접 커맨드라인으로 생성하려면, 아래 명령어를 참고하세요.
데이터베이스 프롬프트에서,

```mysql
CREATE USER IF NOT EXISTS 'wordpress_test'@'localhost' IDENTIFIED BY 'wordpress_test';
GRANT ALL PRIVILEGES ON `wordpress_test`.* TO 'wordpress_test'@'localhost';
```

- 데이터베이스 이름: `wordpress_test`
- 데이터베이스 사용자: `wordpress_test`
- 데이터베이스 비밀번호: `wordpress_test`
- 데이터베이스 호스트: `localhost`

조건으로 실행하는 예시입니다.

### 테스트 수트 설치하기

아래 코드를 참고하여 워드프레스 코어와 테스트 수트를 설치합니다.

```shell
chmod +x ./bin/install-wp-tests.sh # 실행 권한을 줍니다.
rm -rf ./tests/wp-core ./tests/wp-tests # 우선 기존에 있을 수도 있는 디렉토리를 완전 삭제합니다.

# 코드 레퍼런스를 받습니다. 아래 두 줄을 모두 복사하여 붙여 넣습니다.
WP_CORE_DIR=./tests/wp-core WP_TESTS_DIR=./tests/wp-tests \
./bin/install-wp-tests.sh wordpress_test wordpress_test wordpress_test localhost
```

### 유닛 테스트 실행하기

아래 세 환경변수를 설정합니다.

- `WP_CORE_DIR`
- `WP_TESTS_DIR`
- `WP_TESTS_PHPUNIT_POLYFILLS_PATH`

예를 들어 아래처럼 유닛 테스트를 할 수 있습니다.

```shell
WP_CORE_DIR=./tests/wp-core \
WP_TESTS_DIR=./tests/wp-tests \
WP_TESTS_PHPUNIT_POLYFILLS_PATH=./vendor/yoast/phpunit-polyfills \
vendor/bin/phpunit
```

## wp-env 사용하기

데이터베이스 서버가 따로 설치되지 않았다면 wp-env를 사용해 테스트를 할 수 있습니다.
wp-env를 설치하고, 프로젝트 루트에서 `wp-env start`를 실행하여 워드프레스 도커를 구동합니다.
워드프레스가 실행하면 다음 명령어로 테스트를 실행할 수 있습니다.

```shell
wp-env run cli --env-cwd=wp-content/continy vendor/bin/phpunit
```
