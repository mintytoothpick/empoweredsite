<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/Blogs.php';

class Brigade_Db_Table_BlogSites extends Zend_Db_Table_Abstract {

    protected $_name = 'blogsite';
    protected $_primary = 'BlogSiteId';

    public function AddSiteBlog($data) {
        $data['BlogSiteId'] = $this->createBlogSiteId();
        $this->insert($data);
        return $data['BlogSiteId'];
    }

    public function createBlogSiteId() {
        $row = $this->fetchRow($this->select()->from("blogsite", array('UUID() as BlogSiteId')));
        return strtoupper($row['BlogSiteId']);
    }

    public function deleteBlogSite($BlogId, $SiteId) {
        $where = $this->getAdapter()->quoteInto("BlogId = '$BlogId' AND SiteId = '$SiteId'");
        $this->delete($where);
    }

    public function DeleteSiteBlogs($SiteId) {
        $Blogs = new Brigade_Db_Table_Blogs();
        $site_blogs = $Blogs->getSiteBlogs($SiteId);
        foreach($site_blogs as $blog) {
            $this->deleteBlogSite($blog['BlogId'], $SiteId); // delete recorss from child table first
            $Blogs->deleteBlog($blog['BlogId']); // delete records from parent table
        }
    }
    
}
?>
