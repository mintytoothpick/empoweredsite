<?php

/**
 * StaffController - The "staff" controller class
 *
 * @author
 * @version
 */

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/Organizations.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Brigade/Util/Auth.php';
require_once 'BaseController.php';

class StaffController extends BaseController {
    public function init() {
        parent::init();
    }

    /**
     * The default action - show the home page
     */
    public function indexAction() {

    }

    public function manageAction() {
      if (!$this->_helper->authUser->isLoggedIn()) {
        $this->_helper->redirector('login', 'profile');
      }
      $parameters = $this->_getAllParams();
      $SiteId = $parameters['SiteId'];
      $Level = $parameters['Level'];
      $this->view->Level = $Level;
      $this->view->SiteId = $SiteId;
      $SiteStaffs = new Brigade_Db_Table_SiteStaffs();
      $LookupTable = new Brigade_Db_Table_LookupTable();
      $this->view->sitestaffs = $SiteStaffs->getSiteStaffs($SiteId);
      $this->view->URLName = $LookupTable->getURLbyId($SiteId);
    }

    public function searchmembersAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Level = $_POST["Level"];
        $SiteId = $_POST["SiteId"];
        if (strtolower($Level) == "group") {
            $Groups = new Brigade_Db_Table_Groups();
            $result = $Groups->searchMembers($SiteId, $_POST["search_text"]);
        } else if (strtolower($Level) == 'nonprofit') {
            $Organizations = new Brigade_Db_Table_Organizations();
            $result = $Organizations->searchMembers($SiteId, $_POST["search_text"]);
        }
        $output = '<table id="site-staffs-list" cellspacing="0" cellpadding="3" border="0"><tr class="tblHeader" style=""><th scope="col" width="130">Member Name</th><th scope="col" width="100">Email</th><th scope="col" width="100">Action</th></tr>';
        $ctr = 0;
        if ($result) {
            foreach($result as $row) {
                $output .= '<tr style="background-color:'.($ctr%2 == 1 ? "#e7e7e9" : "white").';">';
                $output .= '<td>'.stripslashes($row['FirstName']." ".$row['LastName']).'</td>';
                $output .= '<td>'.stripslashes($row['Email']).'</td>';
                $output .= '<td class="add_staff"><a id="a_'.$row['UserId'].'" href="javascript:;" onclick="toggle(\''.$row['UserId'].'\')">Set Title</a><div style="display:none" id="div_'.$row['UserId'].'"><input type="text" id="title_'.$row['UserId'].'"/>&nbsp;<input type="button" value="Add" onclick="addTitle(\''.$row['UserId'].'\')"/></div></td>';
                $output .= '</tr>';
            }
        } else {
            $output .= '<tr><td colspan="3">No records found for "'.$_POST["search_text"].'"</td></tr>';
        }
        $output .= "</table>";
        echo $output;
    }

    public function addAction() {
        $SiteStaffs = new Brigade_Db_Table_SiteStaffs();
        $SiteStaffs->AddStaff(array(
            'SiteId' => $_POST['SiteId'],
            'UserId' => $_POST['UserId'],
            'Title' => $_POST['Title'],
        ));
    }

    public function editAction() {
        $SiteStaffs = new Brigade_Db_Table_SiteStaffs();
        $SiteStaffs->updateStaff($_POST['ID'], array('Title'=>$_POST['Title']));
    }

    public function deleteAction() {
        $parameters = $this->_getAllParams();
        $SiteStaffs = new Brigade_Db_Table_SiteStaffs();
        $staffs_id = $_POST['staffs_id'];
        foreach ($staffs_id as $staff) {
            $SiteStaffs->deleteStaff($staff);
        }
        header("location: /staff/manage/".$parameters['SiteId']."/".$parameters['Level']);
    }
}
