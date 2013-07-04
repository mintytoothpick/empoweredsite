<?php

/**
 * VolunteerController - The "volunteers" controller class
 *
 * @author
 * @version
 */

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Brigade/Db/Table/Volunteers.php';
require_once 'Brigade/Db/Table/VolunteerNotes.php';
require_once 'Zend/Paginator.php';
require_once 'Brigade/Util/Auth.php';
require_once 'BaseController.php';
require_once 'Project.php';

class VolunteerController extends BaseController {
    protected $_http;
    public function init() {
        parent::init();
    }

    public function searchAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $text_search = $_POST['text_search'];
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $list = $Volunteers->search($text_search);
        echo '<h2>'.(count($list) > 0 ? 'Search results for "'.$text_search.'"' : 'There are no results for "$text_search". Please check your spelling or try a new term."').'</h2><div class="clear"></div>';
        if ($list) {
            foreach ($list as $item) {
                if ($item['brigades_participated'] > 0) {
                    echo '
                        <div class="sr2st03">
                            <center><img src="/profile/loadimage/?UserId='.$item['UserId'].'" alt="" height="80px" width="80px" /></center>
                            </div>
                            <div class="sr2st04">
                                <h4><a href="/'.$item['URLName'].'">'.$item['FullName'].'</a></h4>
                                <strong class="txt01">Location: </strong>'.$item['Location'].'<br/>
                                <strong class="txt01">Number of Brigades Participated in: </strong>'.$item['brigades_participated'].'<br/>
                                <strong class="txt01">Amount Raised to Date: </strong>$'.$item['UserDonationGoal'].'<br/>
                                <strong class="txt01">Is Passionate About: </strong>
                                <div id="divLessContent'.$item['UserId'].'" style="display:block;">
                                <span id="ctl00_ContentPHMain_VolunteerList1_repeatGroups_ctl00_lblDescriptionLessContent">
                                    '.(strlen($item['AboutMe']) > 100 ? substr($item['AboutMe'], 0, 100) : $item['AboutMe']).'
                                </span>
                                '.(trim($item['AboutMe']) == '' ? "" : '<a id="ReadMore" href="javascript:ShowHide("divLessContent'.$item['UserId'].'","divMoreContent'.$item['UserId'].'");">Read More</a>').'
                            </div>
                            '.(strlen($item['AboutMe']) > 100 ? '
                            <div id="divMoreContent'.$item['UserId'].'" style="display:none;">
                                <span id="ctl00_ContentPHMain_VolunteerList1_repeatGroups_ctl00_lblDescriptionMoreContent">'.$item['AboutMe'].'</span>
                                <a id="ReadFewer" href="javascript:ShowHide("divMoreContent'.$item['UserId'].'","divLessContent'.$item['UserId'].'")">Read Less</a>
                            </div>' : "" ).'
                        </div>
                    ';
                }
            }
        } else {
            echo '<div class="sr2st04"><h4>No record(s) found. Check your spelling or try another term.</h4></div><div class="clear"></div>';
        }
    }

    public function manageAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }

        $parameters  = $this->_getAllParams();
        $ProjectId   = $parameters['ProjectId'];
        $Volunteers  = new Brigade_Db_Table_Volunteers();
        $Brigades    = new Brigade_Db_Table_Brigades();
        $Groups      = new Brigade_Db_Table_Groups();
        $LookupTable = new Brigade_Db_Table_LookupTable();

        $this->view->volunteer_notes = new Brigade_Db_Table_VolunteerNotes();
        $this->view->sitemedia       = new Brigade_Db_Table_Media();
        $this->view->contactinfo     = new Brigade_Db_Table_ContactInformation();

        if ($this->getRequest()->isPost()) {
            $action = $this->_getParam("_action");
            if ($action=="update_candidates"){
                $accept_ids = $this->_getParam('accept_ids');
                $deny_ids = $this->_getParam('deny_ids');
                if (!empty($accept_ids)){
                    foreach($accept_ids as $accept){
                        //$Mailer = new Mailer();
                        $volObj = Volunteer::get($accept);

                        $GroupMembers = new Brigade_Db_Table_GroupMembers();
                        // register the user as member of the organization
                        if(!$GroupMembers->isMemberExists($volObj->networkId, $volObj->userId, 'organization')) {
                            $GroupMembers->AddGroupMember(array(
                                'NetworkId' => $volObj->networkId,
                                'UserId' => $accept
                            ));
                        }

                        if (!empty($volObj->groupId)) {
                            // make user a member of the group is user does not exists in the group_members table
                            if (!$GroupMembers->isMemberExists($volObj->groupId, $volObj->userId)) {
                                $GroupMembers->AddGroupMember(array(
                                    'GroupId' => $volObj->groupId,
                                    'UserId' => $accept
                                ));
                            }
                        }

                        // log the site activity
                        $activity              = new Activity();
                        $activity->siteId      = $ProjectId;
                        $activity->type        = 'Joined Brigade';
                        $activity->createdById = $volObj->userId;
                        $activity->recipientId = $volObj->userId;
                        $activity->date        = date('Y-m-d H:i:s');
                        $activity->save();

                        $Volunteers->acceptVolunteer($accept);

                        $userInfo = $Volunteers->loadInfo($accept);
                        $brigadeInfo = $Brigades->loadInfo($ProjectId);
                        // send message to volunteer
                        //$Mailer->sendVolunteerJoined($userInfo['Email'], stripslashes($brigadeInfo['Name']), stripslashes($userInfo['FirstName'])." ".stripslashes($userInfo['LastName']));
                    }
                }
                if (!empty($deny_ids)){
                    foreach($deny_ids as $deny){
                        $Volunteers->denyVolunteer($deny);
                    }
                }
            } elseif ($action=="update_members") {
                $admin_ids = $this->_getParam('adminrights_ids');
                $delete_ids = $this->_getParam('delete_ids');
                $user_ids = $this->_getParam('user_ids');

                //remove all admin rights first for clean database
                if (!empty($user_ids)){
                    foreach($user_ids as $user){
                        $Volunteers->removeAdminRights($user, $ProjectId);
                    }
                }
                //then add admin rights to those check
                if (!empty($admin_ids)){
                    foreach($admin_ids as $admin){
                        $Volunteers->addAdminRights($admin, $ProjectId);
                    }
                }

                //if there are delete
                if (!empty($delete_ids)){
                    foreach($delete_ids as $delete){
                        $Volunteers->removeVolunteer($delete,1);
                    }
                }
            } else if ($action == "undo") {
                extract($_POST);
                $Volunteers->undoDeleteOrDeny($VolunteerId, array("$status" => 0));
            }
        }


        if (isset($parameters['ProjectId'])) {
            $project  =  $this->view->project   =  Project::get($parameters['ProjectId']);

            $this->view->active_volunteers   = $Volunteers->getProjectVolunteers($ProjectId, 'active');
            $this->view->inactive_volunteers = $Volunteers->getProjectVolunteers($ProjectId, 'inactive');
            $this->view->deleted_volunteers  = $Volunteers->getProjectVolunteers($ProjectId, 'deleted/denied');

            if(!empty($project->groupId)) {
                $group = $this->view->group = $project->group;
                $this->view->level = 'group';

                $this->view->render('group/header.phtml');
                $this->view->render('group/tabs.phtml');
            } else if(!empty($project->organizationId)) {
                $this->view->organization       =  $organization  =  $project->organization;

                $this->view->level = 'organization';

                $this->view->render('nonprofit/header.phtml');
                $this->view->render('nonprofit/tabs.phtml');

            } else {
                $Users = new Brigade_Db_Table_Users();
                $this->view->data = $Users->loadInfo($project->userId);
                $this->view->header_title = $project->name;
                $this->view->level = 'user';
                $this->view->user  = $this->sessionUser;

                $this->view->render('project/header.phtml');
            }
            $this->view->breadcrumb = $this->view->breadcrumbHelper($project, 'Add Volunteers');
            $this->view->render('nonprofit/footer.phtml');
            $this->view->render('nonprofit/breadcrumb.phtml');

            $this->_helper->layout->setLayout('newlayout');

        }
    }

    public function deleteAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if ($_POST) {
            $Volunteers = new Brigade_Db_Table_Volunteers();
            if (isset($_POST['VolunteerId'])) {
                $Volunteers->removeVolunteer($_POST['VolunteerId'], 1);
                echo "Volunteer has been successfully deleted from this activity.";
            } else {
                $Volunteers->removeVolunteer($_POST['FundraiserId'], 1);
                echo "Fundraiser has been successfully deleted from this campaign.";
            }
        }
    }

    public function addnoteAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $VolunteerNotes = new Brigade_Db_Table_VolunteerNotes();
        $VolunteerNoteId = $_REQUEST['VolunteerId'];
        if ($_POST) {
            $VolunteerNotes->addVolunteerNote(array(
                'VolunteerId' => $_POST['VolunteerId'],
                'Notes' => $_POST['Notes']
            ));
            echo "Note has been successfully added";
        }
    }

    public function editnoteAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $VolunteerNotes = new Brigade_Db_Table_VolunteerNotes();
        if ($_POST) {
            $VolunteerNotes->editVolunteerNote($_POST['VolunteerNoteId'], array(
                'Notes' => $_POST['Notes']
            ));
            echo "Note has been successfully updated";
        }
    }

    public function deletenoteAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $VolunteerNotes = new Brigade_Db_Table_VolunteerNotes();
        if ($_POST) {
            $VolunteerNotes->deleteVolunteerNote($_POST['VolunteerNoteId']);
            echo "Note has been successfully deleted";
        }
    }

    public function volunteersreportAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        if (isset($parameters['ProjectId']) || isset($parameters['GroupId']) || isset($parameters['NetworkId'])) {
            $Users = new Brigade_Db_Table_Users();
            $Volunteers = new Brigade_Db_Table_Volunteers();
            $VolunteerNotes = new Brigade_Db_Table_VolunteerNotes();
            if(isset($parameters['StartDate']) && isset($parameters['EndDate'])) {
                $StartDate = date('Y-m-d 00:00:00', strtotime($parameters['StartDate']));
                $EndDate = date('Y-m-d 23:59:59', strtotime($parameters['EndDate']));

                if(strtotime($StartDate) < strtotime(date('2011-05-27 00:00:00'))) {
                    $StartDate = date('0000-00-00 00:00:00');
                }
            }
            if (isset($parameters['ProjectId']) && !isset($parameters['NetworkId'])) {
                $Level = 'project';
                $SiteId = $parameters['ProjectId'];
                $Brigades = new Brigade_Db_Table_Brigades();
                $projectinfo = $Brigades->loadInfo($parameters['ProjectId']);
                $sitename = str_replace(' ', '-', $projectinfo['Name']." Volunteers Report.xls");
                $rows = $Volunteers->getVolunteersReport($parameters['ProjectId'], 'project', '', '', '', '', '');
            } else if (isset($parameters['GroupId']) && !isset($parameters['NetworkId'])) {
                $Level = 'group';
                $SiteId = $parameters['GroupId'];
                $Groups = new Brigade_Db_Table_Groups();
                $groupinfo = $Groups->loadInfo1($parameters['GroupId']);
                $sitename = str_replace(' ', '-', $groupinfo['GroupName']." Volunteers Report.xls");
                $rows = $Volunteers->getVolunteersReport($parameters['GroupId'], 'group', '', '', '', isset($StartDate) ? $StartDate : '', isset($EndDate) ? $EndDate : '');
            } else if (isset($parameters['NetworkId'])) {
                $Level = 'organization';
                $SiteId = $parameters['NetworkId'];
                $Organizations = new Brigade_Db_Table_Organizations();
                $orginfo = $Organizations->loadInfo($parameters['NetworkId'], false);
                $sitename = str_replace(' ', '-', $orginfo['NetworkName']." Volunteers Report.xls");
                $rows = $Volunteers->getVolunteersReport($parameters['NetworkId'], 'organization', isset($parameters['ProgramId']) && !empty($parameters['ProgramId']) ? $parameters['ProgramId'] : '', isset($parameters['GroupId']) && !empty($parameters['GroupId']) ? $parameters['GroupId'] : '', isset($parameters['ProjectId']) && !empty($parameters['ProjectId']) ? $parameters['ProjectId'] : '', isset($StartDate) ? $StartDate : '', isset($EndDate) ? $EndDate : '');
                if (!$orginfo['hasDownloadedReports']) {
                    $Organizations->editNetwork($parameters['NetworkId'], array('hasDownloadedReports' => 1));
                }
            }
            $sitename = str_replace(',','_',$sitename);
            header("Content-type: application/x-msdownload");
            header("Content-Disposition: attachment; filename=$sitename");
            header("Pragma: no-cache");
            header("Expires: 0");
            $headers = '';
            $data = '';
            if ($Level == 'project') {
                $columns = array('Volunteer Name', 'Volunteer Email',  'Volunteer Opportunity', 'Total Fundraised', 'Notes');
            } else {
                $columns = array('Volunteer First Name', 'Volunteer Last Name', 'Email',  'Activities Participated', 'Total Fundraised', 'Notes');
            }
            foreach($columns as $column) {
                $headers .= $column."\t";
            }
            foreach($rows as $row) {
                try {
                    $notes = $VolunteerNotes->getVolunteerNotesBySite($row['UserId'], $SiteId, $Level, isset($parameters['ProgramId']) ? $parameters['ProgramId'] : '', isset($parameters['GroupId']) ? $parameters['GroupId'] : '');
                } catch(Exception $e) {

                }
                if (!empty($notes)) {
                    $n = array();
                    foreach($notes as $note) {
                        $n[] = $note['Notes'];
                    }
                    $row['Notes'] = implode(", ", $n);
                } else {
                    $row['Notes'] = "";
                }
                foreach($row as $col =>  $value) {
                    if ((!isset($value)) || ($value == "") || empty($value)) {
                        $row[$col] = "\t";
                    } else {
                        $row[$col] = str_replace('"', '""', $value);
                        $row[$col] = '"' . stripslashes($value) . '"' . "\t";
                    }
                }
                extract($row);
                $line = ($Level == 'project' ? $VolunteerName : "$FirstName$LastName")."$VolunteerEmail".($Level == 'project' ? '"' . stripslashes($projectinfo['Name']) . '"' . "\t" : $TotalParticipated)."$TotalFundraised$Notes";
                $data .= trim($line)."\n";
            }
            print "$headers\n$data";
        }
    }

    public function notesreportAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        if (isset($parameters['ProjectId']) || isset($parameters['GroupId']) || isset($parameters['NetworkId'])) {
            $Users = new Brigade_Db_Table_Users();
            $VolunteerNotes = new Brigade_Db_Table_VolunteerNotes();
            if (isset($parameters['ProjectId'])) {
                $Brigades = new Brigade_Db_Table_Brigades();
                $projectinfo = $Brigades->loadInfo($parameters['ProjectId']);
                $sitename = str_replace(' ', '-', $projectinfo['Name']." Volunteer Notes Report.xls");
                $notes = $VolunteerNotes->getVolunteerNotesReport($parameters['ProjectId']);
            } else if (isset($parameters['GroupId'])) {
                $Groups = new Brigade_Db_Table_Groups();
                $groupinfo = $Groups->loadInfo1($parameters['GroupId']);
                $sitename = str_replace(' ', '-', $groupinfo['GroupName']." Volunteer Notes Report.xls");
                $notes = $VolunteerNotes->getVolunteerNotesReport($parameters['GroupId'], 'group');
            } else if (isset($parameters['NetworkId'])) {
                $Organizations = new Brigade_Db_Table_Organizations();
                $orginfo = $Organizations->loadInfo($parameters['NetworkId'], false);
                $sitename = str_replace(' ', '-', $orginfo['NetworkName']." Volunteer Notes Report.xls");
                $notes = $VolunteerNotes->getVolunteerNotesReport($parameters['NetworkId'], 'organization');
                if (!$orginfo['hasDownloadedReports']) {
                    $Organizations->editNetwork($parameters['NetworkId'], array('hasDownloadedReports' => 1));
                }
            }
            header("Content-type: application/x-msdownload");
            header("Content-Disposition: attachment; filename=$sitename");
            header("Pragma: no-cache");
            header("Expires: 0");
            $headers = '';
            $data = '';
            $columns = array('Volunteer Name', 'Notes', 'Activities Participated', 'Total Fundraised', 'Date Added', 'Added By');
            foreach($columns as $column) {
                $headers .= $column."\t";
            }
            foreach($notes as $note) {
                $line = '';
                $userInfo = $Users->loadInfo($note['AddedBy']);
                $note['AddedBy'] = stripslashes($userInfo['FullName']);
                foreach($note as $col =>  $value) {
                    if ((!isset($value)) || ($value == "") || empty($value)) {
                        $note[$col] = "\t";
                    } else {
                        $note[$col] = str_replace('"', '""', $value);
                        $note[$col] = '"' . stripslashes($value) . '"' . "\t";
                    }
                }
                extract($note);
                $line = "$VolunteerName$Notes$activities_participated$total_fundraised$DateAdded$AddedBy";
                $data .= trim($line)."\n";
            }
            print "$headers\n$data";
        }
    }

    public function index2Action() {
        try {
            $search = $this->getRequest()->getParam('text_search');
            $search = trim($search);

            $Volunteers = new Brigade_Db_Table_Volunteers();
            if (!empty($search)){
                $this->view->data = $Volunteers->search($search);
                $this->view->keyword="Search results for '$search'";
            } else {
                $this->view->data = $Volunteers->listAll();
                $this->view->keyword="Upcoming brigades";
            }

            $paginator = Zend_Paginator::factory($this->view->data);
            $page = $this->_getParam('page', 1);
            $paginator->setItemCountPerPage(10);
            $paginator->setCurrentPageNumber($page);
            $this->view->paginator = $paginator;

            $this->view->total = count($this->view->data);
            $this->view->end = $page * 10;
            $this->view->start = $this->view->end - 9;
            $this->view->end = $this->view->end > $this->view->total ? $this->view->total : $this->view->end;

        } catch (Exception  $e) {
            echo "Error: ".$e->getMessage();
        }
    }

    public function searchvolunteerAction() {
        try {
            if (!empty($_GET['text_search'])) {
                $searchstr = $_GET['text_search'];
                $volunteerManage = new Brigade_Db_Table_Volunteers();
                $volunteerList= $volunteerManage->listVolunteers($searchstr);

                if($this->_request->isXmlHttpRequest())
                {
                    $this->_helper->layout->disableLayout();
                    $this->_helper->viewRenderer->setNoRender();
                    $results = array();
                    foreach ($volunteerList as $list) {
                        $results[] = array("id" => $list["FullName"], "value" => "$list[FullName]");
                    }
                    $payload = array("results"=>$results);
                    header("Content-type: text/json");
                    echo Zend_Json::encode($payload);
                }
            }
        } catch (Exception $e) {
            $this->view->error = $e->getMessage();
        }
    }

    /*
     * Ajax search / autocomplete search
     */
    public function searchactivityAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        try {
            if (!empty($_GET['text_search'])) {
                $searchstr = $_GET['text_search'];
                $projectManage = new Brigade_Db_Table_Brigades();
                $projectList= $projectManage->listName($searchstr, isset($_GET['ProgramId']) ? $_GET['ProgramId'] : NULL);
                if($this->_request->isXmlHttpRequest()) {
                    $results = array();
                    foreach ($projectList as $list) {
                        $results[] = array("id" => $list["ProjectId"], "value" => "$list[Name]");
                    }
                    $payload = array("results"=>$results);
                    header("Content-type: text/json");
                    echo Zend_Json::encode($payload);
                }
            }
        }

        catch (Exception $e) {
            $this->view->error = $e->getMessage();
        }
    }
}
