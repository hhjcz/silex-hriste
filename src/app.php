<?php

use Silex\Application;
use Silex\Provider\HttpFragmentServiceProvider;
use Silex\Provider\RoutingServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\ValidatorServiceProvider;

$app = new Application();
$app->register(new RoutingServiceProvider());
$app->register(new ValidatorServiceProvider());
$app->register(new ServiceControllerServiceProvider());
$app->register(new TwigServiceProvider());
$app->register(new HttpFragmentServiceProvider());
$app['twig'] = $app->extend('twig', function ($twig, $app)
{
	// add custom globals, filters, tags, ...

	$twig->addFunction(new \Twig_SimpleFunction('asset', function ($asset) use ($app)
	{
		return $app['request_stack']->getMasterRequest()->getBasepath() . '/' . $asset;
	}));

	return $twig;
});
// hhj:
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
	'db.options' => array(
		'driver' => 'pdo_sqlite',
		'path'   => __DIR__ . '../../var/db/hhj_sqlite.db',
	),
));

return $app;
