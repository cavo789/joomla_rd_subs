<?php

/**
 * Author : AVONTURE Christophe -
 * https://www.aesecure.com
 * Website Firewall Application and unhacking services
 *
 * Small script for helping the migration from CB Subs
 * (https://www.joomlapolis.com/) to RD-Subs (https://rd-media.org/)
 *
 * Please be sure to have a backup of your database before using this
 * script.
 *
 * The latest version of the script can be freely retrieved on
 * https://github.com/cavo789/rd-subs
 *
 * No support is provided by the developer, use this script under
 * your own responsabilities
 *
 * License : MIT
 */

if (!defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR);
}

class aeSecureFct
{

	/**
	* Safely read posted variables
	*
	* @param type $name		  f.i. "password"
	* @param type $type		  f.i. "string"
	* @param type $default		f.i. "default"
	* @return type
	*/
	public static function getParam($name, $type = 'string', $default = '', $base64 = false)
	{
		$tmp = '';
		$return = $default;

		if (isset($_POST[$name])) {
			if (in_array($type, array('int','integer'))) {
				$return = filter_input(INPUT_POST, $name, FILTER_SANITIZE_NUMBER_INT);
			} elseif ($type == 'boolean') {
				// false = 5 characters
				$tmp = substr(filter_input(INPUT_POST, $name, FILTER_SANITIZE_STRING), 0, 5);
				$return = (in_array(strtolower($tmp), array('1','on','true')))?true:false;
			} elseif ($type == 'string') {
				$return = filter_input(INPUT_POST, $name, FILTER_SANITIZE_STRING);
				if ($base64 === true) {
					$return = base64_decode($return);
				}
			} elseif ($type == 'unsafe') {
				$return = $_POST[$name];
			}
		} else { // if (isset($_POST[$name]))
			if (isset($_GET[$name])) {
				if (in_array($type, array('int','integer'))) {
					$return = filter_input(INPUT_GET, $name, FILTER_SANITIZE_NUMBER_INT);
				} elseif ($type == 'boolean') {
					// false = 5 characters
					$tmp = substr(filter_input(INPUT_GET, $name, FILTER_SANITIZE_STRING), 0, 5);
					$return = (in_array(strtolower($tmp), array('1','on','true')))?true:false;
				} elseif ($type == 'string') {
					$return = filter_input(INPUT_GET, $name, FILTER_SANITIZE_STRING);
					if ($base64 === true) {
						$return = base64_decode($return);
					}
				} elseif ($type == 'unsafe') {
					$return = $_GET[$name];
				}
			} // if (isset($_GET[$name]))
		} // if (isset($_POST[$name]))

		return $return;
	}

	// @link : http://ca2.php.net/manual/fr/function.mysql-fetch-assoc.php#74048
	public static function array2table($arr, $width)
	{
		$sReturn = '';
		$count = count($arr);

		if ($count > 0) {
			reset($arr);

			$num = count(current($arr));

			$sReturn .= "<table id=\"tbl\" ".
				"class=\"table tablesorter table-hover ".
				"table-bordered table-striped\" width=\"$width\">\n";

			// Add an ID column ... only if the recordset
			// don't have one.
			$sReturn .= "<thead>".
				(!isset($arr[0]['id'])?"<td>ID</td>":"")."\n";

			foreach (current($arr) as $key => $value) {
				$sReturn .= "<td>".$key."&nbsp;</td>\n";
			}

			$sReturn .= "</thead><tfoot></tfoot><tbody>\n";

			$i = 0;

			while ($curr_row = current($arr)) {
				$i += 1;

				$sReturn .= "<tr>\n".
					(!isset($arr[0]['id'])?"<td>$i</td>":"");

				$col = 1;

				while (false !== ($curr_field = current($curr_row))) {
					$sReturn .= "<td>".
						utf8_encode($curr_field)."&nbsp;</td>\n";

					next($curr_row);

					$col++;
				}

				while ($col <= $num) {
					$sReturn .= "<td>&nbsp;</td>\n";
					$col++;
				}

				$sReturn .= "</tr>\n";

				next($arr);
			}

			$sReturn .= "</tbody></table>\n";
		}

		return $sReturn;
	}
}

class aeSecureMigrate
{
	private $mysqli = null;
	private $JConfig = null;
	private $sFolder = '';

	private $scriptName = '';
	private $sJSONFile = '';
	private $json = array();

	private $CBID = 0;
	private $RDID = 0;
	private $UserID = 0;

	private $debugEnabled = 0;

