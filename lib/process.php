<?php

class Process {
	public static function getUploadUser($db, $guid){
		$prefix = DB_TABLENAME_PREFIX;
		$sql = "SELECT username
				FROM {$prefix}uploaded_content
				WHERE (guid = :guid)
				LIMIT 1";
		$query = $db->prepare($sql);
		$query->bindValue('guid', $guid, PDO::PARAM_STR);
		$query->execute();
		$row = $query->fetch(PDO::FETCH_ASSOC);
		return $row['username'];
	}

	public static function setUploadUser($db, $guid, $username){
		$prefix = DB_TABLENAME_PREFIX;
		$sql = "INSERT INTO {$prefix}uploaded_content (guid, username)
				VALUES (:guid, :username)";
		$query = $db->prepare($sql);
		$query->bindValue('guid', $guid, PDO::PARAM_STR);
		$query->bindValue('username', $username, PDO::PARAM_STR);
		return $query->execute();
	}

	public static function fromSourceFile($db, $guid, $source_main, $template_name="standalone"){
		if(!file_exists(UPLOADDIR.'/'.$guid.'/'.$source_main))
			return false;
		$webbase = WEBCONTENTDIR;
		$logloc = PROCESSDIR.'/logs/'.$guid.'.log';
		$escaped_guid = escapeshellarg($guid);
		$escaped_source = escapeshellarg($source_main);
		$escaped_webbase = escapeshellarg($webbase);
		$escaped_logloc = escapeshellarg($logloc);
		$script_dir = PROCESSDIR;
		$script_owner = PROCESSUSER;
		$template = $template_name;
		exec("cd {$script_dir} && sudo -u {$script_owner} ./process.sh -g {$escaped_guid} -d {$escaped_source} -b {$escaped_webbase} -t {$template}  > {$escaped_logloc} 2>&1 &");
		return Process::setUploadUser($db, $guid, $_SESSION['user_id']);
	}
}

?>