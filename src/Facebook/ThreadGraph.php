<?php namespace Facebook;

/**
 *
 * @author jan.haering@dialtelecom.cz
 */
class ThreadGraph extends AbstractGraph {

	/**
	 * @return \stdClass
	 */
	public function extractThread()
	{
		$thread = new \stdClass();
		$thread->id = $this->graph->getProperty('id');
		$thread->count = $this->graph->getProperty('message_count');
		$thread->unread = $this->graph->getProperty('unread_count') ?: 0;

		$thread->users = $this->extractUsers($this->graph, $thread);

		//dump($messagesInthis->graph);
		$thread->messages = $this->extractMessages();

		return $thread;
	}

	/**
	 * @return string
	 */
	private function extractUsers()
	{
		$threadUsers = '';
		$usersGraph = $this->graph->getProperty('senders');
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
	 * @return array
	 */
	private function extractMessages()
	{
		$messagesGraph = new MessagesGraph($this->graph->getProperty('messages'));
		$messages = $messagesGraph->extractMessages();

		return $messages;
	}
}