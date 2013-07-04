<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Table/Exception.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Brigade/Db/Table/SiteActivities.php';

class Brigade_Db_Table_Photo extends Zend_Db_Table_Abstract {

    // table name
    protected $_name = 'photos';

    public function getAlbums($GroupId) {
        return $this->fetchAll($this->select()
            ->from(array('p'=>'photos'), array('p.*', 'pr.Name', 'pr.URLName as pURLName'))
            ->joinInner(array('pr'=>'projects'), 'p.ProjectId = pr.ProjectId')
            ->where('p.GroupId = ?', $GroupId)
            ->where('p.isAlbumCover = 1')
            ->setIntegrityCheck(false))->toArray();
    }

    public function loadInfo($PhotoId) {
        $row = $this->fetchRow($this->select()->where('PhotoId = ?', $PhotoId));
        if ($row) {
            return $row->toArray();
        } else {
            return false;
        }
    }

    public function getallPhotosbyPG($GroupId, $ProjectId) {
        return $this->fetchRow($this->select()
            ->from('photos', array('p.*','ph.GroupId','ph.ProjectId','COUNT( * ) AS total'))
            ->where('ph.GroupId = ?', $GroupId)
            ->where('ph.ProjectId = ?', $ProjectId)
            ->setIntegrityCheck(false))->toArray();
    }

    public function getProjectId($PhotoId) {
        $rows = $this->fetchAll($this->select()
            ->from(array('ph' => 'photos'), array('ph.ProjectId'))
            ->where('ph.PhotoId = ?', $PhotoId)
            ->setIntegrityCheck(false));
        return !empty($rows) ? $rows->toArray() : NULL;
    }

    public function getSitePhotoGallery($SiteId, $GroupId) {
        $select = $this->select()
            ->from(array('ph' => 'photos'), array('ph.*', 'p.ProjectId', 'p.Name AS AlbumName','u.UserId', 'u.FirstName', 'u.LastName'))
            ->joinInner(array('u' => 'users'), 'ph.CreatedBy=u.UserId')
            ->joinInner(array('p' => 'projects'), 'ph.ProjectId = p.ProjectId')
            ->order('p.Name');
        if ($SiteId != -1) {
            $select = $select->where('ph.ProjectId = ?', $SiteId);
        }
        $rows = $this->fetchAll($select->where('p.GroupId = ?', $GroupId)->setIntegrityCheck(false));
        return !empty($rows) ? $rows->toArray() : NULL;
    }

    public function getGroupAlbums($GroupId) {
        $rows = $this->fetchAll($this->select()
            ->from(array('p' => 'projects'), array('p.ProjectId', 'p.Name AS AlbumName', 'p.URLName as pURLName', 'p.StartDate'))
            ->where('p.GroupId = ?', $GroupId)
            ->order('p.StartDate DESC')
            ->setIntegrityCheck(false));
        return !empty($rows) ? $rows->toArray() : NULL;
    }

    public function getGroupPhotos($GroupId) {
        $rows = $this->fetchAll($this->select()
            ->from(array('ph' => 'photos'), array('ph.*', 'p.ProjectId', 'p.Name AS AlbumName', 'u.URLName as Uploader', 'u.FullName', 'p.URLName as pURLName', 'ph.CreatedOn as DateAdded'))
            ->joinLeft(array('p' => 'projects'), 'ph.ProjectId = p.ProjectId')
            ->joinInner(array('u' => 'users'), 'ph.CreatedBy=u.UserId')
            ->where('ph.GroupId = ?', $GroupId)
            ->setIntegrityCheck(false));
        return !empty($rows) ? $rows->toArray() : NULL;
    }

    public function getAlbumPhotos($ProjectId) {
        $rows = $this->fetchAll($this->select()
            ->from(array('ph' => 'photos'), array('ph.*', 'p.ProjectId', 'p.Name AS AlbumName', 'u.URLName as Uploader', 'u.FullName', 'p.URLName as pURLName', 'ph.CreatedOn as DateAdded'))
            ->joinInner(array('p' => 'projects'), 'ph.ProjectId = p.ProjectId')
            ->joinInner(array('u' => 'users'), 'ph.CreatedBy=u.UserId')
            ->where('ph.ProjectId = ?', $ProjectId)
            ->order("ph.PhotoId")
            ->setIntegrityCheck(false));
        return !empty($rows) ? $rows->toArray() : NULL;
    }

