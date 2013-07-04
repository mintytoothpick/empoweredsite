<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Table/Exception.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Brigade/Db/Table/SiteActivities.php';

class Brigade_Db_Table_Media extends Zend_Db_Table_Abstract {

    // table name
    protected $_name = 'media';

    public function getSiteMediaGallery($SiteId, $LogoMediaId) {
        try {
            return $this->fetchAll($this->select()
                ->from(array('m' => 'media'), array('m.*', 'u.UserId', 'u.FirstName', 'u.LastName'))
                ->joinInner(array('ms' => 'media_site'), 'm.MediaId = ms.MediaId')
                ->joinInner(array('u' => 'users'), 'm.CreatedBy=u.UserId')
                ->where('ms.SiteId = ?', $SiteId)
                ->where('m.MediaId != ?', empty($LogoMediaId) ? "" : $LogoMediaId)
                ->order('m.isPrimary DESC')
                ->setIntegrityCheck(false))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getSiteMediaById($MediaId) {
        try {
            $row = $this->fetchRow($this->select()->where('MediaId = ?', empty($MediaId) ? "" : $MediaId));
            if (count($row)){
                return $row->toArray();
            }else {
                return null;
            }
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getSiteMediaBySiteId($SiteId) {
        try {
            $row = $this->fetchRow($this->select()
                ->from(array('m' => 'media'), array('m.*'))
                ->joinInner(array('ms' => 'media_site'), 'm.MediaId = ms.MediaId')
                ->where('ms.SiteId = ?', $SiteId)
                ->order('m.isPrimary DESC')
                ->setIntegrityCheck(false));
            return !empty($row) ? $row->toArray() : null;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function addMedia($values) {
        $values['MediaId'] = $this->createMediaId();
        $values['CreatedBy'] = $_SESSION['UserId'];
        $values['CreatedOn'] = date('Y-m-d H:i:s');
        $this->insert($values);

        return $values['MediaId'];
    }

    public function addMediaGallery($values) {
        $values['MediaId'] = $this->createMediaId();
        $values['SystemMediaName'] = strtolower($values['MediaId']).".jpeg";
        $this->insert($values);

        return $values['MediaId'];
    }

    public function editMedia($MediaId, $values) {
        $mediaRowset = $this->find($MediaId);
        $media = $mediaRowset->current();
        $values['ModifiedBy'] = $_SESSION['UserId'];
        $values['ModifiedOn'] = date('Y-m-d H:i:s');
        if (!$media) {
            throw new Zend_Db_Table_Exception('Media with id '.$MediaId.' is not present in the database');
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
        var_dump($media->save());

        return $this;
    }

    public function createMediaId() {
        $row = $this->fetchRow($this->select()->from("media", array('UUID() as MediaId')));
        return strtoupper($row['MediaId']);
    }

    public function SetAsPrimary($MediaId, $data) {
        $where = $this->getAdapter()->quoteInto('MediaId = ?', $MediaId);
        $this->update($data, $where);
    }

    public function deleteMedia($MediaId) {
        // delete the images
        $thumb_image_path = realpath(dirname(__FILE__) . '/../../../../').'/public/Media/';
        $large_image_path = realpath(dirname(__FILE__) . '/../../../../').'/public/Media/full';
        $banner_path = realpath(dirname(__FILE__) . '/../../../../').'/public/Photos/banner';
        $image = $this->getSiteMediaById($MediaId);
        if (file_exists($thumb_image_path.$image['SystemMediaName'])) {
            unlink($thumb_image_path.$image['SystemMediaName']);
        }
        if (file_exists($large_image_path.$image['SystemMediaName'])) {
            unlink($large_image_path.$image['SystemMediaName']);
        }
        if (file_exists($banner_path.$image['SystemMediaName'])) {
            unlink($banner_path.$image['SystemMediaName']);
        }

        $where = $this->getAdapter()->quoteInto('MediaId = ?', $MediaId);
        $this->delete($where);
    }

    /* this method is only used in getting the activity list for photo/video uploads and store it in the site_activities table */
    public function storeUploadActivities() {
        $SiteActivities = new Brigade_Db_Table_SiteActivities();
        $rows = $this->fetchAll($this->select()
            ->from(array('m' => 'media'), array('m.*'))
            ->joinInner(array('ms' => 'media_site'), 'm.MediaId = ms.MediaId')
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

    public function updateLogoMediaNames() {
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $rows = $this->fetchAll($this->select()
            ->from(array('m' => 'media'), array('ms.SiteId', 'm.*'))
            ->joinInner(array('ms' => 'media_site'), 'm.MediaId=ms.MediaId')
            ->setIntegrityCheck(false))->toArray();
        $thumb_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/Media/";
        $bigger_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/Media/full/";
        foreach($rows as $row) {
            if (!empty($row['SiteId'])) {
                $siteURL = $LookupTable->getURLbyId($row['SiteId']);
                echo $row['SystemMediaName'].(file_exists($bigger_image_location.$row['SystemMediaName']) ? ": renamed to - ".$siteURL."-logo.jpg" : ": file not exists")."<br>";
                if (file_exists($bigger_image_location.$row['SystemMediaName']) && !empty($siteURL)) {
                    $where = $this->getAdapter()->quoteInto("MediaId = ?", $row['MediaId']);
                    $this->update(array('SystemMediaName' => $siteURL."-logo.jpg"), $where);

                    rename($bigger_image_location.$row['SystemMediaName'], $bigger_image_location.$siteURL."-logo.jpg");
                }
            }
        }
    }

}

?>
