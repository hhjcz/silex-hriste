<?php

use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookRequest;
use Facebook\FacebookRequestException;
use Facebook\FacebookResponse;
use Facebook\FacebookSession;
use Facebook\GraphObject;
use Facebook\GraphUser;

/**
 *
 * @author jan.haering@dialtelecom.cz
 */
class FacebookApiClient {

	/** @var FacebookRedirectLoginHelper */
	private $fbHelper;
	/** @var  FacebookSession */
	private $session;

	public function authenticate($api_key, $api_secret, $redirect_login_url)
	{
		// initialize your app using your key and secret
		FacebookSession::setDefaultApplication($api_key, $api_secret);

		// create a helper object which is needed to create a login URL
		// the $redirect_login_url is the page a visitor will come to after login
		$this->fbHelper = new FacebookRedirectLoginHelper($redirect_login_url);

		// First check if this is an existing PHP session
		if (isset($_SESSION) && isset($_SESSION['fb_token']))
		{
			// create new session from the existing PHP session
			$this->session = new FacebookSession($_SESSION['fb_token']);
			try
			{
				// validate the access_token to make sure it's still valid
				if ( ! $this->session->validate()) $this->session = null;
			} catch (Exception $e)
			{
				// catch any exceptions and set the sesson null
				$this->session = null;
				echo 'No session: ' . $e->getMessage();
				unset($_SESSION['fb_token']);
				//throw $e;
			}
		} elseif (empty($this->session))
		{
			// the session is empty, we create a new one
			try
			{
				// the visitor is redirected from the login, let's pickup the session
				$this->session = $this->fbHelper->getSessionFromRedirect();
			} catch (FacebookRequestException $e)
			{
				// Facebook has returned an error
				//echo 'Facebook (session) request error: ' . $e->getMessage();
				throw $e;
			} catch (Exception $e)
			{
				// Any other error
				//echo 'Other (session) request error: ' . $e->getMessage();
				throw $e;
			}
		}

		if (isset($this->session))
		{
			// store the session token into a PHP session
			$_SESSION['fb_token'] = $this->session->getToken();
			// and create a new Facebook session using the current token
			// or from the new token we got after login
			//$this->session = new FacebookSession($this->session->getToken());
		} else
		{
			// we need to create a new session, provide a login link
			//echo 'No session, please <a href="' . $this->fbHelper->getLoginUrl(array('publish_actions')) . '">login</a>.';
		}

		return $this->session;
	}

	/**
	 * @return FacebookRedirectLoginHelper
	 */
	public function fbHelper()
	{
		return $this->fbHelper;
	}

	/**
	 * @param string $userId
	 * @return GraphUser
	 * @throws FacebookRequestException
	 */
	public function getUserInfo($userId = 'me')
	{
		try
		{
			/** @var GraphObject $graphObject */
			$request = new FacebookRequest($this->session, 'GET', '/' . $userId);
			$response = $request->execute();
			$graphObject = $response->getGraphObject(GraphUser::className());
		} catch (FacebookRequestException $e)
		{
			// show any error for this facebook request
			//echo 'Facebook (post) request error: ' . $e->getMessage();
			throw $e;
		}
		return $graphObject;
	}

	/**
	 * @return GraphObject
	 * @throws FacebookRequestException
	 */
	public function getNotifications()
	{
		try
		{
			$request = new FacebookRequest($this->session, 'GET', '/me/notifications');
			$response = $request->execute();
			$graphObject = $response->getGraphObject();
		} catch (FacebookRequestException $e)
		{
			throw $e;
		}

		return $graphObject;
	}

