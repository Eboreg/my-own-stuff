<?php
require_once(__DIR__.'/chromephp/ChromePhp.php');

class DBSession {
    private $dbHost;
    private $dbUser;
    private $dbPass;
    private $dbDB;
    private $dbe;
    private $dbc;
	private $dbInsertId;
    private $dbWarningsC;
    private $dbWarnings;
    private $num_rows;
    private $isLogin;
    private $isAdmin;
    private $user;
    private $old_errset;
    private $old_errdisp;

    /**
     * @usedby DBSession::sessionStart()
     * @usedby DBSession::resetSession()
     */
    function DBSession() {
	    //Change to suit application
	    $this->dbHost = MYSQL_SERVER;
	    $this->dbUser = MYSQL_USERNAME;
	    $this->dbPass = MYSQL_PASSWORD;
	    $this->dbDB = MYSQL_SCHEMA;
	    
	    //Do not change
	    $this->dbe = '';
	    $this->dbc = 0;
	    //$this->dbWarnings = array();
	    $this->user = '';
	    ini_set("mysql.trace_mode", "0");
    }

    /**
     * Startar en php-session: se http://se2.php.net/manual/en/intro.session.php
     * Om ingen DB-session är igång för aktuell klient, starta ny
     * @uses DBSession::DBSession()
     */
    static function sessionStart() {
	    @session_start();
	    if (!isset($_SESSION['dbSession']) || empty($_SESSION['dbSession']))
		    $_SESSION['dbSession'] = new DBSession();
    }
    
    /**
     * @uses DBSession::DBSession()
     */
    static function resetSession() {
	    $_SESSION['dbSession'] = new DBSession();
    }
    
    /**
     * Anropar MySQL-proceduren login() via DBSession::execute()
     * @uses DBSession::execute()
     * @uses DBSession::dbErrorCode()
     */
    function login($user, $pass) {
	    $result = $this->execute('CALL login(\''.$user.'\', \''.$pass.'\');');
	    if ($result === false || empty($result) || $this->dbErrorCode()) {
		    $this->isLogin = false;
		    $this->user = '';
		    return false;
	    }
	    $this->user = $user;
	    $this->isLogin = true;
	    $this->isAdmin = ($result[0]['admin'] > 0 ? true : false);
	    return true;
    }

    function user() {
	    return $this->user;
    }
    
    function isLogin() {
	    return $this->isLogin;
    }

    function isAdmin() {
	    return $this->isAdmin;
    }

    /**
     * @usedby DBSession::login()
     */
    function dbErrorCode() {
	    return $this->dbc;
    }
    
    function dbErrorString() {
	    return $this->dbe;
    }

    function dbNumRows() {
	    return $this->num_rows;
    }

	function dbInsertId() {
		return $this->dbInsertId;
	}
    
    function safe($str) {
	    $mysqli = @new mysqli($this->dbHost, $this->dbUser, $this->dbPass, $this->dbDB);
	    $this->dbe = $mysqli->connect_error;
	    $this->dbc = $mysqli->connect_errno;
	    if ($this->dbc) {
		    error_reporting($old_errset);
		    ini_set('display_errors', $old_errdisp);
		    return false;
	    }
	    $str = $mysqli->real_escape_string($str);
	    $mysqli->close();
	    return $str;
    }
	
