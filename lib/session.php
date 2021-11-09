<?php

class Session {
	public static function setUserSession($session) {
		$_SESSION['user_id'] = $session['user_id'];
		$_SESSION['user_email'] = $session['user_email'];
		$_SESSION['user_fullname'] = $session['user_fullname'];
		$_SESSION['isStudent'] = $session['isStudent'];
		$_SESSION['isStaff'] = $session['isStaff'];
		$_SESSION['isAdmin'] = false;
	}

	public static function getUserSession($db, $user_id, $token) {
		$prefix = DB_TABLENAME_PREFIX;
		$sql = "SELECT *
				FROM {$prefix}user_session
				WHERE user_id = :user_id
				AND user_session_token = :token
				AND expiry > NOW()";
		$query = $db->prepare($sql);
		$query->bindValue('user_id', $user_id, PDO::PARAM_STR);
		$query->bindValue('token', $token, PDO::PARAM_STR);
		$query->execute();
		$row = $query->fetch(PDO::FETCH_ASSOC);
		if (isset($row['resource_link_pk'])){
			return $row;
		} else {
			return NULL;
		}
	}

	public static function addUserSession($db, $resource_pk, $token, $session) {
		$prefix = DB_TABLENAME_PREFIX;
		$sql = "INSERT INTO {$prefix}user_session (user_session_token, resource_link_pk, 
					user_id, user_email, user_fullname, isStudent, isStaff, timestamp, expiry)
				VALUES (:user_session_token, :resource_link_pk, :user_id, :user_email, 
					:user_fullname, :isStudent, :isStaff, NOW(), NOW() + INTERVAL 1 DAY)";
		$query = $db->prepare($sql);
		$query->bindValue('user_session_token', $token, PDO::PARAM_STR);
		$query->bindValue('resource_link_pk', $resource_pk, PDO::PARAM_INT);
		$query->bindValue('user_id', $session['user_id'], PDO::PARAM_STR);
		$query->bindValue('user_email', $session['user_email'], PDO::PARAM_STR);
		$query->bindValue('user_fullname', $session['user_fullname'], PDO::PARAM_STR);
		$query->bindValue('isStudent', $session['isStudent'], PDO::PARAM_INT);
		$query->bindValue('isStaff', $session['isStaff'], PDO::PARAM_INT);
		return $query->execute();
	}

	public static function addAnonymousUserSession($db, $resource_pk, $token){
		$prefix = DB_TABLENAME_PREFIX;
		$sql = "INSERT INTO {$prefix}user_session (user_session_token, resource_link_pk, 
					user_id, user_email, user_fullname, isStudent, isStaff, timestamp, expiry)
				VALUES (:user_session_token, :resource_link_pk, '', '', 'Anonymous User', 0, 0, NOW(), NULL)";
		$query = $db->prepare($sql);
		$query->bindValue('user_session_token', $token, PDO::PARAM_STR);
		$query->bindValue('resource_link_pk', $resource_pk, PDO::PARAM_INT);
		return $query->execute();
	}
}

?>