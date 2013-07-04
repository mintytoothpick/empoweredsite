<?php

/**
 * BlogController - The "blog" controller class
 *
 * @author
 * @version
 */

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/Users.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Zend/Paginator.php';
require_once 'Brigade/Util/Auth.php';
require_once 'Brigade/Db/Table/VolunteerNotes.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/ProjectDonationNotes.php';
require_once 'Brigade/Db/Table/ReceiptMessages.php';
require_once 'Brigade/Db/Table/GroupSurveys.php';
require_once 'Brigade/Reporting/FusionCharts.php';
require_once 'Brigade/Reporting/FusionCharts/MSLine.php';
require_once 'Brigade/Util/DateTime.php';
require_once 'BaseController.php';

require_once 'Group.php';
require_once 'Donation.php';
require_once 'DonationNote.php';
require_once 'Role.php';

class DashboardController extends BaseController {
    protected $_http;
    public function init() {
        parent::init();

    if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
    }

    public function fundraisersAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $parameters = $this->_getAllParams();
        $Groups = new Brigade_Db_Table_Groups();
        $Brigades = new Brigade_Db_Table_Brigades();
        $ProjectDonations = $this->view->project_donations = new Brigade_Db_Table_ProjectDonations();
        $Volunteers = $this->view->volunteer_class = new Brigade_Db_Table_Volunteers();
        $this->view->volunteer_notes = new Brigade_Db_Table_VolunteerNotes();

        $parameters = $this->_getAllParams();
        extract($parameters);

        if(isset($ProjectId)) {
            $project  =  Project::get($ProjectId);
            if(!empty($project->groupId)) {
                $group  =  $project->group;
            }

            $this->view->fundraisers  = $Volunteers->getProjectVolunteers($project->id, 'active', false, false, isset($search_text) ? $search_text : null);
            $_REQUEST['pageUrl']      = $project->urlName;
            $this->view->project      = $project;
            if ($project->organizationId) {
                $this->view->organization = $project->organization;
            }

        } else if (isset($GroupId)) {
            $group                   = Group::get($GroupId);
            $this->view->fundraisers = $Volunteers->getVolunteersByGroup($group->id, 'all', 1, 1, isset($search_text) ? $search_text : null, isset($ProjectId) ? $ProjectId : null);
            $_REQUEST['pageUrl']     = $group->urlName;
        }

        if(isset($group)) {
            $this->view->group        = $group;
            $this->view->organization = $group->organization;
        } else {
            $this->view->header_title = $project->name;
            $_REQUEST['level'] = 'user';
        }
        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper(
            isset($group) ? $group : $project,
            'Fundraisers');

        $this->renderPlaceholders();

