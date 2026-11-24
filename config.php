<?php
// Local, untracked config is useful for development; environment values take precedence.
$config = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'database' => 'mavambo_school',
    'username' => 'root',
    'password' => '',
];
$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) $config = array_merge($config, require $localConfig);
foreach (['host'=>'SCHOOL_DB_HOST','port'=>'SCHOOL_DB_PORT','database'=>'SCHOOL_DB_NAME','username'=>'SCHOOL_DB_USER','password'=>'SCHOOL_DB_PASSWORD'] as $key=>$variable) {
    $value = getenv($variable);
    if ($value !== false) $config[$key] = $value;
}
return $config;
