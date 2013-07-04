<?php

/**
 * FaqController - The Frequently Asked Questions controller class
 *
 * @author
 * @version
 */

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Util/Auth.php';
require_once 'BaseController.php';

class FaqController extends BaseController {
    public function init() {
        parent::init();
    }

    /**
     * The default action - show the home page
     */
    public function indexAction() { }

}
