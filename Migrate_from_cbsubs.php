<?php

/**
 * Author : AVONTURE Christophe - https://www.aesecure.com | Website Firewall Application and unhacking services
 *
 * Small script for helping the migration from CB Subs (https://www.joomlapolis.com/) to RD-Subs (https://rd-media.org/)
 *
 * Please be sure to have a backup of your database before using this script.
 *
 * The latest version of the script can be freely retrieved on https://github.com/cavo789/rd-subs
 *
 * No support is provided by the developer, use this script under your own responsabilities
 * 
 * License : MIT
 */
 
define('DEBUG', false);

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

class aeSecureFct
{
    
   /**
    * Safely read posted variables
    *
    * @param type $name          f.i. "password"
    * @param type $type          f.i. "string"
    * @param type $default       f.i. "default"
    * @return type
    */
    public static function getParam($name, $type = 'string', $default = '', $base64 = false)
    {
      
        $tmp='';
        $return=$default;
      
        if (isset($_POST[$name])) {
            if (in_array($type, array('int','integer'))) {
                $return=filter_input(INPUT_POST, $name, FILTER_SANITIZE_NUMBER_INT);
            } elseif ($type=='boolean') {
                // false = 5 characters
                $tmp=substr(filter_input(INPUT_POST, $name, FILTER_SANITIZE_STRING), 0, 5);
                $return=(in_array(strtolower($tmp), array('1','on','true')))?true:false;
            } elseif ($type=='string') {
                $return=filter_input(INPUT_POST, $name, FILTER_SANITIZE_STRING);
                if ($base64===true) {
                    $return=base64_decode($return);
                }
            } elseif ($type=='unsafe') {
                $return=$_POST[$name];
            }
        } else { // if (isset($_POST[$name]))
     
            if (isset($_GET[$name])) {
                if (in_array($type, array('int','integer'))) {
                    $return=filter_input(INPUT_GET, $name, FILTER_SANITIZE_NUMBER_INT);
                } elseif ($type=='boolean') {
                    // false = 5 characters
                    $tmp=substr(filter_input(INPUT_GET, $name, FILTER_SANITIZE_STRING), 0, 5);
                    $return=(in_array(strtolower($tmp), array('1','on','true')))?true:false;
                } elseif ($type=='string') {
                    $return=filter_input(INPUT_GET, $name, FILTER_SANITIZE_STRING);
                    if ($base64===true) {
                        $return=base64_decode($return);
                    }
                } elseif ($type=='unsafe') {
                    $return=$_GET[$name];
                }
            } // if (isset($_GET[$name]))
        } // if (isset($_POST[$name]))
      
        return $return;
    } // function getParam()


	// @link : http://ca2.php.net/manual/fr/function.mysql-fetch-assoc.php#74048
	public static function array2table($arr,$width)
	{
		$sReturn='';
		$count = count($arr);
		
		if($count > 0)
		{
			
			reset($arr);
			
			$num = count(current($arr));
			
			$sReturn.="<table id=\"tbl\" class=\"table tablesorter table-hover table-bordered table-striped\" width=\"$width\">\n";
			
			// Add an ID column ... only if the recordset don't have one.
			$sReturn.="<thead>".(!isset($arr[0]['id'])?"<td>ID</td>":"")."\n";
			
			foreach(current($arr) as $key => $value)
			{
			   $sReturn.="<td>".$key."&nbsp;</td>\n";   
			}   
			
			$sReturn.="</thead><tfoot></tfoot><tbody>\n";
			
			$i=0;
			
			while ($curr_row = current($arr)) {
				
				$i+=1;
				$sReturn.="<tr>\n".(!isset($arr[0]['id'])?"<td>$i</td>":"");
				$col = 1;
				
				while (false !== ($curr_field = current($curr_row))) 
				{
				   $sReturn.="<td>".utf8_encode($curr_field)."&nbsp;</td>\n";
				   next($curr_row);
				   $col++;
				}
				
				while($col <= $num){
				   $sReturn.="<td>&nbsp;</td>\n";
				   $col++;       
				}
				
				$sReturn.="</tr>\n";
				
				next($arr);
				
			}
			
			$sReturn.="</tbody></table>\n";
			
		}
		   
		return $sReturn;
	}
   
} // class aeSecureFct

class aeSecureMigrate
{
	private $DebugUser_ID='2189'; 
	// 55 = Christophe GUERTON
	// 76 = Frédéric JOUAN
	// 1210 = Robert GASTAUD - Pro
	// 2316 = Gérard COLLETTA - Premium
	// 2327 = MG Eco - Premium+
	// 2332 = Fabrice PRIEUR - Pro
	
	// 2189 = Eric Merkes - Cleaning + Premium
	
    private $mysqli=null;
	private $JConfig=null;
    private $sFolder='';
      
