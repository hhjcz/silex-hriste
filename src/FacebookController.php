<?php

use Facebook\FacebookRequest;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

/**
 *
 * @author jan.haering@dialtelecom.cz
 */
class FacebookController {

	public function showThread(Request $request, Application $app, $threadId)
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

		//dump($request);
		$paging = $this->getPaging($request);

		$me = $fbClient->getUserInfo();
		$thread = $fbClient->getThread($threadId, $paging);
		//dump($thread);
		$fbUsername = $me->getName();
		$fbId = $me->getId();

		return $app['twig']->render('facebook-thread.twig', array(
			'logoutUrl'       => $logoutUrl,
			'fbUserName'      => $fbUsername,
			'fbId'            => $fbId,
			'showAllMessages' => (bool) $request->query->get('showAllMessages') == 'true' ? true : false,
			'thread'          => $thread
		));
	}

	public function showInbox(Request $request, Application $app)
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

	}

	private function getPaging(Request $request)
	{
		$paging['since'] = $request->query->get('since') ?: null;
		$paging['until'] = $request->query->get('until') ?: null;
		$paging['__previous'] = $request->query->get('__previous') ?: null;
		$paging['limit'] = $request->query->get('limit') ?: 30;
		$paging['__paging_token'] = $request->query->get('__paging_token') ?: null;

		return $paging;
	}
}