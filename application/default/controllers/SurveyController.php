<?php

/**
 * SurveyController - The "survey" controller class
 *
 * @author
 * @version
 */

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Util/Auth.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/GroupSurveys.php';
require_once 'Brigade/Db/Table/GroupSurveyQuestions.php';
require_once 'Brigade/Db/Table/GroupSurveyFeedbacks.php';
require_once 'Brigade/Db/Table/GroupSurveyRespondents.php';
require_once 'Brigade/Db/Table/GroupMembershipRequest.php';
require_once 'Brigade/Db/Table/GroupEmailAccounts.php';
require_once 'Brigade/Db/Table/GroupMembers.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Brigade/Db/Table/Organizations.php';
require_once 'BaseController.php';

require_once 'Group.php';

class SurveyController extends BaseController {

    public function init() {
        parent::init();
        $UserRoles = new Brigade_Db_Table_UserRoles();
        if (isset($parameters['NetworkId'])) {
            $NetworkId = $parameters['NetworkId'];
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
        } else if (isset($parameters['GroupId'])){
            $GroupId = $parameters['GroupId'];
            if($this->_helper->authUser->isLoggedIn()) {
                $role = $UserRoles->getUserRole($_SESSION['UserId']);
                $hasAccess = $UserRoles->UserHasAccess($GroupId, $_SESSION['UserId'], 'group');
                if (($hasAccess && $role['RoleId'] == 'ADMIN') || $role['RoleId'] == 'GLOB-ADMIN') {
                    $this->view->isAdmin = true;
                }
            }
        }

    }

