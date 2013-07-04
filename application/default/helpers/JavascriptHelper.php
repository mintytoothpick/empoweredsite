<?php 
/**
 * Helper for Javascripts includes using zend config.
 *
 * @author Matias Gonzalez
 */
class Layout_Helper_JavascriptHelper extends Zend_View_Helper_Abstract
{
    /**
     * Include Javascript's calls usign site configuration xml for helpers.
     *
     * @param Boolean $useGLobals Use or ignore global includes.
     *
     * @public
     * @return String Javascripts calls
     */
    function javascriptHelper($useGlobals = false) {
        // config object
        $path   = Zend_Registry::get('confHelperPath');
        $config = new Zend_Config_Xml($path . 'headLinks.xml', 'javascript');

        //global config
        if ($useGlobals) {
            if ($useGlobals === true) $useGlobals = 'global';
            $this->_loadGlobals($config, $useGlobals);
        }

        // get config for speciffic controller and action method
        $this->_loadControllerAction($config);
        
        return $this->view->headScript();
    }

    private function _loadGlobals($config, $name) {
        $filesGlobal = $config->get($name)->toArray();
        if (is_array($filesGlobal) && is_array($filesGlobal['file'])) {
            foreach ($filesGlobal['file'] as $file) {
                $this->view->headScript()->appendFile('/public/js/' . $file);
            }
        } else if (isset($filesGlobal['file'])) {
            $this->view->headScript()->appendFile('/public/js/' . $filesGlobal['file']);
        }        
    }

    private function _loadControllerAction($config) {
        $request = Zend_Controller_Front::getInstance()->getRequest();

        $controllerConfig = $config->get($request->getControllerName());
        if ($controllerConfig) {
            $controllerConfig = $controllerConfig->toArray();
            if (isset($controllerConfig[$request->getActionName()])) {
                $actionConfig = $controllerConfig[$request->getActionName()];
                if (is_array($actionConfig) && is_array($actionConfig['file'])) {
                    foreach ($actionConfig['file'] as $file) {
                        $this->view->headScript()->appendFile('/public/js/' . $file);
                    }
                } else if (isset($actionConfig['file'])) {
                    $this->view->headScript()->appendFile('/public/js/' . $actionConfig['file']);
                }
            }
        }
    }
}