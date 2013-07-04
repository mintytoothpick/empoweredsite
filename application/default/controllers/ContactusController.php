<?php

/**
 * ContactusController - The "contact us" controller class
 *
 * @author
 * @version
 */

require_once 'BaseController.php';

class ContactusController extends BaseController {

    /**
     * The default action - show the home page
     */
    public function indexAction() {
        $this->view->activePage = 'contact';
        $this->view->render('aboutus/aboutus_nav.phtml');
    }

    public function contactsendAction() {
        $this->view->activePage = 'contact';
        $this->view->render('aboutus/aboutus_nav.phtml');


        $params   = $this->_getAllParams();

        $message  = "Get Started Request.<br />";
        $message .= "Organization: ".$params['organization'] ."<br />
        URL: ".$params['url']."<br />
        Name: ".$params['name']."<br />
        Phone: ".$params['phone']."<br />
        Contact Email: ".$params['email']."<br />
        Phone Number: ".$params['phone']."<br />
        Annual Online Fundraising Goal: ".$params['goal']."<br />
        Preferred Time to be Contacted: ".$params['time']."<br /><br />
        --<br />
        Empowered.org";

        Zend_Registry::get('eventDispatcher')->dispatchEvent(
            EventDispatcher::$CONTACT_US_GETSTARTED,
            array(
                $message,
                $params['email']
            )
        );
    }

}
