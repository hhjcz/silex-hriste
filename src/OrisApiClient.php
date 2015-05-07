<?php
use GuzzleHttp\Client;

/**
 *
 * @author jan.haering@dialtelecom.cz
 */
class OrisApiClient {

	const ORIS_API_ENDPOINT = 'http://oris.orientacnisporty.cz/API/';
	const USER_ID = '20965'; // hhjczcz

	/** @var string */
	private $format = 'json';

	/** @var  GuzzleHttp\Client */
	private $guzzle;

	public function __construct(Client $guzzle = null)
	{
		if ( ! $guzzle) $guzzle = new Client();
		$this->guzzle = $guzzle;
	}

	public function getUserEventEntries($userId = null)
	{
		if ( ! $userId) $userId = self::USER_ID;

		$res = $this->guzzle->get(self::ORIS_API_ENDPOINT, [
			'method' => 'getUserEventEntries',
			'format' => $this->format,
			'userid' => $userId
		]);

		$events[] = [
			'uid'     => md5(uniqid(mt_rand(), true)) . '@yourhost.test',
			'dtstamp' => date('Ymd') . 'T' . date('His') . 'Z',
			'dtstart' => '20150507T170000CET',
			'dtend'   => '20150507T173000CET',
			'summary' => 'HHj zkouska 4'
		];
	}

}