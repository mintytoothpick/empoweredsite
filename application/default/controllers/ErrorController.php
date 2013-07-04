<?php

/**
 * ErrorController - The default error controller class
 *
 * @author
 * @version
 */

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/Users.php';
require_once 'Brigade/Util/Auth.php';
require_once 'BaseController.php';

class ErrorController extends BaseController {

    public function init() {
        parent::init();
    }

    public function badparamsAction() {
        $parameters = $this->_getAllParams();

        $errMsg = $this->_getParam('errMsg');
        // 500 error
        $this->getResponse()->setHttpResponseCode(500);
        $this->view->message = $errMsg;

        $pageURL = $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];

        $Message = "
            An error has been encountered, please see the details below:<br><br>
                Link: $pageURL<br>
                ".(!empty($errMsg) ? "Additional Info: $errMsg" : "");

        Zend_Registry::get('logger')->err($Message);
    }

    /**
     * This action handles
     *    - Application errors
     *    - Errors in the controller chain arising from missing
     *      controller classes and/or action methods
     */
    public function errorAction() {
        $parameters = $this->_getAllParams();

        if (isset($parameters['related_links'])) {
            $this->view->related_links = $parameters['related_links'];
        }
        $errMsg = $this->_getParam('errMsg');
        $errors = $this->_getParam('error_handler');
        if ($errors && ($errors->type != 'EXCEPTION_NO_ACTION' &&
            $errors->type != 'EXCEPTION_NO_CONTROLLER' )
        ) {
            $errMsg = $errors['exception'];
            $errors = $errors->type;
            // 500 error
            $this->getResponse()->setHttpResponseCode(500);
            $this->view->message = 'You have encountered an unexpected error';

            $pageURL = $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];

            $Message = "
                An error has been encountered, please see the details below:<br><br>
                    Link: $pageURL<br>
                    Error: $errors<br>
                    ".(!empty($errMsg) ? "Additional Info: $errMsg" : "");

            Zend_Registry::get('logger')->err($Message);

        } else {
            $this->getResponse()->setHttpResponseCode(404);
            $this->view->message = 'The requested URL was not found on this server';
        }

    }

    public function badaccessAction() {
        $this->getResponse()->setHttpResponseCode(404);
    }
}
