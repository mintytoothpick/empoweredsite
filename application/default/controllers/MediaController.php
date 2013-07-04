<?php

/**
 * MediaController - The "media" controller class
 *
 * @author
 * @version
 */

require_once 'Brigade/Util/ImageResize.php';
require_once 'Brigade/Db/Table/Media.php';
require_once 'Brigade/Db/Table/MediaSite.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/Programs.php';
require_once 'Brigade/Db/Table/Organizations.php';
require_once 'Brigade/Util/Auth.php';
require_once 'BaseController.php';

class MediaController extends BaseController {
    public function init() {
        parent::init();
    }

    public function indexAction() {

    }

    public function manageAction() {
        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->_helper->redirector('login', 'profile');
        }
        $parameters = $this->_getAllParams();
        $Media = new Brigade_Db_Table_Media();
        $SiteId = $parameters['SiteId'];
        $Type = $parameters['Type'];
        $this->view->SiteId = $parameters['SiteId'];
        $this->view->UserId = $_SESSION['UserId'];
        $this->view->Type = $parameters['Type'];
        $this->view->media = $Media->getSiteMediaGallery($SiteId, '');
        if (strtolower($Type) == 'project') {
            $Brigades = new Brigade_Db_Table_Brigades();
            $brigadeInfo = $Brigades->loadInfo1($SiteId);
            $this->view->URLName = $brigadeInfo['projectLink'];
        } else if (strtolower($Type) == 'group') {
            $Groups = new Brigade_Db_Table_Groups();
            $groupInfo = $Groups->loadInfo1($SiteId);
            if (count($groupInfo)) {
                $this->view->URLName = $groupInfo['URLName'];
            } else {
                $Brigades = new Brigade_Db_Table_Brigades();
                $brigadeInfo = $Brigades->loadInfo1($SiteId);
                $this->view->URLName = $brigadeInfo['groupLink'];
            }
        } else if (strtolower($Type) == 'program') {
            $Programs = new Brigade_Db_Table_Programs();
            $programInfo = $Programs->loadInfo1($SiteId);
            $this->view->URLName = $programInfo['URLName'];
        } else if (strtolower($Type) == 'nonprofit') {
            $Organizations = new Brigade_Db_Table_Organizations();
            $nonprofitInfo = $Organizations->$Organizations($SiteId);
            $this->view->URLName = $nonprofitInfo['URLName'];
        }
    }

    public function setprimaryAction() {
        if ($_POST) {
            $MediaId = $_POST['MediaId'];
            $isPrimary = $_POST['isPrimary'];
            $Media = new Brigade_Db_Table_Media();
            $Media->SetAsPrimary($MediaId, array('isPrimary' => $isPrimary));
        }
    }

    public function deletemediaAction() {
        if ($_POST) {
            $MediaId = $_POST['MediaId'];
            $SystemMediaName = $_POST['SystemMediaName'];
            // delete data from db - start from the child table
            $MediaSite = new Brigade_Db_Table_MediaSite();
            $MediaSite->deleteSiteMedia($MediaId);
            // delete data from parent table
            $Media = new Brigade_Db_Table_Media();
            $Media->deleteMedia($MediaId);
            // delete image copy from /public/Media and /public/Media/full
            if (file_exists("/public/Media/$SystemMediaName")) {
                unlink("/public/Media/$SystemMediaName");
            }
            if (file_exists("/public/Media/full/$SystemMediaName")) {
                unlink("/public/Media/full/$SystemMediaName");
            }
        }
    }

    public function addAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if ($_POST['form'] == 'add') {
            echo '
                <h2>Upload Photo</h2>
                <form id="uploadphoto" method="post" action="/media/add" enctype="multipart/form-data" onsubmit="return validate()">
                    <input type="hidden" id="SiteId" name="SiteId" value="'.$_POST['SiteId'].'" />
                    <input type="hidden" id="Type" name="Type" value="'.$_POST['Type'].'" />
                    <input type="file" name="upload" id="upload" class="textfield" />
                    <br>
                    <input type="submit" name="submit" value="Upload" class="button" />
                </form>
            ';
        } else {
            // save site media/image
            $destination_big = "/public/Media/full";
            $destination_thumb = "/public/Media";
            $SiteId = $_POST['SiteId'];
            $Type = $_POST['Type'];
            $MediaSize = $_FILES['upload']['size'];
            $filename = $_FILES['upload']['name'];
            if ($MediaSize > 0) {
                // create a resized version of the image
                $imageresize = new Brigade_Util_ImageResize();
                $imageresize->upload($_FILES['upload']);
                if ($imageresize->uploaded) {
                    // save media
                    $Media = new Brigade_Db_Table_Media();
                    $MediaId = $Media->addMediaGallery(array(
                        'MediaSize' => $MediaSize,
                        'UploadedMediaName' => $filename,
                        'CreatedBy' => $_SESSION['UserId'],
                        'CreatedOn' => date('Y-m-d H:i:s'),
                        'ModifiedBy' => $_SESSION['UserId'],
                        'ModifiedOn' => date('Y-m-d H:i:s'),
                    ));

                    // save site media
                    $SiteMedia = new Brigade_Db_Table_MediaSite();
                    $SiteMedia->addMediaSite(array(
                        'MediaId' => $MediaId,
                        'SiteID' => $SiteId
                    ));

                    // create thumbnail and bigger image version of the uploaded photo
                    $imageresize->file_new_name_body = strtolower($MediaId);
                    $imageresize->file_safe_name = false;
                    $imageresize->image_resize = true;
                    $imageresize->image_convert = 'jpeg';
                    $imageresize->image_x = 326;
                    $imageresize->image_y = 225;
                    $imageresize->Process("$destination_big/");
                    // create the thumbnail
                    $imageresize->file_new_name_body = strtolower($MediaId);
                    $imageresize->file_safe_name = false;
                    $imageresize->image_resize = true;
                    $imageresize->image_convert = 'jpeg';
                    $imageresize->image_x = 70;
                    $imageresize->image_y = 70;
                    $imageresize->Process("$destination_thumb/");
                    if ($imageresize->processed) {
                        $imageresize->Clean();
                        if (file_exists("$destination_thumb/$filename")) {
                            unlink("$destination_thumb/$filename");
                        }
                        if (file_exists("$destination_big/$filename")) {
                            unlink("$destination_big/$filename");
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
                }
                header("location: /media/manage/$SiteId/$Type");
            }
        }
    }

}
?>
