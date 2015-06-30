<?php namespace FacebookClient;

/**
 *
 * @author jan.haering@dialtelecom.cz
 */
class MessageGraph extends AbstractGraph {

	/**
	 * @return mixed
	 */
	public function extractMessage()
	{
		//dump($messageInThreadGraph);
		$message = [];
		$messageId = $this->graph->getProperty('id');
		//list($nic, $messageInThread['id']) = preg_split('/\_/', $messageId);
		$message['id'] = $messageId;

		//dump($messageId);
		//dump((new FacebookRequest($this->session, 'GET', "/$messageId"))->execute());

		$message['from'] = $this->graph->getProperty('from')->getProperty('name');
		$message['from_id'] = $this->graph->getProperty('from')->getProperty('id');
		$message['from_email'] = $this->graph->getProperty('from')->getProperty('email');
		$message['created_time'] = \Utils::prettifyTimestamp('' . $this->graph->getProperty('created_time'))
			->formatLocalized('%a %d %b %H:%M');
		$message['created_timestamp'] = \Utils::prettifyTimestamp('' . $this->graph->getProperty('created_time'))
			->timestamp;
		$messageText = $this->graph->getProperty('message');
		$message['message'] = $this->htmlEncodeMessageString($messageText);
		$message['status'] = $this->extractStatus();
		$message['attachments'] = $this->extractAttachments();

		return $message;
	}

	/**
	 * @return array
	 */
	public function extractAttachments()
	{
		if ( ! $attachmentsGraph = $this->graph->getProperty('attachments')) return [];
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

	/**
	 * @return string
	 */
	public function extractStatus()
	{
		$tags = $this->extractTags();
		if (in_array('read', $tags)) return 'read';
		else return 'unread';
	}

	/**
	 * @return array
	 */
	public function extractTags()
	{
		$tagsGraph = $this->graph->getProperty('tags');
		$tags = [];
		foreach (range(0, 30) as $key)
		{
			$tag = $tagsGraph->getProperty('' . $key);
			if ($tag) $tags[] = $tag->getProperty('name');
		}

		return $tags;
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
}