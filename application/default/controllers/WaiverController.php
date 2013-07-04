<?php

/**
 * DonationController - The "donations" controller class
 *
 * @author
 * @version
 */

require_once 'Brigade/Db/Table/Users.php';
require_once 'Zend/Controller/Action.php';
require_once 'Mailer.php';
require_once 'BaseController.php';

class WaiverController extends BaseController {
    public function init() {
        parent::init();
    }

    /*
     * The default action - show the home page
     */
    public function indexAction() {
        Zend_Registry::get('logger')->info("DELETE::[Waiver::index]");
    }

    public function globalbrigadesAction() {
        Zend_Registry::get('logger')->info("DELETE::[Waiver::globalbrigades]");
    }

    public function serviceforpeaceAction() {
        Zend_Registry::get('logger')->info("DELETE::[Waiver::serviceforpeace]");
    }
}
