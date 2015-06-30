<?php

use Doctrine\DBAL\Connection;
use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookRequest;
use Facebook\FacebookRequestException;
use Facebook\FacebookResponse;
use Facebook\FacebookSession;
use Facebook\GraphObject;
use Facebook\GraphUser;
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
	 * @param int $limit
	 * @return GraphObject
	 * @throws Exception
	 * @throws FacebookRequestException
	 */
	public function getInbox($limit = 5)
	{
		try
		{
			$request = new FacebookRequest($this->session, 'GET', '/me/threads', ['limit' => $limit]);
			$response = $request->execute();
			$inboxGraph = $response->getGraphObject();
			//dump($inboxGraph);
			$inbox = new stdClass();
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
			$thread->id = $threadGraph->getProperty('id');
			$thread->users = $this->extractUsersFromThreadGraph($threadGraph, $thread);
			$thread->unread = $threadGraph->getProperty('unread_count');
			$thread->count = $threadGraph->getProperty('message_count');

			$request = new FacebookRequest($this->session,
				'GET', "/${threadId}/messages", [
					'since'          => $paging['since'],
					'until'          => $paging['until'],
					'__previous'     => $paging['__previous'],
					'limit'          => $limit,
					'__paging_token' => $paging['__paging_token']
				]);
			$response = $request->execute();
			$messagesInThreadGraph = $response->getGraphObject();

			$paging = $this->extractPagingFromResponse($response);
			$thread->previousPage = $paging['previous'];
			$thread->nextPage = $paging['next'];

			$thread->messages = $this->extractMessagesFromThreadGraph($messagesInThreadGraph);
			$thread->displayed = sizeof($thread->messages);
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
			//dump("${threadId}_${messageId}");
			$request = new FacebookRequest($this->session, 'GET', "/${messageId}");
			//dump($request);
			$response = $request->execute();
			$messageGraph = $response->getGraphObject();
			$message = $this->extractMessageFromMessageGraph($messageGraph);

			return $message;
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
	 * @param $inboxGraph
	 * @return array
	 */
	private function extractThreadsFromInboxGraph($inboxGraph)
	{
		$threads = [];
		foreach ($inboxGraph->getPropertyAsArray('data') as $threadGraph)
		{
			$thread['id'] = $threadGraph->getProperty('id');
			//$thread['unseen'] = $threadGraph->getProperty('unseen');
			$thread['count'] = $threadGraph->getProperty('message_count');
			$thread['unread'] = $threadGraph->getProperty('unread_count') ?: 0;

			$thread['users'] = $this->extractUsersFromThreadGraph($threadGraph, $thread);

			$messagesInThreadGraph = $threadGraph->getProperty('messages');
			//dump($messagesInThreadGraph);
			$thread['messages'] = $this->extractMessagesFromThreadGraph($messagesInThreadGraph);
			$threads[] = $thread;
		}

		return $threads;
	}

	/**
	 * @param $threadGraph
	 * @return mixed
	 */
	private function extractUsersFromThreadGraph(GraphObject $threadGraph)
	{
		$threadUsers = '';
		//dump($threadGraph);
		$usersGraph = $threadGraph->getProperty('senders');//->getProperty('data');
		//foreach ($usersGraph as $userGraph)
		//{
		//	$threadUsers .= $userGraph->getProperty('name') . ', ';
		//}
		//$threadUsers .= $usersGraph->getProperty('0') ? $usersGraph->getProperty('0')->getProperty('name') : '';
		$separator = '';
		//dump($usersGraph);
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
	 * @param GraphObject $messagesInThreadGraph
	 * @return array
	 */
	private function extractMessagesFromThreadGraph(GraphObject $messagesInThreadGraph)
	{
		//dump($messagesInThreadGraph);
		if ($messagesInThreadGraph instanceof GraphObject)
			$messagesInThreadGraph = $messagesInThreadGraph->getPropertyAsArray('data');
		else
			return [];

		$messagesInThread = [];
		foreach ($messagesInThreadGraph as $messageInThreadGraph)
		{
			$messageInThread = $this->extractMessageFromMessageGraph($messageInThreadGraph);

			array_unshift($messagesInThread, $messageInThread);
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
	 * @return Carbon\Carbon
	 */
	public function prettifyTimestamp($timestamp)
	{
		$carbon = Carbon\Carbon::createFromFormat('Y-m-d\TG:i:s\+0000', $timestamp, 'UTC');
		$carbon->setTimezone('Europe/Prague');
		setlocale(LC_TIME, 'cs_CZ');

		return $carbon;
	}

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

	/**
	 * @param GraphObject $messageInThreadGraph
	 * @return string
	 */
	private function extractMessageStatus(GraphObject $messageInThreadGraph)
	{
		$tags = $this->extractTagsFromMessage($messageInThreadGraph);
		if (in_array('read', $tags)) return 'read';
		else return 'unread';
	}

	/**
	 * @param GraphObject $messageInThreadGraph
	 * @return array
	 */
	private function extractTagsFromMessage(GraphObject $messageInThreadGraph)
	{
		$tagsGraph = $messageInThreadGraph->getProperty('tags');
		$tags = [];
		foreach (range(0, 30) as $key)
		{
			$tag = $tagsGraph->getProperty('' . $key);
			if ($tag) $tags[] = $tag->getProperty('name');
		}

		return $tags;
	}

	/**
	 * @param $messageGraph
	 * @return mixed
	 */
	private function extractMessageFromMessageGraph($messageGraph)
	{
		//dump($messageInThreadGraph);
		$message = [];
		$messageId = $messageGraph->getProperty('id');
		//list($nic, $messageInThread['id']) = preg_split('/\_/', $messageId);
		$message['id'] = $messageId;

		//dump($messageId);
		//dump((new FacebookRequest($this->session, 'GET', "/$messageId"))->execute());

		$message['from'] = $messageGraph->getProperty('from')->getProperty('name');
		$message['from_id'] = $messageGraph->getProperty('from')->getProperty('id');
		$message['from_email'] = $messageGraph->getProperty('from')->getProperty('email');
		$message['created_time'] = $this->prettifyTimestamp('' . $messageGraph->getProperty('created_time'))
			->formatLocalized('%a %d %b %H:%M');
		$message['created_timestamp'] = $this->prettifyTimestamp('' . $messageGraph->getProperty('created_time'))
			->timestamp;
		$messageText = $messageGraph->getProperty('message');
		$message['message'] = $this->htmlEncodeMessageString($messageText);
		$message['status'] = $this->extractMessageStatus($messageGraph);
		$message['attachments'] = $this->extractMessageAttachments($messageGraph);

		return $message;
	}

	/**
	 * @param GraphObject $messageInThreadGraph
	 * @return array
	 */
	private function extractMessageAttachments(GraphObject $messageInThreadGraph)
	{
		//dump($messageInThreadGraph);
		if ( ! $attachmentsGraph = $messageInThreadGraph->getProperty('attachments')) return [];
		$attachments = [];
		foreach (range(0, 10) as $key)
		{
			$attachmentGraph = $attachmentsGraph->getProperty('' . $key);
			if ( ! $attachmentGraph) continue;
			//dump($attachmentGraph);
			$attachment = [];
			$attachment['id'] = $attachmentGraph->getProperty('id');
			$attachment['mime_type'] = $attachmentGraph->getProperty('mime_type');
			$attachmentData = $attachmentGraph->getProperty('image_data');
			if ($attachmentData)
			{
				$attachment['url'] = $attachmentData->getProperty('url');
				$attachment['preview_url'] = $attachmentData->getProperty('preview_url');
				$attachment['width'] = $attachmentData->getProperty('width');
				$attachment['height'] = $attachmentData->getProperty('height');
			}
			$attachments[] = $attachment;
		}

		return $attachments;
	}
}