    /**
     * Kör SQL-fråga
     * @usedby DBSession::login()
     */
    function execute($sql) {
	    ChromePhp::log($sql);
	    $this->dbWarnings = array();
	    $this->dbWarningsC = 0;
	    $this->num_rows = 0;
	    $result = array();
	    set_time_limit(500);
	    $old_errset = error_reporting(0);
	    $old_errdisp = ini_get('display_errors');
	    ini_set('display_errors', 0);
	    $mysqli = @new mysqli($this->dbHost, $this->dbUser, $this->dbPass, $this->dbDB);
	    $this->dbe = $mysqli->connect_error;
	    $this->dbc = $mysqli->connect_errno;
	    if ($this->dbc) {
		    error_reporting($old_errset);
		    ini_set('display_errors', $old_errdisp);
		    return false;
	    }
	    //if (!$mysqli->query("SET NAMES 'utf8' COLLATE 'utf8_swedish_ci';")) {
	    if (!$mysqli->set_charset('utf8')) {
		    $this->dbe = $mysqli->error;
		    $this->dbc = $mysqli->errno;
		    $mysqli->close();
		    error_reporting($old_errset);
		    ini_set('display_errors', $old_errdisp);
		    return false;
	    }
	    //$qResult = $mysqli->query($sql);
	    $mysqli->multi_query($sql);
	    $this->dbe = $mysqli->error;
	    $this->dbc = $mysqli->errno;
	    if ($this->dbc) {
		    $mysqli->close();
		    error_reporting($old_errset);
		    ini_set('display_errors', $old_errdisp);
		    return false;
	    }
	    do {
		    if ($qResult = $mysqli->store_result()) {
			    //rprint($qResult);
			    //$this->num_rows += $qResult->num_rows;
			    if ($qResult === true) {
				    $mysqli->close();
				    error_reporting($old_errset);
				    ini_set('display_errors', $old_errdisp);
				    return true;
			    }
			    while ($row = $qResult->fetch_assoc()) {
				    $result[] = $row;
			    }
			    $qResult->free();
		    }
	    } while ($mysqli->more_results() && $mysqli->next_result());
		$this->dbInsertId = $mysqli->insert_id;
	    if ($fResult = $mysqli->query("SELECT FOUND_ROWS() AS 'found'")) {
		    $row = $fResult->fetch_assoc();
		    $this->num_rows += $row['found'];
		    $fResult->free();
	    }
	    else {
		    $this->dbc = $mysqli->errno;
		    $this->dbe = $mysqli->error;
	    }
	    //$qResult->close();
	    if ($wResult = $mysqli->query("CALL getWarnings()")) {
		    $this->dbWarningsC += $wResult->num_rows;
		    while ($row = $wResult->fetch_assoc())
			    $this->dbWarnings[] = $row['formatted_text'];
		    $wResult->free();
	    }
	    /*
	    $mysqli->multi_query("CALL getWarnings()");
	    do {
		    if ($qResult = $mysqli->store_result()) {
			    $this->dbWarningsC += $qResult->num_rows;
			    while ($row = $qResult->fetch_assoc())
				    $this->dbWarnings[] = $row['formatted_text'];
			    $qResult->free();
		    }
	    } while ($mysqli->more_results() && $mysqli->next_result());
	    */
	    $mysqli->close();
	    error_reporting($old_errset);
	    ini_set('display_errors', $old_errdisp);
	    return $result;
    }

    function call() {
	    $args = func_get_args();
	    $proc = $args[0];
	    $sqlargs = array();
	    $mysqli = @new mysqli($this->dbHost, $this->dbUser, $this->dbPass, $this->dbDB);
	    if (!isset($proc))
		    return null;
	    for ($i = 1; $i < count($args); $i++) {
		    if (is_array($args[$i]))
			    $sqlargs[] = "'".$mysqli->real_escape_string(implode(DELIMITER, $args[$i]))."'";
		    else
			    $sqlargs[] = "'".$mysqli->real_escape_string($args[$i])."'";
	    }
	    $mysqli->close();
	    $query = "CALL $proc(".implode(', ', $sqlargs).');';
	    //rprint($query);
	    $ret = $this->execute($query);
	    //rprint($ret);
	    if ($this->dbc)
		    trigger_error($this->dbe.' ['.$this->dbc.']', E_USER_WARNING);
	    /*
	    $warnings = $this->execute('CALL getWarnings()');
	    foreach ($warnings as $warning)
		    trigger_error($warning['formatted_text'], E_USER_NOTICE);
	    */
	    foreach ($this->dbWarnings as $warning)
		    trigger_error($warning, E_USER_NOTICE);
	    return $ret;
    }
}
?>
