<?php

/**
 * TermsAndConditionController - The "terms and conditions" controller class
 *
 * @author
 * @version
 */

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Util/Auth.php';
require_once 'BaseController.php';

class TermsandconditionController extends BaseController {
    
    protected $_http;
    public function init() {
        parent::init();
    }

    /**
     * The default action - show the home page
     */
    public function indexAction() {

    }
}
