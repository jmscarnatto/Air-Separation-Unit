<?php
////////////////////////////////////////////////////////////////////////////////////////
// Class: DbConnector
// Purpose: Connect to a database, MySQL version
///////////////////////////////////////////////////////////////////////////////////////
require_once 'SystemComponent.php';
class DbConnector extends SystemComponent {
	var $theQuery;
	var $link;
	//*** Function: DbConnector, Purpose: Connect to the database ***
	function DbConnector(){
		// Load settings from parent class
		$settings = SystemComponent::getSettings();
		// Get the main settings from the array we just loaded
		$host = $settings['dbhost'];
		$db = $settings['dbname'];
		$user = $settings['dbuser'];
		$pass = $settings['dbpassword'];

		// Connect to the database
		$this->link = mysql_connect($host, $user, $pass);
		mysql_select_db($db);
		register_shutdown_function(array(&$this, 'close'));
	}
	//*** Function: query, Purpose: Execute a database query ***
	function query($query) {
		$this->theQuery = $query;
		mysql_query("SET NAMES 'latin1'", $this->link);
		return mysql_query($query, $this->link);

	}
	//*** Function: fetchArray, Purpose: Get array of query results ***
	function fetchArray($result) {
		return mysql_fetch_array($result);
	}
	//*** Function: fetchRow, Purpose: Get one row of query results ***
	function fetchRow($result) {
		return mysql_fetch_row($result);
	}
	//*** Function: close, Purpose: Close the connection ***
	function close() {
		mysql_close($this->link);
	}
}

?>
