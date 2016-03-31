<?php

use Silex\Provider\MonologServiceProvider;

// configure your app for the production environment

$app['twig.path'] = array(__DIR__.'/../templates');
// hhj: disable cache even in prod env:
//$app['twig.options'] = array('cache' => __DIR__.'/../var/cache/twig');

$app['fb_api_key'] = getenv('fb_api_key');
$app['fb_api_secret'] = getenv('fb_api_secret');
$app['fb_redirect_login_url'] = getenv('fb_redirect_login_url');

$envFile = __DIR__ . '/../env.php';
if(file_exists($envFile)) include $envFile;

$app->register(new MonologServiceProvider(), array(
	'monolog.logfile' => __DIR__.'/../var/logs/silex_dev.log',
));
