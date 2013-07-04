<?php

/**
 * AboutusController - The "about us" controller class
 *
 * @author Eamonn Pascal
 * @version
 */

require_once 'BaseController.php';


class AboutusController extends BaseController {
    protected $_http;

    public function postDispatch() {
        $this->view->render('aboutus/aboutus_nav.phtml');
    }

    public function indexAction() {
        $this->view->activePage = 'about';
    }

    public function serviciesAction() {
        $this->view->activePage = 'services';
    }

    public function pricingAction() {
        $this->view->activePage = 'pricing';
    }

    public function demoAction() {
        $this->view->activePage = 'demo';
    }

    public function flyforgoodAction() {
        $this->view->activePage = 'flyforgood';
    }
}
