<?php

/**
 * BenefitsController - The "about us" controller class
 *
 * @author
 * @version
 */

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/Users.php';
require_once 'Brigade/Util/Auth.php';
require_once 'BaseController.php';

class BenefitsController extends BaseController {
    protected $_http;

    function init() {
        parent::init();
    }

	public function preDispatch() {
		parent::preDispatch();
    }

    public function indexAction() {
		$this->view->activePage = 'grow';
	    $this->view->render('tour/left_tour_nav.phtml');
	}

    public function efficientAction() {
		$this->view->activePage = 'efficient';
	    $this->view->render('tour/left_tour_nav.phtml');
    }

    public function saveAction() {
		$this->view->activePage = 'save';
	    $this->view->render('tour/left_tour_nav.phtml');
    }

    public function whyAction() {
		$this->view->activePage = 'why';
	    $this->view->render('tour/left_tour_nav.phtml');
    }

    public function pricingAction() {
		$this->view->activePage = 'pricing';
	    $this->view->render('tour/left_tour_nav.phtml');
    }
}