   /**
    * Class constructor : initialize a few private variables
    *
    * @return boolean
    */
    function __construct()
    {
        
		if (isset($_SERVER['SCRIPT_FILENAME'])) {
			// In case of this script isn't in the current folder but is a symbolic link.
			// The folder should be the current folder and not the folder where the script is stored
			$this->sFolder=str_replace('/', DS, dirname($_SERVER['SCRIPT_FILENAME'])).DS;
		} else {
			$this->sFolder=__DIR__;
		}
		
		if (file_exists($sFileName=$this->sFolder.'configuration.php'))
		{
			
			require_once($sFileName);
			$this->JConfig = new JConfig();
			
			if (DEBUG===true) 
			{
				mysqli_report(MYSQLI_REPORT_STRICT);
			}
			
			$this->mysqli = new mysqli($this->JConfig->host, $this->JConfig->user, $this->JConfig->password);
			
			// Don't block if somes dates are 0000-00-00 00:00:00 but allow the code to handle these dates.
			$this->mysqli->query("SET sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';");
			
			if (mysqli_connect_errno()!==0) {
				
				echo '<p class="bg-danger error">Could not connect to mysql.</p>';
				$this->mysqli->close();
				$this->mysqli=null;
			
			} else { // if (mysqli_connect_errno()!==0)
				
				// Be sure to work on the correct database
				mysqli_select_db($this->mysqli, $this->JConfig->db);
				
			} // if (mysqli_connect_errno()!==0)
				
		} // if (file_exists($sFileName=$this->sFolder.'configuration.php'))
			
        return true;
		
    } // function __construct()
	
	/**
	 * Release
	 */
    function __destructor()
	{
		
		unset($this->JConfig);
		$this->mysqli->close();
		unset($this->mysqli);
		
		return true;
		
	}
	
	/**
	 * Shwo debugging info
	 */
	public function debug()
	{
		
		if ($this->mysqli===null)
		{
			echo '<p class="text-warning error">Please put this script in the same folder of your Joomla\'s <em>configuration.php</em> file i.e. in the root folder of your website.</p>';
			die();
		} 
				
		$sReturn='';
			
		// Check the presence of CBSubs
		
		$sSQL="SELECT * FROM INFORMATION_SCHEMA.TABLES ".
			"WHERE (TABLE_SCHEMA LIKE '".$this->JConfig->db."') AND ".
			"(TABLE_NAME='".$this->JConfig->dbprefix."cbsubs_subscriptions')";

        if ($this->mysqli->query($sSQL)) 
        {
			
			$arrQueries=array(
				array("SELECT * ".
				   "FROM `".$this->JConfig->dbprefix."cbsubs_subscriptions` CBSubs ".
				   "WHERE CBSubs.user_id = ".$this->DebugUser_ID.";","cbsubs_subscriptions"),
				//array("SELECT * FROM `".$this->JConfig->dbprefix."comprofiler` WHERE user_id=".$this->DebugUser_ID.";","comprofiler"),			   
				//array("SELECT * FROM `".$this->JConfig->dbprefix."rd_subs_users` WHERE userid=".$this->DebugUser_ID.";","rd_subs_users"),
				array("SELECT Items.* ".
				   "FROM `".$this->JConfig->dbprefix."cbsubs_payment_items` Items ".
				   (DEBUG?"INNER JOIN `".$this->JConfig->dbprefix."cbsubs_payment_baskets` Baskets ON (Baskets.id=Items.payment_basket_id) AND (Baskets.user_id='".$this->DebugUser_ID."') ":"").
				   //"INNER JOIN `".$this->JConfig->dbprefix."cbsubs_subscriptions` CBSubs ON CBSubs.id=Items.subscription_id ".
				   //"WHERE (CBSubs.user_id = ".$this->DebugUser_ID.") ".
				   //"AND (Items.item_type='usersubscription') ".  // Force and only return usersubscription.  Use "merchandise" for products
				   "ORDER BY Items.id;","cbsubs_payment_items"),
				array("SELECT Baskets.* ".
				   "FROM `".$this->JConfig->dbprefix."cbsubs_payment_baskets` Baskets ".
				   "INNER JOIN `".$this->JConfig->dbprefix."cbsubs_payment_items` Items ON Items.payment_basket_id=Baskets.id ".
				   "WHERE (Baskets.user_id = ".$this->DebugUser_ID.") ".
				   "ORDER BY Baskets.id;","cbsubs_payment_baskets"),
				array("SELECT * FROM `".$this->JConfig->dbprefix."rd_subs_orders` ORDER BY date","rd_subs_orders"),
				array("SELECT * FROM `".$this->JConfig->dbprefix."rd_subs_transactions` ORDER BY date","rd_subs_transactions"),
				array("SELECT * FROM `".$this->JConfig->dbprefix."rd_subs_product2user` ORDER BY valid_to","rd_subs_product2user")
			);

			foreach ($arrQueries as $query) 
			{
				if ($results = $this->mysqli->query($query[0])) 
				{
					
					// For display purpose
					$arr=array();
					while ($row = mysqli_fetch_assoc($results)) {
						$arr[] = $row; 
					}
					
					$sReturn.='<hr/><h3>'.$query[1].'</h3>';
					
					if(count($arr)>0) 
					{
						
						$sReturn.=aeSecureFct::array2table($arr,1200);	
						
					} else {
						
						$sReturn.='<p class="bg-danger error">No record</p>';
					}
					
				} else {
					
					$sReturn.='<p class="bg-danger error">Error in SQL:<br/>'.$query[0].'</p>';
					
				}
			}
            
        } else {
			
			$sReturn.='<p class="bg-danger error">CBSubs not found (table #_cbsubs_subscriptions not found).</p>';
			
		} // if ($mysqli->query($sSQL)) 
			
		$this->mysqli->close();
		return $sReturn;		
		
   } // function debug()   
   
