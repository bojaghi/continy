# 개발 셋업

## 필요 사항

- PHP 8.3 이상
- composer
- WP-CLI
- Subversion

## 셋업하기

프로젝트 루트에서 아래 쉘 스크립트를 실행

```shell
WP_TESTS_DIR=./tests/wp-tests \
WP_CORE_DIR=./tests/wp-core \
./bin/install-wp-tests.sh db_name db_user db_pass localhost latest true
```

test/wp-core, test/wp-tests 디렉토리에 각각 워드프레스 코어와 테스트 수트가 설치됩니다.
