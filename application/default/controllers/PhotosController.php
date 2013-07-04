<?php

/**
 * PhotosController - The "photos" controller class
 *
 * @author
 * @version
 */
ini_set("memory_limit", "64M");
ini_set("post_max_size", "32M");
ini_set("upload_max_filesize", "32M");

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/ContactInformation.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Brigade/Db/Table/Media.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/Events.php';
require_once 'Brigade/Db/Table/Photo.php';
require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Util/ImageResize.php';
require_once 'Brigade/Db/Table/Media.php';
require_once 'Brigade/Db/Table/MediaSite.php';
require_once 'Brigade/Db/Table/SiteActivities.php';
require_once 'Brigade/Db/Table/Survey.php';
require_once 'Brigade/Db/Table/GroupMembershipRequest.php';
require_once 'Brigade/Db/Table/GroupMembers.php';
require_once 'Brigade/Util/Auth.php';
require_once 'Brigade/Db/Table/GroupSurveys.php';

require_once 'Brigade/Util/ImageResize.php';
require_once 'BaseController.php';
require_once 'Photo.php';
require_once 'Project.php';


class PhotosController extends BaseController {

    public function init() {
        $parameters = $this->_getAllParams();

        $front = Zend_Controller_Front::getInstance();
        $actionName = $front->getRequest()->getActionName();

        parent::init();

        if (isset($_SESSION['UserId'])) {
            $Users = new Brigade_Db_Table_Users();
            $UserRoles = new Brigade_Db_Table_UserRoles();
            $role = $UserRoles->getUserRole($_SESSION['UserId']);
            $GroupMembers = new Brigade_Db_Table_GroupMembers();
            if(isset($parameters['GroupId'])) {
                $GroupId = $parameters['GroupId'];
                $isMember = $GroupMembers->isMemberExists($GroupId, $_SESSION['UserId']);
                $hasAccess = $UserRoles->UserHasAccess($GroupId, $_SESSION['UserId'], 'group');
            } else if (isset($parameters['ProjectId'])) {
                $ProjectId = $parameters['ProjectId'];
                $Brigades = new Brigade_Db_Table_Brigades();
                $GroupId = $Brigades->getGroupId($ProjectId);
                $isMember = $GroupMembers->isMemberExists($GroupId, $_SESSION['UserId']);
                $hasAccess = $UserRoles->UserHasAccess($GroupId, $_SESSION['UserId'], 'group');
            } else if (isset($parameters['PhotoId'])) {
                $Photos = new Brigade_Db_Table_Photo();
                $photo = $Photos->loadInfo($parameters['PhotoId']);
                $GroupId = $photo['GroupId'];
                $isMember = $GroupMembers->isMemberExists($GroupId, $_SESSION['UserId']);
                $hasAccess = $UserRoles->UserHasAccess($GroupId, $_SESSION['UserId'], 'group');
            }
            if (isset($hasAccess) && ($hasAccess && $role['RoleId'] == 'ADMIN') || $role['RoleId'] == 'GLOB-ADMIN') {
                $this->view->isAdmin = true;
                $this->view->toggleAdminView = $role['isToggleAdminView'];
                $this->view->UserRoleId = $role['UserRoleId'];
            }
            if(isset($isMember) && $isMember) {
                $this->view->isMember = true;
            } else {
                $this->view->isMember = false;
            }
        }

        if (isset($parameters['GroupId']) && ($actionName == 'manage' || $actionName == 'add' || $actionName == 'show')) {
            //s$parameters = $this->_getAllParams();
            //$GroupId = $parameters['GroupId'];
            //$this->view->GroupId = $GroupId;
            //$Photo = new Brigade_Db_Table_Photo();
            //$album = $Photo->getAlbumPhotoBySiteId($GroupId);
            //$this->view->album = $album;
        }
        
        /* Moved to BaseController. TO DELETE.
        if (!isset($_SESSION['UserId']) && isset($_COOKIE['siteAuth'])) {
            parse_str($_COOKIE['siteAuth']);
            $auth = Zend_Auth::getInstance();
            $authAdapter = new Brigade_Util_Auth();
            $authAdapter->setIdentity($user)->setCredential($hash);
            $authResult = $auth->authenticate($authAdapter);
            if ($authResult->isValid()) {
                $userInfo = $authAdapter->_resultRow;
                if ($userInfo->Active == 1) {
                    $_SESSION['FullName'] = $userInfo->FirstName." ".$userInfo->LastName;
                    $_SESSION['UserId'] = $userInfo->UserId;
                    header("Location: " . $_SERVER['PHP_SELF']);
                }
            }
        }
        */
    }

