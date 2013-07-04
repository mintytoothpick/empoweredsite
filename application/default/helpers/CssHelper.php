<?php  
/**
 * Helper for Styles includes using zend config.
 *
 * @author Matias Gonzalez
 */
class Layout_Helper_CssHelper extends Zend_View_Helper_Abstract 
{
    /**
     * Include Css calls usign site configuration xml for helpers.
     *
     * @param Boolean $useGLobals Use or ignore global includes.
     *
     * @public
     * @return String Css calls
     */
    function cssHelper($useGlobals = false) { 
        // config object
        $path   = Zend_Registry::get('confHelperPath');
        $config = new Zend_Config_Xml($path . 'headLinks.xml', 'css');
        
        //global config
        if ($useGlobals) {
            if ($useGlobals === true) $useGlobals = 'global';
            $this->_loadGlobals($config, $useGlobals);
        }

        // get config for speciffic controller and action method
        $this->_loadControllerAction($config);

        return $this->view->headLink(); 
    }

    private function _loadGlobals($config, $name) {
        $filesGlobal = $config->get($name)->toArray();
        if (is_array($filesGlobal) && is_array($filesGlobal['file'])) {
            foreach ($filesGlobal['file'] as $file) {
                $this->view->headLink()->appendStylesheet('/public/css/' . $file);
            }
        } else if (isset($filesGlobal['file'])) {
            $this->view->headLink()->appendStylesheet('/public/css/' . $filesGlobal['file']);
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
                        $this->view->headLink()->appendStylesheet('/public/css/' . $file);
                    }
                } else if (isset($actionConfig['file'])) {
                    $this->view->headLink()->appendStylesheet('/public/css/' . $actionConfig['file']);
                }
            }
        }
    }
}
