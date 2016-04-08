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
		$app = require __DIR__ . '/../app.php';
		// $this->object = m::mock(self::OBJECT_CLASS);
		$className = self::OBJECT_CLASS;
		$this->object = new $className($app);
		//$this->object = App::make(self::OBJECT_CLASS);
	}

	//region tests

	public function test_instance()
	{
		$this->assertInstanceOf(self::OBJECT_CLASS, $this->object);
	}

	//endregion
}