    /**
     * The default action - show the home page
     */
    public function indexAction() {
        $Groups = new Brigade_Db_Table_Groups();
        $Photos = new Brigade_Db_Table_Photo();
        $Brigades = new Brigade_Db_Table_Brigades();
        $contact_info = new Brigade_Db_Table_ContactInformation();
        if (isset($parameters['$GroupId'])) {
            $this->view->albums = $Photos->getAlbums($GroupId);
            $this->view->data = $Groups->loadInfo($GroupId);

            $this->view->brigades = $Groups->loadUpcomingBrigades($GroupId, "all");
            $this->view->sitemedia = new Brigade_Db_Table_Media();
            $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();
            $this->view->survey = new Brigade_Db_Table_Survey();

            //load group's events
            $Events = new Brigade_Db_Table_Events();
            $this->view->events = $Events->getSiteEvents($parameters['GroupId']);

            // load group's fundraising campaigns
            $Projects = new Brigade_Db_Table_Brigades();
            $this->view->fundraising_campaigns = $Projects->listGroupCampaigns($GroupId);

            // load group members
            $GroupMembers = new Brigade_Db_Table_GroupMembers();
            $GroupMembershipRequest = new Brigade_Db_Table_GroupMembershipRequest();
            $this->view->members = $GroupMembers->getGroupMembers($this->view->data['GroupId']);
            // check if user is a member of this group hide the "Join Group" button otherwise display it
            $is_member = $GroupMembers->isMemberExists($this->view->data['GroupId'], $_SESSION['UserId']);
            $has_request_membership = $GroupMembershipRequest->hasMembershipRequest($this->view->data['GroupId'], $_SESSION['UserId']);
            if (isset($_SESSION['UserId']) && ($is_member || $has_request_membership)) {
                $this->view->is_member = true;
            } else if (isset($_SESSION['UserId'])) {
                $this->view->is_member = false;
            }

        }
    }

    /**
     * Upload image to album project
     * Came from profile tab photos upload image.
     */
    public function uploadimageAction() {
        $parameters = $this->_getAllParams();
        $srcBig     = realpath(dirname(__FILE__) . '/../../../')."/public/Photos/full";
        $srcThumb   = realpath(dirname(__FILE__) . '/../../../')."/public/Photos/thumb";
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        // Check extensions
        $badExtension = false;
        for($i = 0; $i < count($_FILES['files']['name']); $i++) {
            $ext = strtolower(substr($_FILES['files']['name'][$i], strrchr($_FILES['files']['name'][$i], '.') + 1));
            if(!in_array($ext, array('jpg', 'jpeg', 'png', 'gif'))) {
                $badExtensions = true;
            }
        }

        if (!$badExtension) {
            //upload and create records
            for($i = 0; $i < count($_FILES['files']['name']); $i++) {
                $name = strtolower($_FILES['files']['name'][$i]);
                $dot  = strrpos($name, '.');
                $name = str_replace('.', '_', $name);
                $name = str_replace(' ', '_', $name);
                $name = str_replace('-', '_', $name);

                $name[$dot]  = '.';
                $fileData = explode('.', $name);
                $fileName = "{$fileData[0]}";
                $fileExt  = "{$fileData[1]}";

                $fileCounter = 0;
                while (file_exists("$srcThumb/$fileName.$fileExt")) {
                    $fileCounter++;
                    $fileName = "{$fileData[0]}_{$fileCounter}";
                }

                $handle = new Brigade_Util_ImageResize();
                $handle->upload(array(
                    'name'     => $_FILES['files']['name'][$i],
                    'tmp_name' => $_FILES['files']['tmp_name'][$i],
                    'size'     => $_FILES['files']['size'][$i],
                    'type'     => $_FILES['files']['type'][$i],
                    'error'    => $_FILES['files']['error'][$i],
                ));

                //thumb
                $handle->file_new_name_body = $fileName;
                $handle->image_resize       = true;
                $handle->image_y            = 80;
                $handle->image_ratio_x      = true;
                $handle->image_src_type     = $fileData[1];
                $handle->process("$srcThumb/");

                //big
                $handle->file_new_name_body = $fileName;
                $handle->image_resize       = true;
                $handle->image_y            = 600;
                $handle->image_ratio_x      = true;
                $handle->process("$srcBig/");

                $handle->clean();

                //object
                $photo = new Photo();
                if (isset($parameters['groupId'])) {
                    $photo->groupId = $parameters['groupId'];
                } else {
                    $photo->groupId = '';
                }
                $photo->projectId = $parameters['projectId'];
                $photo->createdById       = $_SESSION['UserId'];
                $photo->createdOn         = date('Y-m-d H:i:s');
                $photo->systemMediaName   = "$fileName.$fileExt";
                $photo->uploadedMediaName = "$fileName.$fileExt";
                $photo->description       = $parameters['captions'][$i];
                $photo->save();
            }
            echo json_encode(array('ok' => true));
        } else {
            echo json_encode(array('err' => 'Bad extensions.'));
        }
    }

    /**
     * Download all photos of the project album selected.
     *
     */
    public function downloadalbumAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $parameters  = $this->_getAllParams();
        $zip         = new ZipArchive();
        $zipFileName = session_id(). '.zip';
        $project     = Project::get($parameters['projectId']);
        $srcBig      = realpath(dirname(__FILE__) . '/../../../')."/public/Photos/full/";

