<?php

use Facebook\FacebookJavaScriptLoginHelper;
use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookRequest;
use Facebook\FacebookRequestException;
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
	 * @return GraphObject
	 * @throws FacebookRequestException
	 */
	public function getMessages()
	{
		try
		{
			$request = new FacebookRequest($this->session, 'GET', '/me/inbox', ['limit' => 5]);
			$response = $request->execute();
			$messagesGraph = $response->getGraphObject();
			//dump($messagesGraph);
			$messages = new stdClass();
			$messages->summary = $messagesGraph->getProperty('summary');
			$messages->unseen = $messages->summary->getProperty('unseen_count');
			$messages->unread = $messages->summary->getProperty('unread_count');
			foreach ($messagesGraph->getPropertyAsArray('data') as $threadGraph)
			{
				$thread['unseen'] = $threadGraph->getProperty('unseen');
				$thread['unread'] = $threadGraph->getProperty('unread');

				$threadUsers = '';
				$usersGraph = $threadGraph->getProperty('to');
				foreach ($usersGraph as $userGraph)
				{
					$threadUsers .= $userGraph->getProperty('name') . ', ';
				}
				$thread['users'] = '';
				$thread['users'] .= '' . $usersGraph->getProperty('0')->getProperty('name');
				$thread['users'] .= ', ' . $usersGraph->getProperty('1')->getProperty('name');
				$thread['users'] .= $usersGraph->getProperty('2') ? ', ' . $usersGraph->getProperty('2')->getProperty('name') : '';

				$messagesInThreadGraph = $threadGraph->getProperty('comments')->getPropertyAsArray('data');
				$messagesInThread = [];
				foreach ($messagesInThreadGraph as $messagesInThreadGraph)
				{
					$messageInThread['from'] = $messagesInThreadGraph->getProperty('from')->getProperty('name');
					$messageInThread['created_time'] = $messagesInThreadGraph->getProperty('created_time');
					$messageInThread['message'] = $messagesInThreadGraph->getProperty('message');
					array_unshift($messagesInThread, $messageInThread);
				}
				$thread['messages'] = $messagesInThread;
				$messages->threads[] = $thread;
			}
		} catch (FacebookRequestException $e)
		{
			throw $e;
		}

		//dump($messages);
		return $messages;
	}
}