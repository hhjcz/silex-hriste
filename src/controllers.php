<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

//Request::setTrustedProxies(array('127.0.0.1'));

$app->get('/', function () use ($app)
{
	return $app['twig']->render('index.html', array());
})
	->bind('homepage');

$app->get('/facebook', function () use ($app)
{
	session_start();
	$fbClient = new FacebookApiClient();
	$fbSession = $fbClient->authenticate();
	$loginUrl = '';
	$logoutUrl = '';
	//$loginUrl = $fbClient->fbHelper()->getLoginUrl(['manage_notifications', 'read_mailbox']);
	//$logoutUrl = $fbClient->fbHelper()->getLogoutUrl($fbSession, 'http://localhost:8888/facebook');

	$me = $fbClient->getUserInfo();
	$fbUsername = $me->getName();
	$fbId = $me->getId();
	$messages = $fbClient->getMessages();

	return $app['twig']->render('facebook.html', array(
		'userLoggedIn' => true,
		'loginUrl'     => $loginUrl,
		'logoutUrl'    => $logoutUrl,
		'fbUserName'   => $fbUsername,
		'userLoggedIn' => true,
		'fbId'         => $fbId,
		'messages'     => $messages
	));
});

$app->error(function (\Exception $e, Request $request, $code) use ($app)
{
	if ($app['debug'])
	{
		return;
	}

	// 404.html, or 40x.html, or 4xx.html, or error.html
	$templates = array(
		'errors/' . $code . '.html',
		'errors/' . substr($code, 0, 2) . 'x.html',
		'errors/' . substr($code, 0, 1) . 'xx.html',
		'errors/default.html',
	);

	return new Response($app['twig']->resolveTemplate($templates)->render(array('code' => $code)), $code);
});
