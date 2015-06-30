<?php namespace FacebookClient;

/**
 *
 * @author jan.haering@dialtelecom.cz
 */
class InboxGraph extends AbstractGraph {

	/**
	 * @return object
	 */
	public function extractThreads()
	{
		$threads = [];
		foreach ($this->graph->getPropertyAsArray('data') as $_threadGraph)
		{
			$threadGraph = new ThreadGraph($_threadGraph);
			$threads[] = $threadGraph->extractThread();
		}
		$inbox = new \stdClass();
		$inbox->threads = $threads;

		return $inbox;
	}

}