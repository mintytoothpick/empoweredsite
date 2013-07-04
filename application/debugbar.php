<?php
/**
 * Debug Bar Plugin
 *
 * @author: Daniel Valverde
 */

require_once 'Zend/Controller/Plugin/Abstract.php';
require_once 'Zend/Controller/Front.php';
require_once 'Zend/Controller/Request/Abstract.php';

/**
 *
 * Initializes configuration depndeing on the type of environment
 * (test, development, production, etc.)
 *
 * This can be used to configure environment variables, databases,
 * layouts, routers, helpers and more
 *
 */
class Debug_Plugin extends Zend_Controller_Plugin_Abstract 
{

    /**
     * @var Current environment config
     */
    protected $_config;

    /**
     * @var Zend_Controller_Front
     */
    protected $_front;
	
	/**
     * @var Default db adapter
     */
    protected $_dbAdapter;
	
	
	public function __construct($config, $dbAdapter) {
        $this->_config = $config;
        $this->_dbAdapter = $dbAdapter;
        $this->_front = Zend_Controller_Front::getInstance();
    }
	
	
    public function preDispatch(Zend_Controller_Request_Abstract $request) {

    	$params = $request->getParams();
		if ( $this->_config->debug->bar ) {
    		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
			$options = array(
			    'plugins' => array('Variables', 
			                       'Html',
			                       'Database' => array('adapter' => array('standard' =>  $db)), 
			                       'Memory', 
			                       'Time', 
			                       'Exception')
			);
			
			$debug = new ZFDebug_Controller_Plugin_Debug($options);
			$this->_front->registerPlugin($debug);
		}

    }    
}
