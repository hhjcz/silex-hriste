<?php namespace Facebook;

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

}