	/**
	* Class constructor : initialize a few private variables
	*
	* @return boolean
	*/
	public function __construct($CBSubs_ID, $RDSubs_ID)
	{
		// CBSubs Plan Number
		$this->CBID = (int)$CBSubs_ID;

		// RDSubs Plan Number
		$this->RDID = (int)$RDSubs_ID;

		if (isset($_SERVER['SCRIPT_FILENAME'])) {
			// In case of this script isn't in the current folder but
			// is a symbolic link.
			// The folder should be the current folder and not the
			// folder where the script is stored
			$this->sFolder = dirname($_SERVER['SCRIPT_FILENAME']);
			$this->script_name = basename($_SERVER['SCRIPT_FILENAME']);
		} else {
			$this->sFolder = __DIR__;
			$this->script_name = basename(__FILE__);
		}

		$this->sFolder = str_replace('/', DS, $this->sFolder).DS;

		// Retrieve the name of this script (f.i. migrate.php)
		$this->script_name = basename($_SERVER['SCRIPT_FILENAME']);

		// Get the .json file for this tool.
		// That file is mandatory
		$this->sJSONFile = str_replace('.php', '.json', $this->script_name);
		$this->sJSONFile = $this->sFolder.$this->sJSONFile;

		if (!is_file($this->sJSONFile)) {
			// Stop. Fatal error
			self::showError('no_json');
		}

		// Read the JSON into an array
		$sContent = file_get_contents($this->sJSONFile);
		$this->json = json_decode($sContent, true);

		if ($this->json == array()) {
			self::showError('bad_json');
		}

		$this->debugEnabled = (bool)$this->json['debug']['enabled'];
		$this->UserID = self::getUserID();

		if (self::debug()) {
			ini_set("display_errors", "1");
			ini_set("display_startup_errors", "1");
			ini_set("html_errors", "1");
			ini_set("docref_root", "http://www.php.net/");
			ini_set("error_prepend_string", "<div style='color:red; font-family:verdana; border:1px solid red; padding:5px;'>");
			ini_set("error_append_string", "</div>");
			error_reporting(E_ALL);
		} else {
			ini_set('error_reporting', E_ALL & ~ E_NOTICE);
		}

		$sFileName = $this->sFolder.'configuration.php';

		if (file_exists($sFileName)) {
			require_once($sFileName);

			$this->JConfig = new JConfig();

			if (self::debug()) {
				mysqli_report(MYSQLI_REPORT_STRICT);
			}

			$this->mysqli = new mysqli($this->JConfig->host, $this->JConfig->user, $this->JConfig->password);

			// Don't block if somes dates are 0000-00-00 00:00:00 but
			// allow the code to handle these dates.
			$this->mysqli->query("SET sql_mode=".
				"'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';");

			if (mysqli_connect_errno() !== 0) {
				self::showError("cannot_connect");
				$this->mysqli->close();
				$this->mysqli = null;
			} else {
				// Be sure to work on the correct database
				mysqli_select_db($this->mysqli, $this->JConfig->db);
			}
		} else {
			self::showError('not_joomla');
		}

		return true;
	}

	/**
	 * Release
	 */
	public function __destructor()
	{
		unset($this->JConfig);
		$this->mysqli->close();
		unset($this->mysqli);

		return true;
	}

	public function debug()
	{
		return $this->debugEnabled;
	}

	public function scriptName()
	{
		return $this->script_name;
	}

	/**
	 * Retrieve an info from the .json file.
	 * $info is a key like errors.cannot_connect
	 */
	public function getJSON($info)
	{
		$tmp = $this->json;

		// $info is f.i. debug.enabled so we first need to
		// extract the "debug" node then "enabled".
		// Use an array for this
		$arr = explode('.', $info);

		foreach ($arr as $key) {
			if (isset($tmp[$key])) {
				$tmp = $tmp[$key];
			} else {
				return '--key '.$info.' not found in the json file--';
			}
		}

		return $tmp;
	}

	/**
	 * Display error messages and die.
	 * Text are stored in the .json file
	 */
	private function showError($sErrorCode = '')
	{
		// Don't need the 'errors.' prefix
		$sErrorCode = str_replace('errors.', '', $sErrorCode);

		$sError = 'Undefined error in '.__FILE__;

		if (isset($this->json['errors'][$sErrorCode])) {
			$sError = $this->json['errors'][$sErrorCode];
		} else {
			if ($sErrorCode == 'no_json') {
				$sError = "File ".$this->sJSONFile." not found";
			} else {
				$sError = "There is an error in the json file, ".
					"please lint the file to be sure it's ok";
			}
		}

		echo
			'<p class="text-warning error">'.
				$sError.' (error code <em>"'.$sErrorCode.'"</em>)'.
			'</p>';

		die();
	}

	private function getUserID()
	{
		// Retrieve the user id to use for the debugging
		// % for all users
		$ID = '%';
		if (self::debug()) {
			try {
				$ID = intval($this->json['debug']['test_userid']);
			} catch (\Exception $e) {
				$ID = '%';
			}

			if ($ID=='0') {
				$ID = '%';
			}
		}

		return $ID;
	}

