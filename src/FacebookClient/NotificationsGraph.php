<?php namespace FacebookClient;

/**
 *
 * @author jan.haering@dialtelecom.cz
 */
class NotificationsGraph extends AbstractGraph {

	/**
	 * @return object
	 */
	public function extractNotifications()
	{
		$notifications = [];
		foreach ($this->graph->getPropertyAsArray('data') as $notificationGraph)
		{
			//dump($notificationGraph);
			$notification = new \stdClass();
			$notification->id = $notificationGraph->getProperty('id');
			$notification->from = $notificationGraph->getProperty('from')->getProperty('name');
			$notification->title = $notificationGraph->getProperty('title');
			$notification->created_time = \Utils::prettifyTimestamp('' . $notificationGraph->getProperty('created_time'));
			$object = $notificationGraph->getProperty('object');
			if ($object) $notification->object = $object->getProperty('name') . ' - ';
			else $notification->object = '';

			$notifications[] = $notification;
		}

		return $notifications;
	}

}