	/**
	 * @param int $limit
	 * @return GraphObject
	 * @throws Exception
	 * @throws FacebookRequestException
	 */
	public function getMessages($limit = 5)
	{
		try
		{
			//$request = new FacebookRequest($this->session, 'GET', '/390523424453383/comments', ['limit' => $limit]);
			$request = new FacebookRequest($this->session, 'GET', '/me/inbox', ['limit' => $limit]);
			$response = $request->execute();
			$inboxGraph = $response->getGraphObject();
			//dump($inboxGraph);
			$inbox = new stdClass();
			$inbox->summary = $inboxGraph->getProperty('summary');
			$inbox->unseen = $inbox->summary->getProperty('unseen_count');
			$inbox->unread = $inbox->summary->getProperty('unread_count');
			$inbox->threads = $this->extractThreadsFromInboxGraph($inboxGraph);
		} catch (FacebookRequestException $e)
		{
			throw $e;
		}

		//dump($inbox);
		return $inbox;
	}

	/**
	 * @param $threadId
	 * @param array $paging
	 * @return GraphObject
	 * @throws Exception
	 * @throws FacebookRequestException
	 */
	public function getThread($threadId, $paging)
	{
		try
		{
			$limit = $paging['limit'] ?: 30;
			$request = new FacebookRequest($this->session, 'GET', "/${threadId}", ['limit' => $limit]);
			$response = $request->execute();
			$threadGraph = $response->getGraphObject();
			//dump($threadGraph);
			$thread = new stdClass();
			//$thread->summary = $threadGraph->getProperty('summary');
			$thread->id = $threadGraph->getProperty('id');
			$thread->users = $this->extractUsersFromThreadGraph($threadGraph, $thread);
			$thread->unseen = $threadGraph->getProperty('unseen');
			$thread->unread = $threadGraph->getProperty('unread');

			$request = new FacebookRequest($this->session,
				'GET', "/${threadId}/comments", [
					'since'          => $paging['since'],
					'until'          => $paging['until'],
					'__previous'     => $paging['__previous'],
					'limit'          => $limit,
					'__paging_token' => $paging['__paging_token']
				]);
			$response = $request->execute();
			$messagesInThreadGraph = $response->getGraphObject();
			//dump($threadGraph);
			$paging = $this->extractPagingFromResponse($response);
			$thread->previousPage = $paging['previous'];
			$thread->nextPage = $paging['next'];

			$thread->messages = $this->extractMessagesFromThreadGraph($threadGraph, $messagesInThreadGraph);
		} catch (FacebookRequestException $e)
		{
			throw $e;
		}

		return $thread;
	}

	/**
	 * @param FacebookResponse $response
	 * @return array paging parameters
	 */
	private function extractPagingFromResponse(FacebookResponse $response)
	{
		$nextPage = $response->getRequestForNextPage();
		if ($nextPage instanceof FacebookRequest) $nextPage = $nextPage->getParameters();
		else $nextPage = $response->getRequest()->getParameters();

		$previousPage = $response->getRequestForPreviousPage();
		if ($previousPage instanceof FacebookRequest) $previousPage = $previousPage->getParameters();
		else $previousPage = $response->getRequest()->getParameters();

		//dump($nextPage);
		//dump($previousPage);

		$pagingParams = [
			'next'     => [
				//'until'          => 1419969997 ?: $nextPage['until'],
				'until'          => $nextPage['until'],
				'__previous'     => isset($nextPage['__previous']) ? $nextPage['__previous'] : null,
				'since'          => isset($nextPage['since']) ? $nextPage['since'] : null,
				'__paging_token' => $nextPage['__paging_token'],
				'limit'          => $nextPage['limit']
			],
			'previous' => [
				'__previous'     => $previousPage['__previous'],
				'since'          => $previousPage['since'],
				'until'          => isset($previousPage['until']) ? $previousPage['until']: null,
				'__paging_token' => $previousPage['__paging_token'],
				'limit'          => $previousPage['limit']
			],
		];

		return $pagingParams;
	}