	private function prepareSQL($sSQL)
	{
		$sSQL = str_replace('#__', $this->JConfig->dbprefix, $sSQL);
		$sSQL = str_replace('%DBNAME%', $this->JConfig->db, $sSQL);
		$sSQL = str_replace('%USERID%', $this->UserID, $sSQL);
		$sSQL = str_replace('%CBID%', $this->CBID, $sSQL);
		$sSQL = str_replace('%RDID%', $this->RDID, $sSQL);
		return $sSQL;
	}

	/**
	 * Retrieve a SQL statement
	 * Statements are stored in the .json file, in SQL node
	 */
	private function getSQL($node)
	{
		// Don't need the 'sql.' prefix
		$node = str_replace('sql.', '', $node);

		$tmp = $this->json['sql'];

		// $node is f.i. migrate.rd_subs_transactions.merchandise
		// so we first need to extract the "migrate" node then
		// "rd_subs_transactions" and finally "merchandise"
		// Use an array for this
		$arr = explode('.', $node);

		foreach ($arr as $key) {
			if (isset($tmp[$key])) {
				$tmp = $tmp[$key];
			} else {
				return '--key '.$key.' not found in the json file--';
			}
		}

		// $tmp contains the SQL statement, replace variables
		$sSQL = self::prepareSQL($tmp);

		return $sSQL;
	}

	/**
	 * Show debugging info
	 */
	private function showDebugInfos()
	{
		// Only if debug mode is enabled
		if (!self::debug()) {
			return '';
		}

		if ($this->mysqli === null) {
			self::showError(1);
		}

		$sReturn = self::getJSON('text.debug_infos');

		// Check the presence of CBSubs
		$sSQL = self::getSQL('sql.cbsubs.check_presence');

		if ($this->mysqli->query($sSQL)) {
			$arrQueries = $this->json['debug']['queries'];

			foreach ($arrQueries as $name => $sSQL) {
				// Use the prefix
				$sSQL = self::prepareSQL($sSQL);

				if ($results = $this->mysqli->query($sSQL)) {
					$arr = array();
					while ($row = mysqli_fetch_assoc($results)) {
						$arr[] = $row;
					}

					$sReturn .= '<hr/><h3>'.$name.'</h3>'.
						'<small>'.count($arr).' records.</small>'.
						'<br/><code>'.$sSQL.'</code>';

					if (count($arr) > 0) {
						$sReturn .= aeSecureFct::array2table($arr, 1200);
					} else {
						$sReturn .= '<p class="bg-danger error">'.
							'No record</p>';
					}
				} else {
					$sReturn .= '<p class="bg-danger error">'.
						'Error in SQL:<br/>'.$sSQL.'</p>';
				}
			}

			$sReturn .= '<h3>Content of the json file</h3>'.
				'<p>Only SQLs parts and DB prefix added instead of #__</p>'.
				'<pre style="white-space: pre-wrap;  ">'.str_replace('#__', $this->JConfig->dbprefix, print_r($this->json['sql'], true)).'</pre>';
		} else { // if ($mysqli->query($sSQL))
			self::showError('errors.cbsubs_not_found');
		}

		$this->mysqli->close();
		return $sReturn;
	}

	/**
	 * Retrieve a recordset from the database and display it
	 * as a nice HTML table
	 */
	private function CBSubs_ShowTable()
	{
		if ($this->mysqli === null) {
			self::showError(1);
		}

		$sReturn = '';

		$sSQL = self::getSQL('cbsubs.check_presence');
		$results = $this->mysqli->query($sSQL);

		if ($results->num_rows>0) {
			$sSQL = self::getSQL('cbsubs.get_list');

			$results = $this->mysqli->query($sSQL);

			if ($results->num_rows>0) {
				$arr = array();
				while ($row = mysqli_fetch_assoc($results)) {
					$arr[] = $row;
				}

				$title = $this->getJSON('text.list_of_cbsubscriptions');
				$title = str_replace('%1', $this->CBID, $title);
				$sReturn .= $title.
					'<small>'.count($arr).' records.</small>'.
					aeSecureFct::array2table($arr, 1200);
			} else {
				self::showError('cbsubs_no_subs_found');
			}
		} else {
			self::showError('cbsubs_not_found');
		}

		$this->mysqli->close();
		return $sReturn;
	}

	/**
	 * Return the name of the CBSubs plan
	 */
	private function CBSubs_getPlan()
	{
		if ($this->mysqli === null) {
			self::showError(1);
		}
		$sSQL = self::getSQL('cbsubs.get_plan_title');

		$result = $this->mysqli->query($sSQL);

		$arr = $result->fetch_array(MYSQLI_ASSOC);
		$sReturn = isset($arr['plan']) ? $arr['plan'] : 'unknown';

		$this->mysqli->close();
		return $sReturn;
	}

