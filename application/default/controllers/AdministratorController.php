<?php

/**
 * AdministratorController - The "administrator" controller class
 *
 * @author
 * @version
 */

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/Users.php';
require_once 'Brigade/Db/Table/UserRoles.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Brigade/Util/Auth.php';
require_once 'BaseController.php';
require_once 'Infusionsoft.php';

class AdministratorController extends BaseController {

    protected $_message;
    public function init() {
        parent::init();

        $front = Zend_Controller_Front::getInstance();
        $actionName = $front->getRequest()->getActionName();

        if (isset($_SESSION['UserId'])) {
            if ($actionName == 'manage') {
                $parameters = $this->_getAllParams();
                $NetworkId = $parameters['SiteId'];
                $UserRoles = new Brigade_Db_Table_UserRoles();
                $UserRoles = new Brigade_Db_Table_UserRoles();
                if(isset($_SESSION['UserId'])) {
                    $role = $UserRoles->getUserRole($_SESSION['UserId']);
                    $hasAccess = $UserRoles->UserHasAccess($NetworkId, $_SESSION['UserId']);
                    if (($hasAccess && $role['RoleId'] == 'ADMIN') || $role['RoleId'] == 'GLOB-ADMIN') {
                        $this->view->isAdmin = true;
                    }
                    if ($role['RoleId'] == 'GLOB-ADMIN') {
                        $this->view->isGlobalAdmin = true;
                    }
                }
            }
        }
    }

    /**
     * The default action
     */
    public function indexAction() {

    }

    public function manageAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $parameters = $this->_getAllParams();
        $SiteId = $parameters['SiteId'];
        if (isset($parameters['Type'])) {
            $this->view->Type = $parameters['Type'];
        } else {
            $this->view->Type = "";
        }
        if (isset($parameters['Prev'])) {
            $this->view->Prev = $parameters['Prev'];
        } else {
            $this->view->Prev = "";
        }
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $Organizations = new Brigade_Db_Table_Organizations();
        $this->view->administrators = $UserRoles->getSiteAdmin($SiteId);
        $this->view->SiteId = $SiteId;
        $this->view->network = $Organizations->loadInfo($SiteId);
        $this->view->URLName = $LookupTable->getURLbyId($SiteId);
        if (!empty($this->_message)) {
            echo $this->_message;
        }
    }

    public function deleteAction(){
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $SiteId = $_POST['SiteId'];
        $UserId = $_POST['UserId'];
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $UserRoles->deleteUserRole($UserId, $SiteId);
        $group  = Group::get($SiteId);
        $user   = User::get($UserId);
        $member = $group->getMember($user);
        if ($member) {
            $member->setAdmin(false);
        }

        $configIS = Zend_Registry::get('configuration')->infusionsoft;
        if ($configIS->active &&
            in_array($member->organizationId, $configIS->orgs->toArray())
        ) {
            $is = Infusionsoft::getInstance();
            $is->updateMemberContact($member);
            Zend_Registry::get('logger')->info('InfusionSoft::Member Admin Removed:'.$member->id);
        }
        echo "You have successfully removed this user's admin access";
    }

    public function createAction(){
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Email = $_POST['Email'];
        $SiteId = $_POST['SiteId'];
        $Users = new Brigade_Db_Table_Users();
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $userInfo = $Users->findBy($Email);
        if (count($userInfo) > 0) {
            $user_role_exists = $UserRoles->isUserRoleExists($SiteId, $userInfo['UserId']);
            if (!$user_role_exists) {
                $UserRoleId = $UserRoles->addUserRole(array(
                    'UserId' => $userInfo['UserId'],
                    'RoleId' => 'ADMIN',
                    'SiteId' => $SiteId
                ));
                echo "success|User with email $Email has been successfully added to the administrator list.";
            } else {
                echo "error|User is already an administrator.";
            }
        } else {
            echo "error|Email does not exists, please check the email or specify another.";
        }
        //header("location: $url");
    }

    public function preDispatch() {
        parent::preDispatch();
        $this->view->MLKchallengeId = "405B8C76-DEEC-11DF-867B-0025900034B2";
    }


}
