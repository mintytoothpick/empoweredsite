<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_Blogs extends Zend_Db_Table_Abstract {

    protected $_name = 'blog';
    protected $_primary = 'BlogId';

    public function getSiteBlogs($SiteId, $Type = '') {
        $where = $Type == "" ? "bs.SiteId = '$SiteId'" : "bs.SiteId = '$SiteId' OR bs.SiteId IN (SELECT ProjectId FROM projects WHERE GroupId = '$SiteId')";
        $result = $this->fetchAll($this->select()
            ->from(array('b' => 'blog'), array('b.*', 'u.FirstName', 'u.LastName', 'u.UserId'))
            ->joinInner(array('bs' => 'blogsite'), 'b.BlogId=bs.BlogId')
            ->joinInner(array('u' => 'users'), 'b.CreatedBy = u.UserId')
            ->where($where)
            ->setIntegrityCheck(false));
        return !empty($result) ? $result->toArray() : null;
    }

    public function loadInfo($BlogId) {
        $row = $this->fetchRow($this->select()
		->from(array('b' => 'blog'), array('b.*', 'g.GroupId', 'g.URLName', 'b.Description as bDescription'))
		->joinInner(array('bs' => 'blogsite'), 'b.BlogId = bs.BlogId')
		->joinInner(array('g' => 'groups'), 'bs.SiteId = g.GroupId')
		->where('b.BlogId = ?', $BlogId)
		->setIntegrityCheck(false))->toArray();
	if(empty($row)) { 
	    $row = $this->fetchRow($this->select()
                ->from(array('b' => 'blog'), array('b.*', 'p.ProjectId', 'p.URLName', 'b.Description as bDescription'))
                ->joinInner(array('bs' => 'blogsite'), 'b.BlogId = bs.BlogId')
                ->joinInner(array('p' => 'projects'), 'bs.SiteId = p.ProjectId')
                ->where('b.BlogId = ?', $BlogId)
                ->setIntegrityCheck(false))->toArray();
	}
	return $row;
    }

    public function loadActivityInfo($BlogId) {
	return $this->fetchRow($this->select()
		->from(array('b' => 'blog'), array('b.*'))
		->where('b.BlogId = ?', $BlogId)
		->setIntegrityCheck(false))->toArray();
    }
    
    public function getLatestBlog(){
    	try{
    	    $select = $this->select()
    	        ->setIntegrityCheck(false)
    	        ->from(array('blog' => 'blog'),array('Title','Description','CreatedOn'))
    	        ->order('CreatedOn desc')
    	        ->limit(1)
    	        ;
    	    $row = $this->fetchRow($select);
    	    if (count($row)){
    		    return $row->toArray();
    	    }
    	    return null;
	    } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $zde) {
            throw $zde;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function AddBlog($data) {
        $data['BlogId'] = $this->createBlogId();
        $this->insert($data);
        return $data['BlogId'];
    }

    public function createBlogId() {
        $row = $this->fetchRow($this->select()->from("blog", array('UUID() as BlogId')));
        return strtoupper($row['BlogId']);
    }

    public function updateBlog($BlogId, $data) {
        $where = $this->getAdapter()->quoteInto('BlogId = ?', $BlogId);
        $this->update($data, $where);
    }

    public function deleteBlog($BlogId) {
        $where = $this->getAdapter()->quoteInto('BlogId = ?', $BlogId);
        $this->delete($where);
    }

    public function getUserBlogs($UserId) {
        $rows = $this->fetchAll($this->select()
            ->from(array('b' => 'blog'), array('b.*', 'u.FirstName', 'u.LastName', 'u.UserId'))
            ->joinInner(array('u' => 'users'), 'b.CreatedBy = u.UserId')
            ->where('b.CreatedBy = ?', $UserId)
	    ->order('b.CreatedOn DESC')
            ->setIntegrityCheck(false));
	return $rows;
    }

    /* this method is only used in getting the activity list for added blogs and store it in the site_activities table */
    public function storeBlogActivities() {
        $SiteActivities = new Brigade_Db_Table_SiteActivities();
        $rows = $this->fetchAll($this->select()
            ->from(array('b' => 'blog'), array('CreatedBy', 'CreatedOn', 'bs.SiteId', 'b.BlogId'))
            ->joinInner(array('bs' => 'blogsite'), 'b.BlogId=bs.BlogId')
            ->where("CreatedBy != '00000000-0000-0000-0000-000000000000' AND CreatedBy IS NOT NULL AND CreatedBy != ''")
	    ->where("bs.SiteId != '' AND bs.SiteId IS NOT NULL")
	    ->where("b.BlogId != '' AND b.BlogId IS NOT NULL")
	    ->where("CreatedOn != '' AND CreatedOn IS NOT NULL AND CreatedOn != '0000-00-00 00:00:00'")
            ->setIntegrityCheck(false));
        foreach ($rows as $row) {
            if (!empty($row['SiteId'])) {
                $SiteActivities->addSiteActivity(array(
                    'SiteId' => $row['SiteId'],
                    'ActivityType' => 'Blogs',
                    'CreatedBy' => $row['CreatedBy'],
                    'ActivityDate' => $row['CreatedOn'],
                    'Link' => '/blog/'.$row['BlogId'],
                    'Details' => $row['BlogId'],
                ));
            }
        }
    }
}
?>