    /**
	 * Retrieve a recordset from the database and display it as a nice HTML table
	 */
	public function showTable($CB_Subs_ID)
	{
	    
		if ($this->mysqli===null)
		{
			echo '<p class="text-warning error">Please put this script in the same folder of your Joomla\'s <em>configuration.php</em> file i.e. in the root folder of your website.</p>';
			die();
		} 
				
		$sReturn='';
				
		// Check the presence of CBSubs
		
		$sSQL="SELECT * FROM INFORMATION_SCHEMA.TABLES ".
			"WHERE (TABLE_SCHEMA LIKE '".$this->JConfig->db."') AND ".
			"(TABLE_NAME='".$this->JConfig->dbprefix."cbsubs_subscriptions')";

        if ($this->mysqli->query($sSQL)) 
        {
			
			$sSQL = "SELECT P.name AS product, user_id, U.name AS username, CAST(subscription_date AS date) AS created, ".
			   "CAST(expiry_date AS date) AS valid_to, " .
			   "CASE WHEN (expiry_date < Now()) THEN 0 ELSE 1 END AS status ".
               "FROM `".$this->JConfig->dbprefix."cbsubs_subscriptions` CB ".
			   "INNER JOIN `".$this->JConfig->dbprefix."cbsubs_plans` P ON CB.plan_id = P.id ".
			   "INNER JOIN `".$this->JConfig->dbprefix."users` U ON CB.user_id = U.id ".
               "WHERE CB.plan_id = ".$CB_Subs_ID.";";
			
			if ($results = $this->mysqli->query($sSQL)) 
			{
				
				// For display purpose
				$arr=array();
				while ($row = mysqli_fetch_assoc($results)) {
					$arr[] = $row; 
				}
				
				if(count($arr)>0) 
				{
					
					$sReturn.=aeSecureFct::array2table($arr,1200);	
					
				} else { // if(count($arr)>0) 
					
					// Check if the plan is a merchandise and not a subscription (i.e. no entries in cbsubs_subscriptions but well in 
					// cbsubs_payment_items)
					
					$sSQL = 'select Baskets.user_id, Items.* '.
						'from `'.$this->JConfig->dbprefix.'cbsubs_payment_items` Items '.
							'inner join `'.$this->JConfig->dbprefix.'cbsubs_payment_baskets` Baskets on Items.payment_basket_id = Baskets.id '.
						'where plan_id = '.$CB_Subs_ID.';';

					if ($results = $this->mysqli->query($sSQL)) 
					{
						
						// For display purpose
						$arr=array();
						while ($row = mysqli_fetch_assoc($results)) {
							$arr[] = $row; 
						}
						$sReturn.=aeSecureFct::array2table($arr,1200);	
						
					} else {
						
					   $sReturn.='<p class="bg-danger error">--No subscriptions in CBSubs for plan '.$CB_Subs_ID.'.</p>';
					   
					}
						
				} // if(count($arr)>0) 
				
		    } else { // if ($results = $this->mysqli->query($sSQL)) 
				
				$sReturn.='<p class="bg-danger error">**No subscriptions in CBSubs for plan '.$CB_Subs_ID.'.</p>';
				
			} // if ($results = $this->mysqli->query($sSQL)) 
            
        } else { // if ($this->mysqli->query($sSQL)) 
			
			$sReturn.='<p class="bg-danger error">CBSubs not found (table #_cbsubs_subscriptions not found).</p>';
			
		} // if ($this->mysqli->query($sSQL)) 
			
		$this->mysqli->close();
		return $sReturn;		
		
   } // function showTable()
   
    /**
	 * Return the name of the CBSubs plan
	 */
	public function getPlan($CB_Subs_ID) 
	{
		$sReturn='unknown';

		if ($this->mysqli===null)
		{
			echo '<p class="text-warning error">Please put this script in the same folder of your Joomla\'s <em>configuration.php</em> file i.e. in the root folder of your website.</p>';
			die();
		} 

		$sSQL = 'SELECT name as plan FROM '.$this->JConfig->dbprefix.'cbsubs_plans WHERE ID='.(int)$CB_Subs_ID.';';

		if ($result = $this->mysqli->query($sSQL)) 
		{	

			$arr=$result->fetch_array(MYSQLI_ASSOC);
			$sReturn=isset($arr['plan']) ? $arr['plan'] : 'unknown';
		}

		$this->mysqli->close();
		return $sReturn;	
		
	} // function getPlan()
   
