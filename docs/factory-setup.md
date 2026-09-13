# Continy_Factory 설정법

Continy_Factory는 Continy를 초기화하는 클래스입니다.
인자로 하나의 문자열, 또는 설정 배열을 받을 수 있습니다.

문자열일 경우 설정 배열 배열을 리턴하는 파일의 경로를 의미합니다.
또한 설정 배열은 PHP 배열로, continy 설정 배열의 형식을 따릅니다.

## 설정 배열을 사용

설정 배열을 직접 입력하는 경우 아래와 같이 작성합니다.

```php
$continy = Bojaghi\Continy\Continy_Factory::create( array(
    'main_file' => __FILE__,
    'version'   => '1.0.0',
    // ...
) );
```

## 문자열을 사용

문자열의 경우는 아래처럼 코드를 작성합니다.

```php
$continy = Bojaghi\Continy\Continy_Factory::create( __DIR__ . '/conf/setup.php' );
```

여기서 /conf/setup.php의 내부는 아래와 같이 작성할 수 있습니다.

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'main_file' => MAIN_FILE,
    'version'   => '1.0.0',
    // ... 
);
```

## 설정 배열의 예시

아래는 설정의 예시입니다.

```php
/**
 * 설정 파일의 예시
 */
if (!defined('ABSPATH')) {
    exit;
}

return array(
    'main_file' => dirname(__DIR__) . '/index.php', // 필수. 플러그인, 테마의 메인 파일
    'version'   => '1.0.0',                         // 필수. 플러그인, 테마의 버전
    'bindings' => array(
        'foo'       => Foo::class,     // 단순 바인딩
        IBar::class => BarImpl::class, // 인터페이스 - 구현
        'baz'       => array(
            'fqcn' => Baz::class,                // 필수
            'args' => array('x' => 8, 'y' => 3), // 배열을 리턴
        ),
        'buf'       => array(
            'fqcn'  => Buf::class,                                 // 필수
            'args'  => function (Continy $continy) { return []; }, // 익명 함수를 통해 배열을 리턴
            'reuse' => false,                                      // 호출시 매번 인스턴스를 새로 생성, 캐싱되지 않음
        ),
        'cond'      => array(
           // 조건적 바인딩
           array(
               'when' => A::class,                 // A 클래스가 요구하는 경우
               'fqcn' => Cond_For_A::class,        // Cond_For_A 클래스를 사용
               'args' => array( 'value' => 'x' ),  // A 클래스에 전달할 생성자
           ),
           array(
               'when' => B::class,                 // B 클래스가 요구하는 경우
               'fqcn' => Cond_For_B::class,        // Cond_For_B 클래스를 사용
               'args' => array( 'value' => 'y' ),  // B 클래스에 전달할 생성자
           ),
           array(
               'fqcn' => Cond_Fallback::class,     // A, B 클래스 이외의 경우
               'args' => array( 'value' => 'z' ),
           ),
        ),
    ),   
    'modules' => array(
        // '_' 키는 액션 콜백에 사용되는 모듈이 아닌, 플러그인 실행 시점에 바로 생성되는 모듈을 선언하기 위해 사용합니다.
        '_' => array(
            'foo',
            IBar::class, // IBar::class 의 실제 구현인 BarImpl::class 객체가 생성될 것입니다.
        ),
        // 액션의 훅 이름들입니다. 해당 액션이 동작할 때 Continy가 바인딩된 객체를 생성할 것입니다.
        'init' => array(
            // 모듈 우선순위입니다. 
            Continy::PR_DEFAULT => array(
                // 모듈 목록
                Module::class,             // 'bindings' 항목에서 언급하지 않은 클래스 FQCN을 직접 집어 넣어도 됩니다.
                'baz',                     // 이미 선언된 항목은 물론 가능합니다.
                'cls@method',              // 콜백으로 파싱 가능한 문자열도 전달할 수 있습니다. 
                function () { /* ... */ }, // 직접 함수로도 구현 가능합니다
            ),
        ),
        'my_init_hook' => array(
            'accepted_args' => 3, // add_action()의 $accepted_args 기본값은 '1'이지만, 그렇지 않은 경우 맞춰 주어야 합니다.
            Continy::PR_LOW => array( /* ... */ ),
        ),
    ),
];
```

### 의존성 주입 예시

Continy 객체를 얻기 우해서는 `get()` 메소드를 사용합니다.

메소드 또는 함수 호출에 의존성 주입을 위해 `call()` 메소드를 사용합니다.
