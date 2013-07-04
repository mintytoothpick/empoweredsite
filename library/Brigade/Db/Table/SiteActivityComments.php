<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_SiteActivityComments extends Zend_Db_Table_Abstract {

    protected $_name = 'site_activity_comments';
    protected $_primary = 'ID';

    public function getSiteActivityComments($SiteActivityId) {
        return $this->fetchAll($this->select()
            ->from(array('c' => 'site_activity_comments'), array('c.*', 'u.UserId', 'u.FirstName', 'u.LastName'))
            ->joinInner(array('u' => 'users'), 'c.CommentedBy = u.UserId')
            ->where('SiteActivityId = ?', $SiteActivityId)
            ->order('CommentedOn')
            ->setIntegrityCheck(false))->toArray();
    }

    public function addSiteActivityComment($data) {
        return $this->insert($data);
    }

    public function DeleteSiteActivityComments($SiteActivityId) {
        $where = $this->getAdapter()->quoteInto('SiteActivityId = ?', $SiteActivityId);
        $this->delete($where);
    }
    
    
    /** Start Refactor SQL **/
    public function loadInfo($id) {
        return $this->fetchAll($this->select()
            ->from(array('c' => 'site_activity_comments'), array('c.*'))
            ->where('ID = ?', $id))->toArray();
    }

    public function getCommentsByActivity($id) {
        return $this->fetchAll($this->select()
            ->from(array('c' => 'site_activity_comments'), array('c.*'))
            ->where('SiteActivityId = ?', $id)
            ->order('CommentedOn'))->toArray();
    }
}