    /**
	 * Return the name of the RD-Subs product
	 */
	public function getProduct($RD_Subs_ID) 
	{
		$sReturn='unknown';

		if ($this->mysqli===null)
		{
			echo '<p class="text-warning error">Please put this script in the same folder of your Joomla\'s <em>configuration.php</em> file i.e. in the root folder of your website.</p>';
			die();
		} 

		$sSQL = 'SELECT name as product FROM '.$this->JConfig->dbprefix.'rd_subs_products WHERE ID='.(int)$RD_Subs_ID.';';

		if ($result = $this->mysqli->query($sSQL)) 
		{	

			$arr=$result->fetch_array(MYSQLI_ASSOC);
			$sReturn=isset($arr['product']) ? $arr['product'] : 'unknown';
		}

		$this->mysqli->close();
		return $sReturn;	
		
	} // function getProduct()
   
    /**
	 * Debuging - Empty RD concerned tables
	 */
	public function emptyTables() 
	{
		$arr=array('rd_subs_orders','rd_subs_product2user','rd_subs_transactions','rd_subs_users');
		
		foreach ($arr as $tbl) {
			$rdTable=$this->JConfig->dbprefix.$tbl;
			$this->resetTable($rdTable);
		}
		
		return implode($arr,', ').' have been clean up';
		
	} // function emptyTables()
	
    /**
	 * Run a DML query like INSERT INTO or TRUNCATE or UPDATE ...
	 */
	private function runSQL($sSQL, $text, $rdTable, $extra_where='', $orderby='') 
	{	   

		$sSQL = rtrim($sSQL, ' ;');
		
		if ($extra_where!=='')
		{
			if (stripos($sSQL, 'WHERE')!==FALSE) 
			{
				// Add an extra condition
				$sSQL.=' AND '.$extra_where;
			} else 
			{	
				// Add a condition
				$sSQL.=' WHERE '.$extra_where;
			}
		} // if ($extra_where!=='')
			
		if($orderby!=='') $sSQL.=' '.$orderby;

		if (DEBUG) 
		{
			echo '<code class="language-sql" language="sql">'.$sSQL.'</code>';
		}
		
		if ($this->mysqli->query($sSQL) === TRUE) 
		{
			$sReturn='<li><i class="text-success fa-li fa fa-check"></i>'.$text.' migrated to '.$this->JConfig->dbprefix.$rdTable.'</li>';
		} else 
		{
			$sReturn='<li><i class="text-danger fa-li fa fa-exclamation-triangle"></i>Error - '.$this->mysqli->error.'<br/>'.$sSQL.'</li>';
		}		

		return $sReturn;
		
   } // function runSQL()
   
	/**
	* Reset : empty the table and reset the auto_increment to 1
	*/
	private function resetTable($rdTable) 
	{
	   
		$this->mysqli->query('truncate table `'.$rdTable.'`;');
		$this->mysqli->query('alter table `'.$rdTable.'` AUTO_INCREMENT = 1');
		
		return true;
		
	} // function resetTable()

	/**
	 * Helper. Implodes an array into a list of fields and a list of values.
	 */
    private function keyToSQL($arrKeys) 
    {
	   	
		$fields='';
		$values='';
		foreach ($arrKeys as $name=>$value) {
			$fields.='`'.$name.'`, ';
			$values.=($value==''?'""':$value).' as `'.$name.'`, ';
		}
		$fields=rtrim($fields,', ');
		$values=rtrim($values,', ');
		
		return array($fields, $values);
		
	} // function keyToSQL()
   