    public function getPhotoBySiteId($SiteId) {
        $rows = $this->fetchAll($this->select()
            ->setIntegrityCheck(false)
            ->from(array('P' => 'projects'), array('ph.*','P.ProjectId','P.Name AS Album Name'))
            ->join(array('ph'=>'photos'), 'P.ProjectId = ph.ProjectId')
            ->where('ph.ProjectId = ?', $SiteId)
            ->setIntegrityCheck(false));
        return !empty($rows) ? $rows->toArray() : NULL;
    }

    public function getSitePhotoById($PhotoId) {
        return $this->fetchRow($this->select()->where('PhotoId = ?', empty($PhotoId) ? "" : $PhotoId))->toArray();
    }

    public function getSitePhotoBySiteId($SiteId) {
        $row = $this->fetchRow($this->select()
            ->from(array('p' => 'photos'), array('p.*'))
            ->joinInner(array('ms' => 'media_site'), 'p.MediaId = ms.MediaId')
            ->where('ms.SiteId = ?', $SiteId)
            ->order('p.isPrimary DESC')
            ->setIntegrityCheck(false));
        return !empty($row) ? $row->toArray() : NULL;
    }

    public function getAlbumPhotoBySiteId($GroupId) {//poner el query requerido
        $rows = $this->fetchAll($this->select()
            ->setIntegrityCheck(false)
            ->from(array('p' => 'projects'), array('ProjectId','Name as AlbumName'))
            ->where('p.GroupId = ?', $GroupId)
            ->group('P.ProjectId')
            ->setIntegrityCheck(false));
        return !empty($rows) ? $rows->toArray() : NULL;
    }

    public function addPhoto($values) {
        return $this->insert($values);
    }

    public function getGroupId($ProjectId) {
        $row = $this->fetchRow($this->select()
            ->from('photos', array('GroupId'))
            ->where('ProjectId = ?', $ProjectId))->toArray();
        return $row['GroupId'];
    }

    public function setAlbumCover($PhotoId, $isAlbumCover) {
        $where = $this->getAdapter()->quoteInto('PhotoId = ?', $PhotoId);
        $this->update(array('isAlbumCover' => $isAlbumCover), $where);
    }

    public function returncurrent_ac($SiteId) {
        $rows = $this->fetchAll($this->select()
            ->from('photos', array('PhotoId'))
            ->where('ProjectId  = ?', $SiteId)
            ->where('isAlbumCover = 1'));
        return !empty($rows) ? $rows->toArray() : NULL;
    }

    public function current_ac($PhotoId) {
        $rows = $this->fetchAll($this->select()
            ->from('photos', array('ProjectId'))
            ->where('PhotoId  = ?', $PhotoId)
            ->where('isAlbumCover = 1'));
        return !empty($rows) ? $rows->toArray() : NULL;
    }

    public function getProjectPhotos($ProjectId) {
        $rows = $this->fetchAll($this->select()->where('ProjectId  = ?', $ProjectId));
        return !empty($rows) ? $rows->toArray() : NULL;
    }

    public function SetAsCero($PhotoId) {
        $current_Id = $this->current_ac($PhotoId);
        return $current_Id;
    }

    /*
     * This method is used to check if an album has a cover photo, if yes it returns the
     * current album cover photo otherwise it will return false
     */
    public function hasAlbumCover($ProjectId) {
        $rows = $this->fetchRow($this->select()
            ->from('photos', array('*'))
            ->where('ProjectId = ?', $ProjectId)
            ->where('isAlbumCover = 1'));
        return !empty($rows) ? $rows->toArray() : false;
    }

    public function addPhotoGallery($values) {
        //$values['SystemMediaName'] = strtolower($this->createSysMediaName()).".jpeg";
        $values['CreatedBy'] = $_SESSION['UserId'];
        $values['CreatedOn'] = date('Y-m-d H:i:s');
        $this->insert($values);

        //return $values['SystemMediaName'];
    }

    public function isPhotoNameExists($SystemMediaName) {
        $row = $this->fetchRow($this->select()->where("SystemMediaName = '$SystemMediaName'"));
        return !empty($row) ? true : false;
    }

