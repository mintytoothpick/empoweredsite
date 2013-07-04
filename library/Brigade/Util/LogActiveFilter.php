<?php
require_once 'Zend/Log/Filter/Interface.php';

class LogActiveFilter implements Zend_Log_Filter_Interface {

	protected $_active;

	public function __construct($active) {
		$this->_active=$active;
	}

	public function accept($event) {
		return $this->_active;
	}

}
