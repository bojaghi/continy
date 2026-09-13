# Continy 사용법

Continy를 초기화하려면 ContinyFactory 클래스를 사용합니다.
설정법에 대해서는 [how-to-setup-continy-factory.md](./how-to-setup-continy-factory.md)를 참고하세요.

## 객체 생성

### get () 메소드 사용

가장 기본적인 사용법입니다. 아래처럼 사용 가능합니다.

```php
$continy->get( 'id' );
```

인자로 미리 바인딩한 ID 또는 클래스 이름을 넣으면 됩니다. 클래스 생성시 생성자의 인자는 Continy가 최대한 추론합니다.
그리고 한 번 만들어진 객체는 재사용됩니다.

### instantiate () 메소드 사용

`instantiate()`는 `get()`보다 좀 더 기능을 갖춘 메소드입니다.

첫번째 인자는 찾아올 id나 클래스 이름을 넣을 수 있습니다. `get()`에 들어가는 인자와 동일한 역할입니다.

두번째 인자에는 이 객체를 직접 생성하는 함수를 입력합니다. 아래의 예처럼 사용합니다.

```php
$continy->instantiate( 'id', function ( string $id, string $class_name, Continy $continy) {
    return new $class_name();
} );
```

콜백 함수에서는 원하는 객체를 완전히 생성하여 리턴해야 합니다. 두번째 인자를 기본값인 `null`로 두면,
Continy가 최대한 생성자 파라미터를 추론합니다.

세번째 인자는 이 객체를 Continy에서 재사용할지를 결정합니다. 바인딩시 설정한 내용보다 우선합니다.

## 객체 확인

Continy가 객체를 생성하여 가지고 있는지를 확인하는 메소드는 `has` 입니다.

```php
$continy->has( 'id' );
```

해당 객체를 생성한 적이 있다면 참, 아니면 거짓을 리턴합니다.

## 객체 지우기

Continy 내부에 캐싱된 객체를 명시적으로 삭제하기 위해 사용합니다.

```php
$continy->drop( 'id' );
```

문자열인 아이디로 삭제하든, 실제 클래스 이름으로 삭제하든 관계없이 삭제 처리됩니다.
예를 들어 'foo' 문자열을 `class Foo_Class {}`에 바인딩 한 후에는 foo와 Foo_Class는 대등한 관계를 가집니다.
그러므로 아래처럼 동작합니다.

```php
$continy->get( 'foo' );
$continy->has( 'foo' );       // true
$continy->has( 'Foo_Class' ); // true

$continy->drop( 'foo' ); 
$continy->has( 'foo' );       // false
$continy->has( 'Foo_Class' ); // false

$continy->get( 'Foo_Class' );
$continy->has( 'foo' );       // true
$continy->has( 'Foo_Class' ); // true

$continy->drop( 'Foo_Class' ); 
$continy->has( 'foo' );       // false
$continy->has( 'Foo_Class' ); // false
```

## 콜백 해석

`parse_callback()` 메소드는 1개의 인수를 가집니다.
이것을 사용하면 콜백 함수를 만들기 위해 일일이 코드에서 인스턴스를 생성하지 않고, Continy에 객체를 예약해 두고 필요할 때 불러낼 수 있습니다.

인자로 이미 콜백으로 호출 가능한 것도 전달 가능합니다. 그리고 '@'를 사용한 특수한 표기를 콜백으로 해석할 수 있습니다.
형색은 다음과 같습니다.

`<continy-id>@<method_name>`

가령 Foo_Class가 아래처럼 되어 있다고 합니다.

```php
class Foo_Class {
   public function bar() { ... }
}
```

그리고 아래처럼 매핑되었다고 합니다.

```php
$continy = Continy_Factory(
    array(
        'bindings' =>array(
            'foo' => array(
                'as' => 'Foo_Class',
            ),
        )
    )
);
```

그러면 아래와 같은 호출은,

```php
add_action( 'init', $continy->parse_callback( 'foo@bar' ) );
```

아래의 결과와 동일하게 됩니다.

```php
$foo = new Foo_Class();
add_action( 'init', array( $foo, 'bar' ) );
```

객체를 일일이 생성하거나, 의존성에 대해 생각하지 않아도 되어 편리합니다.

## 함수, 메소드 호출

`call()` 메소트 호출을 통해 함수나 메소드에 대해서도 파라미터에 대한 의존성 주입을 할 수 있습니다.

의존성을 주입하기 어려운 값인 경우 미리 두번째 인자에 배열 형태, 또는 클백 함수 형태로 전달할 수 있습니다.
콜백이나 배열이 아니면 해당 요소를 가지고 길이 1인 배열을 만듭니다.
그리고 콜백 함수는 반드시 배열을 리턴해야 합니다.
그리고 콜백 함수는 첫번째 인자로 `call()`의 첫번째 인자를 그대로 전달하며, 두번째 인자로 Continy 인스턴스를 전달합니다.

또한 첫번째 인자로 `parse()`로 해석 가능한 문자열도 지원합니다.
그러므로 앞서 예와 같이 Foo_Class가 foo에 바인딩 되어 있다면 아래처럼 사용 가능하며,

```php
add_action( 'init', fn() => $continy->call( 'foo@bar' ) );
```

이는 아래의 결과와 동일합니다.

```php
$foo = new Foo_Class();
add_action( 'init', array( $foo, 'bar' ) );
```

또한 아래와 같이 함수가 작성되었다고 한다면,

```php
function baf( Component_A $a, Component_B $b ) {
    ...
}
```

Continy에 Component_A, Component_B 등이 미리 생성되어 있거나,
자동으로 생성할 수 있는 조건이 갖추어져 있다면 이렇게 간단하게 호출이 가능합니다.

```php
$continy->call( 'baf' )
```

함수의 인자를 Continy가 알아서 맞추어 줍니다.
한편 Continy가 호출 시점에 값을 자동으로 결정할 수 없거나,  
Continy가 가진 값과는 다른 값으로 대신하고 싶다면 아래처럼 인자를 명시적으로 지정할 수도 있습니다.

```php
$new_b = new Component_B( ... );

$continy->call( 'baf', array( 'b' => $new_b ) );
```