	/**
	 * Return the name of the RD-Subs product
	 */
	private function RDSubs_getProduct()
	{
		if ($this->mysqli === null) {
			self::showError(1);
		}

		$sSQL = self::getSQL('rdsubs.get_product_title');

		$result = $this->mysqli->query($sSQL);
		$arr = $result->fetch_array(MYSQLI_ASSOC);
		$sReturn = isset($arr['product']) ? $arr['product'] : 'unknown';

		$this->mysqli->close();
		return $sReturn;
	}

	/**
	 * Debuging - Empty RD concerned tables
	 */
	private function emptyTables()
	{
		$arr = $this->json['sql']['rdsubs']['empty'];

		$sReturn = '';

		foreach ($arr as $sSQL) {
			$sSQL = str_replace('#__', $this->JConfig->dbprefix, $sSQL);

			$sReturn .= '<li>'.$sSQL.'</li>';

			$this->mysqli->query($sSQL);
		}

		return self::getJSON('text.tables_purged').'<ul>'.$sReturn.'</ul>';
	}

	/**
	 * Run a DML query like INSERT INTO or TRUNCATE or UPDATE ...
	 */
	private function runSQL($sSQL, $text, $rdTable, $extra_where = '', $orderby = '')
	{
		$sReturn = '';

		$sSQL = rtrim($sSQL, ' ;');

		if ($extra_where !== '') {
			if (stripos($sSQL, 'WHERE') !== false) {
				// Add an extra condition
				$sSQL .= ' AND '.$extra_where;
			} else {
				// Add a condition
				$sSQL .= ' WHERE '.$extra_where;
			}
		} // if ($extra_where!=='')

		if ($orderby !== '') {
			$sSQL .= ' '.$orderby;
		}

		if ($this->mysqli->query($sSQL) === true) {
			$sReturn .= '<li><i class="text-success fa-li fa fa-check"></i>'.$text.' migrated to '.$this->JConfig->dbprefix.$rdTable.'</li>';
		} else {
			$sReturn .= '<li><i class="text-danger fa-li fa fa-exclamation-triangle"></i>Error - '.$this->mysqli->error.'<br/>'.$sSQL.'</li>';
		}

		if (self::debug()) {
			$sReturn .= '<code class="language-sql" language="sql">'.$sSQL.'</code>';
		}

		return $sReturn;
	}

	private function showTable($sSQL, $text) {

		$results = $this->mysqli->query($sSQL);

		if ($results->num_rows>0) {
			$arr = array();
			while ($row = mysqli_fetch_assoc($results)) {
				$arr[] = $row;
			}

			$sReturn = $text.
				'<small>'.count($arr).' records.</small>'.
				($this->debug()?'<br/><code>'.$sSQL.'</code>':'').
				aeSecureFct::array2table($arr, 1200);
		} else {
			$sReturn = $text.'<small>No record found.</small>';
		}

		return $sReturn;

	}

