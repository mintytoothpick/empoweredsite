<?php

require_once 'Zend/Controller/Action/Helper/Abstract.php';
require_once 'Zend/Auth.php';

class Brigade_Lib_Controller_Action_Helper_AuthUser extends Zend_Controller_Action_Helper_Abstract {
	
	function __construct() {
	
	}

    public function preDispatch()
    {
        $actionController = $this->getActionController();
        $actionController->view->isLoggedIn = $this->isLoggedIn();
        $actionController->view->username = $this->getUsername();
    }
    
    public function isLoggedIn()
    {
        $auth = Zend_Auth::getInstance();
        return $auth->hasIdentity();
    }
    
    public function getUsername()
    {
        $auth = Zend_Auth::getInstance();
        $identity = $auth->getIdentity();
        return (!$identity? NULL : $identity);
    }

}

?>