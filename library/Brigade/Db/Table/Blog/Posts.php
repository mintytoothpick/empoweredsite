<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/SetDbTableAdapter.php';

class Brigade_Db_Table_Blog_Posts extends Brigade_Db_Table_SetDbTableAdapter {
    protected $_name = 'wp_posts';
    protected $_primary = 'ID';
    protected $_use_adapter = 'blog';
    
    public function getLatestPost($limit = 1){
    	try{
    	    $select = $this->select()
    	        ->setIntegrityCheck(false)
    	        ->from(array('p' => 'wp_posts'),array('p.post_title','p.post_content','p.post_date','u.display_name', 'p.ID as BlogId'))
    	        ->joinInner(array('u' => 'wp_users'), 'p.post_author = u.ID')
    	        ->where("post_status = 'publish'")
    	        ->where("post_type = 'post'")
    	        ->order('p.post_date desc')
    	        ->limit($limit)
    	        ;
    	    $row = $this->fetchAll($select);
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
}
?>