	/**
	 * Start the migration; get records from CBSubs and insert
	 * them into RD-Subs tables
	 */
	private function startMigration()
	{
		$sReturn = '';

		if ($this->mysqli === null) {
			self::showError(1);
		}

		// Check if the CBSubs plan is a usersubscription or a
		// merchandise (a product; one-time fee)
		$sSQL = self::getSQL('cbsubs.get_type');

		$result = $this->mysqli->query($sSQL);
		$arr = $result->fetch_array(MYSQLI_ASSOC);
		$sCBSubsItemType = isset($arr['item_type']) ? $arr['item_type'] : 'unknown';

		if ($sCBSubsItemType == 'unknown') {
			self::showError('cbsubs_not_supported');
		}

		// Display the type of the plan (can be usersubscription,
		// merchandise, ...)
		$sText = self::getJSON('text.cbtype');
		$sText = str_replace('%1', $this->CBID, $sText);
		$sText = str_replace('%2', $sCBSubsItemType, $sText);

		$sReturn = '<h3>'.$sText.'</h3>';

		// -------------------------------------------------
		// 1. Migrate records of #__cbsubs_payment_items i.e.
		// get transactions
		// -------------------------------------------------

		$rdTable = 'rd_subs_transactions';

		$sSQL = self::getSQL('migrate.'.$rdTable.'.'.$sCBSubsItemType);

		$sTitle = self::getJSON('text.migrate_cbsubs_plan');
		$sTitle = str_replace('%CBID%', $this->CBID, $sTitle);

		$sReturn .= self::runSQL($sSQL, $sTitle, $rdTable, '');

		// -------------------------------------------------
		// 2. Migrate records of #_cbsubs_subscriptions i.e.
		// get subscriptions
		// -------------------------------------------------

		$rdTable = 'rd_subs_product2user';
		$sSQL = self::getSQL('migrate.'.$rdTable.'.'.$sCBSubsItemType);
		$sReturn .= $this->runSQL($sSQL, 'Subscriptions', $rdTable, '');

		// -------------------------------------------------
		// 3. Migrate records of #_comprofiler i.e. get users
		//	Migrate all users and not only the ones concerned by
		//	  the subscription	(CB_Subs_ID)
		// -------------------------------------------------

		$rdTable = 'rd_subs_users';
		$sSQL = self::getSQL('migrate.'.$rdTable);
		$sReturn .= $this->runSQL($sSQL, 'Users', $rdTable, '');

		// -------------------------------------------------
		// 4. Migrate records of #__cbsubs_payment_items i.e.
		// get orders
		// -------------------------------------------------

		$rdTable = 'rd_subs_orders';
		$sSQL = self::getSQL('migrate.'.$rdTable);
		$sReturn .= $this->runSQL($sSQL, 'Orders', $rdTable, '');

		// -------------------------------------------------
		// Finally, show results : number of subscriptions still
		// active
		// -------------------------------------------------

		$sReturn = self::getJSON('text.controls');

		$sSQL = self::getSQL('cbsubs.control_still_running');
		$sTitle = self::getJSON('text.controls_cbsubs_still_active');
		$sTitle = str_replace('%1', $this->CBID, $sTitle);
		$sReturn .= $this->showTable($sSQL, $sTitle);

		$sSQL = self::getSQL('rdsubs.control_still_running');
		$sTitle = self::getJSON('text.controls_rdsubs_still_active');
		$sTitle = str_replace('%1', $this->RDID, $sTitle);
		$sReturn .= $this->showTable($sSQL, $sTitle);

		// ---------------------

		if (self::debug()) {
			$sReturn .= self::showDebugInfos();
		}

		return '<ul class="fa-ul">'.$sReturn.'</ul>';
	}

	/**
	 * Process a task
	 */
	public function process($task = '')
	{
		switch ($task) {
			case 'debug':
				echo self::showDebugInfos();
				break;

			case 'empty':
				echo self::emptyTables();
				break;

			case 'getList':
				if ($this->CBID > 0) {
					echo self::CBSubs_ShowTable();
				} else {
					self::showError('cbsubs_id_missing');
				}
				break;

			case 'getPlan':
				if ($this->CBID > 0) {
					echo utf8_encode(self::CBSubs_getPlan());
				} else {
					$aeSMigrate->showError('cbsubs_id_missing');
				}
				break;

			case 'getProduct':
				if ($this->RDID > 0) {
					echo utf8_encode(self::RDSubs_getProduct());
				} else {
					die('rdSubs parameter is missing');
				}
				break;

			case 'doIt':
				if (($this->CBID > 0) && ($this->RDID > 0)) {
					echo self::startMigration();
				} else {
					self::showError('IDs_missing');
				}
				break;

			case 'killMe':
				$msg = self::getJSON('text.killed');
				$msg = str_replace('%1', $this->script_name, $msg);
				echo '<p class="text-success">'.$msg.'</p>';
				unlink($this->script_name);
				break;
		} // switch

		// Can stop after a processing since all tasks are done by
		// ajax requests
		die();
	}
}

// -----------------------------------------------------
//
//						 ENTRY POINT
//
// -----------------------------------------------------

// Get the three parameters, task, CBSbus ID and RDSubs ID
$task = aeSecureFct::getParam('task', 'string', '', false);
$CB_ID = abs(aeSecureFct::getParam('cbSubs', 'int', 0, false));
$RD_ID = abs(aeSecureFct::getParam('rdSubs', 'int', 0, false));

$aeSMigrate = new aeSecureMigrate($CB_ID, $RD_ID);

if ($task !== '') {
	$aeSMigrate->process($task);
}

?>

