<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

//Request::setTrustedProxies(array('127.0.0.1'));

$app->get('/', function () use ($app)
{
	return $app->redirect('/facebook');
	//return $app['twig']->render('index.html', array());
})
	->bind('homepage');

$app->get('/facebook', function () use ($app)
{
	$fbUsername = '';
	$fbId = '';
	$messages = ['unread' => 0, 'unseen' => 0, 'threads' => []];
	session_start();
	$fbClient = new FacebookApiClient();
	$fbSession = $fbClient->authenticate($app['fb_api_key'], $app['fb_api_secret'], $app['fb_redirect_login_url']);
	if ($fbSession)
	{
		$loginUrl = '';
		$logoutUrl = $fbClient->fbHelper()->getLogoutUrl($fbSession, $app['fb_redirect_login_url'] . '?logout');
	} else
	{
		$loginUrl = $fbClient->fbHelper()->getLoginUrl(['manage_notifications', 'read_mailbox']);
		$logoutUrl = '';
		return $app['twig']->render('facebook.html', array(
			'userLoggedIn' => false,
			'loginUrl'     => $loginUrl,
			'logoutUrl'    => $logoutUrl,
			'fbUserName'   => $fbUsername,
			'fbId'         => $fbId,
			'messages'     => $messages
		));
	}

	$me = $fbClient->getUserInfo();
	$fbUsername = $me->getName();
	$fbId = $me->getId();
	$messages = $fbClient->getMessages();

	return $app['twig']->render('facebook.html', array(
		'userLoggedIn' => true,
		'loginUrl'     => $loginUrl,
		'logoutUrl'    => $logoutUrl,
		'fbUserName'   => $fbUsername,
		'fbId'         => $fbId,
		'messages'     => $messages
	));
});

//$app->get('{url}', function () use ($app)
//{
//	return $app->redirect('/angular');
//})->assert('url', '.+');

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
