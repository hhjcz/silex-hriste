<?php namespace FacebookClient;

use Facebook\GraphObject;

/**
 *
 * @author jan.haering@dialtelecom.cz
 */
class AbstractGraph {

	/** @var  GraphObject */
	protected $graph;

	/**
	 * InboxGraph constructor.
	 * @param GraphObject $graph
	 */
	public function __construct(GraphObject $graph)
	{
		$this->graph = $graph;
	}

	/**
	 * @param string $propertyName
	 * @param string $type
	 * @return mixed
	 */
	protected function getProperty($propertyName, $type = 'Facebook\GraphObject')
	{
		return $this->graph->getProperty($propertyName, $type);
	}
}