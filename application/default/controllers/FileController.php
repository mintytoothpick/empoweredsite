<?php

/**
 * FileController - The "file" controller class
 *
 * @author
 * @version
 */

require_once 'Zend/Controller/Action.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/ContactInformation.php';
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
require_once 'Brigade/Db/Table/FundraisingCampaign.php';
require_once 'Brigade/Db/Table/GroupMembershipRequest.php';
require_once 'Brigade/Db/Table/GroupMembers.php';
require_once 'Brigade/Db/Table/Files.php';
require_once 'Brigade/Util/Auth.php';
require_once 'BaseController.php';

require_once 'Group.php';
require_once 'File.php';

class FileController extends BaseController {

    public function init() {
        parent::init();
    }

    /**
     * The default action - show the home page
     */
    public function indexAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
    }

    public function galleryAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $this->_helper->layout->disableLayout();
        $parameters = $this->_getAllParams();
        $GroupId = $parameters['ProjectId'];
        $this->view->GroupId = $GroupId;
        // get project GroupId
        $Brigades = new Brigade_Db_Table_Brigades();
        $groupInfo = $Brigades->loadInfo1($GroupId);
        $GroupId = $groupInfo['GroupId'];
        $this->view->GroupId = $GroupId;

        if ($_POST) {
            $destination_small = "/public/Photos";
            $destination_big = "/public/Photos/full";
            $destination_thumb = "/public/Photos/thumb";
            $GroupId = $_POST['GroupId'];
            $PhotoSize = $_FILES['upload']['size'];
            $filename = $_FILES['upload']['name'];
            if ($PhotoSize > 0 && $PhotoSize < 2097152) {
            // create a resized version of the image
                $imageresize = new Brigade_Util_ImageResize();
                $imageresize->upload($_FILES['upload']);
                if ($imageresize->uploaded) {
                    $Photo = new Brigade_Db_Table_Photo();
                    $there_is_album = $Photo->istherealbumcover($GroupId);
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
                        'ProjectId' => $GroupId,
                        'isAlbumCover' => $isalbumc,
                        'Description'=> $_POST['Comment']
                    ));
                    $pic = $Photos[0];
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
                        'GroupId' => $GroupId,
                        'ActivityType' => 'Uploads',
                        'CreatedBy' => $_SESSION['UserId'],
                        'ActivityDate' => date('Y-m-d H:i:s'),
                    ));
                    $this->view->uploadedimage = "/public/Photos/".strtolower($pieces[0]).".jpeg";
                    $this->view->link = '/'.$groupInfo['projectLink'].'/show-photos';
                }
            } else {
                $this->view->error = "Please select an image not greater than 2MB";
            }
        }
    }

    /**
     * Upload files action
     *
     */
    public function uploadAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $parameters = $this->_getAllParams();
        if (count($_FILES) > 0) {
            $fileDest = realpath(dirname(__FILE__) . '/../../../').'/public/Files';
            $file     = $_FILES['files'];

            // check if a file is greater than 2MB
            $exceededSize = false;
            for($i = 0; $i < count($_FILES['files']['name']); $i++) {
                if ($file['size'][$i] > 2097152) {
                    $exceededSize = true;
                    break;
                }
            }
            
            $emails = array();
            $target = null;
            if (!$exceededSize) {
                if (isset($_POST['ProjectId']) && $_POST['ProjectId'] != "-1" ) {
                   $ProjectId = $_POST['ProjectId'];
            	   $users = User::getUsersVolunteersForProject($ProjectId);
            	   foreach ($users as $user) {
            	       $emails[] = $user->email;
            	   }
            	   $target = Project::get($ProjectId);
            	   
            	} else if (isset($_POST['GroupId']) && $_POST['GroupId'] != "-1" ) {
                   $SiteId = $_POST['GroupId'];
                   $GroupMembers  = new Brigade_Db_Table_GroupMembers();
                   $users = $GroupMembers->getGroupMembers($SiteId);
            	   foreach ($users as $user) {
                       $emails[] = $user["Email"];
                   }
                   $target = Group::get($SiteId);
                } else {
                   $SiteId = $_POST['NetworkId'];
                   $GroupMembers  = new Brigade_Db_Table_GroupMembers();
                   $users = $GroupMembers->getOrganizationMembers($SiteId);
                   foreach ($users as $user) {
                       $emails[] = $user["Email"];
                   }
                   $target = Organization::get($SiteId);
                }

                $attachedFiles = array();
                for($i = 0; $i < count($_FILES['files']['name']); $i++) {

                    $caption = stripslashes($_POST['captions'][$i]);

                    $name = strtolower($_FILES['files']['name'][$i]);
                    $dot  = strrpos($name, '.');
                    $name = str_replace('.', '_', $name);
                    $name = str_replace(' ', '_', $name);
                    $name = str_replace('-', '_', $name);

                    $name[$dot]  = '.';
                    $fileData    = explode('.', $name);
                    $fileName    = "{$fileData[0]}";
                    $fileExt     = "{$fileData[1]}";

                    // check if already exists
                    $fileCounter = 0;
                    while (file_exists("$fileDest/$fileName.$fileExt")) {
                        $fileCounter++;
                        $fileName = "{$fileData[0]}_{$fileCounter}";
                    }

                    // save the file
                    $fileObj                   = new File();
                    if (isset($ProjectId)) {
                        $fileObj->projectId = $ProjectId;
                    } else {
                        $fileObj->groupId = $SiteId;
                    }
                    $fileObj->systemFileName   = "$fileName.$fileExt";
                    $fileObj->uploadedFileName = "$fileName.$fileExt";
                    $fileObj->caption          = $caption;
                    $fileObj->type             = $fileExt;
                    $fileObj->save();

                    $filePath = "$fileDest/$fileName.$fileExt";
                    move_uploaded_file(
                        $file['tmp_name'][$i],
                        $filePath    
                    );
                    
                    $attachedFiles[] = array('filePath'=>$filePath, 'fileName'=>$fileName.".".$fileExt);
                    
                    // log the site activity
                    $activity            = new Activity();
                    $activity->siteId    = (isset($SiteId)) ? $SiteId : $ProjectId ;
                    $activity->type      = 'File Added';
                    $activity->createdBy = $_SESSION['UserId'];
                    $activity->date      = date('Y-m-d H:i:s');
                    $activity->details   = $fileObj->id;
                    $activity->save();
                }

                if (!empty($attachedFiles) && !empty($emails)) {
                    Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$FILE_SHARED, array($emails, $attachedFiles, $target));
                }

                echo json_encode(array('ok' => true));
            } else {
                echo json_encode(array('err' => "Please upload a file not greater than 2MB."));
            }
        }
    }

    public function manageAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }

        $parameters     =  $this->_getAllParams();
        $Files          =  new Brigade_Db_Table_Files();
        $Media          =  new Brigade_Db_Table_Media();
        $Organizations  =  new Brigade_Db_Table_Organizations();

        if (isset($parameters['GroupId'])) {
            $group              =  Group::get($parameters['GroupId']);
            $organization       =  $group->organization;
            $this->view->level  =  "group";
            $this->view->group  =  $group;
            
            $Projects          =  new Brigade_Db_Table_Projects();
            $this->view->projects = $Projects->getProjects($parameters['GroupId']); 

            $this->view->files  = $Files->getSiteFiles($group->id);

            $this->view->urlName = $group->urlName;

            $this->view->render('group/header.phtml');
            $this->view->render('group/tabs.phtml');

            //breadcrumb
            $this->view->breadcrumb = $this->view->breadcrumbHelper($group);

        } else if(isset($parameters['NetworkId'])) {
            $organization              =  Organization::get($parameters['NetworkId']);
            $this->view->level         =  "organization";
            $this->view->organization  =  $organization;
            
            if ($organization->hasGroups) {
            	$Groups = new Brigade_Db_Table_Groups();
                $this->view->groups  = $Groups->listOrgGroups($organization->id);    
            } else {
                $Projects          =  new Brigade_Db_Table_Projects();
            	$this->view->projects  = $Projects->getProjectsByNetwork($organization->id);
            }

            $this->view->files  = $Files->getSiteFiles($organization->id);

            if ($organization->logoMediaId != '') {
                $this->view->media_image = $Media->getSiteMediaById($organization->logoMediaId);
            }
            
            $this->view->urlName = $organization->urlName;

            $this->view->render('nonprofit/header.phtml');
            $this->view->render('nonprofit/tabs.phtml');

	        //breadcrumb
	        $this->view->breadcrumb = $this->view->breadcrumbHelper($organization);

        } else if(isset($parameters['ProjectId'])) {
            $project              =  Project::get($parameters['ProjectId']);
            if ($project->organizationId) {
	            $organization       =  Organization::get($project->organizationId);
	            $group = null;
	            
	            if ($project->groupId) {
	                $group       =  Group::get($project->groupId);
	            }
            }

            $this->view->level  =  "project";
            $this->view->project  =  $project;

            $Projects          =  new Brigade_Db_Table_Projects();
            $this->view->projects = $Projects->getProjects($parameters['ProjectId']); 

            $this->view->files  = $Files->getProjectFiles($project->id);

            $this->view->urlName = $project->urlName;
            
            $this->view->render('group/header.phtml');
            $this->view->render('group/tabs.phtml');
            
            //breadcrumb
            $this->view->breadcrumb = $this->view->breadcrumbHelper($project);
        	
        }

        if (isset($group)) {
          if ($group->logoMediaId != '') {
            $this->view->media_image = $Media->getSiteMediaById($group->logoMediaId);
          }
        }

        $this->view->breadcrumb[] = 'Files';

        $this->view->Prev = '';
        if (isset($_REQUEST['Prev'])) {
            if ($_REQUEST['Prev'] == 'volunteers') {
                $this->view->Prev = "/volunteers";
            } else if ($_REQUEST['Prev'] == 'members') {
                $this->view->Prev = "/members";
            }
        }

        $this->view->render('nonprofit/breadcrumb.phtml');
        $this->view->render('nonprofit/footer.phtml');

        $this->_helper->layout->setLayout('newlayout');

    }

    public function deletefilesAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if ($_POST) {
            foreach($_POST['deleted_files'] as $FileId) {
                File::delete($FileId);
            }
            echo "Selected file(s) has been successfully deleted.";
        }
    }

    public function updatecaptionAction(){
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if ($_POST) {
            $Files = new Brigade_Db_Table_Files();
            $Files->updateFile($_POST['FileId'], array('Caption' => $_POST['Caption']));
        }
    }
}