	/**
	 * Start the migration; get records from CBSubs and insert them into RD-Subs tables
	 */
	public function startMigration($CB_Subs_ID, $RD_Subs_ID) 
	{		
		$sReturn='';
		
		if ($this->mysqli===null)
		{
			echo '<p class="text-warning error">Please put this script in the same folder of your Joomla\'s <em>configuration.php</em> file i.e. in the root folder of your website.</p>';
			die();
		} 
		
		// Get if the plan is a usersubscription or a merchandise (a product; one-time fee)
				
		$sSQL = 'SELECT item_type FROM '.$this->JConfig->dbprefix.'cbsubs_plans WHERE ID='.(int)$CB_Subs_ID.';';

		if ($result = $this->mysqli->query($sSQL)) 
		{	
			$arr=$result->fetch_array(MYSQLI_ASSOC);
			$sCBSubsItemType=isset($arr['item_type']) ? $arr['item_type'] : 'unknown';
		}
		
		if ($sCBSubsItemType=='unknown') 
		{
			die('Invalid CBSubs item_type');
		}	
				
		// -----------------------------------------------------------------
		// 1. Migrate records of #__cbsubs_payment_items i.e. get transactions
		// -----------------------------------------------------------------
					
		$rdTable=$this->JConfig->dbprefix.'rd_subs_transactions';
	/*	
		if($sCBSubsItemType!=='merchandise')
		{
			// It's a subscription (a "usersubscription")
			
			list($fields, $values) = $this->keyToSQL(array(
				'transaction_id' => 'Items.payment_basket_id ',
				'userid' => 'CBSubs.user_id',
				'details' => 'Items.artnum',
				'amount' => 'Items.rate',
				'date' => 'Items.start_date',
				'type' => 'Baskets.txn_type',
				'email_paypal' => 'Baskets.payer_email',
				'ordercode' => 'Baskets.id',                   // Link with cbsubs_payment_baskets.id
				'plugin' => '"CBSubs"'                        // Specify CBSubs to make intuitive the origin of the record
			));	
						
			$sSQL='insert into `'.$rdTable.'` ('.$fields.') select distinct '.$values.' '.
				'from `'.$this->JConfig->dbprefix.'cbsubs_subscriptions` CBSubs '.
					'inner join `'.$this->JConfig->dbprefix.'cbsubs_payment_items` Items on CBSubs.id = Items.subscription_id '.
					'inner join `'.$this->JConfig->dbprefix.'cbsubs_payment_baskets` Baskets on Items.payment_basket_id = Baskets.id '.
				'where (CBSubs.`plan_id` = '.(int)$CB_Subs_ID.') AND (Baskets.user_id=CBSubs.user_id) AND '.
					// Use a NOT IN criteria to be sure to add this record only once even if the script is started several times
					'(Items.payment_basket_id NOT IN (SELECT transaction_id FROM `'.$rdTable.'`));';
		} else {
			*/
			// It's a product (a "merchandise")
			
			list($fields, $values) = $this->keyToSQL(array(
				'transaction_id' => 'Items.payment_basket_id ',
				'userid' => 'Baskets.user_id',
				'details' => 'Items.artnum',
				'amount' => 'Items.rate',
				'date' => 'Items.start_date',
				'type' => 'Baskets.txn_type',
				'email_paypal' => 'Baskets.payer_email',
				'ordercode' => 'Baskets.id',                   // Link with cbsubs_payment_baskets.id
				'plugin' => '"CBSubs"'                        // Specify CBSubs to make intuitive the origin of the record
			));	
			
			$sSQL='insert into `'.$rdTable.'` ('.$fields.') select distinct '.$values.' '.
				'from `'.$this->JConfig->dbprefix.'cbsubs_payment_items` Items '.
					'inner join `'.$this->JConfig->dbprefix.'cbsubs_payment_baskets` Baskets on Items.payment_basket_id = Baskets.id '.
				'where (Items.`plan_id` = '.(int)$CB_Subs_ID.') AND '.
					// Use a NOT IN criteria to be sure to add this record only once even if the script is started several times
					'(Items.payment_basket_id NOT IN (SELECT transaction_id FROM `'.$rdTable.'`));';
			
		//}
			
		$sReturn.=$this->runSQL($sSQL, 'Transactions for plan '.(int)$CB_Subs_ID.' are ', $rdTable, 
			(DEBUG&&($this->DebugUser_ID!='')?'(Baskets.user_id='.$this->DebugUser_ID.')':''));		

		// -----------------------------------------------------------------
		// 2. Migrate records of #_cbsubs_subscriptions i.e. get subscriptions
		// -----------------------------------------------------------------
		
		$rdTable=$this->JConfig->dbprefix.'rd_subs_product2user';
		
		if($sCBSubsItemType!=='merchandise')
		{
			// It's a subscription (a "usersubscription")
			// We need to correctly handle dates (start and end date of the subscription)
			
			list($fields, $values) = $this->keyToSQL(array(		
				'userid' => 'CBSubs.user_id',
				'payment_id' => 'T.id',
				'product_id' => (int)$RD_Subs_ID,
				'download_id' => '0',
				'status' => 'case when (Items.stop_date < NOW()) then 0 else 1 end',
				'valid_to' => 'CBSubs.expiry_date', //'case when (stop_date=null) then start_date else stop_date end',
				'created' => 'CBSubs.subscription_date',// 'cast(Items.start_date as date)',
				'extra_information' => 'Items.artnum',                           // CBSubs invoice number
				'private_information' => 'concat_ws(\' \',\'Record migrated from CB Subs, order\',Items.artnum)',		
				'reminder_sent' => '0',
				'ordercode' => 'Items.payment_basket_id',
				'order_id' => '0',
				'ordercount' => '1'
			));	
			
			$sSQL='insert into `'.$rdTable.'` ('.$fields.') select distinct '.$values.' '.
				'from `'.$this->JConfig->dbprefix.'cbsubs_subscriptions` CBSubs '.
					'inner join `'.$this->JConfig->dbprefix.'cbsubs_payment_items` Items on CBSubs.id = Items.subscription_id '.
					'inner join `'.$this->JConfig->dbprefix.'rd_subs_transactions` T on T.ordercode = Items.payment_basket_id '.
				'where (CBSubs.`plan_id` = '.(int)$CB_Subs_ID.') AND (Items.item_type="'.$sCBSubsItemType.'");';
				
		} else // if($sCBSubsItemType!=='merchandise')
		{
			
			// It's a product (a "merchandise"); one-time fee.   A product don't have expiration, just like buying a 
			// service (fi.i. unhacking of a website)
			
			list($fields, $values) = $this->keyToSQL(array(		
				'userid' => 'Baskets.user_id',
				'payment_id' => 'T.id',
				'product_id' => (int)$RD_Subs_ID,
				'download_id' => '0',
				'status' => 'case when (Items.start_date < NOW()) then 0 else 1 end',  // Not really usefull, it's a product
				'valid_to' => 'Items.start_date', 
				'created' => 'Items.start_date',
				'extra_information' => 'Items.artnum',                           
				'private_information' => 'concat_ws(\' \',\'Record migrated from CB Subs, order\',Items.artnum)',		
				'reminder_sent' => '0',
				'ordercode' => 'Items.payment_basket_id',
				'order_id' => '0',
				'ordercount' => '1'
			));	
			
			$sSQL='insert into `'.$rdTable.'` ('.$fields.') select distinct '.$values.' '.
				'from `'.$this->JConfig->dbprefix.'cbsubs_payment_items` Items '.
					'inner join `'.$this->JConfig->dbprefix.'cbsubs_payment_baskets` Baskets on Items.payment_basket_id = Baskets.id '.
					'inner join `'.$this->JConfig->dbprefix.'rd_subs_transactions` T on T.ordercode = Items.payment_basket_id '.
				'where (Items.`plan_id` = '.(int)$CB_Subs_ID.') AND (Items.item_type="'.$sCBSubsItemType.'");';
				
		} // if($sCBSubsItemType!=='merchandise')
		
		$sReturn.=$this->runSQL($sSQL, 'Subscriptions', $rdTable, '');							

		// -----------------------------------------------------------------
		// 3. Migrate records of #_comprofiler i.e. get users	
		//	  Migrate all users and not only the ones concerned by the subscription	(CB_Subs_ID)
		// -----------------------------------------------------------------
		
		list($fields, $values) = $this->keyToSQL(array(
			'userid' => 'CBSubs.user_id',
			'firstname' => 'if(CB.cb_subs_inv_first_name is null, if(CB.firstname is null, "", CB.firstname), CB.cb_subs_inv_first_name)',
			'lastname' => 'if(CB.cb_subs_inv_last_name is null, if(CB.lastname is null, "", CB.lastname), CB.cb_subs_inv_last_name)',
			'address1' => 'if(cb_subs_inv_address_street is null, P.address_street, cb_subs_inv_address_street)',
			'address2' => '',
			'zipcode' => 'if(CB.cb_subs_inv_address_zip is null, P.address_zip, CB.cb_subs_inv_address_zip)',
			'city' => 'if(CB.cb_subs_inv_address_city is null, P.address_city, CB.cb_subs_inv_address_city)',
			'country' => 'C.id',
			'phone' => 'if(CB.cb_subs_inv_contact_phone is null, "", replace(replace(replace(CB.cb_subs_inv_contact_phone," ",""),".",""),"-",""))',
			'mobile' => '',
			'is_business' => 'if(CB.cb_subs_inv_vat_number is null, 0, 1)',
			'companyname' => 'if(CB.cb_subs_inv_payer_business_name is null, if(P.payer_business_name is null, "", P.payer_business_name), CB.cb_subs_inv_payer_business_name)',
			'vatnumber' => 'if(CB.cb_subs_inv_vat_number is null, "", upper(replace(replace(replace(CB.cb_subs_inv_vat_number," ",""),".",""),"-","")))',
			'vat_validation_result' => '1',         // ######## VOIR CHAMPS VAT_VERIFICATION DANS betanew_cbsubs_payment_baskets
			'vat_validation_date' => 'now()',      // "status":"VALID" et oussi "time"
			'newsletter' => 1, 
			'profile_image' => 'if(CB.avatar is null, "", CB.avatar)',
			'ip_address' => 'registeripaddr',
			'watchful_key' => '',
			'published' => 1
		));	
				
		$rdTable=$this->JConfig->dbprefix.'rd_subs_users';
		
		$sSQL='insert into `'.$rdTable.'` ('.$fields.') select distinct '.$values.' '.
			'from `'.$this->JConfig->dbprefix.'cbsubs_subscriptions` CBSubs '.
				'inner join `'.$this->JConfig->dbprefix.'users` U on CBSubs.user_id = U.id '.
				'inner join `'.$this->JConfig->dbprefix.'rd_subs_product2user` R on CBSubs.user_id = R.userid '.
				'inner join `'.$this->JConfig->dbprefix.'comprofiler` CB on CBSubs.user_id = CB.user_id '.
				'inner join `'.$this->JConfig->dbprefix.'cbsubs_payments` P ON (U.id = P.for_user_id) OR IsNull(P.for_user_id) '.
				'inner join `'.$this->JConfig->dbprefix.'rd_subs_countries` C ON (P.address_country_code = C.country_2_code) '.
			// Use a NOT IN criteria to be sure to add this record only once even if the script is started several times
			'WHERE (CBSubs.user_id not in (SELECT userid FROM `'.$rdTable.'`))';

		$sReturn.=$this->runSQL($sSQL, 'Users', $rdTable, '');	

		// -----------------------------------------------------------------
		// 4. Migrate records of #__cbsubs_payment_items i.e. get orders
		// -----------------------------------------------------------------
		
		list($fields, $values) = $this->keyToSQL(array(		
			'date' => 'start_date',
			'ordercode' => 'Items.payment_basket_id',
			'product_id' => (int)$RD_Subs_ID,
			'coupon_code' => '',
			'userid' => 'Baskets.user_id',
			'countrycode' => 'C.id',
			'ip' => 'Baskets.ip_addresses',
			'price' => 'rate',
			'amount' => '0',
			'discount' => '0',
			'discount_group' => '',
			'discount_type' => '',
			'discount_amount' => 'if(Items.discount_amount is null, 0, Items.discount_amount)'
		));	
		
		$rdTable=$this->JConfig->dbprefix.'rd_subs_orders';
		
		$sSQL='insert into `'.$rdTable.'` ('.$fields.') select distinct '.$values.' '.
			'from `'.$this->JConfig->dbprefix.'cbsubs_payment_items` Items '.				
				'inner join `'.$this->JConfig->dbprefix.'cbsubs_payment_baskets` Baskets on Items.payment_basket_id = Baskets.id '.
				'inner join `'.$this->JConfig->dbprefix.'rd_subs_countries` C ON (Baskets.residence_country = C.country_2_code) '.
			'where (Baskets.user_id=Baskets.user_id);';	
		
		$sReturn.=$this->runSQL($sSQL, 'Orders', $rdTable, (DEBUG&&($this->DebugUser_ID!='')?'(Baskets.user_id='.$this->DebugUser_ID.')':''), 'ORDER BY Items.start_date');		

		$this->mysqli->close();
		return '<ul class="fa-ul">'.$sReturn.'</ul>';	
		
	} // function getProduct()
   
} // class aeSecureMigrate