        //create the file and throw the error if unsuccessful
        $zip->open(
            realpath(dirname(__FILE__) . '/../../../').'/public/tmp/'.
            $zipFileName,
            ZIPARCHIVE::CREATE
        );

        //add each files of $file_name array to archive
        foreach($project->photos as $file) {
            echo $srcBig.$file->uploadedMediaName . '<br>';
            $zip->addFile($srcBig.$file->uploadedMediaName, $file->uploadedMediaName);
        }
        $zip->close();

        //then send the headers to foce download the zip file
        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=album_{$project->urlName}.zip");
        header("Pragma: no-cache");
        header("Expires: 0");
        readfile(realpath(dirname(__FILE__) . '/../../../').'/public/tmp/'.$zipFileName);
    }

    public function albumsoldAction() {
        $parameters = $this->_getAllParams();
        $Groups = new Brigade_Db_Table_Groups();
        $Brigades = new Brigade_Db_Table_Brigades();
        $ContactInformation = new Brigade_Db_Table_ContactInformation();
        $donations = new Brigade_Db_Table_ProjectDonations();

        if (isset($parameters['GroupId'])) {
            $GroupId = $parameters['GroupId'];
            $this->view->data = $Groups->loadInfo($GroupId);
            $this->view->progOrg = $Groups->loadProgOrg($GroupId);
            $this->view->brigades = $Groups->loadBrigades($GroupId, "upcoming");
            $this->view->past_brigades = $Groups->loadBrigades($GroupId, "completed");

            $this->view->sitemedia = new Brigade_Db_Table_Media();
            $this->view->contactinfo = $ContactInformation;
            $this->view->survey_class = new Brigade_Db_Table_Survey();

            //load group's events
            $Events = new Brigade_Db_Table_Events();
            $this->view->events = $Events->getSiteEvents($parameters['GroupId']);

            //load group memebers
            $GroupMembers = new Brigade_Db_Table_GroupMembers();
            $this->view->members = $GroupMembers->getGroupMembers($GroupId);

            // load group's fundraising campaigns
            $Projects = new Brigade_Db_Table_Brigades();
            $this->view->fundraising_campaigns = $Projects->listGroupCampaigns($GroupId);

            $Photo = new Brigade_Db_Table_Photo();
            $this->view->photo_class = $Photo;
            $this->view->survey_class = new Brigade_Db_Table_Survey();
            $this->view->albums = $Photo->getGroupAlbums($GroupId);
            $this->view->imageresize_class = new Brigade_Util_ImageResize();

            // check if user is a member of this group hide the "Join Group" button otherwise display it
            $GroupMembershipRequest = new Brigade_Db_Table_GroupMembershipRequest();
            if(isset($_SESSION['UserId'])) {
                $is_member = $GroupMembers->isMemberExists($GroupId, $_SESSION['UserId']);
                if ($is_member) {
                    $this->view->is_member = true;
                } else if (isset($_SESSION['UserId'])) {
                    $this->view->is_member = false;
                }
            }

        }
    }

    public function activityphotoAction() {
        $parameters = $this->_getAllParams();
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $Brigades = new Brigade_Db_Table_Brigades();
        $ProjectId = $parameters['projectId'];

        if (!empty($ProjectId)) {
            $media_gallery = $Brigades->getMediaGallery($ProjectId);
            if (count($media_gallery)) {
                $payload = json_encode($media_gallery);
                header("content-type: application/x-json; charset=utf-8");
                echo $payload;
            }
        }
    }

    /**
     * Delete photo from project album
     * If deleting the album cover it sets the next photo as it.
     *
     * @param Integer $PhotoId By post.
     */
    public function deletephotoAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if ($_POST) {
            $parameters = $this->_getAllParams();
            $Photo      = Photo::get($parameters['PhotoId']);
            if (is_null($Photo) || !$Photo) {
                return false;
            }
            $SiteId    = $Photo->projectId;
            $mediaName = $Photo->systemMediaName;

            //check is there any photo in the album and set this as album cover
            if ($Photo->isAlbumCover) {
                $album = Photo::getPhotosByProject($SiteId);
                foreach($album as $pic) {
                    if ($pic->id != $Photo->id) {
                        $pic->setAlbumCover();
                        break;
                    }
                }
            }
            $Photo->delete();

            // delete image copy from /public/Media and /public/Media/full
            $src = realpath(dirname(__FILE__) . '/../../../');
            if (file_exists("$src/public/Photos/$mediaName")) {
                unlink("$src/public/Photos/$mediaName");
            }
            if (file_exists("$src/public/Photos/full/$mediaName")) {
                unlink("$src/public/Photos/full/$mediaName");
            }
            if (file_exists("$src/public/Photos/thumb/$mediaName")) {
                unlink("$src/public/Photos/thumb/$mediaName");
            }
        }
    }

    public function tagphotoAction() {
        if ($_POST) {
            $PhotoId = $_POST['PhotoId'];
            $SystemMediaName = $_POST['SystemMediaName'];
            $Photo = new Brigade_Db_Table_Photo();
            $data = $Photo->loadInfo($PhotoId);
            $Photo->deletePhoto($PhotoId);
            $SiteId = $data['ProjectId'];
            $there_is_album=$Photo->istherealbumcover($SiteId);
            if ($there_is_album[0]['albumcover']==0) {
                $phoid = $Photo->GetAllPhotosbyPi($SiteId);
                $pid= $phoid[0]['PhotoId'];
                $Photo->SetAsPrimary($pid, array('isAlbumCover' => 1));
            }
            // delete image copy from /public/Media and /public/Media/full
            if (file_exists("/public/Photos/$SystemMediaName")) {
                unlink("/public/Photos/$SystemMediaName");
            }
            if (file_exists("/public/Photos/full/$SystemMediaName")) {
                unlink("/public/Photos/full/$SystemMediaName");
            }
        }
    }


    public function uploadAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $this->_helper->layout->disableLayout();
        $parameters = $this->_getAllParams();
        $Brigades = new Brigade_Db_Table_Brigades();
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $Photo = new Brigade_Db_Table_Photo();
        if (isset($parameters['ProjectId'])) {
            $this->view->SiteId = $parameters['ProjectId'];
            $this->view->project = $projectInfo = $Brigades->loadInfo1($parameters['ProjectId']);
            $this->view->URLName = $LookupTable->getURLbyId($parameters['ProjectId']);
            $GroupId = $projectInfo['GroupId'];
            $this->view->albums = $Photo->getGroupAlbums($GroupId);
        } else if (isset($parameters['GroupId'])) {
            $GroupId = $parameters['GroupId'];
            $this->view->URLName = $LookupTable->getURLbyId($GroupId);
            $this->view->SiteId = $parameters['GroupId'];
        }
        $this->view->albums = $Photo->getGroupAlbums($GroupId);
        $this->view->GroupId = $GroupId;
        if ($_POST) {
            echo 'TEst';
            $this->_helper->veiwRenderer->setNoRender();
            $SiteId = $_POST['ProjectId'];
            $projInfo = $Brigades->loadInfoBasic($_POST['ProjectId']);
            $this->_helper->viewRenderer->setNoRender();
            $destination_small = realpath(dirname(__FILE__) . '/../../../')."/public/Photos";
            $destination_big = realpath(dirname(__FILE__) . '/../../../')."/public/Photos/full";
            $destination_thumb = realpath(dirname(__FILE__) . '/../../../')."/public/Photos/thumb";
            if ($_POST['total_uploads'] > 0) {
                $this->view->uploadedimage = array();
                for($i = 1; $i <= $_POST['total_uploads']; $i++) {
                    $PhotoSize = $_FILES["attachment-$i"]['size'];
                    echo 'PhotoSize:'.$PhotoSize;
                    $filename = $_FILES["attachment-$i"]['name'];
                    $comments = stripslashes($_POST['Comments'][$i]);
                    if ($PhotoSize > 0 && $PhotoSize < 2097152) {
                        // create a resized version of the image
                        $imageresize = new Brigade_Util_ImageResize();
                        $imageresize->upload($_FILES["attachment-$i"]);
                        if ($imageresize->uploaded) {
                            $Photo = new Brigade_Db_Table_Photo();
                            $has_album_cover = $Photo->hasAlbumCover($SiteId);
                            $isAlbumCover = !$has_album_cover ? 1 : 0;
                            $ctr = 1;
                            $PhotoName = $projInfo['URLName']."-photo-$ctr.jpeg";
                            while($Photo->isPhotoNameExists($PhotoName)) {
                                $ctr++;
                                $PhotoName = $projInfo['URLName']."-photo-$ctr.jpeg";
                            }
                            $Photo->addPhotoGallery(array(
                                'GroupId' => $GroupId,
                                'MediaSize' => $PhotoSize,
                                'UploadedMediaName' => $filename,
                                'SystemMediaName' => $PhotoName,
                                'ProjectId' => $SiteId,
                                'isAlbumCover' => $isAlbumCover,
                                'Description' => $comments
                            ));
                            $SystemMediaName = explode(".", $PhotoName);
                            $imageresize->file_new_name_body = $SystemMediaName[0];
                            $imageresize->file_safe_name = false;
                            $imageresize->image_resize = true;
                            $imageresize->image_convert = 'jpeg';
                            $imageresize->image_x = 326;
                            $imageresize->image_y = 225;
                            $imageresize->Process("$destination_small/");
                            // create the thumbnail
                            $imageresize->file_new_name_body = $SystemMediaName[0];
                            $imageresize->file_safe_name = false;
                            $imageresize->image_resize = true;
                            $imageresize->image_convert = 'jpeg';
                            $imageresize->image_x = 200;
                            $imageresize->image_y = 150;
                            $imageresize->Process("$destination_thumb/");
                            // create the full not resizing
                            $imageresize->file_new_name_body = $SystemMediaName[0];//$photoId
                            $imageresize->file_safe_name = false;
                            $imageresize->image_resize = false;
                            $imageresize->image_convert = 'jpeg';
                            if($imageresize->image_x > 800 || $imageresize->image_y > 800) {
                                $imageresize->image_resize = true;
                                if($imageresize->image_x > $imageresize->image_y) {
                                    $scale = $imageresize->image_y / $imageresize->image_x;
                                    $imageresize->image_x = 800;
                                    $imageresize->image_y = 800 * $scale;
                                } else {
                                    $scale = $imageresize->image_x / $imageresize->image_x;
                                    $imageresize->image_y = 800;
                                    $imageresize->image_x = 800 * $scale;
                                }
                            }
                            $imageresize->Process("$destination_big/");
                            if ($imageresize->processed) {
                                $imageresize->Clean();
                                if (file_exists("$destination_thumb/$filename")) {
                                    unlink("$destination_thumb/$filename");
                                }
                                if (file_exists("$destination_big/$filename")) {
                                    unlink("$destination_big/$filename");
                                }
                                if (file_exists("$destination_small/$filename")) {
                                    unlink("$destination_small/$filename");
                                }
                            } else {
                                echo 'error: ' . $imageresize->error;
                            }
                            // log the site activity
                            $SiteActivities = new Brigade_Db_Table_SiteActivities();
                            $SiteActivities->addSiteActivity(array(
                                'SiteId' => $SiteId,
                                'ActivityType' => 'Uploads',
                                'CreatedBy' => $_SESSION['UserId'],
                                'ActivityDate' => date('Y-m-d H:i:s'),
                            ));
                            $this->view->uploadedimage[] = "/public/Photos/".strtolower($SystemMediaName[0]).".jpeg";
                        }
                    }
                }
                if (count($this->view->uploadedimage)) {
                    header("location: /".$projInfo['URLName']."/photos");
                } else {
                    echo 'no photos were uploaded';
                }
            }
        }
    }


    public function manageAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();
        $Photo = new Brigade_Db_Table_Photo();
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $Brigades = new Brigade_Db_Table_Brigades();
        $Groups = new Brigade_Db_Table_Groups();
        $this->view->sitemedia = new Brigade_Db_Table_Media();
        $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();
        $this->view->UserId = $_SESSION['UserId'];
        $this->view->imageresize_class = new Brigade_Util_ImageResize();
        if (isset($parameters['GroupId'])) {
            $GroupId = $parameters['GroupId'];
            $this->view->GroupId = $GroupId;
            $this->view->data = $Groups->loadInfo1($GroupId);
            $this->view->ProgOrg = $Groups->loadProgOrg($GroupId);
            $this->view->album = $Photo->getGroupAlbums($GroupId);
            $this->view->photos = $Photo->getGroupPhotos($GroupId);
            $this->view->URLName = $LookupTable->getURLbyId($GroupId);
            $this->view->progOrg = $Groups->loadProgOrg($GroupId);

        } else if (isset($parameters['ProjectId'])) {
            $ProjectId = $parameters['ProjectId'];
            $projInfo = $Brigades->loadInfoBasic($ProjectId);
            $this->view->ProjectId = $ProjectId;
            $this->view->GroupId = $projInfo['GroupId'];
            $this->view->URLName = $LookupTable->getURLbyId($ProjectId);
            $this->view->photos = $Photo->getAlbumPhotos($ProjectId);
            $this->view->album = $Photo->getGroupAlbums($projInfo['GroupId']);
            $this->view->data = $Groups->loadInfo($projInfo['GroupId']);
            $this->view->progOrg = $Groups->loadProgOrg($projInfo['GroupId']);

        }
    }

    /* set the lasted as 0 and the new with 1*/
    public function setprimaryAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        if ($_POST) {
            $Photo = new Brigade_Db_Table_Photo();

            $PhotoId = $_POST['PhotoId'];
            $data = $Photo->loadInfo($PhotoId);

            if($data['isAlbumCover'] != 1) {
                //unset current album cover if one exists
                $currentAlbumCover = $Photo->hasAlbumCover($data['ProjectId']);
                if($currentAlbumCover){
                    $Photo->setAlbumCover($currentAlbumCover['PhotoId'], 0);
                }
                $Photo->setAlbumCover($PhotoId, 1);
            }
        }
    }

    public function showphotosAction() {
        $parameters = $this->_getAllParams();
        $Groups = new Brigade_Db_Table_Groups();
        $Brigades = new Brigade_Db_Table_Brigades();
        $contact_info = new Brigade_Db_Table_ContactInformation();
        $donations = new Brigade_Db_Table_ProjectDonations();
        if (isset($parameters['ProjectId'])) {
            $ProjectId = $parameters['ProjectId'];
            $Photo = new Brigade_Db_Table_Photo();
            $this->view->project = $Brigades->loadInfo($ProjectId);
            $GroupId = $this->view->project['GroupId'];
            $this->view->data = $Groups->loadInfo($GroupId);
            $this->view->brigades = $Groups->loadBrigades($GroupId, "upcoming");
            $this->view->past_brigades = $Groups->loadBrigades($GroupId, "completed");
            $this->view->progOrg = $Groups->loadProgOrg($GroupId);
            $this->view->sitemedia = new Brigade_Db_Table_Media();
            $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();
            $this->view->survey = new Brigade_Db_Table_Survey();

            //load group's events
            $Events = new Brigade_Db_Table_Events();
            $this->view->events = $Events->getSiteEvents($GroupId);

            //load group memebers
            $GroupMembers = new Brigade_Db_Table_GroupMembers();
            $this->view->members = $GroupMembers->getGroupMembers($GroupId);

            // load group's fundraising campaigns
            $Projects = new Brigade_Db_Table_Brigades();
            $this->view->fundraising_campaigns = $Projects->listGroupCampaigns($GroupId);

            $this->view->photos = $Photo->getAlbumPhotos($ProjectId);
            $this->view->survey_class = new Brigade_Db_Table_Survey();

            // check if user is a member of this group
            $GroupMembershipRequest = new Brigade_Db_Table_GroupMembershipRequest();
            if(isset($_SESSION['UserId'])) {
                $is_member = $GroupMembers->isMemberExists($GroupId, $_SESSION['UserId']);
                if ($is_member) {
                    $this->view->is_member = true;
                } else if (isset($_SESSION['UserId'])) {
                    $this->view->is_member = false;
                }
            }

        }
    }


    public function pictureAction() {
        $parameters = $this->_getAllParams();
        $Brigades = new Brigade_Db_Table_Brigades();
        $contact_info = new Brigade_Db_Table_ContactInformation();

        $PhotoId = $parameters['PhotoId'];
        if (isset($PhotoId)) {
            $Photo = new Brigade_Db_Table_Photo();
            $this->view->photo = $Photo->loadInfo($PhotoId);
            // get other photos in the album if any, and determine the prev and next photos
            $ctr = 0;
            $album_photos = $Photo->getAlbumPhotos($this->view->photo['ProjectId']);
//            echo "<pre>";
//            print_r($album_photos);
//            echo "</pre>";
            $photos = array();
            foreach($album_photos as $pic) {
                $photos[] = $pic['PhotoId'];
            }
            $curr_photo = array_search($PhotoId, $photos);
            if ($curr_photo && $curr_photo > 0) {
                $this->view->prev_photo = $photos[$curr_photo-1];
            }
            if ($curr_photo < count($photos) - 1) {
                $this->view->next_photo = $photos[$curr_photo+1];
            }

            $Groups = new Brigade_Db_Table_Groups();
            $GroupId = $this->view->photo['GroupId'];
            $this->view->data = $Groups->loadInfo($GroupId);
            $this->view->project = $Brigades->loadInfo($this->view->photo['ProjectId']);
            $this->view->brigades = $Groups->loadBrigades($GroupId, "upcoming");
            $this->view->past_brigades = $Groups->loadBrigades($GroupId, "completed");
            $this->view->progOrg = $Groups->loadProgOrg($GroupId);
            $this->view->brigadesclass = new Brigade_Db_Table_Brigades();
            $this->view->sitemedia = new Brigade_Db_Table_Media();
            $this->view->contactinfo = new Brigade_Db_Table_ContactInformation();
            $this->view->survey_class = new Brigade_Db_Table_Survey();

            // get other photos for this album
            $this->view->gallery = $Photo->getAlbumPhotos($this->view->photo['ProjectId']);

            //load group's events
            $Events = new Brigade_Db_Table_Events();
            $this->view->events = $Events->getSiteEvents($GroupId);

            //load group members
            $GroupMembers = new Brigade_Db_Table_GroupMembers();
            $this->view->members = $GroupMembers->getGroupMembers($GroupId);

            // load group's fundraising campaigns
            $Projects = new Brigade_Db_Table_Brigades();
            $this->view->fundraising_campaigns = $Projects->listGroupCampaigns($GroupId);

            $Users = new Brigade_Db_Table_Users();
            $this->view->uploader = $Users->loadInfo($this->view->photo['CreatedBy']);

            // check if group has created a survey for users joining the group
            $GroupSurveys = new Brigade_Db_Table_GroupSurveys();
            $this->view->survey = $GroupSurveys->getSurveys($GroupId, false, "Joining Group");

        }
    }



    public function showaAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        $Photo = new Brigade_Db_Table_Photo();
        $Groups = new Brigade_Db_Table_Groups();
        $GroupId = $parameters['GroupId'];
        if (isset($parameters['ProjectId'])) {
            $SiteId = $parameters['ProjectId'];
            $this->view->ProjectId =$SiteId;
        } else {
            $SiteId = -1;
            $this->view->ProjectId = $SiteId;
        }
        $groupInfo = $Groups->loadInfo($GroupId);
        $photos = $Photo->getSitePhotoGallery($SiteId, $GroupId);
        $printalbum ='<table id="media-list" cellspacing="3" cellpadding="3" border="0">
            <tr class="tblHeader" style="background-color:#333">
                <th scope="col" style="width:20px;">&nbsp;</th>
                <th scope="col" >Image</th>
                <th scope="col" >Album</th>
                <th scope="col" >Added By</th>
                <th scope="col" >Date Added</th>
                <th scope="col" style="width:90px;">Album Cover?</th>
            </tr>';
        if (count($photos)) {
            $ctr = 0;
            foreach($photos as $media) {
                $printalbum .= '<tr style="background-color:';
                $printalbum .= $ctr%2 == 1 ? "#e7e7e9" : "white";
                $printalbum .= ';">
                    <td>
                        <input type="hidden" id="PhotoId" name="PhotoId" value="'.$media['PhotoId'].'" />
                        <input type="hidden" id="SystemMediaName" name="SystemMediaName" value="'.strtolower($media['SystemMediaName']).'" />
                        <input id="delete_'.$media['PhotoId'].'" type="checkbox" name="delete_'.$media['PhotoId'].'" />
                    </td>';
                $printalbum .= '
                    <td style="padding:5px;">
                        <img src="/public/Photos/'.$media['SystemMediaName'].'" height="50" width="100">
                    </td>';
                $printalbum .= '
                    <td style="text-align:center;">
                        <a href="/'.$media['URLName'].'/show-photos">'.$media['Name'].'</a></td>';
                $printalbum .= '
                    <td style="text-align:center;">
                        <a href="'.$media['URLName'].'target="_blank">';
                $printalbum .= stripslashes($media['FirstName']);
                $printalbum .= stripslashes($media['LastName']);
                $printalbum .= '</a>
                        </td>';
                $printalbum .= '
                    <td style="text-align:center;">
                        '.date('Y-m-d', strtotime($media['CreatedOn'])) .'
                    </td>';
                $printalbum .='<td align="center">';
                if ($media['isAlbumCover'] == 1) {
                    $printalbum .= 'Default';
                } else {
                    $printalbum .= '<a href="javascript:;" onclick="setPrimary(';
                    $printalbum .= $media['PhotoId'];
                    $printalbum .= ')">Set as Primary</a>';
                }
                $printalbum .= '</td>
                    </tr>';
                $ctr++;
            }
        } else {
            $printalbum .= '<tr>
                <td colspan="6" style="font-style:italic">No record found</td>
            </tr>';
        }
        $printalbum .='</table>
                <br/>';
        if (count($photos)) {
            $printalbum .= '
                <div style="width:100%">
                    <a class="btn btngreen" title="Back" href="/'.$groupInfo['URLName'].'">Back</a>
                    <input type="button" class="btn btngreen" name="delete" value="Delete" onclick="return deletePhoto()" />
                    <a id="btngreen1" class="btn btngreen" title="Upload" href="javascript:;" onclick="openWindow(\'/'.$groupInfo['URLName'].'/upload-photos\', \'Upload Photo\', \'600\',\'400\')">Upload</a>
                </div>';
        }
        echo $printalbum;
    }


    public function addAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        //the same code that manage in init
        $parameters = $this->_getAllParams();
        $GroupId = $parameters['GroupId'];
        $this->view->GroupId = $GroupId;

        if (isset($parameters['comments'])) {
            $comments=$parameters['comments'];
        } else {
            $comments="";
        }
        $Photo = new Brigade_Db_Table_Photo();
        $album = $Photo->getAlbumPhotoBySiteId($GroupId);
        $this->view->album = $album;
        $Photo = new Brigade_Db_Table_Photo();
        $album = $Photo->getAlbumPhotoBySiteId($GroupId);
        $this->view->album = $album;
        if (isset($parameters['SiteId'])) {
            $SiteId = $parameters['SiteId'];
            $this->view->SiteId =$SiteId;
        } else {
            $SiteId = -1;
            $this->view->SiteId = $SiteId;
        }

        if ((isset($_POST['form'])) && ($_POST['form'] == 'add')) {
            $printting = '<div class="clear"></div><form id="uploadphoto" method="post" action="/photos/add/GroupId/'.$_POST['GroupId'].'" enctype="multipart/form-data" onsubmit="return validate();">
                <div style="float: left; margin-right: 10px; margin-top: -23px;">';
            if (count($album)) {
                $printting .= '
                    <br /><h3>Upload to Volunteer Opportunity:</h3>
                    <select id="Albumfilter" style="padding:2px" onchange="setsiteid(this)">
                            <option selected value="-1">Please select Album</option>';
                foreach ($album as $list) {
                    $printting .='<option value="'.$list['ProjectId'].'">'.$list['Albumname'].'</option>';
                }
                $printting .= '</select>';
            }
            $printting .= '
                <div id="otro" class="multfile">
                <input type="file" name="attachment1" id="attachment1" onchange="document.getElementById(\'moreUploadsLink\').style.display = \'block\';" />
                <textarea id="comments1" name="comments1" rows="2" cols="48" style="font-size: 12px;" onfocus="if(this.value==this.defaultValue)this.value=\'\';" onblur="if(this.value==\'\')this.value=this.defaultValue;" >Include Description?</textarea>
                <div id="moreUploads"></div>
                <div id="moreUploadsLink" style="display:none;"><a href="javascript:addFileInput();">Attach another File</a></div>
                </div>
                    <input type="hidden" id="SiteId" name="SiteId" value="'.$_POST['SiteId'].'" />
                    <input type="hidden" id="cant" name="cant" value="1" />
                    <input type="submit" name="submit" value="Upload" class="button" />
             </form> ';
            echo $printting;
        } else {
            $destination_small = "/public/Photos";
            $destination_big = "/public/Photos/full";
            $destination_thumb = "/public/Photos/thumb";
            if ($_POST['cant'] >= 1) {
                for($i = 1; $i <= $_POST['cant']; $i++) {
                    $SiteId = $_POST['SiteId'];
                    $filenumber = 'attachment'.$i;
                    $com = 'comments'.$i;

                    $PhotoSize = $_FILES[$filenumber]['size'];
                    $filename = $_FILES[$filenumber]['name'];
                    $comments = $_POST[$com];
                    if ($PhotoSize > 0 && $PhotoSize < 2097152) {
                        // create a resized version of the image
                        $imageresize = new Brigade_Util_ImageResize();
                        $imageresize->upload($_FILES[$filenumber]);
                        if ($imageresize->uploaded) {
                            $Photo = new Brigade_Db_Table_Photo();
                            $there_is_album=$Photo->istherealbumcover($SiteId);
                            if ($there_is_album[0]['albumcover'] == 0) {
                                $isalbumc = 1;
                            }else {
                                $isalbumc = 0;
                            }

                            $Photos = $Photo->addPhotoGallery(array(
                                'GroupId' => $GroupId,
                                'MediaSize' => $PhotoSize,
                                'UploadedMediaName' => $filename,
                                'CreatedBy' => $_SESSION['UserId'],
                                'CreatedOn' => date('Y-m-d H:i:s'),
                                'ProjectId' => $SiteId,
                                'isAlbumCover'=>$isalbumc,
                                'Description'=>$comments
                            ));
                            $pic = $Photos[0];//Obtain the last rows
                            $splitpic = $pic['SystemMediaName'];
                            $pieces = explode(".",$splitpic );

                            $imageresize->file_new_name_body = strtolower($pieces[0]);
                            $imageresize->file_safe_name = false;
                            $imageresize->image_resize = true;
                            $imageresize->image_convert = 'jpeg';
                            $imageresize->image_x = 326;
                            $imageresize->image_y = 225;
                            $imageresize->Process("$destination_small/");
                            // create the thumbnail
                            $imageresize->file_new_name_body = strtolower($pieces[0]);
                            $imageresize->file_safe_name = false;
                            $imageresize->image_resize = true;
                            $imageresize->image_convert = 'jpeg';
                            $imageresize->image_x = 200;
                            $imageresize->image_y = 150;
                            $imageresize->Process("$destination_thumb/");
                            // create the full not resizing
                            $imageresize->file_new_name_body = strtolower($pieces[0]);//$photoId
                            $imageresize->file_safe_name = false;
                            $imageresize->image_resize = false;
                            $imageresize->image_convert = 'jpeg';
                            if($imageresize->image_x > 800 || $imageresize->image_y > 800) {
                                $imageresize->image_resize = true;
                                if($imageresize->image_x > $imageresize->image_y) {
                                    $scale = $imageresize->image_y / $imageresize->image_x;
                                    $imageresize->image_x = 800;
                                    $imageresize->image_y = 800 * $scale;
                                } else {
                                    $scale = $imageresize->image_x / $imageresize->image_x;
                                    $imageresize->image_y = 800;
                                    $imageresize->image_x = 800 * $scale;
                                }
                            }
                            $imageresize->Process("$destination_big/");


                            if ($imageresize->processed) {
                                $imageresize->Clean();
                                if (file_exists("$destination_thumb/$filename")) {
                                    unlink("$destination_thumb/$filename");
                                }
                                if (file_exists("$destination_big/$filename")) {
                                    unlink("$destination_big/$filename");
                                }
                                if (file_exists("$destination_small/$filename")) {
                                    unlink("$destination_small/$filename");
                                }
                            } else {
                                echo 'error: ' . $imageresize->error;
                            }

                            // log the site activity
                            $SiteActivities = new Brigade_Db_Table_SiteActivities();
                            $SiteActivities->addSiteActivity(array(
                                'SiteId' => $SiteId,
                                'ActivityType' => 'Uploads',
                                'CreatedBy' => $_SESSION['UserId'],
                                'ActivityDate' => date('Y-m-d H:i:s'),
                            ));

                            $this->view->uploadedimage = "/public/Photos/".strtolower($pieces[0]).".jpeg";
                        }

                    }
                }
            }
            header("location: /photos/manage/GroupId/$GroupId");
        }
    }

    public function updatedescriptionAction(){
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        if ($_POST) {
            $Photo = new Brigade_Db_Table_Photo();

            $photoId = $_POST['PhotoId'];
            $newDescription = $_POST['newPhotoDescription'];

            $Photo->updateDescription($photoId, $newDescription);
        }
    }

}
