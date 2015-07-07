<?php

use Oris\OrisApiClient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

//Request::setTrustedProxies(array('127.0.0.1'));

$app->get('/', function () use ($app)
{
	return $app->redirect('/facebook');
	//return $app['twig']->render('index.html', array());
})
	->bind('homepage');

// facebook login middleware:
$facebookLogin = function (Request $request, Silex\Application $app)
{
	session_start();
	/** @var \FacebookClient\FacebookApiClient $fbClient */
	//$fbClient = new Facebook\FacebookApiClient($app);
	//$fbSession = $fbClient->authenticate($app['fb_api_key'], $app['fb_api_secret'], $app['fb_redirect_login_url']);
	$fbClient = $app['facebook_api_client'];
	if ($fbClient->session())
	{
		return;
	} else
	{
		return new RedirectResponse('/facebook/login');
	}
};

$app->get('/facebook/login', 'FacebookController::login');

$app->get('/facebook/thread/{threadId}/count', 'FacebookController::countThread')->before($facebookLogin);

$app->get('/facebook/thread/{threadId}/message/{messageId}', 'FacebookController::showMessage')->before($facebookLogin);

$app->get('/facebook/thread/{threadId}/images', 'FacebookController::showThreadImages')->before($facebookLogin);

$app->get('/facebook/thread/{threadId}', 'FacebookController::showThread')->before($facebookLogin);

$app->get('/facebook/notifications', 'FacebookController::showNotifications')->before($facebookLogin);

$app->get('/facebook', 'FacebookController::showInbox')->before($facebookLogin);


/* ORIS */

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
