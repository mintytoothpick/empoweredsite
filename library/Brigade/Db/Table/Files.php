<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_Files extends Zend_Db_Table_Abstract {

    protected $_name = 'files';
    protected $_primary = 'FileId';

    public function getSiteFiles($GroupId) {
        $result = $this->fetchAll($this->select()
            ->from(array('f' => 'files'), array('f.*', 'u.FirstName', 'u.LastName', 'u.UserId', 'f.CreatedOn as DateUploaded'))
            ->joinInner(array('u' => 'users'), 'f.CreatedBy = u.UserId')
            ->where('f.GroupId = ?', $GroupId)
            ->setIntegrityCheck(false));
        return !empty($result) ? $result->toArray() : null;
    }

    public function getProjectFiles($ProjectId) {
        $result = $this->fetchAll($this->select()
            ->from(array('f' => 'files'), array('f.*', 'u.FirstName', 'u.LastName', 'u.UserId', 'f.CreatedOn as DateUploaded'))
            ->joinInner(array('u' => 'users'), 'f.CreatedBy = u.UserId')
            ->where('f.ProjectId = ?', $ProjectId)
            ->setIntegrityCheck(false));
        return !empty($result) ? $result->toArray() : null;
    }
    
    public function loadInfo($FileId) {
        return $this->fetchRow($this->select()->where('FileId = ?', $FileId))->toArray();
    }

    public function loadFiles($public = 1) {
        try {
            $row = $this->fetchAll($this->select()
                ->from(array('Fi' => 'files'), array('Fi.*','u.FullName'))
                ->joinInner(array('u' => 'users'), 'u.UserId=Fi.CreatedBy')
                ->where('Fi.isPrivate = ?', $public)->setIntegrityCheck(false));
            return !empty($row) ? $row->toArray() : NULL;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function AddFile($data) {
        $data['FileId'] = $this->createFileId();
        $data['CreatedOn'] = date("Y-m-d H:i:s");
        $data['CreatedBy'] = $_SESSION['UserId'];
        $this->insert($data);
        
        return strtolower($data['FileId']);
    }

    public function createFileId() {
        $row = $this->fetchRow($this->select()->from("files", array('UUID() as FileId')));
        return strtoupper($row['FileId']);
    }

    public function updateFile($FileId, $data) {
        $where = $this->getAdapter()->quoteInto('FileId = ?', $FileId);
        $this->update($data, $where);
    }

    public function deleteFile($FileId) {
        $where = $this->getAdapter()->quoteInto('FileId = ?', $FileId);
        $this->delete($where);
    }

    public function getUserFiles($UserId) {
        $rows = $this->fetchAll($this->select()
            ->from(array('b' => 'files'), array('b.*', 'u.FirstName', 'u.LastName', 'u.UserId'))
            ->joinInner(array('u' => 'users'), 'b.CreatedBy = u.UserId')
            ->where('b.CreatedBy = ?', $UserId)
            ->setIntegrityCheck(false));
        return $rows;
    }

    public function isFileNameExists($FileName) {
        $row = $this->fetchRow($this->select()->where("SystemFileName = ?", $FileName));
        return count($row) ? true : false;
    }

}
?>
