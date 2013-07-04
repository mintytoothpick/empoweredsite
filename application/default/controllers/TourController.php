<?php
require_once 'BaseController.php';

/**
 * BenefitsController - The "about us" controller class
 *
 * @author : Eamonn Pascal
 * @version
 */

class TourController extends BaseController {
    protected $_http;

	function init() {
		parent::init();
	}
	
	public function preDispatch() {
		parent::preDispatch();
    }

	public function chapterManagementAction() {
		$this->view->activePage = 'chapter';
	    $this->view->render('tour/left_tour_nav.phtml');
	}
	
	public function eventsAction() {
		$this->view->activePage = 'events';
	    $this->view->render('tour/left_tour_nav.phtml');
	}
	
    public function fundraisingCampaignsAction() {
		$this->view->activePage = 'campaigns';
	    $this->view->render('tour/left_tour_nav.phtml');
	}

	public function managementAction() {
		$this->view->activePage = 'management';
	    $this->view->render('tour/left_tour_nav.phtml');
	}

    public function videoAction() {
		$this->view->activePage = 'video';
	    $this->view->render('tour/left_tour_nav.phtml');
	}

    public function volunteerActivitiesAction() {
		$this->view->activePage = 'activities';
	    $this->view->render('tour/left_tour_nav.phtml');
	}
    
    public function customSolutionsAction() {
		$this->view->activePage = 'solutions';
	    $this->view->render('tour/left_tour_nav.phtml');
	}
	
	public function sendApplyFormAction() {
	    $this->_helper->layout()->disableLayout();
	    
	    $mailer = new Zend_Mail('utf-8');
        $mailer->addTo('fightonthatlie@gmail.com');
        
        $mailer->setSubject('Custom Solutions Form');
        
        $mailTxt = 'Name: ' . $_POST['apply_name'] . '<br />';
        $mailTxt .= 'Org. Name: ' . $_POST['apply_orgname'] . '<br />';
        $mailTxt .= 'Email: ' . $_POST['apply_email'] . '<br />';
        $mailTxt .= 'Phone: ' . $_POST['apply_phone'] . '<br />';
        $mailTxt .= 'Fundraising details: ' . $_POST['apply_funds'] . '<br />';
        
        $mailer->setBodyHtml($mailTxt, 'utf8');
        $mailer->setFrom("Empowered.org <admin@empowred.org>");
        
        $mailer->send();
        
        Zend_Debug::dump($_POST);
	}
}