if (DEBUG===true) {
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

$task=aeSecureFct::getParam('task', 'string', '', false);

$CB_Subs_ID=abs(aeSecureFct::getParam('cbSubs', 'int', 0, false));
$RD_Subs_ID=abs(aeSecureFct::getParam('rdSubs', 'int', 0, false));

$aeSMigrate=new aeSecureMigrate();

switch ($task) 
{
	
	case 'debug':
	
		echo $aeSMigrate->debug();
		die();
		break;
		
	case 'empty':
	
		echo $aeSMigrate->emptyTables();
		die();
		break;
		
	case 'getList':
	
		if ($CB_Subs_ID>0) 
		{
			echo $aeSMigrate->showTable($CB_Subs_ID);
			die();
		} else {
			die('cbSubs parameter is missing');
		}
	
		break;
		
	case 'getPlan':
	
		if ($CB_Subs_ID>0) 
		{			
			echo $aeSMigrate->getPlan($CB_Subs_ID);
			die();
		} else {
			die('cbSubs parameter is missing');
		}
		
		break;
		
	case 'getProduct':
	
		if ($RD_Subs_ID>0) 
		{			
			echo $aeSMigrate->getProduct($RD_Subs_ID);
			die();
		} else {
			die('rdSubs parameter is missing');
		}
		
		break;
		
	case 'doIt':
	
		if (($CB_Subs_ID>0) && ($RD_Subs_ID>0))
		{			
			echo $aeSMigrate->startMigration($CB_Subs_ID, $RD_Subs_ID);
			die();
		} else {
			die('cbSubs and rdSubs parameters are missing');
		}
		
		break;
		
	case 'killMe':
	
		echo '<p class="text-success">The file '.__FILE__.' has been removed from your server</p>';
		unlink(__FILE__);
		
		die();
		
} // case

unset($aeSMigrate);

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
		<link href= "data:image/x-icon;base64,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" rel="shortcut icon" type="image/vnd.microsoft.icon"/>  
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
				margin-right: 20px;				background-image: url('data:image/gif;base64,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AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
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
			#rdLabel {
				padding-left: 25px;
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
		
            <div class="page-header"><h1>From CB Subscriptions to RD-Subs</h1></div>

			<div class="alert alert-danger alert-dismissible" role="alert">
				<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<strong>Be aware !</strong>&nbsp;This script will add records to : #_rd_subs_orders, #_rd_subs_product2user, #_rd_subs_transactions and #_rd_subs_users. <strong>No backup is taken by this script so, please, know what you're doing (and at least, take a backup before)</strong>
			</div>
			
			<div class="alert alert-info alert-dismissible" role="alert">
				<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<strong>No support provided</strong>&nbsp;Please take a look on the startMigration() function of this script to be sure that the script do what you're expecting.</strong>
			</div>
			
			<ul class="fa-ul">
				<li><i class="fa-li fa fa-check"></i>1. Type your CB Subs plan ID and click on the <i class="fa fa-shopping-cart" aria-hidden="true"></i> button. If your plan name is the good one, click on the <strong>1. Show customers</strong> button. Verify if concerned records are the good ones.</li>
				<li><i class="fa-li fa fa-check"></i>2. Type your RD-Subs product ID and click on the <i class="fa fa-shopping-cart" aria-hidden="true"></i> button. If your product name is the good one, press the <strong>2. Migrate to RD-Subs</strong> button.</li>
				<li><i class="fa-li fa fa-check"></i>3. Repeat for each plans to migrate and press the <strong>3. Remove this script </strong> button when finished.</li>
			</ul>
			<hr/>
         
            <form id="form" class="form-inline">  
					
				<div class="form-group">
					<label for="cbSubs">CB Subs plan ID</label>&nbsp;
					<input id="cbSubs" value="8" size="5" width="5" class="form-control" placeholder="#CB">&nbsp;&nbsp;&nbsp;<button type="button" id="btnGetCBPlan" class="btn btn-default"><i class="fa fa-shopping-cart" aria-hidden="true"></i></button>&nbsp;
					<span class="text-success" id="PlanName">&nbsp;</span>
					<label id="rdLabel" for="rbSubs">RD-Subs product ID</label>&nbsp;
					<input disabled="disabled" size="5" width="5" id="rdSubs" class="form-control" placeholder="#RD">
					<button disabled="disabled" type="button" id="btnGetRDProduct" class="btn btn-default"><i class="fa fa-shopping-cart" aria-hidden="true"></i></button>&nbsp;
					<span class="text-success" id="ProductName">&nbsp;</span>
				</div>
				<hr/>
                <div class="row"> 
                    <button type="button" disabled="disabled" id="btnGetList" class="btn btn-primary"><i class="fa fa-refresh" aria-hidden="true"></i>&nbsp;1. Show customers</button>
                    <button type="button" disabled="disabled" id="btnDoIt" class="btn btn-success"><i class="fa fa-trash-o" aria-hidden="true"></i>&nbsp;2. Migrate to RD-Subs</button>
                    <button type="button" id="btnKillMe" class="btn btn-danger pull-right" style="margin-left:10px;"><i class="fa fa-eraser" aria-hidden="true"></i>&nbsp;3. Remove this script</button>
					<?php 
						if (DEBUG) {
							echo '<button type="button" id="btnDebug" class="btn btn-warning pull-right" style="margin-left:10px;"><i class="fa fa-bug" aria-hidden="true"></i>&nbsp;DEBUG</button>';
							echo '<button type="button" id="btnEmpty" class="btn btn-primary pull-right" style="margin-left:10px;"><i class="fa fa-bug" aria-hidden="true"></i>&nbsp;Empty RD tables</button>';
							echo '<button type="button" id="btnaeSecure" class="btn btn-primary pull-right" style="margin-left:10px;"><i class="fa fa-bug" aria-hidden="true"></i>&nbsp;aeSecure</button>';
						} 
					?>
                </div>     
            </form>

            <div id="Result">&nbsp;</div>
            
        </div>
       
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
			 * aeSecure automatisation
			 */
			$('#btnaeSecure').click(function(e)  { 
				
				e.stopImmediatePropagation(); 
				
				// PRO
				$('#cbSubs').val(8);
				$('#btnGetList').prop("disabled", false).removeClass("hidden").click();
				$('#rdSubs').prop("disabled", false).val(6);
				$('#btnDoIt').prop("disabled", false).removeClass("hidden").click();
				
				var $html = $html + $('#Result').html();
				
				// Premium+
				$('#cbSubs').val(2);
				$('#btnGetList').prop("disabled", false).removeClass("hidden").click();
				$('#rdSubs').prop("disabled", false).val(5);
				$('#btnDoIt').prop("disabled", false).removeClass("hidden").click();

				var $html = $html + $('#Result').html();
				
				// Premium
				$('#cbSubs').val(1);
				$('#btnGetList').prop("disabled", false).removeClass("hidden").click();
				$('#rdSubs').prop("disabled", false).val(4);
				$('#btnDoIt').prop("disabled", false).removeClass("hidden").click();

				var $html = $html + $('#Result').html();
				
				$('#Result').html($html);
				

			}); // $('#btnDebug').click()
			
			/*
			 * Debug, display tables
			 */
			$('#btnDebug').click(function(e)  { 
				
				e.stopImmediatePropagation(); 

				var $data = new Object;
				$data.task = "debug";

				$.ajax({
					beforeSend: function() 
					{
						$('#Result').html('<div><span class="ajax_loading">&nbsp;</span><span style="font-style:italic;font-size:1.5em;">Please wait...</span></div>');
					},// beforeSend()
					async:true,
					type:"POST",
					url: "<?php echo basename(__FILE__); ?>",
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
					url: "<?php echo basename(__FILE__); ?>",
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
					url: "<?php echo basename(__FILE__); ?>",
					data:$data,
					datatype:"html",
					success: function (data) 
					{ 
						$('#Result').html(data);    
						$('#rdSubs').prop("disabled", false).select();
						$('#btnGetProduct').prop("disabled", false);				  
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
						6: {sorter: "digit"}   // Number of records
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
					url: "<?php echo basename(__FILE__); ?>",
					data:$data,
					datatype:"html",
					success: function (data) { 
						$('#PlanName').html(data);
						if(data!=='unknown') 
						{
							$('#btnGetList').prop("disabled", false).removeClass("hidden");  
						} else {
							$('#btnGetList').prop("disabled", true).addClass("hidden");  
						}
					}
				}); // $.ajax() 			
			}); // $('#btnGetCBPlan').click() 
			
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
					url: "<?php echo basename(__FILE__); ?>",
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
					url: "<?php echo basename(__FILE__); ?>",
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
						$url='<?php echo basename(__FILE__); ?>?'+$data.toString();
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
				  url: "<?php echo basename(__FILE__); ?>",
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
