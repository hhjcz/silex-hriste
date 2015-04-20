<?php

// configure your app for the production environment

$app['twig.path'] = array(__DIR__.'/../templates');
// hhj: disable cache even in prod env:
//$app['twig.options'] = array('cache' => __DIR__.'/../var/cache/twig');

require __DIR__ . '/../env.php';