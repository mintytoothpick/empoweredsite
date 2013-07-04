<?php
$this->headTitle(stripslashes($this->header_title)." on Empowered.org");

require_once 'Zend/Controller/Front.php';
$front = Zend_Controller_Front::getInstance();
$actionName = $front->getRequest()->getActionName();
$actions = array('donors', 'donations', 'volunteers', 'donordonations', 'fundraisers');
?>
<script type="text/javascript" src="http://s7.addthis.com/js/250/addthis_widget.js#pubid=ra-4e319b9362855da9"></script>
<div style="width:100%; <?php echo !in_array($actionName, $actions) ? 'float:left;' : '' ?>">
        <h1 style="font-size:27px; line-height:27px; float:left; padding:0; margin:8px 0;"><?php echo stripslashes($this->header_title) ?></h1>
        <div class="clear"></div>
        <div id="TabbedPanels1" class="TabbedPanels">