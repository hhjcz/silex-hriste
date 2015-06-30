<?php namespace Tests;

use App;
use FacebookClient\FacebookApiClient;
use Mockery as m;
use PHPUnit_Framework_TestCase;
use TestCase;

/**
 *
 * @author jan.haering@dialtelecom.cz
 */
class FacebookApiClientTest extends \PHPUnit_Framework_TestCase {

	const OBJECT_CLASS = FacebookApiClient::class;

	/** @var \Facebook\FacebookApiClient */
	protected $object;

	public function setUp()
	{
		parent::setUp();
		// $this->object = m::mock(self::OBJECT_CLASS);
		$className = self::OBJECT_CLASS;
		$this->object = new $className();
		//$this->object = App::make(self::OBJECT_CLASS);
	}

	//region tests

	public function test_instance()
	{
		$this->assertInstanceOf(self::OBJECT_CLASS, $this->object);
	}

	/**
	 * @test
	 */
	public function should_prettify_timestamp()
	{
		$prettyTime = $this->object->prettifyTimestamp('2015-04-20T14:43:51+0000')->formatLocalized('%a %d %b %H:%M');
		$this->assertInternalType('string', $prettyTime);
	}

	//endregion
}

