<?php

require_once 'Brigade/Db/Table/Users.php';

class Brigade_Util_LoadImage {

	public static function getImage($userID) {
		$Users = new Brigade_Db_Table_Users();
		$row = $Users->findBy($userID);
		header("Content-type: image/jpeg");
		echo $row['profileimage'];
	}
	
}
