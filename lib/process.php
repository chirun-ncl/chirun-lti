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

	public static function fromSourceFile($db, $guid, $source_main){
		if(!file_exists(UPLOADDIR.'/'.$guid.'/'.$source_main))
			return false;
		$webbase = WEBCONTENTDIR;
		$logloc = PROCESSDIR.'/logs/'.$guid.'.log';
		$processuser = PROCESSUSER;
		$escaped_guid = escapeshellarg($guid);
		$escaped_source = escapeshellarg($source_main);
		$escaped_webbase = escapeshellarg($webbase);
		$escaped_logloc = escapeshellarg($logloc);
		$escaped_processdir = escapeshellarg(PROCESSDIR);
		$escaped_uploaddir = escapeshellarg(UPLOADDIR);
		$escaped_contentdir = escapeshellarg(CONTENTDIR);
		$escaped_docker_volume = escapeshellarg(isset($_ENV["DOCKER_PROCESSING_VOLUME"])?$_ENV['DOCKER_PROCESSING_VOLUME']:'');
		
		// Template variables for standalone mode
		$escaped_itemtype = escapeshellarg(isset($_POST['itemType'])?$_POST['itemType']:"standalone");
		$escaped_splitlevel = escapeshellarg(isset($_POST['docSplit'])?$_POST['docSplit']:"-2");
		$escaped_title = escapeshellarg((isset($_POST['itemTitle']) && !empty($_POST['itemTitle']))?$_POST['itemTitle']:("Document: ".$source_main));
		$escaped_sidebar = escapeshellarg(isset($_POST['sidebar'])?"True":"False");
		$escaped_buildpdf = escapeshellarg(isset($_POST['buildPDF'])?"True":"False");
		
		exec("cd {$escaped_processdir} && sudo -u {$processuser} PROCESSDIR={$escaped_processdir} CONTENTDIR={$escaped_contentdir} UPLOADDIR={$escaped_uploaddir} DOCKER_PROCESSING_VOLUME={$escaped_docker_volume} ./process.sh -g {$escaped_guid} -d {$escaped_source} -b {$escaped_webbase} -i {$escaped_itemtype} -l {$escaped_splitlevel} -t {$escaped_title} -s {$escaped_sidebar} -p {$escaped_buildpdf} > {$escaped_logloc} 2>&1 &");
		return Process::setUploadUser($db, $guid, $_SESSION['user_id']);
	}

}

?>
