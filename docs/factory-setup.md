# Factory 설정법

`Continy\Factory`는 Continy를 초기화하는 클래스입니다.
인자로 하나의 문자열, 또는 설정 배열을 받을 수 있습니다.

문자열일 경우 설정 배열 배열을 리턴하는 파일의 경로를 의미합니다.
또한 설정 배열은 PHP 배열로, continy 설정 배열의 형식을 따릅니다.

## 설정 배열을 사용

설정 배열을 직접 입력하는 경우 아래와 같이 작성합니다.

```php
$continy = Bojaghi\Continy\Factory::create( array(
    'main_file' => __FILE__,
    'version'   => '1.0.0',
    // ...
) );
```

## 문자열을 사용

문자열의 경우는 아래처럼 코드를 작성합니다.

```php
$continy = Bojaghi\Continy\Factory::create( __DIR__ . '/conf/setup.php' );
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
            'as'   => Baz::class,                // 필수
            'args' => array('x' => 8, 'y' => 3), // 배열을 리턴
        ),
        'buf'       => array(
            'as'    => Buf::class, // 필수
            'args'  => function ( Continy $continy, string $id, array $item ) {
                // item has 'when', 'as', 'reuse', 'args' (recursive)
                return [];
            },
            'reuse' => false,                                      // 호출시 매번 인스턴스를 새로 생성, 캐싱되지 않음
        ),
        'cond'      => array(
           // 조건적 바인딩
           array(
               'when' => A::class,                 // A 클래스가 요구하는 경우
               'as'   => Cond_For_A::class,        // Cond_For_A 클래스를 사용
               'args' => array( 'value' => 'x' ),  // A 클래스에 전달할 생성자
           ),
           array(
               'when' => B::class,                 // B 클래스가 요구하는 경우
               'as'   => Cond_For_B::class,        // Cond_For_B 클래스를 사용
               'args' => array( 'value' => 'y' ),  // B 클래스에 전달할 생성자
           ),
           array(
               'as'   => Cond_Fallback::class,     // A, B 클래스 이외의 경우
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

## 설정 배열 설명

### `main_file`

문자열입니다. 플러그인 또는 테마의 메인 파일의 절대 경로입니다.
플러그인의 경우 플러그인 헤더가 있는 PHP 파일입니다.
테마의 경우 테마 헤더가 있는 style.css 파일입니다.

이 키의 값을 필수로 입력해야 합니다.

### `version`

문자열입니다. 버전을 기록합니다.

이 키의 값을 필수로 입력해야 합니다.

### `bindings`

연관 배열입니다. 객체를 고유한 식별자로 매핑하고 나중에 해당 식별자를 이용해 객체의 인스턴스를
생성하고 가져올 수 있습니다.

키는 컨테이너가 인스턴스를 구분하기 위한 식별자로 사용됩니다. 프로그램 내부에서 인스턴스를 얻기 위한 꼬리표가 됩니다.
값으로 문자열, 또는 배열을 허용합니다.

문자열 타입 값은 FQCN, 또는 상수 문자열일 수 있습니다.

연관 배열 타입 값 (바인딩된 배열)은 아래 키를 가질 수 있습니다.

- `as`: FQCN, 또는 호출 가능한 타입입니다.
- `args`: 객체를 인스턴스화 시킬 때의 생성자의 파라미터, 또는 호출 가능한 객체의 파라미터와 대응됩니다. 다음 형태로 입력 가능합니다.
    - 배열
        - 배열은 생성자 파라미터에 대응됩니다.
        - 연관 배열은 인수와 키 이름으로 대응합니다.
        - 순차 배열은 순서대로 생성자의 인수와 대응됩니다.
    - 콜백. 콜백 함수는 3개의 인자를 가집니다.
        - 첫번째 인자는 Continy 인스턴스입니다.
        - 두번째 인수는 바인딩하는 식별자 id 입니다.
        - 세번째 인수는 바인딩된 배열입니다.
        - 반드시 배열을 리턴해야 합니다.
- `when`: 어떤 객체가 이 식별자의 인스턴스를 요청하는지 조건적으로 대응할 수 있습니다.
- `reuse`: 기본값은 true지만, false로 입력할 경우, 매번 새롭게 인스턴스를 생성합니다.
- `value`: `as`는 FQCN로서 인스턴스화, 혹은 호출 가능한 객체로서 호출되는대 비해,
  `value`는 단지 상수로서 취급됩니다. 이 키가 사용되면 'args', 'when' 키는 무효 처리되며, 'reuse' 또한 true로 고정됩니다.

### `modules`

모듈은 워드프레스 플러그인, 테마 개발에 유용한 콤포넌트입니다.
모듈별로 기능을 적절히 구분하여 관리하는 것이 가능합니다.

컨테이너를 시동하게 되면 `do_action()` 함수가 실행될 때, 지정한 액션이 되는 시점에
콜백으로서 등록한 모든 모듈을 인스턴스화 시켜줍니다.

이 연관 배열의 키는 액션의 훅 이름으로 사용됩니다.
이 연관 배열의 값은 재차 연관 배열 타입이며 다음 키를 허용합니다.

- `accepted_args`: do_action () 함수의 'accepted_args' 인수에 사용됩니다. 생략하면 1입니다.
- 정수:
    - do_action () 함수의 'priority' 인수에 대응됩니다.
    - 이 키의 값은 순차 배열입니다.
    - 호출할 모듈의 식별자를 나열합니다.

단, 키 중 '_' (언더스코어)는 특별한 의미를 가집니다.
플러그인이 로딩되는 시점에 바로 인스턴스화 되는 모듈을 지정하기 위해 사용됩니다.
이 키의 값은 연관 배열이 아닌, **순차 배열**입니다.
