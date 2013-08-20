<?php

/**
 * ReportingController - The "reporting" controller class
 *
 * @author
 * @version
 */

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/Organizations.php';
require_once 'Brigade/Db/Table/Programs.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Reporting/FusionCharts.php';
require_once 'Brigade/Reporting/FusionCharts/MSLine.php';
require_once 'Brigade/Util/Auth.php';
require_once 'Brigade/Util/DateTime.php';
require_once 'BaseController.php';

require_once 'Group.php';
require_once 'MembershipStat.php';

class ReportingController extends BaseController {

    public function init() {
        $parameters = $this->_getAllParams();
        if (isset($parameters['Type']) && isset($parameters['SiteId'])) {
            $_GET[$parameters['Type'].'Id'] = $parameters['SiteId'];
        }
        parent::init();
    }

    public function indexAction() {

    }

    public function donationAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }

        //$this->_helper->layout->disableLayout();
        $parameters = $this->_getAllParams();

        $Organizations = new Brigade_Db_Table_Organizations();
        $SiteId = $parameters['SiteId'];
        $this->view->data = $Organizations->loadInfo($SiteId);

        if (isset($parameters['Type'])){
            $this->view->Type = $parameters['Type'];
        } else {
            $this->view->Type = "";
        }
        $this->view->SiteId = $SiteId;

    }

    public function preDispatch() {
        $this->_helper->layout->disableLayout();
        parent::preDispatch();
    }

    public function exportAction() {
        if(!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        ini_set("memory_limit", "512M");
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        if ((isset($parameters['SiteId']) || isset($parameters['UserId'])) && isset($parameters['Type'])) {
            $StartDate = '';
            $EndDate = '';
            if(isset($parameters['StartDate']) && isset($parameters['EndDate'])) {
                $StartDate = date('Y-m-d 00:00:00', strtotime($parameters['StartDate']));
                $EndDate = date('Y-m-d 23:59:59', strtotime($parameters['EndDate']));
            }
            if ($parameters['Type'] == 'nonprofit' || $parameters['Type'] == 'Organization') {
                $Organizations = new Brigade_Db_Table_Organizations();
                $donations = $Organizations->getDonationReport($parameters['SiteId'], $StartDate, $EndDate, isset($_REQUEST['ProgramId']) ? $_REQUEST['ProgramId'] : "", isset($_REQUEST['GroupId']) ? $_REQUEST['GroupId'] : "", isset($_REQUEST['ProjectId']) ? $_REQUEST['ProjectId'] : "", isset($_REQUEST['search_text']) ? $_REQUEST['search_text'] : "");
                $orgInfo = $Organizations->loadInfo($parameters['SiteId'], false);
                if (!$orgInfo['hasDownloadedReports']) {
                    $Organizations->editNetwork($parameters['SiteId'], array('hasDownloadedReports' => 1));
                }
            } else if ($parameters['Type'] == 'Program') {
                $Programs = new Brigade_Db_Table_Programs();
                $donations = $Programs->getDonationReport($parameters['SiteId'], $StartDate, $EndDate);
            } else if ($parameters['Type'] == 'Group') {
                $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
                $donations = $ProjectDonations->getGroupDonations($parameters['SiteId'], '', true, '', isset($StartDate) ? $StartDate : '', isset($EndDate) ? $EndDate : '', isset($parameters['ProjectId']) ? $parameters['ProjectId'] : NULL);
            } else if ($parameters['Type'] == 'Project') {
                set_time_limit(0);
                $Brigades = new Brigade_Db_Table_Brigades();
                $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
                $donations = $Brigades->getDonationReport($parameters['SiteId'], isset($StartDate) ? $StartDate : '', isset($EndDate) ? $EndDate : '');
                $general_group_donations['total'] = $ProjectDonations->getUserProjectDonationsByStatus("", $parameters['SiteId'], isset($StartDate) ? $StartDate : '', isset($EndDate) ? $EndDate : '', 'all');
                $general_group_donations['pending'] = $ProjectDonations->getUserProjectDonationsByStatus("", $parameters['SiteId'], isset($StartDate) ? $StartDate : '', isset($EndDate) ? $EndDate : '', 'being processed');
                $general_group_donations['processed'] = $ProjectDonations->getUserProjectDonationsByStatus("", $parameters['SiteId'], isset($StartDate) ? $StartDate : '', isset($EndDate) ? $EndDate : '', 'processed');
            } else if ($_POST['Type'] == 'User') {
                $Users = new Brigade_Db_Table_Users();
                $donations = $Brigades->getDonationReport($parameters['UserId']);
            }
            header("Content-type: application/x-msdownload");
            header("Content-Disposition: attachment; filename=donationreport.xls");
            header("Pragma: no-cache");
            header("Expires: 0");
            $headers = '';
            $data = '';
            if ($parameters['Type'] == 'Project') {
                $columns = array('Volunteer', 'Being Processed', 'Processed', 'Total Raised', 'Donation Goal');
            } else if ($parameters['Type'] == 'nonprofit' || $parameters['Type'] == 'user') {
                $columns = array('Transaction ID', 'Program Name', 'Chapter Name', 'Activity Name', 'Activity Date', 'Volunteer Name', 'Donation Amount', 'Donor Name', 'Donor Email', 'Donation Comments', 'Created On', 'Modified On', 'Order Status');
                if($parameters['Type'] == 'nonprofit') {
                    $columns[] = 'Donation Notes';
                }
            } else if ($parameters['Type'] == 'Group') {
                $columns = array('Transaction ID', 'Donation Amount', 'Donation Destination', 'Supporter Name', 'Supporter Email', 'DonationComments', 'Date');
            } else {
                $columns = array('Transaction Id', 'Donation Amount', 'Volunteer', 'Supporter Email', 'Supporter Name', 'Donation Comments', 'Donation Date', 'Name', 'Start Date', 'End Date', 'Chapter Name', 'Program Name', 'Order Status', 'Donation Notes');
            }
            foreach($columns as $column) {
                $headers .= $column."\t";
            }
            foreach($donations as $donation) {
                $line = '';
                if ($parameters['Type'] == 'Group') {
                    if(!empty($donation['VolunteerId'])) {
                        $Users = new Brigade_Db_Table_Users();
                        $userInfo = $Users->loadInfo($donation['VolunteerId']);
                        $Destination = '"' . stripslashes($userInfo['FullName']) . '"' . "\t";
                    } else {
                        $Projects = new Brigade_Db_Table_Brigades();
                        $projInfo = $Projects->loadInfo1($donation['ProjectId']);
                        $Destination = '"' . stripslashes($projInfo['Name']) . '"' . "\t";
                    }
                }
                foreach($donation as $col =>  $value) {
                    if ($col == 'DonationComments') {
                        if (is_array($value)) {
                            $donation[$col] = implode(", ", $value);
                        }
                    }
                    if ((!isset($value)) || ($value == "") || empty($value)) {
                        $donation[$col] = "\t";
                    } else {
                        $donation[$col] = str_replace('"', '""', $value);
                        $donation[$col] = '"' . $value . '"' . "\t";
                    }
                }
                extract($donation);
                if ($parameters['Type'] == 'Project') {
                    $line = "$Volunteer$BeingProcessed$Processed$AmountRaised$DonationGoal";
                } else if ($parameters['Type'] == 'nonprofit') {
                    $DonationComments = str_replace('\n', ' ', $DonationComments);
                    $line = "$TransactionId$ProgramName$GroupName$Name$BrigadeDate$Volunteer$DonationAmount$SupporterName$SupporterEmail$DonationComments$DonatedOn$ModifiedOn$OrderStatusName$DonationNotes";
                } else if ($parameters['Type'] == 'Group') {
                    $line = "$TransactionId$DonationAmount".$Destination."$SupporterName$SupporterEmail$DonationComments$DonationDate";
                } else {
                    $DonationComments = str_replace('\n', ' ', $DonationComments);
                    $line = "$TransactionId$DonationAmount$Volunteer$SupporterEmail$SupporterName$DonationComments$CreatedOn$Name$StartDate$EndDate$GroupName$ProgramName$OrderStatusName$DonationNotes";
                }
                $data .= trim($line)."\n";
            }
            if ($parameters['Type'] == 'Project') {
                extract($general_group_donations);
                $line = "General Chapter Donations\t$pending\t$processed\t$total\t";
                $data .= trim($line)."\n";
            }
            $data = str_replace("\r","",$data);

           print "$headers\n$data";
        }
    }


    /**
     * Generate Report for Organization donations.
     * Refactored Method
     *
     * @matias
     */
    public function export2Action() {
        ini_set("memory_limit", "1024M");
        set_time_limit(900);
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params    = $this->_getAllParams();
        $StartDate = false;
        $EndDate   = false;
        $Search    = false;
        $filter    = false;
        if(!$this->view->isAdmin || (!(isset($params['SiteId']) ||
            isset($params['UserId'])) && !isset($params['Type']))
        ) {
            $this->_helper->redirector('badaccess', 'error');
        }
        if (!empty($params['search_text']) || !empty($params['StartDate'])
            || !empty($params['EndDate'])
        ) {
            $filter = true;
        }
        if ($params['Type'] == 'nonprofit' ||
            $params['Type'] == 'Organization')
        {
            $organization = Organization::get($params['SiteId']);
            if ($filter) {
                $donations = Donation::getListByOrganization(
                    $organization,
                    !empty($params['search_text']) ? $params['search_text'] : false,
                    (!empty($params['StartDate'])) ? $params['StartDate'] : false,
                    (!empty($params['EndDate'])) ? $params['EndDate'] : false
                );
            } else {
                $donations = $organization->donations;
            }
            if (!$organization->hasDownloadedReports) {
                $organization->hasDownloadedReports = true;
                $organization->save();
            }
        } else if ($params['Type'] == 'Program') {
            $program = Program::get($params['SiteId']);
            if ($filter) {
                $donations = Donation::getListByProgram(
                    $program,
                    !empty($params['search_text']) ? $params['search_text'] : false,
                    (!empty($params['StartDate'])) ? $params['StartDate'] : false,
                    (!empty($params['EndDate'])) ? $params['EndDate'] : false
                );
            } else {
                $donations = $program->donations;
            }
        } else if ($params['Type'] == 'Group') {
            $group = Group::get($params['SiteId']);
            if ($filter) {
                $donations = Donation::getListByGroup(
                    $organization,
                    !empty($params['search_text']) ? $params['search_text'] : false,
                    (!empty($params['StartDate'])) ? $params['StartDate'] : false,
                    (!empty($params['EndDate'])) ? $params['EndDate'] : false
                );
            } else {
                $donations = $group->donations;
            }
        } else if ($params['Type'] == 'Project') {
            $project = Project::get($params['SiteId']);
            if ($filter) {
                $donations = Donation::getListByProject(
                    $project,
                    !empty($params['search_text']) ? $params['search_text'] : false,
                    (!empty($params['StartDate'])) ? $params['StartDate'] : false,
                    (!empty($params['EndDate'])) ? $params['EndDate'] : false
                );
            } else {
                $donations = $project->donations;
            }
        } else if ($_POST['Type'] == 'User') {
            $Users = new Brigade_Db_Table_Users();
            $donations = $Brigades->getDonationReport($params['UserId']);
        }

        header("Content-type: application/x-msdownload");
        header("Content-Disposition: attachment; filename=donationreport.xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        $headers = '';
        $data    = '';
        $columns = array(
            'Transaction ID',
            'Program',
            'Group',
            'Initiative',
            'Start Date',
            'End Date',
            'Donation Amount',
            'Donation Destination',
            'Supporter Name',
            'Supporter Email',
            'Comments',
            'Date',
            'Order Status'
        );
        foreach($columns as $column) {
            $headers .= '"'.$column.'"'."\t";
        }
        print $headers ."\n";
        foreach($donations as $k=>$donation) {
            $line  = '"'.$donation->transactionId.'"'."\t";
            if (!empty($donation->programId)) {
                $line .= '"'.$donation->program->name.'"'."\t";
            } else {
                $line .= '""'."\t";
            }
            if (!empty($donation->groupId)) {
                $line .= '"'.$donation->group->name.'"'."\t";
            } else {
                $line .= '""'."\t";
            }
            if (!empty($donation->projectId)) {
                $line .= '"'.$donation->project->name.'"'."\t";
                $line .= '"'.$donation->project->startDate.'"'."\t";
                $line .= '"'.$donation->project->endDate.'"'."\t";
            } else {
                $line .= '""'."\t";
                $line .= '""'."\t";
                $line .= '""'."\t";
            }
            $line .= '"'.$donation->amount.'"'."\t";
            $line .= '"'.$donation->destination.'"'."\t";
            $line .= '"'.$donation->supporterName.'"'."\t";
            $line .= '"'.$donation->supporterEmail.'"'."\t";
            $line .= '"'.str_replace("\n","",$donation->comments).'"'."\t";
            $line .= '"'.$donation->createdOn.'"'."\t";
            $line .= '"'.$donation->orderStatus.'"'."\n";
            print str_replace("\r","",$line);
        }
    }

    public function loadgroupsAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Groups = new Brigade_Db_Table_Groups();
        $parameters = $this->_getAllParams();
        if (isset($parameters['ProgramId'])) {
            $list = '<option value="">Chapter: All</option>';
            if (!empty($parameters['ProgramId'])) {
                $groups = $Groups->listByProgram($parameters['ProgramId']);
                foreach($groups as $group) {
                    $list .= '<option value="'.$group['GroupId'].'">Chapter: '.stripslashes($group['GroupName']).'</option>';
                }
            }
            echo $list;
        }
    }

    public function loadprojectsAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Groups = new Brigade_Db_Table_Groups();
        $parameters = $this->_getAllParams();
        if (isset($parameters['GroupId'])) {
            $list = '<option value="">Activity or Campaign: All</option>';
            if (!empty($parameters['GroupId'])) {
                $projects = $Groups->loadBrigades($parameters['GroupId'], 'all');
                foreach($projects as $project) {
                    $list .= '<option value="'.$project['ProjectId'].'">';
                    if($project['Type'] == 1) {
                        $list .= 'Campaign: ';
                    } else {
                        $list .= 'Activity: ';
                    }
                    $list .= stripslashes($project['Name']).'</option>';
                }
            }
            echo $list;
        }
    }

    public function statAction(){
        if(!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        try {
            $parameters = $this->_getAllParams();
            $Groups = new Brigade_Db_Table_Groups();
            $Brigades = new Brigade_Db_Table_Brigades();
            $Organizations = new Brigade_Db_Table_Organizations();
            $Programs = new Brigade_Db_Table_Programs();
            $this->view->sitemedia = new Brigade_Db_Table_Media();
            $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();

            if(!empty($parameters['ProjectId'])) {
                $project =  Project::get($parameters['ProjectId']);

                if(!empty($project->groupId)) {
                    $group = $project->group;
                }
                if(!empty($project->organizationId)) {
                    $organization = $project->organization;
                }
            }
            if(!empty($parameters['GroupId']) && !isset($group)) {
                $group        = Group::get($parameters['GroupId']);
                if (!isset($organization)) {
                    $organization = $group->organization;
                    $this->view->level = 'group';
                }
            }
            if(!empty($parameters['ProgramId'])) {
                $program      = Program::get($parameters['ProgramId']);
                if (!isset($organization)) {
                    $organization = $program->organization;
                    $this->view->level = 'organization';
                }
            } else if(!empty($parameters['NetworkId'])) {
                $organization  =  Organization::get($parameters['NetworkId']);
                $this->view->level = 'organization';
            }
            $this->view->organization = $organization;
            $this->view->breadcrumb = $this->view->breadcrumbHelper($organization, 'Reporting');
            if (isset($project)) {
                $this->view->project      =  $project;
                $this->view->header_title = $project->name;
                $this->view->breadcrumb = $this->view->breadcrumbHelper($project, 'Reporting');
            }
            if(isset($program)) {
                $this->view->program = $program;
            }


            if(isset($group)) {
                $this->view->breadcrumb = $this->view->breadcrumbHelper($group, 'Reports');
                $this->view->group  = $group;
                $this->view->filter = $filter = "";
                if(isset($parameters['activity_id']) || isset($parameters['campaign_id'])) {
                    $this->view->filter = $filter = isset($parameters['activity_id']) ? $parameters['activity_id']: $parameters['campaign_id'];
                }

                //dropdown list
                $this->view->activityList = $Groups->loadBrigades($group->id, 'all', NULL, NULL, 3);
                $this->view->campaignList = $Brigades->listGroupCampaigns($group->id);

                $this->view->render('group/header.phtml');
                $this->view->render('group/tabs.phtml');
                $this->view->render('nonprofit/breadcrumb.phtml');

            } else if(isset($organization)) {
                if ($organization->hasPrograms == 1) {
                    $this->view->programs = $Programs->simpleListByNetwork($organization->id);
                    if (isset($_REQUEST['ProgramId'])) {
                        $this->view->groups = $Groups->simpleListByProgram($_REQUEST['ProgramId']);
                    }
                } else {
                    $this->view->groups = $Groups->getNetworkGroups($organization->id, 0);
                }
                if (isset($_POST['GroupId']) && !empty($_POST['GroupId'])) {
                    $this->view->projects = $Groups->loadBrigades($_POST['GroupId'], 'all');
                }
                if (isset($_POST['ProjectId']) && !empty($_POST['ProjectId']) && !isset($_POST['GroupId']) && !isset($_POST['ProgramId'])) {
                    $projectInfo = $Brigades->loadBrigadeTreeInfo($_POST['ProjectId']);
                    $this->view->groups = $Groups->simpleListByProgram($projectInfo['ProgramId']);
                    $this->view->projects = $Groups->loadBrigades($projectInfo['GroupId'], 'all');
                    $_POST['ProgramId'] = $projectInfo['ProgramId'];
                    $_POST['GroupId'] = $projectInfo['GroupId'];
                }

                $Media = new Brigade_Db_Table_Media();
                $this->view->siteBanner = false;
                if (!empty($organization->bannerMediaId)) {
                    $siteBanner = $Media->getSiteMediaById($organization->bannerMediaId);
                    $this->view->siteBanner = $siteBanner;
                    $this->view->siteBanner['path'] = "/public/Photos/banner/".$siteBanner['SystemMediaName'];
                } else {
                    $siteMedia = $Media->getSiteMediaById($organization->logoMediaId);
                }
                $this->view->render('nonprofit/header.phtml');
                $this->view->render('nonprofit/breadcrumb.phtml');
                $this->view->render('nonprofit/tabs.phtml');
            } else {
                $this->view->level = 'user';
            }

            $this->view->render('nonprofit/footer.phtml');
            $this->_helper->layout->setLayout('newlayout');

            //date range
            $this->view->fundraising_date_from = $fundraising_date_from = $this->_getParam('fundraising_date_from', date("Y-m-d", strtotime("-1 month")));
            $this->view->fundraising_date_to = $fundraising_date_to = $this->_getParam('fundraising_date_to', date("Y-m-d"));
            $this->view->supporters_date_from = $supporters_date_from = $this->_getParam('supporters_date_from', date("Y-m-d", strtotime("-1 month")));
            $this->view->supporters_date_to = $supporters_date_to = $this->_getParam('supporters_date_to', date("Y-m-d"));

            //fundrasing data
            $projdonationsManage = new Brigade_Db_Table_ProjectDonations();
            if ($parameters['Level'] == 'group') {
                $dataNew = $projdonationsManage->getdailyGroupDonations($group->id, $fundraising_date_from, $fundraising_date_to, $filter, 'pd.CreatedOn ASC');
            } else if ($parameters['Level'] == 'organization') {
                $dataNew = $projdonationsManage->getDailyNetworkDonations($organization->id, $fundraising_date_from, $fundraising_date_to, 'pd.CreatedOn ASC', isset($program) ? $program->id : NULL, isset($parameters['GroupId']) ? $parameters['GroupId'] : NULL, isset($parameters['ProjectId']) ? $parameters['ProjectId'] : NULL);
            } else {
                $dataNew = $projdonationsManage->getDailyProjectDonations($parameters['ProjectId'], $fundraising_date_from, $fundraising_date_to, 'pd.CreatedOn ASC');
            }

            $fundraisingchart = new Brigade_Reporting_FusionCharts_MSLine();
            $fundraisingchart ->setBaseFontSize('10')
                ->setDivLineThickness(0)
                ->setShowValues(0)
                ->setNumberPrefix('$')
                ->setShowLabels(1)
                ->setShowYAxisValues(1)
                ->setFormatNumberScale(0)
                ->setDivLineColor('CCCCCC')
                ->setDivLineIsDashed(0)
                ->setDivLineDashLen(1)
                ->setDivLineDashGap(1)
                ->setShowAlternateHGridColor(0)
                ->setAlternateHGridAlpha(5)
                ->setAlternateHGridColor('0066CC')
                ->setShadowAlpha(2)
                ->setLabelStep(7)
                ->setNumVDivLines(0)
                ->setBgColor('FFFFFF')
                ->setBgAngle(270)
                ->setBgAlpha('10')
                ->setLabelDisplay('Rotate')
                ->setSlantLabels(1)
                ->setShowLegend(1)
                ->setLegendCaption("Connections")
                ->setLegendPosition('Bottom')
                ->setLegendMarkerCircle(0)
                ->setLegendBorderColor('FF0000')
                ->setLineThickness(2)
                ->setReverseLegend(1)
                ->setShowBorder(0)
                ->setCanvasLeftMargin(0)
                ->setCanvasRightMargin(0)
                ->setChartLeftMargin(10)
                ->setChartRightMargin(5)
                ->setChartBottomMargin(0)
                ->setChartTopMargin(5)
                ->setCanvasBorderColor('CCCCCC')
                ->setCanvasBorderThickness(1)
                ->setAnchorRadius(3);

            $dateManage = new Brigades_Util_DateTime();
            $days = $dateManage->DateRangeArray($fundraising_date_from, $fundraising_date_to, "Y-m-d");

            foreach($dataNew as $row) {
                $dataNew[$row['timestamp']] = $row;
            }

            $donation = array();
            foreach ($days as $day){
                $fundraisingchart->createCategories()
                    ->setLabel(date("M d", strtotime($day)));
                if (!empty($dataNew[$day])) {
                    $donation[] = $dataNew[$day]['donation'];
                } else {
                    $donation[] = 0;
                }
            }

            $fundraisingchart->createDataset()
                ->setId('dataset1')
                ->setColor('008CFF')
                ->setAnchorBgColor('008CFF')
                ->setAnchorBorderColor('FFFFFF')
                ->setAnchorBorderThickness(1);
            foreach($donation as $ch){
                $fundraisingchart->createSet()
                    ->setValue($ch)
                    ->setId('dataset1');
            }

            $chartId1 = 'fundraisingChart';
            $chartWidth = '100%';
            $chartHeight = '250px';

            $this->view->fundraisingchart = $fundraisingchart->render($chartId1,$chartWidth,$chartHeight);

            //supporters data
            $supporterList = array("1" => "Volunteers", "2" => "Donors");
            $supporters_data=array();
            $supporters_dataNew = array();
            /*1. New volunteers */
            $volunteerManage = new Brigade_Db_Table_Volunteers();
            if ($parameters['Level'] == 'group') {
                $volunteer_data = $volunteerManage->getdailyVolunteers($group->id,$supporters_date_from,$supporters_date_to,$filter,'CreatedOn ASC');
            } else if ($parameters['Level'] == 'organization') {
                $volunteer_data = $volunteerManage->getDailyNetworkVolunteers($organization->id, $supporters_date_from, $supporters_date_to, 'CreatedOn ASC', isset($program) ? $program->id : NULL, isset($parameters['GroupId']) ? $parameters['GroupId'] : NULL, isset($parameters['ProjectId']) ? $parameters['ProjectId'] : NULL);
            } else {
                $volunteer_data = $volunteerManage->getDailyProjectVolunteers($parameters['ProjectId'], $supporters_date_from, $supporters_date_to, 'CreatedOn ASC');
            }

            foreach($volunteer_data as $data){
                $supporters_data[1][$data['timestamp']] = $data;
            }

            /*2. Donors */
            if ($parameters['Level'] == 'group') {
                $donor_data = $projdonationsManage->getdailyGroupDonor($group->id,$supporters_date_from,$supporters_date_to,$filter,'CreatedOn ASC');
            } else if ($parameters['Level'] == 'organization') {
                $donor_data = $projdonationsManage->getDailyNetworkDonor($organization->id, $supporters_date_from, $supporters_date_to, 'CreatedOn ASC', isset($program) ? $program->id : NULL, isset($parameters['GroupId']) ? $parameters['GroupId'] : NULL, isset($parameters['ProjectId']) ? $parameters['ProjectId'] : NULL);
            } else {
                $donor_data = $projdonationsManage->getDailyProjectDonor($parameters['ProjectId'], $supporters_date_from, $supporters_date_to, 'CreatedOn ASC');
            }
            foreach($donor_data as $data) {
                $supporters_data[2][$data['timestamp']] = $data;
            }
            /*3. New Members (only for group/ should be no filters) */
//            if (empty($filter)){
//              $supporterList[3] = "Members";
//              $memberManage = new Brigade_Db_Table_GroupMembers();
//              $member_data = $memberManage->getDailyGroupMembers($groupId,$supporters_date_from,$supporters_date_to,'JoinedOn ASC');
//                foreach($member_data as $data){
//                  $supporters_data[3][$data['timestamp']] = $data;
//                }
//            }

            $supporterschart = new Brigade_Reporting_FusionCharts_MSLine();
            $supporterschart ->setBaseFontSize('10')
                ->setDivLineThickness(0)
                ->setShowValues(0)
                ->setShowLabels(1)
                ->setShowYAxisValues(1)
                ->setFormatNumberScale(0)
                ->setDivLineColor('CCCCCC')
                ->setDivLineIsDashed(0)
                ->setDivLineDashLen(1)
                ->setDivLineDashGap(1)
                ->setShowAlternateHGridColor(0)
                ->setAlternateHGridAlpha(5)
                ->setAlternateHGridColor('0066CC')
                ->setShadowAlpha(2)
                ->setLabelStep(7)
                ->setNumVDivLines(0)
                ->setBgColor('FFFFFF')
                ->setBgAngle(270)
                ->setBgAlpha('10')
                ->setLabelDisplay('Rotate')
                ->setSlantLabels(1)
                ->setShowLegend(0)
                ->setLegendCaption("Connections")
                ->setLegendPosition('Bottom')
                ->setLegendMarkerCircle(0)
                ->setLegendBorderColor('FF0000')
                ->setLineThickness(2)
                ->setReverseLegend(1)
                ->setShowBorder(0)
                ->setCanvasLeftMargin(0)
                ->setCanvasRightMargin(0)
                ->setChartLeftMargin(10)
                ->setChartRightMargin(5)
                ->setChartBottomMargin(0)
                ->setChartTopMargin(5)
                ->setCanvasBorderColor('CCCCCC')
                ->setCanvasBorderThickness(1)
                ->setLegendBorderThickness(0)
                ->setLegendBorderColor('FFFFFF')
                ->setAnchorRadius(3);

            $days2 = $dateManage->DateRangeArray($supporters_date_from, $supporters_date_to);
            $colors = array("1" => "008CFF", "2" => "FF0000", "3" => "00CC00");

            $highest = 5;
            foreach ($days2 as $day) {
                $supporterschart->createCategories()
                    ->setLabel(date("M d", strtotime($day)));
                foreach ($supporterList as $key => $value) {
                    if (isset($supporters_data[$key][$day])){
                        $supporters_dataNew[$key][] = $supporters_data[$key][$day]['count'];
                        if ($supporters_data[$key][$day]['count'] > $highest ) {
                            $highest = $supporters_data[$key][$day]['count'];
                        }
                    } else {
                        $supporters_dataNew[$key][] = 0;
                    }
                }
            }

            if ($highest = 5){
                $supporterschart ->setyAxisMaxValue($highest);
            }

            foreach ($supporterList as $key => $value) {
                $supporterschart->createDataset()
                    ->setId($value)
                    ->setSeriesName($value)
                    ->setColor($colors[$key])
                    ->setAnchorBgColor($colors[$key])
                    ->setAnchorBorderColor('FFFFFF')
                    ->setAnchorBorderThickness(1);
            }

            foreach($supporters_dataNew as $key => $val){
                foreach($val as $fkey => $fval){
                    $supporterschart->createSet()
                        ->setId($supporterList[$key])
                        ->setValue($fval);
                }
            }

            $chartId2 = 'suportersChart';
            $this->view->supporterschart = $supporterschart->render($chartId2,$chartWidth,$chartHeight);

        }
        catch (Exception $e) {
            $this->view->error = $e->getMessage();
        }
    }

    /**
     * Generate gift aid csv report.
     *
     */
    public function giftaidAction() {
        $params = $this->_getAllParams();
        $org    = Organization::get($params['NetworkId']);
        if (!$org->hasGiftAid()) {
            $this->_helper->redirector('badaccess', 'error');
        }

        $list = GiftAid::getListByOrganization($org);
    }

    /**
     * Report for transfered membership organization level
     */
    public function membershiptransfersexportAction() {
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params = $this->_getAllParams();
        $org    = Organization::get($params['OrgId']);
        $funds  = MembershipFund::getListByOrg($org);


        header("Content-type: application/x-msdownload");
        header("Content-Disposition: attachment; filename=membershipreport.xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        $headers = '';
        $data    = '';
        $columns = array(
            'Chapter',
            'Initiative Destination',
            'Date',
            'Made By',
            'Amount Transfered'
        );
        foreach($columns as $column) {
            $headers .= '"'.$column.'"'."\t";
        }
        print $headers ."\n";

        if (count($funds) > 0) {
            foreach ($funds as $fund) {
                $line = '';
                if (count($fund->transfers) > 0) {
                    // with details
                    foreach ($fund->transfers as $trans) {
                        $line .= '"'.stripslashes($fund->group->name).'"'."\t";
                        $line .= '"'.stripslashes($fund->project->name).'"'."\t";
                        $line .= '"'.$trans->createdOn.'"'."\t";
                        $line .= '"'.stripslashes($trans->createdBy->fullName).'"'."\t";
                        $line .= '"'.$fund->group->currency.number_format($trans->amount,2).'"'."\n";
                        print str_replace("\r","",$line);
                        $line = '';
                    }
                    $line  = '';
                    $line .= '"'.stripslashes($fund->group->name).'"'."\t";
                    $line .= '"'.stripslashes($fund->project->name).'"'."\t";
                    $line .= '"'.'"'."\t";
                    $line .= '"'.'"'."\t";
                    $line .= '"Total Transfered: '.$fund->group->currency.number_format($fund->amount,2).'"'."\n";
                } else {
                    // no details
                    $line .= '"'.stripslashes($fund->group->name).'"'."\t";
                    $line .= '"'.stripslashes($fund->project->name).'"'."\t";
                    $line .= '"'.'"'."\t";
                    $line .= '"'.'"'."\t";
                    $line .= '"Total Transfered: '.$fund->group->currency.number_format($fund->amount,2).'"'."\n";
                }
                print str_replace("\r","",$line);
            }
        }
    }

    /**
     * Report for membership status
     */
    public function membershipsAction() {
        $this->_helper->layout()->disableLayout();

        $params = $this->_getAllParams();
        $list   = MembershipStat::getList();
        $_REQUEST['subPage'] = 'memberships';
        $_REQUEST['pageUrl'] = 'reporting';

        $paginator = Zend_Paginator::factory($list);
        $page      = $this->_getParam('page', 1);
        $paginator->setItemCountPerPage(50);
        $paginator->setCurrentPageNumber($page);
        $this->view->paginator = $paginator;
    }

    /**
     * Report for supporter status
     */
    public function supportersAction() {
        $this->_helper->layout()->disableLayout();

        $org = Organization::get('DAF7E701-4143-4636-B3A9-CB9469D44178'); //usa
        //$org = Organization::get("2FAADB94-5267-11E1-9A0D-0025900034B2"); //matias

        $params = $this->_getAllParams();
        $list   = Supporter::getByOrganization($org);

        $paginator = Zend_Paginator::factory($list);
        $page      = $this->_getParam('page', 1);
        $paginator->setItemCountPerPage(50);
        $paginator->setCurrentPageNumber($page);
        $this->view->paginator = $paginator;
    }

    /**
     * Add notes for reports chapters membership
     */
    public function membershipnotesAction() {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $param = $this->_getAllParams();

        MembershipStat::saveNote($param['GroupId'], $param['StatId'], $param['Notes']);

        echo json_encode(array('status' => 'ok'));
    }

    /**
     * Report for membership donations
     *
     * @matias
     */
    public function membershipexportAction() {
        ini_set("memory_limit", "1024M");
        set_time_limit(900);
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params = $this->_getAllParams();
        $filter = false;
        if (!$this->view->isAdmin || (empty($params['GroupId']) &&
            empty($params['OrganizationId']))
        ) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $search   = $this->_getParam('searchFilter', false);
        $fromDate = $this->_getParam('FromDate', false);
        $toDate   = $this->_getParam('ToDate', false);

        if ($search || $fromDate || $toDate) {
            $filter = true;
            if ($fromDate) {
                $fromDate = date('Y-m-d', strtotime($fromDate));
            }
            if ($toDate) {
                $toDate = date('Y-m-d', strtotime($toDate));
            }
        }
        if (empty($params['GroupId'])) {
            // org filter
            $organization = Organization::get($params['OrganizationId']);
            if ($filter) {
                $payments = Payment::getListByOrganization(
                    $organization, $search, $fromDate, $toDate
                );
            } else {
                $payments = $organization->payments;
            }
        } else {
            // group filter
            $group = Group::get($params['GroupId']);
            if ($filter) {
                $payments = Payment::getListByGroup($group, $search, $fromDate, $toDate);
            } else {
                $payments = $group->payments;
            }
        }

        header("Content-type: application/x-msdownload");
        header("Content-Disposition: attachment; filename=membershipreport.xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        $headers = '';
        $data    = '';
        $columns = array(
            'Transaction ID',
            'Member',
            'Destination',
            'Date',
            'Paid Until',
            'Amount',
        );
        foreach($columns as $column) {
            $headers .= '"'.$column.'"'."\t";
        }
        print $headers ."\n";
        $total = 0;
        foreach($payments as $k=>$payment) {
            $total += $payment->amount;

            $line  = '"'.$payment->transactionId.'"'."\t";
            $line .= '"'.stripslashes($payment->user->fullName).'"'."\t";
            $line .= '"'.$payment->group->name.'"'."\t";
            $line .= '"'.$payment->createdOn.'"'."\t";
            $line .= '"'.(($payment->paidUntil == '0000-00-00') ? 'One Time' : $payment->paidUntil).'"'."\t";
            $line .= '"'.$payment->group->currency.number_format($payment->amount).'"'."\n";
            print str_replace("\r","",$line);
        }
        $line  = "\n\r".'"Total Donations"'."\t\t\t\t\t".'"'.$payment->group->currency.number_format($total).'"'."\n";
        print str_replace("\r","",$line);
    }
}
