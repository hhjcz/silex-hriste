<?php

use Facebook\FacebookPermissions;
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

		$paging = $this->parsePaging($request, 5);

		//$me = $fbClient->getUserInfo();
		//$fbUsername = $me->getName();
		//$fbId = $me->getId();
		$inbox = $fbClient->getInbox($paging);
		$logoutUrl = $fbClient->getLogoutUrl();

		return $app['twig']->render('facebook-inbox.twig', array(
			//'userLoggedIn'    => true,
			'logoutUrl'       => $logoutUrl,
			//'fbUserName'      => $fbUsername,
			//'fbId'            => $fbId,
			'showAllMessages' => (bool) $request->query->get('showAllMessages') == 'true' ? true : false,
			'inbox'           => $inbox
		));

	}

	public function showThread(Request $request, Application $app, $threadId)
	{
		$fbClient = $app['facebook_api_client'];

		$paging = $this->parsePaging($request, 30);

		//$me = $fbClient->getUserInfo();
		//$fbUsername = $me->getName();
		//$fbId = $me->getId();
		$thread = $fbClient->getThread($threadId, $paging);
		$logoutUrl = $fbClient->getLogoutUrl();

		return $app['twig']->render('facebook-thread.twig', array(
			'logoutUrl'       => $logoutUrl,
			//'fbUserName'      => $fbUsername,
			//'fbId'            => $fbId,
			'showAllMessages' => (bool) $request->query->get('showAllMessages') == 'true' ? true : false,
			'thread'          => $thread
		));
	}

	public function showMessage(Request $request, Application $app, $threadId, $messageId)
	{
		$fbClient = $app['facebook_api_client'];

		//$me = $fbClient->getUserInfo();
		//$fbUsername = $me->getName();
		//$fbId = $me->getId();
		$message = $fbClient->getMessage($messageId);
		$logoutUrl = $fbClient->getLogoutUrl();

		return $app['twig']->render('facebook-message.twig', array(
			'logoutUrl' => $logoutUrl,
			//'fbUserName'      => $fbUsername,
			//'fbId'            => $fbId,
			'message'   => $message
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

	public function showNotifications(Request $request, Application $app)
	{
		$fbClient = $app['facebook_api_client'];

		$paging = $this->parsePaging($request, 30);
		$notifications = $fbClient->getNotifications($paging);

		$logoutUrl = $fbClient->getLogoutUrl();

		return $app['twig']->render('facebook-notifications.twig', array(
			'logoutUrl'     => $logoutUrl,
			'notifications' => $notifications,
		));
	}

	public function showThreadImages(Request $request, Application $app, $threadId)
	{
		$fbClient = $app['facebook_api_client'];

		//$paging = $this->parsePaging($request, 30);

		//$me = $fbClient->getUserInfo();
		//$fbUsername = $me->getName();
		//$fbId = $me->getId();
		$images = $fbClient->getThreadImages($threadId);
		$logoutUrl = $fbClient->getLogoutUrl();

		return $app['twig']->render('facebook-thread-images.twig', array(
			'logoutUrl'       => $logoutUrl,
			//'fbUserName'      => $fbUsername,
			//'fbId'            => $fbId,
			'showAllMessages' => (bool) $request->query->get('showAllMessages') == 'true' ? true : false,
			'images'          => $images
		));
	}

	private function parsePaging(Request $request, $defaultLimit = 30)
	{
		$paging['since'] = $request->query->get('since') ?: null;
		$paging['until'] = $request->query->get('until') ?: null;
		$paging['__previous'] = $request->query->get('__previous') ?: null;
		$paging['limit'] = $request->query->get('limit') ?: $defaultLimit;
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
		$loginUrl = $fbClient->fbHelper()->getLoginUrl([
			FacebookPermissions::MANAGE_NOTIFICATIONS,
			FacebookPermissions::READ_MAILBOX,
			//FacebookPermissions::PUBLISH_ACTIONS,
		]);

		return $app['twig']->render('facebook-login.twig', array(
			'loginUrl' => $loginUrl,
		));
	}
}