<!DOCTYPE html>
<html lang="en">

	<head>
		<meta charset="utf-8"/>
		<meta name="author" content="aeSecure (c) Christophe Avonture" />
		<meta name="robots" content="noindex, nofollow" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=9; IE=8;" />
		<title>aeSecure - From CBSubs to RD-Subs</title>
		<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet"integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
		<link href="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.25.3/css/theme.ice.min.css" rel="stylesheet" media="screen" />
		<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" media="screen" />

		<style>
			#Result {
				margin-top: 25px;
			}
			.error {
				margin: 10px;
				font-size: 2em;
			}
			.ajax_loading {
				display: inline-block;
				width: 32px;
				height: 32px;
				margin-right: 20px;
				background-image: url('<?php echo $aeSMigrate->getJSON('misc.ajax_loading')?>');
			}
			.joomla {
				padding: 10px;
				margin-bottom: 10px;
				border: 1px solid green;
			}
			.joomla ul {
				padding-top: 8px;
			}
			.joomla li {
				min-width: 210px;
			}
			#PlanName, #ProductName {
				min-width: 210px;
				display: inline-block;
				font-style: italic;
			}
			#cbLabel, #rdLabel {
				width: 150px;
			}
			#cbSubs, #rdSubs {
				width: 60px;
			}
			#btnDebug {
				margin-right: 150px;
			}
			.language-sql
			{
				display: block;
				margin-bottom: 15px;
				font-size: 0.8em;
			}
			</style>
	</head>

	<body>
		<div class="container">

			<div class="page-header">
				<h1><?php echo $aeSMigrate->getJSON('text.title')?></h1>
			</div>

			<div class="alert alert-danger alert-dismissible" role="alert">
				<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<?php echo $aeSMigrate->getJSON('text.be_aware')?>
			</div>

			<details>
				<summary>
					<?php echo $aeSMigrate->getJSON('text.how_to_use')?>
				</summary>
				<ol>
					<li>
						<?php echo $aeSMigrate->getJSON('text.step_1')?>
					</li>
					<li>
						<?php echo $aeSMigrate->getJSON('text.step_2')?>
					</li>
					<li>
						<?php echo $aeSMigrate->getJSON('text.step_3')?>
					</li>
				</ol>
				<?php echo $aeSMigrate->getJSON('text.informations');?>
				<ul>
					<li><?php echo $aeSMigrate->getJSON('text.notes_button_empty_RD_desc');?></li>
					<li><?php echo $aeSMigrate->getJSON('text.notes_sql_statements');?></li>
					<li><?php echo $aeSMigrate->getJSON('text.notes_debug_mode');?></li>
				</ul>
			</details>

			<hr/>

			<form id="form" class="form-inline">

				<!-- CBSubs plan ID and RDSubs product IC -->
				<div class="form-group">

					<!-- CBSubs -->
					<label id="cbLabel" for="cbSubs">
						<?php echo $aeSMigrate->getJSON('text.cbsubs_plan_id')?>
					</label>
					&nbsp;
					<input id="cbSubs" value="1" size="5" width="5" class="form-control" placeholder="#CB" />
					&nbsp;&nbsp;&nbsp;
					<button type="button" id="btnGetCBPlan" class="btn btn-default">
						<i class="fa fa-shopping-cart" aria-hidden="true"></i>
					</button>
					&nbsp;
					<span class="text-success" id="PlanName">&nbsp;</span>
					<br/>

					<!-- RDSubs -->
					<label id="rdLabel" for="rbSubs">
						<?php echo $aeSMigrate->getJSON('text.rdsubs_product_id')?>
					</label>
					&nbsp;
					<input disabled="disabled" size="5" width="5" id="rdSubs" class="form-control" value="1" placeholder="#RD" />
					&nbsp;&nbsp;&nbsp;
					<button disabled="disabled" type="button" id="btnGetRDProduct" class="btn btn-default">
						<i class="fa fa-shopping-cart" aria-hidden="true"></i>
					</button>
					&nbsp;
					<span class="text-success" id="ProductName">&nbsp;</span>
				</div>

				<br /><br />

				<!-- Buttons -->
				<div class="row">
					<button type="button" disabled="disabled" id="btnGetList" class="btn btn-primary">
						<?php echo $aeSMigrate->getJSON('text.step_1_button')?>
					</button>

					<button type="button" disabled="disabled" id="btnDoIt" class="btn btn-success">
						<?php echo $aeSMigrate->getJSON('text.step_2_button')?>
					</button>

					<button type="button" id="btnKillMe" class="btn btn-danger pull-right" style="margin-left:10px;">
						<?php echo $aeSMigrate->getJSON('text.step_2_button')?>
					</button>

					<?php
						if ($aeSMigrate->debug()) {
							echo '<button type="button" id="btnDebug" class="btn btn-warning pull-right" style="margin-left:10px;">'.
							$aeSMigrate->getJSON('text.button_debug').
							'</button>';
						}
					?>

					<button type="button" id="btnEmpty" class="btn btn-primary pull-right" style="margin-left:10px;">
						<?php echo $aeSMigrate->getJSON('text.button_empty_RD') ?>
					</button>
				</div>
			</form>

			<div id="Result">&nbsp;</div>

		</div>

		<footer class="footer">
			<div class="container">
				<span class="text-muted">
				<?php echo $aeSMigrate->getJSON('text.no_support')?>
				</span>
		</div>
		</footer>

		<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
		<script type="text/javascript" src="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js"></script>
		<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.25.3/js/jquery.tablesorter.combined.min.js"></script>
		<script type="text/javascript">

			$(document).ready(function() {
				$('#cbSubs').select();

				$('#cbSubs').keydown(function(e)
				{
					if (e.keyCode == 13)
					{
						$('#btnGetCBPlan').prop("disabled", false).removeClass("hidden");
						$('#btnGetCBPlan').click();
						$('#btnGetList').click();
					}
				});

				$('#rdSubs').keydown(function(e)
				{
					if (e.keyCode == 13)
					{
						$('#btnGetRDProduct').prop("disabled", false).removeClass("hidden");
						$('#btnGetRDProduct').click();
					}
				});

			}); // $( document).ready()

			/*
			 * Debug, display tables
			 */
			$('#btnDebug').click(function(e)  {

				e.stopImmediatePropagation();

				var $data = new Object;
				$data.task = "debug";
				$data.cbSubs = $('#cbSubs').val();
				$data.rdSubs = $('#rdSubs').val();

				$.ajax({
					beforeSend: function()
					{
						$('#Result').html('<div><span class="ajax_loading">&nbsp;</span><span style="font-style:italic;font-size:1.5em;">Please wait...</span></div>');
					},// beforeSend()
					async:true,
					type:"GET",
					url: "<?php echo $aeSMigrate->scriptName(); ?>",
					data:$data,
					datatype:"html",
					success: function (data)
					{
						$('#Result').html(data);
					}
				}); // $.ajax()

			}); // $('#btnDebug').click()

			/**
			 * Empty RD tables
			 */
			$('#btnEmpty').click(function(e)  {

				e.stopImmediatePropagation();

				var $data = new Object;
				$data.task = "empty";

				$.ajax({
					beforeSend: function()
					{
						$('#Result').html('<div><span class="ajax_loading">&nbsp;</span><span style="font-style:italic;font-size:1.5em;">Please wait...</span></div>');
					},// beforeSend()
					async:true,
					type:"POST",
					url: "<?php echo $aeSMigrate->scriptName(); ?>",
					data:$data,
					datatype:"html",
					success: function (data)
					{
						$('#Result').html(data);
					}
				}); // $.ajax()

			}); // $('#btnEmpty').click()

			/*
			 * Retrieve the list of customers, current subscriptions in CB Subs
			 */
			$('#btnGetList').click(function(e)  {

				e.stopImmediatePropagation();

				var $data = new Object;
				$data.task = "getList";
				$data.cbSubs = $('#cbSubs').val();

				$.ajax({
					beforeSend: function()
					{
						$('#Result').html('<div><span class="ajax_loading">&nbsp;</span><span style="font-style:italic;font-size:1.5em;">Please wait...</span></div>');
					},// beforeSend()
					async:true,
					type:"POST",
					url: "<?php echo $aeSMigrate->scriptName(); ?>",
					data:$data,
					datatype:"html",
					success: function (data)
					{
						$('#Result').html(data);
						$('#rdSubs').prop("disabled", false).select();
						$('#btnGetProduct').prop("disabled", false);
						$('#btnGetRDProduct').prop("disabled", false);

						initTableSort();
					}
				}); // $.ajax()

			}); // $('#btnGetList').click()

			/*
			 * Make the list of customers sortable
			 */
			function initTableSort()
			{

				$("#tbl").tablesorter(
				{
					theme: "ice",
					widthFixed: false,
					sortMultiSortKey: "shiftKey",
					sortResetKey: "ctrlKey",
					headers:
					{
						0: {sorter: "digit"}, // Table name
						1: {sorter: "text"}, // Table name
						2: {sorter: "digit"},  // Table name
						3: {sorter: "text"}, // Table name
						4: {sorter: "date"}, // Table name
						5: {sorter: "date"}, // Table name
						6: {sorter: "digit"}	// Number of records
					},
					ignoreCase: true,
					headerTemplate: "{content} {icon}",
					widgets: ["uitheme", "filter"],
					initWidgets: true,
					widgetOptions: {
						uitheme: "ice",
						filter_columnFilters: false
					},
					sortList: [[4,1]]  // Sort by default on the table name
				});

			} // function initTableSort()

			$('#cbSubs').on("change", function() {
				$('#btnGetCBPlan').click();
			});

			/*
			 * Retrieve the name of the CBSubs plan
			 */
			$('#btnGetCBPlan').click(function (e)
			{

				e.stopImmediatePropagation();

				var $data = new Object;
				$data.task = "getPlan";
				$data.cbSubs = $('#cbSubs').val();

				$.ajax(
				{
					async:true,
					type:"POST",
					url: "<?php echo $aeSMigrate->scriptName(); ?>",
					data:$data,
					datatype:"html",
					success: function (data) {
						$('#PlanName').html(data);
						if(data!=='unknown')
						{
							$('#btnGetList').prop("disabled", false).removeClass("hidden");
							$('#btnGetRDProduct').prop("disabled", false);
							$('#rdSubs').prop("disabled", false);

							$('#btnDoIt').prop("disabled",  ($('#rdSubs').val()>0));
						} else {
							$('#btnGetList').prop("disabled", true).addClass("hidden");
						}
					}
				}); // $.ajax()
			}); // $('#btnGetCBPlan').click()

			$('#rdSubs').on("change", function() {
				if ($('#rdSubs').val()>0) {
					$('#btnGetRDProduct').click();
				}
			});

			/*
			 * Retrieve the name of the RD-Subs product
			 */
			$('#btnGetRDProduct').click(function (e)
			{

				e.stopImmediatePropagation();

				var $data = new Object;
				$data.task = "getProduct";
				$data.rdSubs = $('#rdSubs').val();

				$.ajax(
				{
					async:true,
					type:"POST",
					url: "<?php echo $aeSMigrate->scriptName(); ?>",
					data:$data,
					datatype:"html",
					success: function (data) {
						$('#ProductName').html(data);
						if(data!=='unknown')
						{
							$('#btnDoIt').prop("disabled", false).removeClass("hidden");
						} else {
							$('#btnDoIt').prop("disabled", true).addClass("hidden");
						}
					}
				}); // $.ajax()
			}); // $('#btnGetProduct').click()

			/*
			 * Do it, migrate
			 */
			$('#btnDoIt').click(function(e)  {

				e.stopImmediatePropagation();

				var $data = new Object;
				$data.task = "doIt";
				$data.cbSubs = $('#cbSubs').val();
				$data.rdSubs = $('#rdSubs').val();

				$.ajax({

					beforeSend: function() {
						$('#Result').html('<div><span class="ajax_loading">&nbsp;</span><span style="font-style:italic;font-size:1.5em;">Please wait...</span></div>');
						$('#btnKillMe').prop("disabled", true);
						$('#btnGetList').prop("disabled", true);
						$('#btnGetProduct').prop("disabled", true);
						$('#btnDoIt').prop("disabled", true);
						$('#cbSubs').prop("disabled", true);
						$('#rdSubs').prop("disabled", true);
					},// beforeSend()
					async:true,
					type:"GET",
					url: "<?php echo $aeSMigrate->scriptName(); ?>",
					data:$data,
					datatype:"html",
					success: function (data) {

						$('#btnGetList').prop("disabled", false);
						$('#btnGetProduct').prop("disabled", false);
						$('#btnKillMe').prop("disabled", false);
						$('#btnDoIt').prop("disabled", false);
						$('#cbSubs').prop("disabled", false);
						$('#rdSubs').prop("disabled", false);

						$('#Result').html(data);

					}, // success
					error: function(Request, textStatus, errorThrown)
					{
						$('#btnKillMe').prop("disabled", false);
						$('#btnDoIt').prop("disabled", false);
						// Display an error message to inform the user about the problem
						var $msg = '<div class="bg-danger text-danger img-rounded" style="margin-top:25px;padding:10px;">';
						$msg = $msg + '<strong>An error has occured :</strong><br/>';
						$msg = $msg + 'Internal status: '+textStatus+'<br/>';
						$msg = $msg + 'HTTP Status: '+Request.status+' ('+Request.statusText+')<br/>';
						$msg = $msg + 'XHR ReadyState: ' + Request.readyState + '<br/>';
						$msg = $msg + 'Raw server response:<br/>'+Request.responseText+'<br/>';
						$url='<?php echo $aeSMigrate->scriptName(); ?>?'+$data.toString();
						$msg = $msg + 'URL that has returned the error : <a target="_blank" href="'+$url+'">'+$url+'</a><br/><br/>';
						$msg = $msg + '</div>';
						$('#Result').html($msg);
					} // error
				}); // $.ajax()
			}); // $('#btnDoIt').click()

			// Remove this script
			$('#btnKillMe').click(function(e)  {
				e.stopImmediatePropagation();

				var $data = new Object;
				$data.task = "killMe";

				$.ajax({
				  beforeSend: function() {
					 $('#Result').empty();
					 $('#btnKillSelected').prop("disabled", true);
					 $('#btnKillMe').prop("disabled", true);
				  },// beforeSend()
				  async:true,
				  type:"POST",
				  url: "<?php echo $aeSMigrate->scriptName(); ?>",
				  data:$data,
				  datatype:"html",
				  success: function (data) {
					 $('#form').remove();
					 $('#Result').html(data);
				  }
				}); // $.ajax()
			}); // $('#KillMe').click()
		</script>

	</body>
</html>

<?php
	unset($aeSMigrate);
?>