    public function editPhoto($PhotoId, $values) {
        $mediaRowset = $this->find($PhotoId);
        $media = $mediaRowset->current();
        if (!$media) {
            throw new Zend_Db_Table_Exception('Photo with id '.$PhotoId.' is not present in the database');
        }
        $values['ModifiedOn'] = date('Y-m-d H:i:s');
        foreach ($values as $k => $v) {
            if (in_array($k, $this->_cols)) {
                if ($k == $this->_primary) {
                    throw new Zend_Db_Table_Exception('Id of media cannot be changed');
                }
                $media->{$k} = $v;
            }
        }
        $media->save();

        return $this;
    }

    public function createSysMediaName() {
        $row = $this->fetchRow($this->select()->from("photos", array('UUID() as SystemMediaName')));
        return $row['SystemMediaName'];
    }


    /**
     * Delete picture from DB.
     *
     * @param Integer $id Photo Id.
     */
    public function deletePhoto($PhotoId) {
        $where = $this->getAdapter()->quoteInto('PhotoId = ?', $PhotoId);
        $this->delete($where);
    }

    /* this method is only used in getting the activity list for photo/video uploads and store it in the site_activities table */
    public function storeUploadActivities() {
        $SiteActivities = new Brigade_Db_Table_SiteActivities();
        $rows = $this->fetchAll($this->select()
            ->from(array('p' => 'photos'), array('p.*'))
            ->joinInner(array('ms' => 'media_site'), 'p.MediaId = ms.MediaId')
            ->where("SiteId IS NOT NULL AND SiteId != ''")
            ->where("CreatedOn != '' AND CreatedOn IS NOT NULL AND CreatedOn != '0000-00-00 00:00:00'")
            ->where("CreatedBy IS NOT NULL AND CreatedBy != '' AND CreatedBy != '00000000-0000-0000-0000-000000000000'")
            ->setIntegrityCheck(false));
        foreach ($rows as $row) {
            if (!empty($row['SiteId'])) {
                $SiteActivities->addSiteActivity(array(
                    'SiteId' => $row['SiteId'],
                    'ActivityType' => 'Uploads',
                    'CreatedBy' => $row['CreatedBy'],
                    'ActivityDate' => $row['CreatedOn'],
                ));
            }
        }
    }

    public function updateDescription($photoId, $newDescription) {
        $where = $this->getAdapter()->quoteInto('PhotoId = ?', $photoId);
        $this->update(array('Description' => $newDescription), $where);
    }

    public function updatePhotoNames() {
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $rows = $this->fetchAll($this->select())->toArray();
        $destination_small = realpath(dirname(__FILE__) . '/../../../')."/public/Photos/";
        $destination_big = realpath(dirname(__FILE__) . '/../../../')."/public/Photos/full/";
        $destination_thumb = realpath(dirname(__FILE__) . '/../../../')."/public/Photos/thumb/";
        foreach($rows as $row) {
            if (file_exists($destination_small.$row['SystemMediaName']) && !empty($row['SystemMediaName']) && !empty($row['ProjectId'])) {
                $siteURL = $LookupTable->getURLbyId($row['ProjectId']);
                $ctr = 1;
                $PhotoName = "$siteURL-photo-$ctr.jpeg";
                while($this->isPhotoNameExists($PhotoName)) {
                    $ctr++;
                    $PhotoName = "$siteURL-photo-$ctr.jpeg";
                }
                $where = $this->getAdapter()->quoteInto("PhotoId = ?", $row['PhotoId']);
                $this->update(array('SystemMediaName' => $PhotoName), $where);

                rename($destination_small.$row['SystemMediaName'], $destination_small.$PhotoName);
                if (file_exists($destination_big.$row['SystemMediaName'])) {
                    rename($destination_big.$row['SystemMediaName'], $destination_big.$PhotoName);
                }
                if (file_exists($destination_thumb.$row['SystemMediaName'])) {
                    rename($destination_thumb.$row['SystemMediaName'], $destination_thumb.$PhotoName);
                }
            }
        }
    }

    /* Start Refactor */

    /**
     * Returns all photos for a selected initiative.
     *
     */
    public function getInitiativePhotos($projectId) {
        $rows = $this->fetchAll($this->select()->where('ProjectId  = ?', $projectId));
        return !empty($rows) ? $rows->toArray() : null;
    }

    /**
     * Returns all photos for a selected group.
     *
     */
    public function getNewGroupPhotos($GroupId) {
        $rows = $this->fetchAll($this->select()->where('GroupId = ?', $GroupId));
        return !empty($rows) ? $rows->toArray() : NULL;
    }
}
