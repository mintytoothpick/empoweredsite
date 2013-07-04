<?php

/**
 * BlogController - The "blog" controller class
 *
 * @author
 * @version
 */

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/Petitions.php';
require_once 'BaseController.php';

class PetitionController extends BaseController {
    protected $_http;
    public function init() {
        parent::init();
    }

    public function auAction() {
        if($_POST) {
            extract($_POST);
            $ip_addr  = $_SERVER['REMOTE_ADDR'];
            $Petition = new Brigade_Db_Table_Petitions();
            $Petition->Sign(array(
                'name' => $Name,
                'email' => $Email,
                'zip' => $Zip,
                'ip' => $ip_addr,
                'university' => 0,
                'discipline' => 'none'
            ));

            $this->view->message2 = "Thank you for your commitment to International Service!";
        }
    }

    public function gbAction() {
        if($_POST) {
            extract($_POST);
            $ip_addr  = $_SERVER['REMOTE_ADDR'];
            $Petition = new Brigade_Db_Table_Petitions();
            $Petition->Sign(array(
                'name' => $Name,
                'email' => $Email,
                'zip' => $Zip,
                'ip' => $ip_addr,
                'university' => $University,
                'discipline' => $Discipline
            ));

            $this->view->message2 = "Thank you for your commitment to International Service!";
        }
    }
}