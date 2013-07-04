<?php

/**
 * ProgramController - The default controller class
 *
 * @author
 * @version
 */

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/Programs.php';
require_once 'Brigade/Db/Table/ContactInformation.php';
require_once 'Brigade/Db/Table/Media.php';
require_once 'Brigade/Db/Table/MediaSite.php';
require_once 'Brigade/Db/Table/Blogs.php';
require_once 'Brigade/Db/Table/Users.php';
require_once 'Brigade/Util/ImageResize.php';
require_once 'Brigade/Util/ImageCrop.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Brigade/Db/Table/Organizations.php';
require_once 'Brigade/Util/Auth.php';
require_once 'BaseController.php';

require_once 'Program.php';
require_once 'Salesforce.php';

class ProgramController extends BaseController {

    protected $_http;
    function init() {
        parent::init();
    }

    public function preDispatch() {
        parent::preDispatch();
        $this->view->http = $this->_http;
        $this->view->media_path = "/public/Media/";
    }

    public function indexAction() {
        $parameters = $this->_getAllParams();
        $Media = $this->view->sitemedia = new Brigade_Db_Table_Media();
        $program      = Program::get($parameters['ProgramId']);
        $organization = $program->organization;

        // new url for cms redirect
        $config = Zend_Registry::get('configuration');
        if ($config->cms_migrate->active &&
            in_array($program->organizationId, $config->cms_migrate->org->toArray())
        ) {
            $this->_helper->redirector->gotoUrl(
                $config->cms_migrate->host . '/program/' . $group->id
            );
        }

        $this->view->headTitle(stripslashes($program->name));

        if ($program->logoMediaId != '') {
            $this->view->prog_image = $Media->getSiteMediaById($program->logoMediaId);
        } else {
            $this->view->prog_image = 'nologo';
        }
        $this->getOrganizationHeaderMedia($organization);

        $this->view->currentTab   = 'programs';
        $this->view->breadcrumb   = $this->view->breadcrumbHelper($program);
        $this->view->organization = $organization;
        $this->view->program      = $program;

        if (isset($parameters['Coalition'])) {
            $program->showCoalitions = true;
        }
        $this->view->groups     = $program->groups_2;
        $this->view->activities = $program->activities_2;
        $this->view->campaigns  = $program->campaigns_2;

        $this->view->render('program/header.phtml');
        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('nonprofit/tabs.phtml');
        $this->view->render('nonprofit/footer.phtml');
        $this->_helper->layout->setLayout('newlayout');
        if ($this->view->isAdmin) {
            $this->view->toolPopupObj = $program; // for logo upload toolbox
            $this->view->render('program/popup_upload_logo.phtml');
            $this->view->render('program/toolbox.phtml');
        }
    }

    public function groupsAction() {
        $Programs = new Brigade_Db_Table_Programs();
        $ContactInfo = new Brigade_Db_Table_ContactInformation();
        $Media = new Brigade_Db_Table_Media();
        $Groups = new Brigade_Db_Table_Groups();
        $parameters = $this->_getAllParams();
        if (isset($parameters['ProgramId'])) {
            $ProgramId = $parameters['ProgramId'];
            $this->view->nonprofit = $Programs->loadOrganization($ProgramId);
        if ($this->view->nonprofit['LogoMediaId'] != '' ) {
            $this->view->media_image = $Media->getSiteMediaById($this->view->nonprofit['LogoMediaId']);
        }
            // load program info
            $this->view->data = $Programs->loadInfo($ProgramId);
            $this->view->contact_info = new Brigade_Db_Table_ContactInformation();
            $this->view->sitemedia = new Brigade_Db_Table_Media();
            $this->view->groups = new Brigade_Db_Table_Groups();
            $this->view->donations = new Brigade_Db_Table_ProjectDonations();
            $this->view->sitegroups = $Groups->getProgramGroups($ProgramId);
        }
    }

