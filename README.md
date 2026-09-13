# Continy

워드프레스 플러그인과 테마 개발을 위한 아주 작고 간단한 컨테이너입니다.

## 요구사항

* PHP 8.3 이상

## 설치

워드프레스 플러그인에서 composer 패키지 매니저를 사용하여 설치합니다.

```bash
composer require bojaghi/continy
```

## 빠른 시작

ContinyFactory에 설정을 담은 배열이 있는 파일의 경로,
또는 설정 배열을 인자로 집어 넣으면 됩니다.

```php
$continy = Bojaghi\Continy\Continy_Factory::create( __DIR__ . '/conf/setup.php' );
// 또는,
$continy = Bojaghi\Continy\Continy_Factory::create( array( /* ... 설정 배열 ... */ ) );
```

보다 상세한 사용법은 [팩터리 설정](./docs/factory-setup.md)를 참조하세요.
