<?php
use GuzzleHttp\Client;

/**
 *
 * @author jan.haering@dialtelecom.cz
 */
class OrisApiClient {

	const DEFAULT_ORIS_API_ENDPOINT = 'http://oris.orientacnisporty.cz/API/';
	const USER_ID = '20965'; // hhjczcz

	/** @var string */
	private $format = 'json';
	/** @var  GuzzleHttp\Client */
	private $guzzle;
	/** @var  string */
	private $apiEndpoint;

	public function __construct($apiEndpoint = null, Client $guzzle = null)
	{
		if ( ! $apiEndpoint) $apiEndpoint = self::DEFAULT_ORIS_API_ENDPOINT;
		$this->apiEndpoint = $apiEndpoint;

		if ( ! $guzzle) $guzzle = new Client();
		$this->guzzle = $guzzle;
	}

	/**
	 * @param string $userId
	 * @return array
	 */
	public function getUserEventEntries($userId = null, $fromDate = null)
	{
		if ( ! $userId) $userId = self::USER_ID;
		if ( ! $fromDate) $fromDate = date('Y-m-d');
		$toDate = '2099-12-31';

		$res = $this->guzzle->get($this->apiEndpoint, [
			'query' => [
				'method'   => 'getUserEventEntries',
				'format'   => $this->format,
				'userid'   => $userId,
				'dateFrom' => $fromDate,
				'dateTo'   => $toDate,
			],
		]);
		if ($eventsResponse = $res->json())
		{
			//dump($eventsResponse);
			if ($eventsResponse['Status'] === 'OK')
				$events = $this->extractEventsFromResponse($eventsResponse);
		}

		return $events;
	}

	public function getEvent($eventID)
	{
		$res = $this->guzzle->get($this->apiEndpoint, [
			'query' => [
				'method' => 'getEvent',
				'format' => $this->format,
				'id'     => $eventID,
			]
		]);

		if ($eventResponse = $res->json())
		{
			if ($eventResponse['Status'] === 'OK')
				return $this->extractEventDetailFromResponse($eventResponse['Data']);
		}

		return null;
	}

	/**
	 * @param array $eventsResponse
	 * @return array
	 */
	private function extractEventsFromResponse($eventsResponse)
	{
		$events = [];
		foreach ($eventsResponse['Data'] as $eventSource)
		{
			$event = $this->eventToICalendar($eventSource);
			if ($event) $events[] = $event;
		}

		return $events;
	}

	/**
	 * @param array $eventSource
	 * @return array|null
	 */
	private function eventToICalendar($eventSource)
	{
		if ( ! array_key_exists('EventDate', $eventSource)) return null;
		$eventDate = str_replace('-', '', $eventSource['EventDate']);
		$eventID = $eventSource['EventID'];
		$eventDetail = $this->getEvent($eventID);
		$eventSummary = 'OB ' . $eventDetail['place'] . ', kat ' . $eventSource['ClassDesc'];

		$event = [
			'uid'     => md5(uniqid(mt_rand(), true)) . '@hhj.cz',
			'dtstamp' => date('Ymd') . 'T' . date('His') . 'Z',
			'dtstart' => $eventDate,
			'dtend'   => $eventDate,
			'summary' => $eventSummary,
			'eventid' => $eventID,
		];

		return $event;
	}

	private function extractEventDetailFromResponse($eventSource)
	{
		$event = [
			'date'       => $eventSource['Date'],
			'place'      => $eventSource['Place'],
			'map'        => $eventSource['Map'],
			'prihlasky1' => $eventSource['EntryDate1'],
			'prihlasky2' => $eventSource['EntryDate2'],
			'prihlasky3' => $eventSource['EntryDate3'],
		];

		return $event;
	}

}