	/**
	 * @param $inboxGraph
	 * @return array
	 */
	private function extractThreadsFromInboxGraph($inboxGraph)
	{
		$threads = [];
		foreach ($inboxGraph->getPropertyAsArray('data') as $threadGraph)
		{
			$thread['id'] = $threadGraph->getProperty('id');
			$thread['unseen'] = $threadGraph->getProperty('unseen');
			$thread['unread'] = $threadGraph->getProperty('unread');

			$thread['users'] = $this->extractUsersFromThreadGraph($threadGraph, $thread);

			$messagesInThreadGraph = $threadGraph->getProperty('comments');
			$thread['messages'] = $this->extractMessagesFromThreadGraph($threadGraph, $messagesInThreadGraph);
			$threads[] = $thread;
		}

		return $threads;
	}

	/**
	 * @param $threadGraph
	 * @return mixed
	 */
	private function extractUsersFromThreadGraph($threadGraph)
	{
		$threadUsers = '';
		$usersGraph = $threadGraph->getProperty('to');
		//foreach ($usersGraph as $userGraph)
		//{
		//	$threadUsers .= $userGraph->getProperty('name') . ', ';
		//}
		//$threadUsers .= $usersGraph->getProperty('0') ? $usersGraph->getProperty('0')->getProperty('name') : '';
		$separator = '';
		foreach (range(0, 30) as $key)
		{
			if ( ! $usersGraph->getProperty('' . $key)) continue;

			$threadUser = $usersGraph->getProperty('' . $key)->getProperty('name');
			if ($threadUser == 'Honza Haering') continue;

			$threadUsers .= $separator . $threadUser;
			$separator = ', ';
		}

		return $threadUsers;
	}

	/**
	 * @param GraphObject $threadGraph
	 * @param GraphObject $messagesInThreadGraph
	 * @return array
	 */
	private function extractMessagesFromThreadGraph($threadGraph, $messagesInThreadGraph)
	{
		if ($messagesInThreadGraph instanceof GraphObject) $messagesInThreadGraph = $messagesInThreadGraph->getPropertyAsArray('data');
		else return [];
		$messagesInThread = [];
		$i = 0;
		foreach ($messagesInThreadGraph as $messageInThreadGraph)
		{
			//dump($messageInThreadGraph);
			$messageId = $messageInThreadGraph->getProperty('id');
			list($nic, $messageInThread['id']) = preg_split('/\_/', $messageId);

			//dump($messageId);
			//dump((new FacebookRequest($this->session, 'GET', "/$messageId"))->execute());

			$messageInThread['from'] = $messageInThreadGraph->getProperty('from')->getProperty('name');
			$messageInThread['created_time'] = $this->prettifyTimestamp('' . $messageInThreadGraph->getProperty('created_time'));
			$messageText = $messageInThreadGraph->getProperty('message');
			$messageInThread['message'] = $this->htmlEncodeMessageString($messageText);
			if ($i++ < sizeof($messagesInThreadGraph) - $threadGraph->getProperty('unread'))
				$messageInThread['status'] = 'read';
			else
				$messageInThread['status'] = 'unread';
			array_push($messagesInThread, $messageInThread);
		}

		return $messagesInThread;
	}

	/**
	 * @param $messageText
	 * @return mixed|string
	 */
	private function htmlEncodeMessageString($messageText)
	{
		$messageText = htmlspecialchars($messageText);
		$messageText = preg_replace('/https?:\/\/[^\s"<>]+/', '<a href="$0" target="_blank">$0</a>', $messageText);
		$messageText = preg_replace('/\n/', '<br/>' . PHP_EOL, $messageText);

		return $messageText;
	}

	/**
	 * @param $timestamp
	 * @return string
	 */
	public function prettifyTimestamp($timestamp)
	{
		$carbon = Carbon\Carbon::createFromFormat('Y-m-d\TG:i:s\+0000', $timestamp, 'UTC');
		$carbon->setTimezone('Europe/Prague');
		setlocale(LC_TIME, 'cs_CZ');

		return $carbon->formatLocalized('%a %d %b %H:%M');
	}
}