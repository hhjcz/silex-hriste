<?php namespace FacebookClient;

/**
 *
 * @author jan.haering@dialtelecom.cz
 */
class InboxGraph extends AbstractGraph {

	/**
	 * @param bool $unreadOnly
	 * @return object
	 */
	public function extractThreads($unreadOnly = true)
	{
		$threads = [];
		foreach ($this->graph->getPropertyAsArray('data') as $_threadGraph)
		{
			$threadGraph = new ThreadGraph($_threadGraph);
			$thread = $threadGraph->extractThread();
			if ($unreadOnly && $thread->unread == 0) continue;
			$threads[] = $threadGraph->extractThread();
		}
		$inbox = new \stdClass();
		$inbox->threads = $threads;

		return $inbox;
	}

}