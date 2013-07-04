<?php

require_once 'Zend/Session/SaveHandler/Interface.php';
require_once 'Brigade/Lib/Session/Data.php';

class Brigade_Lib_Session_Manager implements Zend_Session_SaveHandler_Interface
{
    private static $sessionData;
    
    private static $thisIsOldSession = false;
     
    public function open($save_path, $name)
    {
        self::$sessionData = new Brigade_Lib_Session_Data();
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        $rows = self::$sessionData->find($id);
        $row = $rows->current();
        if ($row)
        {
            self::$thisIsOldSession = true;
            return $row->session_data;
        }
        else
        {
            return '';
        }
    }

    public function write($id, $sessionData)
    {
        $profiler = self::$sessionData->getAdapter()->getProfiler();
        $profiler->setEnabled(true);

        $data = array
        (
            'session_data' => $sessionData,
            't_updated' => new Zend_Db_Expr('NOW()'),
        );


        if (self::$thisIsOldSession)
        {
            self::$sessionData->update
            (
            $data,
            self::$sessionData->getAdapter()->quoteInto('session_id = ?', $id)
            );
        }
        else
        {
            //no such session, create new one
            $data['session_id'] = $id;
            $data['t_created'] = new Zend_Db_Expr('NOW()');
            self::$sessionData->insert($data);
        }

        return true;
    }

    public function destroy($id)
    {
        self::$sessionData->delete(self::$sessionData->getAdapter()->quoteInto('session_id = ?', $id));
        return true;
    }

    public function gc($maxLifetime)
    {
        $maxLifetime = intval($maxLifetime);
        self::$sessionData->delete("DATE_ADD(t_updated, INTERVAL $maxLifetime SECOND) < NOW()");
        return true;
    }
}