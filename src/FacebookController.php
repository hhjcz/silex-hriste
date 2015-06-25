<?php

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

/**
 *
 * @author jan.haering@dialtelecom.cz
 */
class FacebookController {

	public function showInbox(Request $request, Application $app)
	{
		$fbClient = $app['facebook_api_client'];

		$me = $fbClient->getUserInfo();
		$fbUsername = $me->getName();
		$fbId = $me->getId();
		$messages = $fbClient->getMessages($limit = 5);
		$logoutUrl = $fbClient->getLogoutUrl();

		return $app['twig']->render('facebook.twig', array(
			'userLoggedIn'    => true,
			'logoutUrl'       => $logoutUrl,
			'fbUserName'      => $fbUsername,
			'fbId'            => $fbId,
			'showAllMessages' => (bool) $request->query->get('showAllMessages') == 'true' ? true : false,
			'messages'        => $messages
		));

	}

	public function showThread(Request $request, Application $app, $threadId)
	{
		$fbClient = $app['facebook_api_client'];

		$paging = $this->parsePaging($request);

		$me = $fbClient->getUserInfo();
		$thread = $fbClient->getThread($threadId, $paging);
		$fbUsername = $me->getName();
		$fbId = $me->getId();
		$logoutUrl = $fbClient->getLogoutUrl();

		return $app['twig']->render('facebook-thread.twig', array(
			'logoutUrl'       => $logoutUrl,
			'fbUserName'      => $fbUsername,
			'fbId'            => $fbId,
			'showAllMessages' => (bool) $request->query->get('showAllMessages') == 'true' ? true : false,
			'thread'          => $thread
		));
	}

	public function countThread(Request $request, Application $app, $threadId)
	{
		$fbClient = $app['facebook_api_client'];

		$messageCount = $fbClient->countThread($threadId);
		$logoutUrl = $fbClient->getLogoutUrl();

		return $app['twig']->render('facebook-thread-count.twig', array(
			'logoutUrl'  => $logoutUrl,
			'threadId'   => $threadId,
			'totalCount' => $messageCount['totalCount'],
			'newCount'   => $messageCount['newCount'],
			'wordsCount' => $messageCount['wordsCount'],
			'charsCount' => $messageCount['charsCount'],
		));
	}

	private function parsePaging(Request $request)
	{
		$paging['since'] = $request->query->get('since') ?: null;
		$paging['until'] = $request->query->get('until') ?: null;
		$paging['__previous'] = $request->query->get('__previous') ?: null;
		$paging['limit'] = $request->query->get('limit') ?: 30;
		$paging['__paging_token'] = $request->query->get('__paging_token') ?: null;

		return $paging;
	}

	/**
	 * @param Application $app
	 * @return FacebookApiClient
	 * @throws Exception
	 * @throws \Facebook\FacebookRequestException
	 * @throws \Facebook\FacebookSDKException
	 */
	public function login(Application $app)
	{
		session_start();
		$fbClient = $app['facebook_api_client'];
		$loginUrl = $fbClient->fbHelper()->getLoginUrl(['manage_notifications', 'read_mailbox']);

		return $app['twig']->render('facebook-login.twig', array(
			'loginUrl' => $loginUrl,
		));
	}
}