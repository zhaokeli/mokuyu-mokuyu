<?php
return [
    'mysql'  => [
        // 必须配置项
        'database_type' => 'mysql',
        'database_name' => $_ENV['test_database_name'],
        'server'        => $_ENV['test_server'],
        'username'      => $_ENV['test_username'],
        'password'      => $_ENV['test_password'],
        'charset'       => 'utf8',
        // 可选参数
        'port'          => 3306,
        'debug_mode'    => false,
        'table_mode'    => 1,
        'debug'         => true,
        // 可选，定义表的前缀
        'prefix'        => $_ENV['test_prefix'],
    ],
    'sqlite' => [
        // 必须配置项
        'database_type' => 'sqlite',
        'database_file' => __DIR__ . '/../test.db',
        'charset'       => 'utf8',
        // 可选，定义表的前缀
        'prefix'        => 'kl_',
    ],
    'cache'  => [
        // 缓存类型为File
        'type'     => 'redis', //目前支持file和memcache redis
        'memcache' => [
            'host' => 'localhost',
            'port' => 11211,
        ],
        'redis'    => [
            'host'     => '127.0.0.1',
            'port'     => 6379,
            'index'    => 7,
            'password' => 'adminrootkl',
        ],
        // 全局缓存有效期（0为永久有效）
        'expire'   => 24 * 3600,
        // 缓存前缀
        'prefix'   => 'mokuyu_db',
        // 缓存目录
        'path'     => '/datacache',
    ],
];