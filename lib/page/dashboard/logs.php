<?php
class DashboardLogsPage {
	public $title = "Logs";
	protected $resource_pk = NULL;
	protected $db = NULL;
	protected $module = NULL;
	public function setup($module, $db, $res){
		$this->module = $module;
		$this->resource_pk = $res;
		$this->db = $db;
	}
	public function main(){
		$userSessions = getAllUserSessions($this->db, $this->resource_pk);
		$main = <<< EOD
			<div class="row">
				<div class="col">
					<h2>Access Logs</h2>
					<p>Student access to the loaded module is logged and shown in the table below.</p>
					<table id="access-log-table" class="table table-striped">
                                          <thead>
                                            <tr>
                                              <th scope="col">Date/Time</th>
                                              <th scope="col">Username</th>
                                              <th scope="col">Name</th>
                                              <th scope="col">Role</th>
                                            </tr>
                                          </thead>
                                          <tbody>
EOD;
		foreach($userSessions as $userSession){
			$role = $userSession['isStaff']>0?'Staff':'Student';
			if (empty($userSession['timestamp'])){
				$timestamp = strtotime($userSession['expiry'] . ' -1 day');
			} else {
				$timestamp = strtotime($userSession['timestamp']);
			}
			$timestamp_string = date( 'Y-m-d H:i:s', $timestamp );
			$main .= <<< EOD
                                            <tr>
                                              <th scope="row">{$timestamp_string}</th>
                                              <td>{$userSession['user_id']}</td>
                                              <td>{$userSession['user_fullname']}</td>
                                              <td>{$role}</td>
                                            </tr>
EOD;
		}
		$main .= <<< EOD
                                          </tbody>
                                        </table>
				</div>
			</div>
			<script>
				$(document).ready( function () {
					$('#access-log-table').DataTable();
				} );
			</script>

EOD;
		return $main;
	}
}
?>
