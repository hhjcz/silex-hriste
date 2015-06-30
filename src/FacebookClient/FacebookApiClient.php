<?php namespace FacebookClient;

use Doctrine\DBAL\Connection;
use Exception;
use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookRequest;
use Facebook\FacebookRequestException;
use Facebook\FacebookResponse;
use Facebook\FacebookSession;
use Silex\Application;
use Silex\Application\MonologTrait;

/**
 *
 * @author jan.haering@dialtelecom.cz
 */
class FacebookApiClient {

	use MonologTrait;

	/** @var FacebookRedirectLoginHelper */
	private $fbHelper;
	/** @var  FacebookSession */
	private $session;
	/** @var  Application */
	private $app;

	public function __construct(Application $app)
	{
		$this->app = $app;
	}

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
	 * @return FacebookSession
	 */
	public function session()
	{
		return $this->session;
	}

	/**
	 * @return string
	 * @throws \Facebook\FacebookSDKException
	 */
	public function getLogoutUrl()
	{
		$logoutUrl = $this->fbHelper->getLogoutUrl($this->session, $this->app['fb_redirect_login_url'] . '?logout');

		return $logoutUrl;
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
	 * @param array $paging
	 * @return GraphObject
	 * @throws Exception
	 * @throws FacebookRequestException
	 */
	public function getInbox($paging)
	{
		$paging['limit'] = $paging['limit'] ?: 5;
		try
		{
			$request = new FacebookRequest($this->session, 'GET', '/me/threads', $paging);
			$response = $request->execute();
			$inboxGraph = new InboxGraph($response->getGraphObject());

			$inbox = $inboxGraph->extractThreads($inboxGraph);

			$paging = $this->extractPagingFromResponse($response);
			$inbox->previousPage = $paging['previous'];
			$inbox->nextPage = $paging['next'];

		} catch (FacebookRequestException $e)
		{
			throw $e;
		}

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
		$paging['limit'] = $paging['limit'] ?: 30;
		try
		{
			$request = new FacebookRequest($this->session, 'GET', "/${threadId}", ['limit' => $paging['limit']]);
			$threadGraph = new ThreadGraph($request->execute()->getGraphObject());
			$thread = $threadGraph->extractThread();

			$request = new FacebookRequest($this->session, 'GET', "/${threadId}/messages", $paging);
			$response = $request->execute();
			$messagesGraph = new MessagesGraph($response->getGraphObject());
			$thread->messages = $messagesGraph->extractMessages();
			$thread->displayed = sizeof($thread->messages);

			$paging = $this->extractPagingFromResponse($response);
			$thread->previousPage = $paging['previous'];
			$thread->nextPage = $paging['next'];

		} catch (FacebookRequestException $e)
		{
			throw $e;
		}
		return $thread;
	}

	/**
	 * @param int $messageId
	 * @return GraphObject
	 * @throws Exception
	 * @throws FacebookRequestException
	 */
	public function getMessage($messageId)
	{
		try
		{
			$request = new FacebookRequest($this->session, 'GET', "/${messageId}");
			$messageGraph = new MessageGraph($request->execute()->getGraphObject());
			$message = $messageGraph->extractMessage();
		} catch (FacebookRequestException $e)
		{
			throw $e;
		}

		return $message;
	}

	/**
	 * @param int $threadId
	 * @param int $limit
	 * @return int
	 * @throws Exception
	 * @throws FacebookRequestException
	 */
	public function countThread($threadId, $limit = 300)
	{
		try
		{
			$since = $this->getLatestPersistedMessage($threadId);
			$initialParams['limit'] = $limit;
			if ($since > 0)
			{
				$initialParams['since'] = $since;
				$initialParams['__previous'] = 1;
			}
			$request = new FacebookRequest($this->session, 'GET', "/${threadId}/comments", $initialParams);
			$i = 0;
			$newCount = 0;
			while ($request instanceof FacebookRequest && $i++ < 600)
			{
				$response = $request->execute();

				$messages = $this->extractMessagesFromThreadGraph($response->getGraphObject(), 0);
				$this->persistMessages($threadId, $messages);
				$newCount += sizeof($messages);
				$this->app['monolog']->addDebug(sprintf("Pocet zprav so far: %d.", $newCount));

				if ($since > 0)
					$request = $response->getRequestForPreviousPage();
				else
					$request = $response->getRequestForNextPage();
				usleep(700000);
			}

			$count = $this->getPersistedMessagesCount($threadId);
		} catch (FacebookRequestException $e)
		{
			throw $e;
		}

		return [
			'totalCount' => $count['totalCount'],
			'newCount'   => $newCount,
			'wordsCount' => $count['wordsCount'],
			'charsCount' => $count['charsCount']
		];

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
				'until'          => isset($previousPage['until']) ? $previousPage['until'] : null,
				'__paging_token' => $previousPage['__paging_token'],
				'limit'          => $previousPage['limit']
			],
		];

		return $pagingParams;
	}

	/**
	 * @param string $threadId
	 * @param array $messages
	 * @throws \Doctrine\DBAL\ConnectionException
	 * @throws \Doctrine\DBAL\DBALException
	 */
	private function persistMessages($threadId, $messages)
	{
		/** @var Connection $db */
		$db = $this->app['db'];
		$sql = 'INSERT OR IGNORE INTO Messages (id, thread_id, from_id, from_name, text, created_time) VALUES (?, ?, ?, ?, ?, ?)';
		$stmt = $db->prepare($sql);
		$db->beginTransaction();
		foreach ($messages as $message)
		{
			$stmt->execute([
				$message['id'],
				$threadId,
				$message['from_id'],
				$message['from'],
				$message['message'],
				$message['created_timestamp']
			]);
		}
		$db->commit();
		//dump($message);

	}

	private function getLatestPersistedMessage($threadId)
	{
		/** @var Connection $db */
		$db = $this->app['db'];
		$sql = "SELECT MAX(id) AS max_id FROM Messages WHERE thread_id = $threadId";
		$result = $db->fetchAssoc($sql);

		return isset($result['max_id']) && $result['max_id'] > 0 ? $result['max_id'] : 0;
	}

	private function getPersistedMessagesCount($threadId)
	{
		/** @var Connection $db */
		$db = $this->app['db'];
		$sql = "SELECT COUNT(*) AS totalCount, SUM(LENGTH(text)) AS charsCount , SUM(LENGTH(text) - LENGTH(REPLACE(text, ' ', '')) + 1) AS wordsCount
				FROM Messages WHERE thread_id = $threadId";
		$result = $db->fetchAssoc($sql);

		return [
			'totalCount' => $result['totalCount'],
			'wordsCount' => $result['wordsCount'],
			'charsCount' => $result['charsCount'],
		];
	}

}