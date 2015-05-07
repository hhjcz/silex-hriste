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

$app->get('/facebook/thread/{threadId}', function (Request $request, $threadId) use ($app)
{
	session_start();
	$fbClient = new FacebookApiClient();
	$fbSession = $fbClient->authenticate($app['fb_api_key'], $app['fb_api_secret'], $app['fb_redirect_login_url']);
	if ($fbSession)
	{
		$logoutUrl = $fbClient->fbHelper()->getLogoutUrl($fbSession, $app['fb_redirect_login_url'] . '?logout');
	} else
	{
		$loginUrl = $fbClient->fbHelper()->getLoginUrl(['manage_notifications', 'read_mailbox']);
		return $app['twig']->render('facebook-login.twig', array(
			'loginUrl' => $loginUrl,
		));
	}

	$me = $fbClient->getUserInfo();
	$thread = $fbClient->getThread($threadId, $limit = 500);
	$fbUsername = $me->getName();
	$fbId = $me->getId();

	return $app['twig']->render('facebook-thread.twig', array(
		'logoutUrl'       => $logoutUrl,
		'fbUserName'      => $fbUsername,
		'fbId'            => $fbId,
		'showAllMessages' => (bool) $request->query->get('showAllMessages') == 'true' ? true : false,
		'thread'          => $thread
	));
});

$app->get('/facebook', function (Request $request) use ($app)
{
	// TODO - refactor as login() function of better route filter (if silex supports it)
	session_start();
	$fbClient = new FacebookApiClient();
	$fbSession = $fbClient->authenticate($app['fb_api_key'], $app['fb_api_secret'], $app['fb_redirect_login_url']);
	if ($fbSession)
	{
		$logoutUrl = $fbClient->fbHelper()->getLogoutUrl($fbSession, $app['fb_redirect_login_url'] . '?logout');
	} else
	{
		$loginUrl = $fbClient->fbHelper()->getLoginUrl(['manage_notifications', 'read_mailbox']);
		return $app['twig']->render('facebook-login.twig', array(
			'loginUrl' => $loginUrl,
		));
	}

	$me = $fbClient->getUserInfo();
	$fbUsername = $me->getName();
	$fbId = $me->getId();
	$messages = $fbClient->getMessages($limit = 5);

	return $app['twig']->render('facebook.twig', array(
		'userLoggedIn'    => true,
		'logoutUrl'       => $logoutUrl,
		'fbUserName'      => $fbUsername,
		'fbId'            => $fbId,
		'showAllMessages' => (bool) $request->query->get('showAllMessages') == 'true' ? true : false,
		'messages'        => $messages
	));
});

$app->get('/oris/calendar.ics', function (Request $request) use ($app)
{
	$orisClient = new OrisApiClient();
	$events = $orisClient->getUserEventEntries();

	$content = $app['twig']->render('calendar.twig', ['events' => $events]);

	//set correct content-type-header
	$response = new Response($content, 200);
	$response->headers->set('Content-type', 'text/calendar; charset=utf-8');
	$response->headers->set('Content-Disposition', 'inline; filename=calendar.ics');

	return $response;
});

//$app->get('{url}', function () use ($app)
//{
//	return $app->redirect('/angular');
//})->assert('url', '.+');

$app->get('/elasticbuttons', function (Request $request) use ($app)
{
	return $app['twig']->render('elasticbuttons.twig');
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
