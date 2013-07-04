<?php
require_once 'Facebook/facebook.php';

class FBConnect {
    
    protected $_fbAppId = "179837585364534";                      //  eamonn local - matias.empowered.org:8888
    protected $_fbSecret = "d51bcdff4b3d9c43b473f3d698b35194";
    protected $_facebook;
    
    public function __construct() {
      
      if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
        $this->_fbAppId  = "179805142035878";                      // production 
        $this->_fbSecret = "f344ee840138793d6e8148f1b0374046";
      } else if($_SERVER['HTTP_HOST'] == 'dev.empowered.org') {
        $this->_fbAppId  = "132371653490037";                       // dev
        $this->_fbSecret = "63e9adb25978deead3729d2d016f4f6f";
      }

      $fbconnect = new Facebook(array(
        'appId'  => $this->_fbAppId,
        'secret' => $this->_fbSecret,
        'cookie' => true,
      ));

      $this->_facebook     = $fbconnect;
    }

    public function getSession() {
        return $this->_facebook->getSession();
    }

    public function getLoginUrl($params = array('scope' => '')) {
        return $this->_facebook->getLoginUrl($params);
    }

    public function getLogoutUrl() {
        return $this->_facebook->getLogoutUrl();
    }

    public function getUser() {
        return $this->_facebook->getUser();
    }

    public function api($api) {
        return $this->_facebook->api($api);
    }

}
?>