        $paginator = Zend_Paginator::factory($this->view->fundraisers);
        $page = $this->_getParam('page', 1);
        $paginator->setItemCountPerPage(isset($limit) ? $limit : 10);
        $paginator->setCurrentPageNumber($page);
        $this->view->paginator = $paginator;
    }

    public function donordonationsAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }

        $parameters                       = $this->_getAllParams();
        $Groups                           = new Brigade_Db_Table_Groups();
        $Brigades                         = new Brigade_Db_Table_Brigades();
        $Programs                         = new Brigade_Db_Table_Programs();
        $Organizations                    = new Brigade_Db_Table_Organizations();
        $ProjectDonations                 = new Brigade_Db_Table_ProjectDonations();
        $Users = $this->view->users_class = new Brigade_Db_Table_Users;
        $this->view->sitemedia            = new Brigade_Db_Table_Media();
        $this->view->projects_class       = new Brigade_Db_Table_Brigades();
        $this->view->donation_notes       = new Brigade_Db_Table_ProjectDonationNotes();

        if(isset($parameters['ProjectId'])) {
            $project  =  Project::get($parameters['ProjectId']);
            if(empty($project->groupId)) {
                $_REQUEST['level']        = 'user';
                $this->view->header_title = $project->name;
            } else {
                $group = $project->group;
            }
        } else if (!empty($parameters['GroupId'])) {
            $group = Group::get($parameters['GroupId']);
        } else if (!empty($parameters['UserId'])) {
            $user = User::get($parameters['UserId']);
        }

        if (isset($parameters['SupporterEmail'])) {
            $this->view->list      = "Donor";
            $this->view->param     = "SupporterEmail=".$parameters['SupporterEmail'];
            $this->view->supporter = $parameters['SupporterEmail'];
            $this->view->paginator = $ProjectDonations->getDonorDonations($parameters['SupporterEmail'], isset($project) ? $project->id : $group->id, isset($project) ? 'activity' : 'group');
            $lastBread             = 'Donor History';
        } else if (isset($parameters['UserId'])) {
            $this->view->list = $parameters['List'];
            $this->view->param = "UserId=".$parameters['UserId'];
            $this->view->volunteer = $Users->loadInfo($parameters['UserId']);
            if (isset($project)) {
                $entity = $project;
                $type   = 'project';
            } else if (isset($group)) {
                $entity = $group;
                $type   = 'group';
            } else if (isset($user)) {
                $entity = $user;
                $type   = 'user';
            }
            $this->view->paginator = $ProjectDonations->getUserDonationsBySite($parameters['UserId'], $entity->id, $type);
            $lastBread = 'Donation History';
        }

        if(isset($group)) {
            $this->view->group        = $group;
            $this->view->organization = $group->organization;
        } else {
            $lastBread = 'Donations';
            if (isset($project->organization)) {
                $this->view->organization = $project->organization;
            }
        }

        $this->view->soloProject = false;
        if (!isset($project->organization)) {
            $this->view->soloProject = true;
        }

        //breadcrumb
        if (isset($project)) {
            $this->view->breadcrumb = $this->view->breadcrumbHelper(
                $entity,
                $lastBread
            );
            $this->view->project = $project;
        }

        $this->renderPlaceholders();
    }

    public function donorsAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $parameters = $this->_getAllParams();
        extract($parameters);

        $this->view->projects_class = new Brigade_Db_Table_Brigades();
        $Groups = new Brigade_DB_Table_Groups();
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->project_donations = $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        $this->view->donation_notes = new Brigade_Db_Table_ProjectDonationNotes();
        $this->view->users_class = new Brigade_Db_Table_Users();
        if (isset($parameters['limit'])) {
            $this->view->limit = $parameters['limit'];
        }

        if(isset($ProjectId) && !empty($ProjectId)) {
            $project  =  Project::get($ProjectId);
            if(!empty($project->groupId)) {
                $group  =  $project->group;
            }

            $this->view->donations  =  $ProjectDonations->getProjectDonors($project->id, isset($search_text) ? $search_text : NULL);
            $_REQUEST['pageUrl']    =  $project->urlName;
            $this->view->project    =  $project;

        } else if (isset($GroupId)) {
            $group  =  Group::get($GroupId);

            $this->view->donations  =  $ProjectDonations->getGroupDonors($group->id, isset($ProjectId) ? $ProjectId : NULL, isset($search_text) ? $search_text : NULL);
            $_REQUEST['pageUrl']    =  $group->urlName;
        }

        if(isset($group)) {
            $this->view->activities = $Groups->loadBrigades($group->id, 'all');

            $this->view->group         =  $group;
            $this->view->organization  =  $group->organization;

            //breadcrumb
            $this->view->breadcrumb        =  array();
            $this->view->breadcrumb[]      =  '<a href="/'.$group->organization->urlName.'">'.$group->organization->name.'</a>';
            if (!empty($group->programId)) {
                $this->view->breadcrumb[]  =  '<a href="/'.$group->program->urlName.'">'.$group->program->name.'</a>';
            }
            $this->view->breadcrumb[]      =  '<a href="/'.$group->urlName.'">'.$group->name.'</a>';
            if (isset($project)) {
                $this->view->breadcrumb[]  =  '<a href="/'.$project->urlName.'">'.$project->name.'</a>';
            }
            $this->view->breadcrumb[]      =  'Donor History';

            $this->renderPlaceholders();

        } else {
            $this->view->header_title = $project->name;
            $_REQUEST['level'] = 'user';
        }

        $_REQUEST['subPage']    =  'donors';
        $paginator = Zend_Paginator::factory($this->view->donations);
        $page = $this->_getParam('page', 1);
        $paginator->setItemCountPerPage(!empty($this->view->limit) ? ($this->view->limit == 'All' ? count($this->view->donations) : $this->view->limit) : 10);
        $paginator->setCurrentPageNumber($page);
        $this->view->paginator = $paginator;

    }

    /**
     * Donation report for admins.
     * Print report for groups and projects
     *
     * @author Matias
     */
    public function donationsAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $this->view->donation_notes = new Brigade_Db_Table_ProjectDonationNotes();

        $params = $this->_getAllParams();
        extract($params);

        if(!empty($params['ProjectId'])) {
            $this->view->isProgram = true;
        }

        // Is taking always get parameter instead of post if we are filtering
        if (isset($_POST['ProjectId'])) {
            $params['ProjectId'] = $_POST['ProjectId'];
        }
        if (!empty($params['search_text'])) {
            $params['search_text'] = trim($params['search_text']);
        }

        if(!empty($params['ProjectId'])) {
            $project = Project::get($params['ProjectId']);
            if(!empty($project->groupId)) {
                $group  =  $project->group;
            }
            if (!empty($project->organizationId)) {
                $this->view->organization = $project->organization;
            }
            $this->view->project      = $project;
            $_REQUEST['pageUrl']      = $project->urlName;
            $this->view->volunteers   = $project->volunteers;
            if (empty($params['search_text']) && empty($params['FromDate']) &&
                empty($params['ToDate'])
            ) {
                $this->view->donations = $project->donations;
            } else {
                $this->view->donations = Donation::getListByProject(
                    $project,
                    (!empty($params['search_text'])) ? $params['search_text'] : false,
                    (!empty($params['FromDate'])) ? $params['FromDate'] : false,
                    (!empty($params['ToDate'])) ? $params['ToDate'] : false
                );
            }
        } else if (!empty($params['GroupId'])) {
            $group                    = Group::get($params['GroupId']);
            $this->view->group        = $group;
            $this->view->organization = $group->organization;
            $_REQUEST['pageUrl']      = $group->urlName;
            $this->view->volunteers   = $group->initiatives[0]->volunteers;
            if (empty($params['search_text']) && empty($params['FromDate']) &&
                empty($params['ToDate'])
            ) {
                $this->view->donations = $group->donations;
            } else {
                $this->view->donations = Donation::getListByGroup(
                    $group,
                    (!empty($params['search_text'])) ? $params['search_text'] : false,
                    (!empty($params['FromDate'])) ? $params['FromDate'] : false,
                    (!empty($params['ToDate'])) ? $params['ToDate'] : false
                );
            }
        }

        if (isset($group)) {
            $this->view->projects = $group->initiatives;
            $this->view->group    = $group;
        }

        //breadcrumb
        $this->view->breadcrumb = $this->view->breadcrumbHelper(
            isset($project) ? $project : $group,
            'Donations'
        );
        $showList = isset($params['show_list']) ? $params['show_list'] : 25;
        $_REQUEST['subPage'] = 'donations';
        if (is_null($this->view->donations)) {
            $this->view->donations = array();
        }
        $this->view->showList = $showList;

        $paginator = Zend_Paginator::factory($this->view->donations);
        $page = $this->_getParam('page', 1);
        $paginator->setItemCountPerPage($showList);
        $paginator->setCurrentPageNumber($page);
        $this->view->paginator = $paginator;

        $this->renderPlaceholders();
    }

    public function loadvolunteersAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        $Volunteers = new Brigade_Db_Table_Volunteers();
        if (isset($parameters['ProjectId'])) {
            $volunteers = $Volunteers->getProjectVolunteers($parameters['ProjectId']);
            echo '<option value="stop">Select One</option>';
            foreach($volunteers as $volunteer) {
                echo '<option value="'.$volunteer['uUserId'].'">'.stripslashes($volunteer['FullName']).'</option>';
            }
        }
    }

    public function volunteersAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }

        $Groups = new Brigade_Db_Table_Groups();
        $Brigades = new Brigade_Db_Table_Brigades();
        $Programs = new Brigade_Db_Table_Programs();
        $Organizations = new Brigade_Db_Table_Organizations();
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $GroupSurveys = new Brigade_Db_Table_GroupSurveys();
        $this->view->volunteer_notes = new Brigade_Db_Table_VolunteerNotes();
        $this->view->project_donations = new Brigade_Db_Table_ProjectDonations();

        $parameters = $this->_getAllParams();
        extract($parameters);

        if (isset($ProjectId) && $ProjectId != '') {
            $project             = Project::get($ProjectId);
            $_REQUEST['pageUrl'] = $project->urlName;

            $this->view->breadcrumb = $this->view->breadcrumbHelper($project, 'Volunteers');
            $this->view->project    =  $project;

            if(!empty($project->groupId)) {
                $group = $project->group;
            }
            $this->view->volunteers = $Volunteers->getProjectVolunteers(
                $ProjectId,
                'active',
                false,
                false,
                isset($search_text) ? $search_text : null
            );

        } else {
            $group               = Group::get($GroupId);
            $_REQUEST['pageUrl'] = $group->urlName;

            //breadcrumb
            $this->view->breadcrumb = $this->view->breadcrumbHelper($group, 'Volunteers');
            $this->view->volunteers = $Volunteers->getVolunteersByGroup($GroupId, 'all', null, null, isset($search_text) ? $search_text : null, isset($ProjectId) && !empty($ProjectId) ? $ProjectId : null);
        }

        if(isset($group)) {
            $this->view->group = $group;

            $this->view->fundraising_activities = $Groups->loadBrigades($group->id, 'all', NULL, NULL, 0);
            $this->view->fundraising_campaigns = $Groups->loadBrigades($group->id, 'all', NULL, NULL, 1);
            $this->view->surveys = $GroupSurveys->getSurveys($group->id, true);
            $this->view->volunteer_class = $Volunteers;

            $this->view->render('group/header.phtml');
            $this->view->render('nonprofit/breadcrumb.phtml');
            $this->view->render('group/tabs.phtml');
            $this->view->render('nonprofit/footer.phtml');

        } else {
            $this->view->header_title = $this->view->data['Name'];
            $this->view->level = 'user';
            $_REQUEST['level'] = 'user';

            $this->view->render('project/header.phtml');
            $this->view->soloProject = true;

        }

        $this->_helper->layout->setLayout('newlayout');

        $_REQUEST['ProjectId'] = isset($ProjectId) ? $ProjectId: null;
        $_REQUEST['search_text'] = isset($search_text) ? $search_text: null;
        $_REQUEST['limit'] = isset($parameters['limit']) ? $parameters['limit']: 10;

        $this->view->total_volunteers = count($this->view->volunteers);

        $_REQUEST['subPage']  =  'volunteers';
        $paginator = Zend_Paginator::factory($this->view->volunteers);
        $page = $this->_getParam('page', 1);
        $paginator->setItemCountPerPage(isset($parameters['limit']) ? $parameters['limit'] : 10);
        $paginator->setCurrentPageNumber($page);
        $this->view->paginator = $paginator;

    }

    public function reportsAction() {}

    public function addnoteAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $DonationNotes = new Brigade_Db_Table_ProjectDonationNotes();
        if ($_POST) {
            $DonationNotes->addDonationNote(array(
                'ProjectDonationId' => isset($_POST['ProjectDonationId']) ? $_POST['ProjectDonationId'] : "",
                'Notes' => $_POST['Notes'],
                'isPrivate' => isset($_POST['isPrivate']) ? 1 : 0,
                'DonorEmail' => isset($_POST['SupporterEmail']) ? $_POST['SupporterEmail'] : ""
            ));
            echo "Note has been successfully added";
        }
    }

    public function editnoteAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $DonationNotes = new Brigade_Db_Table_ProjectDonationNotes();
        if ($_POST) {
            $DonationNotes->editDonationNote($_POST['DonationNoteId'], array(
                'Notes' => $_POST['Notes'],
                'isPrivate' => isset($_POST['isPrivate']) ? 1 : 0
            ));
            echo "Note has been successfully updated";
        }
    }

    public function deletenoteAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $DonationNotes = new Brigade_Db_Table_ProjectDonationNotes();
        if ($_POST) {
            $DonationNotes->deleteDonationNote($_POST['DonationNoteId']);
            echo "Note has been successfully deleted";
        }
    }

    /**
     * Duplicated code with donationcontroller->_sendReceipt
     * TODO: Remove duplicated code.
     */
    public function reemailreceiptAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        if ($_POST) {
            $params      = $this->_getAllParams();
            $donation    = Donation::get($params['ProjectDonationId']);
            $paymentAmnt = $donation->amount;
            if ($donation->paidFees) {
                $paymentAmnt = $paymentAmnt * (1 + ($donation->project->percentageFee/100));
            }
            $message = "Dear {$donation->supporterName},<br /><br />
            Thank you for your donation to {$donation->organization->name}";
            if (!empty($donation->userId) && $donation->user) {
                $message .= " on behalf of ". $donation->user->fullName;
                $share    = "http://www.empowered.org/" . $donation->user->urlName .
                            "/initiatives/" . $donation->project->urlName .
                            "?utm_campaign=DonationReceipt&utm_medium=Email&utm_source=UserAction";
            } else {
                $share = "http://www.empowered.org/" . $donation->project->urlName .
                         "?utm_campaign=DonationReceipt&utm_medium=Email&utm_source=UserAction";
            }

            $ReceiptMessages = new Brigade_Db_Table_ReceiptMessages();
            if ($donation->organizationId == '2A3801E4-203D-11E0-92E6-0025900034B2') {
                $NPMessage = $ReceiptMessages->getMessage($donation->groupId);
            } else {
                $NPMessage = $ReceiptMessages->getMessage($donation->organizationId);
            }
            if ($NPMessage != '') {
                $NPMessage .= '<br /><br />';
            }

            $message .= ". You donation details are as follows:<br /><br />
            Here are your donation details:<br />
            Recipient: {$donation->organization->name}<br />
            Amount: {$donation->project->currency}" . number_format($paymentAmnt, 2) . "<br />
            Donation #: {$donation->transactionId}<br /><br />
            Know someone who would love to help? Share this cause with family and" .
            " friends by sending them this link: $share<br /><br />
            $NPMessage
            Regards,<br />
            {$donation->organization->name}";


            if ($donation->supporterEmail != '') {
                Zend_Registry::get('eventDispatcher')->dispatchEvent(
                    EventDispatcher::$DONATION_RECEIPT,
                    array(
                        $donation->supporterEmail,
                        $message
                    )
                );
            }

            $donation->isReceiptSent = true;
            $donation->save();


            echo "You have successfully re-emailed the donation receipt.";
        }
    }

    /**
     * Create a manual donation
     */
    public function manualentryAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if ($_POST) {
            $params  = $this->_getAllParams();
            $Project = Project::get($params['ProjectId']);

            $donation                    = new Donation();
            $donation->project           = $Project;
            $donation->transactionId     = 'MANUAL'.time();
            $donation->userId            = $_POST['VolunteerId'];
            $donation->amount            = $_POST['DonationAmount'];
            $donation->comments          = (!isset($params['IsPrivate'])) ? $params['Notes'] : '';
            $donation->orderStatusId     = 2;
            $donation->supporterName     = '[Org. Admin] '.$this->view->userNew->fullName;
            $donation->supporterEmail    = '[Org. Admin] '.$this->view->userNew->email;
            $donation->status            = 1; // ?
            $donation->transactionSource = 'Manual';
            $donation->createdOn         = date('Y-m-d H:i:s');
            $donation->createdById       = $this->view->userNew->id;
            $donation->isReceiptSent     = false;
            $donation->isAnonymous       = false;
            $donation->paidFees          = (isset($params['PaidFees']));
            $donation->save();

            if (!empty($params['Notes']) && trim($params['Notes']) != '') {
                $note                    = new DonationNote();
                $note->projectDonationId = $donation->id;
                $note->note              = $params['Notes'];
                $note->isPrivate         = isset($params['IsPrivate']);
                $note->save();
            }
            echo "success";
        }
    }

    public function editdestinationAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if (!$this->view->isAdmin &&
            !in_array($this->sessionUser->id,
            Zend_Registry::get('configuration')->user->manageFunds->toArray())
        ) {
            $this->_helper->redirector('badaccess', 'error');
        }
        if ($_POST) {
            $params   = $this->_getAllParams();
            $donation = Donation::getByTransactionId($params['TransactionId']);

            $donation->userId     = $_POST['VolunteerId'];
            $donation->modifiedBy = $this->sessionUser->id;
            $donation->modifiedOn = date('Y-m-d H:i:s');
            $donation->save();

            echo "success";
        }
    }

    public function fundraisersreportAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        if (isset($parameters['GroupId']) || isset($parameters['NetworkId']) || isset($parameters['ProjectId'])) {
            $Volunteers = new Brigade_Db_Table_Volunteers();
            if (isset($parameters['GroupId']) && !isset($parameters['NetworkId'])) {
                $Groups = new Brigade_Db_Table_Groups();
                $rows = $Volunteers->getFundraisersReport($parameters['GroupId'], 'group', NULL, NULL, isset($_REQUEST['ProjectId']) ? $_REQUEST['ProjectId'] : "");
                $groupinfo = $Groups->loadInfo1($parameters['GroupId']);
                $sitename = str_replace(' ', '-', $groupinfo['GroupName']." Fundraisers Report.xls");
            } else if (isset($parameters['NetworkId'])) {
                $Organizations = new Brigade_Db_Table_Organizations();
                $rows = $Volunteers->getFundraisersReport($parameters['NetworkId'], 'organization', isset($_REQUEST['ProgramId']) ? $_REQUEST['ProgramId'] : "", isset($_REQUEST['GroupId']) ? $_REQUEST['GroupId'] : "", isset($_REQUEST['ProjectId']) ? $_REQUEST['ProjectId'] : "");
                $orginfo = $Organizations->loadInfo($parameters['NetworkId'], false);
                $sitename = str_replace(' ', '-', $orginfo['NetworkName']." Fundraisers Report.xls");
            } else if (isset($parameters['ProjectId'])) {
                $Brigades = new Brigade_Db_Table_Brigades();
                $rows = $Volunteers->getFundraisersReport($parameters['ProjectId'], 'project');
                $projinfo = $Brigades->loadInfo1($parameters['ProjectId'], false);
                $sitename = str_replace(' ', '-', $projinfo['Name']." Fundraisers Report.xls");
            }
            header("Content-type: application/x-msdownload");
            header("Content-Disposition: attachment; filename=$sitename.xls");
            header("Pragma: no-cache");
            header("Expires: 0");
            $headers = '';
            $data = '';
            if ((isset($_REQUEST['ProjectId']) && !empty($_REQUEST['ProjectId']) && isset($parameters['NetworkId'])) || (isset($parameters['ProjectId'])  && !isset($parameters['NetworkId']) && !isset($parameters['GroupId']))) {
                $columns = array('Fundraiser', 'Email Address', 'Fundraising Campaign Supported', 'Fundraised', 'Fundraiser Notes');
            } else {
                $columns = array('Fundraiser', 'Email Address', '# of Fundraising Campaign Supported', 'Total Fundraised', 'Fundraiser Notes');
            }
            foreach($columns as $column) {
                $headers .= $column."\t";
            }
            foreach($rows as $row) {
                $line = '';
                foreach($row as $col =>  $value) {
                    if ($col == 'DonationNotes' && is_array($row[$col])) {
                        $row[$col] = implode(", ", $value);
                    }
                    if ((!isset($value)) || ($value == "") || empty($value)) {
                        $row[$col] = "\t";
                    } else {
                        $row[$col] = str_replace('"', '""', $value);
                        $row[$col] = '"' . $value . '"' . "\t";
                        $row[$col] = stripslashes($row[$col]);
                    }
                }
                extract($row);
                //$DonationNotes = str_replace('\n', ' ', $DonationNotes);
                $line = "$FundraiserName$FundraiserEmail$TotalParicipated$TotalFundraised";
                $data .= trim($line)."\n";
            }
            $data = str_replace("\r","",$data);

            print "$headers\n$data";
        }
    }

    public function exportdonordonationsAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $parameters = $this->_getAllParams();
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        $Users = new Brigade_Db_Table_Users();
        $Projects = new Brigade_Db_Table_Brigades();
        if (isset($parameters['GroupId']) && (isset($parameters['SupporterEmail']) || isset($parameters['UserId']))) {
            $GroupId = $parameters['GroupId'];
            if (isset($parameters['SupporterEmail'])) {
                $rows = $ProjectDonations->getDonorDonations($parameters['SupporterEmail'], $GroupId, 'group', '', '', isset($parameters['ProjectId']) ? $parameters['ProjectId'] : '');
                header("Content-Disposition: attachment; filename=Donor-Donations-Report.xls");
            } else if (isset($parameters['UserId'])) {
                $rows = $ProjectDonations->getUserDonationsBySite($parameters['UserId'], $GroupId);
                header("Content-Disposition: attachment; filename=Fundraiser-Donations-Report.xls");
            }
            header("Content-type: application/x-msdownload");
            header("Pragma: no-cache");
            header("Expires: 0");
            $headers = '';
            $data = '';
            $columns = array('Transaction ID', 'Donation Amount', 'Donation Destination', 'Donation Date');
            foreach($columns as $column) {
                $headers .= $column."\t";
            }
            foreach($rows as $row) {
                $line = '';
                if(!empty($row['VolunteerId'])) {
                    $userInfo = $Users->loadInfo($row['VolunteerId']);
                    $Recipient = '"' . $userInfo['FullName'] . '"' . "\t";
                } else {
                    $projInfo = $Projects->loadInfo1($row['ProjectId']);
                    $Recipient = '"' . $projInfo['Name'] . '"' . "\t";
                }
                foreach($row as $col =>  $value) {
                    if ((!isset($value)) || ($value == "") || empty($value)) {
                        $row[$col] = "\t";
                    } else {
                        $row[$col] = str_replace('"', '""', $value);
                        $row[$col] = '"' . $value . '"' . "\t";
                        $row[$col] = stripslashes($row[$col]);
                    }
                }
                $Recipient = stripslashes($Recipient);
                extract($row);
                $line = "$TransactionId$DonationAmount$Recipient$CreatedOn";
                $data .= trim($line)."\n";
            }
            $data = str_replace("\r","",$data);

            print "$headers\n$data";
        } else if (isset($parameters['GroupId'])) {
            $GroupId = $parameters['GroupId'];
            $rows = $ProjectDonations->getSiteDonorDonationsReport($GroupId, 'group', isset($parameters['ProjectId']) ? $parameters['ProjectId'] : NULL);
            header("Content-Disposition: attachment; filename=Donor-Donations-Report.xls");
            header("Content-type: application/x-msdownload");
            header("Pragma: no-cache");
            header("Expires: 0");
            $headers = '';
            $data = '';
            $columns = array('Donor Name', 'Donor Email', 'Projects Supported', 'Total Donated', 'Donor Notes');
            foreach($columns as $column) {
                $headers .= $column."\t";
            }
            foreach($rows as $row) {
                $line = '';
                foreach($row as $col =>  $value) {
                    if ((!isset($value)) || ($value == "") || empty($value)) {
                        $row[$col] = "\t";
                    } else {
                        $row[$col] = str_replace('"', '""', $value);
                        $row[$col] = '"' . $value . '"' . "\t";
                        $row[$col] = stripslashes($row[$col]);
                    }
                }
                $row['Notes'] = "\t";
                extract($row);
                $line = "$SupporterName$SupporterEmail$ProjectSupported$TotalDonation";
                $data .= trim($line)."\n";
            }
            $data = str_replace("\r","",$data);

            print "$headers\n$data";
        } else if (isset($parameters['ProjectId']) && (isset($parameters['SupporterEmail']) || isset($parameters['UserId']))) {
            $rows = $ProjectDonations->getSiteDonorDonationsReport($parameters['ProjectId'], 'project');
            if (isset($parameters['SupporterEmail'])) {
                $rows = $ProjectDonations->getDonorDonations($parameters['SupporterEmail'], $parameters['ProjectId'], 'activity', '', '', isset($parameters['ProjectId']) ? $parameters['ProjectId'] : '');
                header("Content-Disposition: attachment; filename=Donor-Donations-Report.xls");
            } else if (isset($parameters['UserId'])) {
                $rows = $ProjectDonations->getUserDonationsBySite($parameters['UserId'], $parameters['ProjectId'], 'project');
                header("Content-Disposition: attachment; filename=Fundraiser-Donations-Report.xls");
            }
            header("Content-Disposition: attachment; filename=Donor-Donations-Report.xls");
            header("Content-type: application/x-msdownload");
            header("Pragma: no-cache");
            header("Expires: 0");
            $headers = '';
            $data = '';
            $columns = array('Transaction ID', 'Donation Amount', 'Donation Destination', 'Donation Date');
            foreach($columns as $column) {
                $headers .= $column."\t";
            }
            foreach($rows as $row) {
                $line = '';
                if(!empty($row['VolunteerId'])) {
                    $userInfo = $Users->loadInfo($row['VolunteerId']);
                    $Recipient = '"' . $userInfo['FullName'] . '"' . "\t";
                } else {
                    $projInfo = $Projects->loadInfo1($row['ProjectId']);
                    $Recipient = '"' . $projInfo['Name'] . '"' . "\t";
                }
                foreach($row as $col =>  $value) {
                    if ((!isset($value)) || ($value == "") || empty($value)) {
                        $row[$col] = "\t";
                    } else {
                        $row[$col] = str_replace('"', '""', $value);
                        $row[$col] = '"' . $value . '"' . "\t";
                        $row[$col] = stripslashes($row[$col]);
                    }
                }
                $Recipient = stripslashes($Recipient);
                extract($row);
                $line = "$TransactionId$DonationAmount$Recipient$CreatedOn";
                $data .= trim($line)."\n";
            }
            $data = str_replace("\r","",$data);

            print "$headers\n$data";
        } else if (isset($parameters['ProjectId'])) {
            $rows = $ProjectDonations->getSiteDonorDonationsReport($parameters['ProjectId'], 'project');
            header("Content-Disposition: attachment; filename=Donor-Donations-Report.xls");
            header("Content-type: application/x-msdownload");
            header("Pragma: no-cache");
            header("Expires: 0");
            $headers = '';
            $data = '';
            $columns = array('Donor Name', 'Donor Email', 'Project Supported', 'Total Donated', 'Donor Notes');
            foreach($columns as $column) {
                $headers .= $column."\t";
            }
            foreach($rows as $row) {
                $line = '';
                foreach($row as $col =>  $value) {
                    if ((!isset($value)) || ($value == "") || empty($value)) {
                        $row[$col] = "\t";
                    } else {
                        $row[$col] = str_replace('"', '""', $value);
                        $row[$col] = '"' . $value . '"' . "\t";
                        $row[$col] = stripslashes($row[$col]);
                    }
                }
                $row['Notes'] = "\t";
                extract($row);
                $line = "$SupporterName$SupporterEmail$ProjectSupported$TotalDonation";
                $data .= trim($line)."\n";
            }
            $data = str_replace("\r","",$data);

            print "$headers\n$data";
        }
    }

    private function renderPlaceholders() {
        if (isset($this->view->group)) {
            $this->view->render('group/header.phtml');
            $this->view->render('group/tabs.phtml');
        } elseif (isset($this->view->project)) {
            $this->view->render('project/header.phtml');
            $this->view->render('nonprofit/tabs.phtml');
        } else {
            $this->view->render('nonprofit/header.phtml');
            $this->view->render('nonprofit/tabs.phtml');
        }
        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('nonprofit/footer.phtml');

        $this->_helper->layout->setLayout('newlayout');
    }

}
