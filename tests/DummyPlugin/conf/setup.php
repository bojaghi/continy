<?php

if (!defined('ABSPATH')) {
    exit;
}

use Bojaghi\Continy\Continy;
use Bojaghi\Continy\Tests\DummyPlugin;

return [
    'main_file' => dirname(__DIR__) . '/dummy-plugin.php',
    'version'   => '1.0.0',

    // Hooks definition
    'hooks'     => [
        'admin_init'              => 0,
        'init'                    => 0,
        'test_action'             => 0,
        'method_call_action'      => 0,
        'callback_with_arguments' => 2,
    ],

    // Objects binding
    'bindings'  => [
        'modCPT'                                                 => DummyPlugin\Modules\CPT::class,
        'reflectionInjectionTester'                              => DummyPlugin\ReflectionInjection\ReflectionTester::class,
        // IDummy implementation
        DummyPlugin\Dummies\IDummy::class                        => DummyPlugin\Dummies\DummyTypeOne::class,
        DummyPlugin\ReflectionInjection\IDependencyTwoOne::class => DummyPlugin\ReflectionInjection\DependencyTwoOne::class,
        // Support binding
        'ds'                                                     => DummyPlugin\Supports\DummySupport::class,

        // aliasedModule: continy->get() method should successfully grab the original class name by alias.
        'aliasedModule'                                          => DummyPlugin\Modules\AliasedModule::class,

        // Method call test
        'methodCall'                                             => DummyPlugin\Modules\MethodCallModule::class,

        // Callback with arguments test
        'callback-with-arguments'                                => DummyPlugin\Modules\CallbackWithArguments::class,
    ],

    // Modules setting
    'modules'   => [
        '_'                  => [
            DummyPlugin\Modules\_FQCNModule::class,
        ],
        'init'               => [
            Continy::PR_DEFAULT => [
                'modCPT',
            ],
        ],
        'method_call_action' => [
            Continy::PR_DEFAULT => [
                'methodCall@testMethod', // Method call test
            ],
        ],
        'callback_with_arguments' => [
            Continy::PR_DEFAULT => [
                'callback-with-arguments',
            ],
        ]
    ],

    // Argument injection
    'arguments' => [
        'modCPT'                                => [
            'foo' => 20,
        ],
        DummyPlugin\Dummies\IDummy::class       => [
            'dummy' => 'interface',
        ],
        DummyPlugin\Dummies\DummyTypeTwo::class => function () {
            return [
                'dummy' => 'type-two',
            ];
        },
        'ds'                                    => [
            'foo' => 20,
        ],

        // FunctionalCall
        DummyPlugin\FunctionCall\FunctionCall::class .
        '::' .
        'configuredCall'                        => [
            'x' => 'Keyboard',
            'y' => 'Mouse',
        ],

        // Incomplete arguments
        DummyPlugin\IncompleteTester::class     => [3],

        // Aliased
        'aliasedModule'                         => ['success'],
    ],
];