    /**
     * The default action - show the home page
     */
    public function indexAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();
        $Groups = new Brigade_Db_Table_Groups();
        $GroupSurveys = new Brigade_Db_Table_GroupSurveys();
        $SurveyQuestions = new Brigade_Db_Table_GroupSurveyQuestions();
        $SurveyFeedbacks = new Brigade_Db_Table_GroupSurveyFeedbacks();
        $SurveyRespondents = new Brigade_Db_Table_GroupSurveyRespondents();
        $this->view->data = $GroupSurveys->loadInfo($parameters['SurveyId']);
        if ($this->view->data['Type'] != 'Existing Members') {
            // add user to group_survey_respondents, this will keep track of users who did or didn't completed the survey
            if (!$SurveyRespondents->isRespondentExists($parameters['SurveyId'], $_SESSION['UserId'])) {
                $SurveyRespondents->AddSurveyRespondent(array(
                    'UserId' => $_SESSION['UserId'],
                    'SurveyId' => $parameters['SurveyId']
                ));
            }
        }
        if (!empty($this->view->data['ProjectId'])) {
            $Brigades = new Brigade_Db_Table_Brigades();
            $this->view->projectInfo = $Brigades->loadInfo1($this->view->data['ProjectId']);
        }
        $this->view->groupInfo = $Groups->loadInfo1($this->view->data['GroupId']);
        $this->view->questions = $SurveyQuestions->getSurveyQuestions($parameters['SurveyId']);
        if ($_POST) {
            foreach($_POST['Feedback'] as $QuestionId => $Feedback) {
                $SurveyFeedbacks->AddSurveyFeedback(array(
                    'SurveyQuestionId' => $QuestionId,
                    'Answer' => $Feedback,
                ));
            }
            if ($this->view->data['Type'] == 'Joining Group') {
                $GroupMembers = new Brigade_Db_Table_GroupMembers();
                $GroupMembershipRequest = new Brigade_Db_Table_GroupMembershipRequest();
                if ($this->view->groupInfo['isOpen'] == 0) {
                    if ($GroupMembershipRequest->hasMembershipRequest($_POST['GroupId'], $_POST['UserId'])) {
                        echo '<script>alert("You have already sent a membership request to this group, please wait for the admin to accept it.")</script>';
                    } else if (!$GroupMembers->isMemberExists($_POST['GroupId'], $_POST['UserId'])) {
                        $GroupMembershipRequest->AddMembershipRequest(array(
                            'GroupId' => $_POST['GroupId'],
                            'UserId' => $_POST['UserId']
                        ));
                        // send notification message to group admin(s)
                        $mailer = new Mailer();
                        $userInfo = $Users->loadInfo($_POST['UserId']);
                        $groupAdmins = $GroupMembers->getGroupAdmins($_POST['GroupId']);
                        foreach($groupAdmins as $admin) {
                            Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_MEMBER_NOTIFICATION,
                                    array($admin['Email'], stripslashes($this->view->groupInfo['GroupName']), stripslashes($userInfo['FullName']), ""));
                        }
                        echo '<script>alert("Chapter membership request has been sent, please wait for the admin to accept it.")</script>';
                    }
                } else if ($this->view->groupInfo['isOpen'] == 1) {
                    $GroupMembers->AddGroupMember(array(
                        'GroupId' => $_POST['GroupId'],
                        'UserId' => $_POST['UserId']
                    ));
                    echo '<script>alert("Congratulations you have joined '.stripslashes($this->view->groupInfo['GroupName']).', would you like to volunteer in an upcoming Volunteer Opportunity? Click Here")</script>';
                }
                header("location: /".$this->view->groupInfo['URLName']);
            } else if ($this->view->data['Type'] == 'Joining Activity') {
                header("location: /signup/?ProjectId=".$this->view->data['ProjectId']);
            } else {
                header("location: /".$this->view->groupInfo['URLName']);
            }
        }
    }

    public function loadgroupsAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Groups = new Brigade_Db_Table_Groups();
        $parameters = $this->_getAllParams();
        if (isset($parameters['ProgramId'])) {
            $groups = $Groups->listByProgram($parameters['ProgramId']);
            echo '<option value="">All</option>';
            foreach($groups as $group) {
                echo '<option value="'.$group['GroupId'].'">'.stripslashes($group['GroupName']).'</option>';
            }
        }
    }

    public function createAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();
        $Groups = new Brigade_Db_Table_Groups();
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $GroupSurveys = new Brigade_Db_Table_GroupSurveys();
        $SurveyQuestions = new Brigade_Db_Table_GroupSurveyQuestions();
        $Programs = new Brigade_Db_Table_Programs();
        $Organizations = new Brigade_Db_Table_Organizations();
        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();

        if(isset($parameters['GroupId'])) {
            $group  =  Group::get($parameters['GroupId']);

            $this->view->members = $GroupMembers->getGroupMembers($group->id);
            $filter = "p.ProjectId NOT IN (SELECT s.ProjectId FROM group_surveys s WHERE s.GroupId = g.GroupId AND s.ProjectId = p.ProjectId)";
            $this->view->upcoming_brigades = $Groups->loadBrigades($group->id, 'all', NULL, $filter);
            $this->view->level = "group";
            if(isset($_REQUEST['Prev']) && $_REQUEST['Prev'] == 'activity' && isset($_REQUEST['ProjectId'])) {
                $LookupTable = new Brigade_Db_Table_LookupTable();
                $this->view->ProjectURL = $LookupTable->getURLbyId($_REQUEST['ProjectId']);
            }

            //breadcrumb
            $this->view->breadcrumb = $this->view->breadcrumbHelper($project, 'Create Survey');
            $this->view->network    = $group->organization;
            $this->view->group      = $group;

            $this->view->render('group/header.phtml');
            $this->view->render('group/tabs.phtml');

        } else if (isset($parameters['NetworkId'])) {
            ini_set("memory_limit", "64M");
            $this->view->data = $this->view->network = $Organizations->loadInfo($parameters['NetworkId'], false);
            $this->view->members = $GroupMembers->getOrganizationMembers($parameters['NetworkId'], 'all', true);
            $filter = "p.ProjectId NOT IN (SELECT s.ProjectId FROM group_surveys s WHERE s.GroupId = g.GroupId AND s.ProjectId = p.ProjectId)";
            $this->view->upcoming_brigades = $Organizations->loadProjects($parameters['NetworkId'], 'upcoming', false, '', 0, $filter, (isset($_REQUEST['ProgramId']) && !empty($_REQUEST['ProgramId']) ? $_REQUEST['ProgramId'] : NULL), NULL, false);
            $this->view->programs = $Programs->ListByNetwork($parameters['NetworkId'], false);
            if(!$this->view->network['hasPrograms']) {
                $this->view->groups = $Groups->getNetworkGroups($parameters['NetworkId'], $this->view->data['hasPrograms']);
            } else if(isset($_REQUEST['ProgramId']) && !empty($_REQUEST['ProgramId'])) {
                $this->view->groups = $Groups->listByProgram($_REQUEST['ProgramId']);
            }
            $this->view->level = "organization";

            // get total count of campaigns, activities and events, display the tabs if count > 0
            $this->view->activities_count = $Organizations->getActivitiesCount($this->view->data['NetworkId']);
            $this->view->campaigns_count = $Organizations->getCampaignsCount($this->view->data['NetworkId']);
            $this->view->events_count = $Organizations->getEventsCount($this->view->data['NetworkId']);

            $this->view->render('nonprofit/header.phtml');
            $this->view->render('nonprofit/tabs.phtml');

        }

        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('nonprofit/footer.phtml');

        $this->_helper->layout->setLayout('newlayout');


        if ($_POST) {
            if (isset($_POST['NetworkId'])) {
                $siteInfo = $Organizations->loadInfo($_POST['NetworkId'], false);
                $SiteId = $_POST['NetworkId'];
                $Level = 'organization';
                if (isset($_POST['GroupId']) && $_POST['GroupId'] != 'All') {
                    $SiteId = $_POST['GroupId'];
                }
            } else {
                $siteInfo = $Groups->loadInfo1($_POST['GroupId']);
                $SiteId = $_POST['GroupId'];
                $Level = 'group';
            }
            if ($_POST['create_survey'] == 'existing members') {
                $SurveyId = $GroupSurveys->AddSurvey(array(
                    'Title' => stripslashes($_POST['SurveyTitle3']),
                    'GroupId' => $SiteId,
                    'Type' => 'Existing Members',
                    'isRequired' => isset($_POST['isRequiredMembers']) ? 1 : 0,
                    'Level' => $Level
                ));
                $ctr = 1;
                foreach ($_POST['MembersQuestions'] as $question) {
                    $SurveyQuestions->AddSurveyQuestion(array(
                        'SurveyId' => $SurveyId,
                        'Question' => stripslashes($question),
                        'isRequired' => isset($_POST['RequireMembersQuestions'][$ctr]) ? 1 : 0
                    ));
                    $ctr++;
                }

                $this->view->message = stripslashes($_POST['SurveyTitle3'])." survey has been successfully created.";
                header("location: /".$siteInfo['URLName']."/email-survey/$SurveyId");
            } else if ($_POST['create_survey'] == 'join activity') {
                foreach($_POST['ProjectId'] as $ProjectId) {
                    $SurveyId = $GroupSurveys->AddSurvey(array(
                        'Title' => stripslashes($_POST['SurveyTitle']),
                        'GroupId' => $SiteId,
                        'Type' => 'Joining Activity',
                        'ProjectId' => $ProjectId,
                        'isRequired' => isset($_POST['isRequiredActivity']) ? 1 : 0,
                        'Level' => $Level
                    ));
                    $ctr = 1;
                    foreach ($_POST['JoinActQuestions'] as $question) {
                        $SurveyQuestions->AddSurveyQuestion(array(
                            'SurveyId' => $SurveyId,
                            'Question' => stripslashes($question),
                            'isRequired' => isset($_POST['RequireJoinActQuestions'][$ctr]) ? 1 : 0
                        ));
                        $ctr++;
                    }
                }

                $this->view->message = stripslashes($_POST['SurveyTitle'])." survey has been successfully created.";
                header("location: /".$siteInfo['URLName']."/manage-surveys");
            } else if ($_POST['create_survey'] == 'join group') {
                $SurveyId = $GroupSurveys->AddSurvey(array(
                    'Title' => stripslashes($_POST['SurveyTitle2']),
                    'GroupId' => $SiteId,
                    'Type' => 'Joining Group',
                    'isRequired' => isset($_POST['isRequiredGroup']) ? 1 : 0,
                    'Level' => $Level
                ));
                $ctr = 1;
                foreach ($_POST['JoinGroupQuestions'] as $question) {
                    $SurveyQuestions->AddSurveyQuestion(array(
                        'SurveyId' => $SurveyId,
                        'Question' => stripslashes($question),
                        'isRequired' => isset($_POST['JoinGroupQuestionsRequire'][$ctr]) ? 1 : 0
                    ));
                    $ctr++;
                }

                $this->view->message = stripslashes($_POST['SurveyTitle2'])." survey has been successfully created.";
                header("location: /".$siteInfo['URLName']."/email-survey".$SurveyId);
            }
        }
    }

    public function editAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $GroupSurveys = new Brigade_Db_Table_GroupSurveys();
        $SurveyQuestions = new Brigade_Db_Table_GroupSurveyQuestions();
        if ($_POST) {
            $GroupSurveys->EditSurvey($_POST['SurveyId'], array('Title' => $_POST['SurveyTitle'], 'Type' => $_POST['Type']));
            foreach($_POST['Questions'] as $id => $val) {
                if ($id > 0) {
                    $SurveyQuestions->EditSurveyQuestion($id, array('Question' => $val, 'isDeleted' => $_POST['isDeleted'][$id], 'isRequired' => isset($_POST['isRequired'][$id]) ? 1 : 0));
                } else {
                    $SurveyQuestions->AddSurveyQuestion(array(
                        'SurveyId' => $_POST['SurveyId'],
                        'Question' => $val,
                        'isRequired' => isset($_POST['isRequired'][$id]) ? 1 : 0
                    ));
                }
            }
            echo "Survey has been successfully updated.";
        }
    }

    public function manageAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();
        $GroupSurveys = new Brigade_Db_Table_GroupSurveys();
        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();
        if (isset($parameters['GroupId'])) {
            $Groups = new Brigade_Db_Table_Groups();
            $this->view->data = $Groups->loadInfo1($parameters['GroupId']);
            $this->view->surveys = $GroupSurveys->getSurveys($parameters['GroupId'], true);
            $this->view->level = "group";
        } else if (isset($parameters['NetworkId'])) {
            $Organizations = new Brigade_Db_Table_Organizations();
            $this->view->network = $this->view->data = $Organizations->loadInfo($parameters['NetworkId'], false);
            $this->view->surveys = $GroupSurveys->getSurveysByNetwork($parameters['NetworkId'], true);
            $this->view->level = "organization";
        }
    }

    public function editresponsesAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();
        $GroupSurveys = new Brigade_Db_Table_GroupSurveys();
        $SurveyQuestions = new Brigade_Db_Table_GroupSurveyQuestions();
        $SurveyFeedbacks = new Brigade_Db_Table_GroupSurveyFeedbacks();
        $this->view->SurveyId = $parameters['SurveyId'];
        $this->view->data = $GroupSurveys->loadInfo($parameters['SurveyId']);
        if(isset($parameters['Prev'])) {
            $this->view->prev = $parameters['Prev'];
        }
        if ($this->view->data['Level'] == 'group') {
            $Groups = new Brigade_Db_Table_Groups();
            $this->view->siteInfo = $Groups->loadInfo1($this->view->data['GroupId']);
        } else {
            $Organizations = new Brigade_Db_Table_Organizations();
            $this->view->siteInfo = $Organizations->loadInfo($this->view->data['GroupId'], false);
        }
        if ($_POST) {
            foreach ($_POST['survey_feedback'] as $SurveyFeedbackId => $Answer) {
                $SurveyFeedbacks->EditSurveyFeedback($SurveyFeedbackId, array('Answer' => stripslashes($Answer)));
            }
            $this->view->message = "Survey feedbacks has been successfully updated.";
        }
        $this->view->filter = true;
        $this->view->feedbacks = $SurveyFeedbacks->getSurveyFeedbacks($parameters['SurveyId'], true, isset($parameters['UserId']) ? $parameters['UserId'] : NULL);
        $this->view->respondents = $SurveyFeedbacks->getSurveyFeedbacks($parameters['SurveyId'], false, isset($parameters['UserId']) ? $parameters['UserId'] : NULL);
        if (isset($parameters['UserId'])) {
            $this->view->filter = false;
        }
    }

    public function filterfeedbacksAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        $SurveyFeedbacks = new Brigade_Db_Table_GroupSurveyFeedbacks();
        $feedbacks = $SurveyFeedbacks->getSurveyFeedbacks($parameters['SurveyId'], true, $parameters['UserId']);
        foreach ($feedbacks as $feedback) {
            echo '
                <tr>
                    <td class="rows" style="width:200px">&nbsp;'.stripslashes($feedback['FullName']).'</td>
                    <td class="rows" style="width:420px">&nbsp;'.stripslashes($feedback['Question']).'</td>
                    <td class="rows last" style="width:420px"><textarea cols="52" rows="2" name="survey_feedback['.$feedback['SurveyFeedbackId'].']">'.stripslashes($feedback['Answer']).'</textarea></td>
                </tr>';
        }
    }

    public function emailsurveyAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();
        $Groups = new Brigade_Db_Table_Groups();
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $GroupSurveys = new Brigade_Db_Table_GroupSurveys();
        $GroupEmailAccounts = new Brigade_Db_Table_GroupEmailAccounts();
        $Organizations = new Brigade_Db_Table_Organizations();
        $Mailer = new Mailer();
        if (isset($parameters['GroupId'])) {
            $SiteId = $parameters['GroupId'];
            $this->view->data = $Groups->loadInfo($SiteId);
            $this->view->data['SiteName'] = $this->view->data['GroupName'];
            $Level = 'group';
        } else if (isset($parameters['NetworkId'])) {
            $SiteId = $parameters['NetworkId'];
            $this->view->data = $Organizations->loadInfo($parameters['NetworkId'], false);
            $this->view->data['SiteName'] = $this->view->data['NetworkName'];
            $Level = 'organization';
        }
        $this->view->SurveyId = $parameters['SurveyId'];
        $this->view->survey_info = $GroupSurveys->loadInfo($parameters['SurveyId']);
        if ($this->view->survey_info['Type'] == 'Existing Members') {
            if ($this->view->survey_info['Level'] == 'group') {
                $this->view->members = $GroupMembers->getGroupMembers($SiteId, array(1));
                $this->view->activities = $Groups->loadUpcomingBrigades($SiteId, "all", NULL, 'p.Name');
            } else if ($this->view->survey_info['Level'] == 'group') {
                $this->view->members = $Organizations->getVolunteers($SiteId, 'all', true);
                $this->view->activities = $Organizations->loadProjects($SiteId);
            }
        } else {
            $SurveyRespondents = new Brigade_Db_Table_GroupSurveyRespondents();
            $this->view->respondents = $SurveyRespondents->getRespondents($parameters['SurveyId']);
        }
        $this->view->emails = $GroupEmailAccounts->getGroupEmailAccounts($SiteId);
        if (isset($_POST['action']) && $_POST['action'] == "Add Emails") {
            $this->_helper->layout->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
        if ($this->view->envUsername == 'admin') {
        $envSite = "www";
        } else if ($this->view->envUsername == 'dev') {
        $envSite = "dev";
        } else {
        $envSite = "qat";
        }
            $GroupId = $_POST['GroupId'];
            $From = $_POST['From'];
            $groupInfo = $Groups->loadInfo($GroupId);
            $FromEmails = str_replace(" ", "", trim($_POST['FromEmails']));
            $FromEmails = explode(",", $FromEmails);
            foreach ($FromEmails as $email) {
                $verification_code = $GroupEmailAccounts->AddEmailAccount(array(
                    'GroupId' => $GroupId,
                    'Email' => $email
                ));
                $Link = "$envSite.empowered.org/group/verify-email/$GroupId/$verification_code";
                Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_EMAIL_VERIFICATION,
                                   array($email, $groupInfo['GroupName'], $Link, $From));
            }

            echo implode(", ", $FromEmails);
        } else if (isset($_POST['action']) && $_POST['action'] == "Send Email") {
            extract($_POST);
            if ($sendTo == "Group") {
                $members = $this->view->members;
                foreach ($members as $member) {
                    Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_NOTIFICATION,
                                   array($member['Email'], $subject, $message, $sentFrom, $this->view->data['SiteName']));
                }
            } else if ($sendTo == "Activity") {
                $Brigades = new Brigade_Db_Table_Brigades();
                foreach($activities as $activity) {
                    $volunteers_email = $Brigades->getVolunteerEmails($activity);
                    foreach ($volunteers_email as $email) {
                        Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_NOTIFICATION,
                                   array($email, $subject, $message, $sentFrom, $this->view->data['SiteName']));
                    }
                }
            } else if ($sendTo == "Members") {
                foreach ($members as $email) {
                    Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$GROUP_NOTIFICATION,
                                   array($email, $subject, $message, $sentFrom, $this->view->data['SiteName']));
                }
            }
            $sentTo = array('Group' => 'entire '.($Level == 'group' ? 'chapter' : $Level).' members', 'Activity' => 'selected volunteer activities', 'Members' => 'selected '.($Level == 'group' ? 'chapter' : $Level).' members');
            $this->view->sent = true;
            $this->view->message = "Your message has been successfully sent to ".$sentTo[$sendTo].".";
        }
    }

    public function deleteAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if ($_POST['SurveyId']) {
            $GroupSurveys = new Brigade_Db_Table_GroupSurveys();
            $GroupSurveys->deleteSurvey($_POST['SurveyId']);
        }
    }

    public function pullreportAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        if (isset($parameters['SurveyId'])) {
            $GroupSurveys = new Brigade_Db_Table_GroupSurveys();
            $surveyInfo = $GroupSurveys->loadInfo($parameters['SurveyId']);
            $survey_feedbacks = $GroupSurveys->getSurveyReport($parameters['SurveyId']);
            $filename = str_replace(" ", "-", $surveyInfo['Title']."-Survey-Report.xls");
            header("Content-type: application/x-msdownload");
            header("Content-Disposition: attachment; filename=$filename");
            header("Pragma: no-cache");
            header("Expires: 0");
            $headers = '';
            $data = '';
            $cols = array('CompletedBy', 'Question', 'Answer', 'AnsweredOn');
            $columns = array('Completed By', 'Question', 'Feedback/Answer', 'Date Answered');
            foreach($columns as $column) {
                $headers .= $column."\t";
            }
            foreach($survey_feedbacks as $feedback) {
                $line = '';
                foreach($feedback as $col => $value) {
                    if (in_array($col, $cols)) {
                        if ((!isset($value)) || ($value == "") || empty($value)) {
                            $feedback[$col] = "\t";
                        } else {
                            $feedback[$col] = str_replace('"', '""', $value);
                            $feedback[$col] = '"' . stripslashes($value) . '"' . "\t";
                        }
                    }
                }
                extract($feedback);
                $line = "$CompletedBy$Question$Answer$AnsweredOn";
                $data .= trim($line)."\n";
            }

            print "$headers\n$data";
        }
    }
}
