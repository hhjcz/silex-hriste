<?php namespace FacebookClient;

use Facebook\GraphObject;

/**
 *
 * @author jan.haering@dialtelecom.cz
 */
class MessagesGraph extends AbstractGraph {

	public function extractMessages()
	{
		if ($this->graph instanceof GraphObject)
			$messagesGraph = $this->graph->getPropertyAsArray('data');
		else
			return [];

		$messages = [];
		foreach ($messagesGraph as $_messageGraph)
		{
			$messageGraph = new MessageGraph($_messageGraph);
			$message = $messageGraph->extractMessage();

			array_unshift($messages, $message);
		}

		return $messages;
	}
}