    public function filterAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Programs = new Brigade_Db_Table_Programs();
        $sitemedia = new Brigade_Db_Table_Media();
        $ContactInfo = new Brigade_Db_Table_ContactInformation();
        $Groups = new Brigade_Db_Table_Groups();
        $brigades = $Programs->loadBrigades($_REQUEST['ProgramId'], $_REQUEST['type'], $_REQUEST['search_text']);
        foreach($brigades as $brigade) {
            $media_src = '';
            $media = $sitemedia->getSiteMediaGallery($brigade['ProjectId'], "");
            if (count($media) > 0) {
                $media_src = '/public/Media/'.$media[0]['SystemMediaName'];
            } else {
                // get the group image by group's LogoMediaId
                $groupInfo = $Groups->loadInfo($brigade['GroupId']);
                $media = $sitemedia->getSiteMediaById($groupInfo['LogoMediaId']);
                // echo '$sitemedia->getSiteMediaById('.$groupInfo['LogoMediaId'].')';
                $media_src = '/public/Media/'.$media['SystemMediaName'];
            }
            echo '
                <div class="box06">
                    <div class="bst01">
                        <a href="/group/?GroupId='.$brigade["GroupId"].'">
                            <img src="'.$media_src.'" alt="" width="74" height="50"/>
                        </a>
                        <div class="bst03">
                            <div class="bst04">
                                <div class="bst05"><span><span><span id="ctl00_ContentPHMain_ctrlGroupBrigDtls_rptGroupBrigDtls_ctl00_lblVoluntSpaceEmpty">'.$brigade["total_volunteers"].'</span></span> / </span> '.$brigade["VolunteerGoal"].'</div>
                                Spaces<br />Available
                            </div>
                        </div>
                    </div>
                    <div class="bst02">
                        <div class="bst06">
                            <div class="bst07">Group: </div>
                            <a href="/group/?GroupId='.$brigade["GroupId"].'" >'.$brigade["GroupName"].'</a>
                        </div>
                        <div class="bst06">
                            <div class="bst07">Brigade: </div>
                                '.$brigade["Name"].'
                        </div>
                        <div class="bst08">
                            <div class="bst07">Where: </div>
                                '.$ContactInfo->getContactInfo($brigade["ProjectId"], "Location").'
                        </div>
                        <div class="bst09">
                            <div class="bst07">When: </div>
                                '.date("M d, Y", strtotime($brigade["StartDate"]))." - ".date("M d, Y", strtotime($brigade["EndDate"])).'
                        </div>
                        <div class="bst10">
                            <div class="but006"><a href="'.($this->_helper->authUser->isLoggedIn() ? '/signup?ProjectId='.$brigade['ProjectId'] : '/profile/login').'" target="_blank">Volunteer</a></div>
                            <div class="but006"><a href="/donation/'.$brigade["ProjectId"].'">Donate</a></div>
                        </div>
                    </div>
                    <div class="clear"></div>
                </div>
            ';
        }
    }

    /*
     * Admin controls
     */
    public function createAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters   = $this->_getAllParams();
        $organization = Organization::get($parameters['NetworkId']);

        $this->getOrganizationHeaderMedia($organization);

        //breadcrumb
        $this->view->breadcrumb   = $this->view->breadcrumbHelper(
                                        $organization,
                                        'Create Program');
        $this->view->organization = $organization;

        $this->view->render('nonprofit/header.phtml');
        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('nonprofit/tabs.phtml');
        $this->view->render('nonprofit/footer.phtml');

        $this->_helper->layout->setLayout('newlayout');

        if (isset($parameters['Type'])) {
            $this->view->Type = $parameters['Type'];
        } else {
            $this->view->Type = "";
        }
        if (isset($parameters['enable_programs'])) {
            $this->view->enable_programs = true;
        }
        if (isset($_SESSION['NewProgram'])) {
            $Programs = new Brigade_Db_Table_Programs();
            $this->view->progInfo = $Programs->loadInfo1($_SESSION['NewProgram']);
        }
        if ($_POST) {
            // validate administrator
            $Users = new Brigade_Db_Table_Users();
            $LookupTable = new Brigade_Db_Table_LookupTable();
            $ProgramName = $_POST['ProgramName'];
            $Description = $_POST['Description'];
            $WebAddress  = $_POST['WebAddress'];
            $isOpen      = $_POST['isOpen'];

            $URLName = str_replace(array(" ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":", "$", "`", "#", "^", "<", ">", "[", "]", "{", "}"), array("-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"), trim($ProgramName));
            // replace other special chars with accents
            $other_special_chars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ');
            $char_replacement = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n');
            $URLName = str_replace($other_special_chars, $char_replacement, $URLName);

            $Taken = $LookupTable->isSiteNameExists($URLName);
            $counter = 1;
            while($Taken) {
                $NewURLName = "$URLName-$counter";
                $counter++;
                $Taken = $LookupTable->isSiteNameExists($NewURLName);
            }
            if($counter > 1) {
                $URLName = $NewURLName;
            }

            if(!empty($_FILES['ProgramLogo']['name'])) {
                $filename = $_FILES['ProgramLogo']['name'];
                $file_ext = strtolower(substr($filename, strrpos($filename, '.') + 1));
                if($file_ext != 'jpg' && $file_ext != 'jpeg' && $file_ext != 'png' && $file_ext != 'gif') {
                    $bad_ext = 1;
                    $this->view->message = "Please upload a logo in jpeg, png and gif format only.";
                } else {
                    $bad_ext = 0;
                }
            }

            if (!$bad_ext) {
                //Save Program Info
                $Programs = new Brigade_Db_Table_Programs();
                $ProgramId = $Programs->addProgram(array(
                    'ProgramName' => $ProgramName,
                    'Description' => $Description,
                    'isOpen'      => $isOpen,
                    'URLName'     => $URLName,
                    'NetworkId'   => $organization->id,
                ));

                // add record on the lookup_table
                $LookupTable->addSiteURL(array(
                    'SiteName' => $URLName,
                    'SiteId' => $ProgramId,
                    'Controller' => 'program',
                    'FieldId' => 'ProgramId'
                ));

                // add default administrator for this program
                $Users = new Brigade_Db_Table_Users();
                $userInfo = $Users->loadInfo($_SESSION['UserId']);
                $UserRole = new Brigade_Db_Table_UserRoles();
                $UserRoleId = $UserRole->addUserRole(array(
                    'UserId' => $userInfo['UserId'],
                    'RoleId' => 'ADMIN',
                    'SiteId' => $ProgramId
                ));

                //check URL for http:// prefix
                if(!empty($WebAddress)){
                    preg_match("/^https?:\/\/[_a-zA-Z0-9-]+\.[\._a-zA-Z0-9-]+$/i", $WebAddress, $website);
                    if(empty($website[0])) {
                        $WebAddress = 'http://'.$WebAddress;
                    }
                }

                // save program contact info
                $ContactInfo = new Brigade_Db_Table_ContactInformation();
                $ContactId = $ContactInfo->addContactInfo(array(
                    'WebAddress' => $WebAddress,
                    'SiteId' => $ProgramId
                ));

                // log the site activity
                $SiteActivities = new Brigade_Db_Table_SiteActivities();
                $SiteActivities->addSiteActivity(array(
                    'SiteId' => $organization->id,
                    'ActivityType' => 'Program Added',
                    'CreatedBy' => $_SESSION['UserId'],
                    'ActivityDate' => date('Y-m-d H:i:s'),
                    'Details' => $ProgramId
                ));

                // if program is enabled
                if (isset($_POST['enable_programs']) && $_POST['enable_programs'] == 1) {
                    // update hasPrograms=1
                    $Organizations = new Brigade_Db_Table_Organizations();
                    $Organizations->editNetwork($organization->id, array('hasPrograms' => 1));

                    // update all groups to have the ProgramId set to the newly created program
                    $Groups = new Brigade_Db_Table_Groups();
                    $Groups->enablePrograms($organization->id, $ProgramId);

                    // update all projects to have the ProgramId set to the newly created program
                    $Brigades = new Brigade_Db_Table_Brigades();
                    $Brigades->enablePrograms($organization->id, $ProgramId);
                }

                // save program media/image
                $MediaSize = $_FILES['ProgramLogo']['size'];
                $tmpfile = $_FILES['ProgramLogo']['tmp_name'];
                if ($MediaSize > 0) {
                    //Get the file information
                    $ImageCrop = new Brigade_Util_ImageCrop();
                    $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_$ProgramId.jpg";

                    // Check if file size does not exceed 2MB
                    move_uploaded_file($tmpfile, $temp_image_location);
                    $width = $ImageCrop->getWidth($temp_image_location);
                    $height = $ImageCrop->getHeight($temp_image_location);
                    //Scale the image if it is greater than the width set above
                    if ($width > 900) {
                        $scale = 900/$width;
                    } else {
                        $scale = 1;
                    }
                    $uploaded = $ImageCrop->resizeImage($temp_image_location,$width,$height,$scale,$file_ext);

                    // save media
                    $Media = new Brigade_Db_Table_Media();
                    $MediaId = $Media->addMedia(array(
                        'MediaSize' => $MediaSize,
                        'SystemMediaName' => strtolower($ProgramId).".jpg",
                        'UploadedMediaName' => $filename,
                        'CreatedBy' => $_SESSION['UserId'],
                    ));

                    // save site media
                    $SiteMedia = new Brigade_Db_Table_MediaSite();
                    $SiteMedia->addMediaSite(array(
                        'MediaId' => $MediaId,
                        'SiteID' => $ProgramId
                    ));

                    // update program LogoMediaId
                    $Programs->editProgram($ProgramId, array('LogoMediaId' => $MediaId));
                    header("location: /program/cropimage/?ProgramId=$ProgramId".(isset($_POST['create_again']) && $_POST['create_again'] == 1 ? "&create_again=1" : ""));
                }
                $this->view->message = "Program \"$ProgramName\" has been created successfully.";
                if ($MediaSize <= 0) {
                    if (isset($_POST['create_again']) && $_POST['create_again'] == 1) {
                        header("location: /$organization->urlName/create-program");
                    } else {
                        header("location: /$URLName");
                    }
                }
            } else {
                foreach($_POST as $key => $val) {
                    if ($key != "Administrator" || count($userInfo) > 0) {
                        $this->view->$key = $val;
                    }
                }
                $message = "";
                if (isset($_FILES['ProgramLogo']) && $_FILES['ProgramLogo']['size'] > 2097152) {
                    $message .= "Please select an image not greater than 2MB.<br>";
                }
                if (!count($userInfo)) {
                    $message .= "Administrator email does not exists.";
                }
                if ($url_exists) {
                    $message .= "URL name already exists, please try another.";
                }
                $this->view->message = $message;
            }
        }
    }

    public function cropimageAction() {
        $parameters = $this->_getAllParams();
        $ProgramId  = $parameters['ProgramId'];
        $this->view->ProgramId = $ProgramId;
        $Programs = new Brigade_Db_Table_Programs();
        $programInfo = $Programs->loadInfo1($ProgramId);
        if ($_POST && isset($_POST['crop_image'])) {
            $this->view->image_preview = $media_name = $programInfo['URLName']."-logo";
            $ImageCrop = new Brigade_Util_ImageCrop();
            $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_$ProgramId.jpg";
            $thumb_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/Media/$media_name.jpg";
            $bigger_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/Media/full/$media_name.jpg";

            $x = $_POST["x"];
            $y = $_POST["y"];
            $width = $_POST["w"];
            $height = $_POST["h"];

            if ($width > 0 && $height > 0) {
                // get the current selected box width & height
                $ImageCrop->resizeThumbnailImage($bigger_image_location, $temp_image_location, $width, $height, $x, $y, 0, 'jpg', true);
            }

            if (!$_POST['preview']) {
                // delete the temp file
                if (file_exists($temp_image_location)) {
                    unlink($temp_image_location);
                }
                if (isset($parameters['create_again']) && $parameters['create_again']) {
                    $network = $Programs->loadOrganization($this->view->ProgramId);
                    header("location: /".$network['URLName']."/create-program");
                } else {
                    $URLName = $Programs->getURLName($this->view->ProgramId);
                    header("location: /$URLName");
                }
            } else {
                $this->view->preview_image = 1;
            }
        }
    }

    /**
     * Delete account information under infusionsoft.
     */
    protected function salesForceIntegrationDeleteAccount($program) {
        Zend_Registry::get('logger')->info('SalesForce');
        $configIS = Zend_Registry::get('configuration')->salesforce;
        if (!($configIS->active &&
            in_array($program->organizationId, $configIS->orgs->toArray()))
        ) {
            return;
        }
        Zend_Registry::get('logger')->info('SalesForce::Program::Delete');
        $salesforce = Salesforce::getInstance();
        if ($salesforce->login($program->organization)) {
            $salesforce->deleteAccount($program);
            $salesforce->logout();
        } else {
            Zend_Registry::get('logger')->info(
                'SalesForce::FailedLogin::Organization:'.$program->organizationId
            );
        }
    }

    public function deleteAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        if (!$this->view->isAdmin) {
            $this->_helper->redirector('badaccess', 'error');
        }
        $params = $this->_getAllParams();
        if ($_POST) {
            $ProgramId = $params['ProgramId'];
            $program   = Program::get($ProgramId);
            $program->delete();
            $this->salesForceIntegrationDeleteAccount($program);
            $this->_helper->redirector->gotoUrl('/'.$program->organization->urlName);
        }
    }

    public function editinfoAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        if (!$this->view->isAdmin) {
            //$this->_helper->redirector('badaccess', 'error');
        }
        $params  = $this->_getAllParams();
        $program = Program::get($params['ProgramId']);

        if ($_POST) {
            $oldName              = $program->name;
            $program->name        = $params['ProgramName'];
            $program->description = $params['Description'];
            $program->isOpen      = $params['isOpen'];
            $program->modifiedBy  = $this->view->userNew->id;
            $program->modifiedOn  = date('Y-m-d H:i:s');
            $program->save();

            $this->_updateSalesForceProgramInfo($program, $oldName);

            if ($program->contact) {
                $contact               = $program->contact;
                $contact->modifiedById = $this->view->userNew->id;
            } else {
                $contact              = new Contact();
                $contact->siteId      = $program->id;
                $contact->createdById = $this->view->userNew->id;
                $contact->createdOn   = date('Y-m-d H:i:s');
            }
            /*$contact->countryId   = $params['CountryId'];
            $contact->stateId     = $params['RegionId'];
            $contact->cityId      = $params['CityId'];
            $contact->email       = $params['Email'];*/
            $contact->website     = $params['WebAddress'];
            /*$contact->street      = $params['Street'];
            $contact->countryName = $params['Country'];
            $contact->regionName  = $params['Region'];
            $contact->cityName    = $params['City'];*/
            $contact->save();

            $activity              = new Activity();
            $activity->siteId      = $group->id;
            $activity->type        = 'Program Updated';
            $activity->createdById = $this->view->userNew->id;
            $activity->date        = date('Y-m-d H:i:s');
            $activity->save();

            $this->_helper->redirector->gotoUrl('/'.$program->urlName);
        } else {
            $this->view->organization = $program->organization;
            $this->view->program      = $program;
            $this->view->edit         = true;

            $this->view->breadcrumb = $this->view->breadcrumbHelper(
                            $program,
                            'Edit Program'
            );

            $this->_helper->layout->setLayout('newlayout');
            $this->_helper->viewRenderer->setRender('create');
            $this->view->render('nonprofit/breadcrumb.phtml');
            $this->view->render('nonprofit/footer.phtml');
            $this->view->render('nonprofit/tabs.phtml');
            $this->view->render('program/header.phtml');
        }
    }

    /**
     * Update program information under infusionsoft.
     */
    protected function _updateSalesForceProgramInfo($program, $oldName = '') {
        Zend_Registry::get('logger')->info('SalesForce');
        $configIS = Zend_Registry::get('configuration')->salesforce;
        if (!($configIS->active &&
            in_array($program->organizationId, $configIS->orgs->toArray()))
        ) {
            return;
        }
        Zend_Registry::get('logger')->info('SalesForce::Program::Update');
        $salesforce = Salesforce::getInstance();
        if ($salesforce->login($program->organization)) {
            $salesforce->updateAccountInfo($program, $oldName);
            $salesforce->logout();
        } else {
            Zend_Registry::get('logger')->info(
                'SalesForce::FailedLogin::Organization:'.$program->organizationId
            );
        }
    }

    public function editlogoAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Media = new Brigade_Db_Table_Media();
        $Programs = new Brigade_Db_Table_Programs();
        if ($_POST) {
            // save organization media/image
            extract($_POST);
            $programInfo = $Programs->loadInfo1($ProgramId, false);
            $this->view->image = $Media->getSiteMediaById($programInfo['LogoMediaId']);
            $MediaSize = $_FILES['ProgramLogo']['size'];
            $tmpfile = $_FILES['ProgramLogo']['tmp_name'];
            $filename = $_FILES['ProgramLogo']['name'];
            $file_ext = strtolower(substr($filename, strrpos($filename, '.') + 1));
            if ($MediaSize > 0) {
                $destination_thumb = realpath(dirname(__FILE__) . '/../../../')."/public/Media";
                $destination_big = realpath(dirname(__FILE__) . '/../../../')."/public/Media/full";
                // delete existing media image if any
                $old_logo = $this->view->image['SystemMediaName'];
                if (!empty($old_logo)) {
                    if (file_exists("$destination_thumb/$old_logo")) {
                        unlink("$destination_thumb/$old_logo");
                    }
                    if (file_exists("$destination_big/$old_logo")) {
                        unlink("$destination_big/$old_logo");
                    }
                }
                if (!empty($MediaId)) {
                    $Media->editMedia($MediaId, array(
                        'MediaSize' => $MediaSize,
                        'SystemMediaName' => $programInfo['URLName']."-logo.jpg",
                        'UploadedMediaName' => $filename,
                    ));
                } else {
                    // save media
                    $MediaId = $Media->addMedia(array(
                        'MediaSize' => $MediaSize,
                        'SystemMediaName' => $programInfo['URLName']."-logo.jpg",
                        'UploadedMediaName' => $filename,
                    ));

                    // save site media
                    $SiteMedia = new Brigade_Db_Table_MediaSite();
                    $SiteMedia->addMediaSite(array(
                        'MediaId' => $MediaId,
                        'SiteID' => $ProgramId
                    ));

                    $Programs->editProgram($ProgramId, array('LogoMediaId' => $MediaId));
                }

                //Get the file information
                $ImageCrop = new Brigade_Util_ImageCrop();
                $temp_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/tmp/tmp_$ProgramId.jpg";

                // Check if file size does not exceed 2MB
                move_uploaded_file($tmpfile, $temp_image_location);
                $width = $ImageCrop->getWidth($temp_image_location);
                $height = $ImageCrop->getHeight($temp_image_location);
                //Scale the image if it is greater than the width set above
                if ($width > 900) {
                    $scale = 900/$width;
                } else {
                    $scale = 1;
                }
                $uploaded = $ImageCrop->resizeImage($temp_image_location,$width,$height,$scale,$file_ext);
                header("location: /program/cropimage/?ProgramId=$ProgramId&type=$file_ext");
            }
        }
    }

    /**
     * Get header media for organization.
     *
     * @TODO: review this, missing "else" => set view.
     *
     * @param Organization $organization Org obj.
     */
    public function getOrganizationHeaderMedia(Organization $organization) {
        $Media = new Brigade_Db_Table_Media();
        $this->view->siteBanner = false;
        if (!empty($organization->bannerMediaId)) {
            $siteBanner = $Media->getSiteMediaById($organization->bannerMediaId);
            $this->view->siteBanner = $siteBanner;
            $this->view->siteBanner['path'] = "/public/Photos/banner/".$siteBanner['SystemMediaName'];
        } else {
            $siteMedia = $Media->getSiteMediaById($organization->logoMediaId);
        }

    }
}
