<?php
/**
 * Primary class for Visitor Audit
 *
 * @version 1.0.0
 * @author Justin Campo <admin@visitoraudit.com>
 * @license GPL2
 */
namespace Visitor_Audit;

/**
 * This class handles all the logic that runs the Visitor Audit system.
 * In approximite order it does the following tasks:
 * 
 * 1) Initializes benchmark system
 * 2) Loads configurations
 * 3) Handles database maintence
 * 4) Blocks banned users
 * 5) Creates/Links visitor session data
 * 6) Stores visitor page data
 * 7) Conducts slow page analysis
 * 8) Conducts security audit
 * 9) Finalizes database maintence
 */	
class Visitor_Audit extends \Visitor_Audit\Visitor_Audit_Setup
{
    /** @var object Stores local database object */
    protected $db;
    /** @var string Store database timestamp value (result of NOW()) */
    protected $db_timestamp;
    /** @var float The current version of the plugin */
    protected $version;
    /** @var int The visitor_audit_id assigned to the visitor */
    public $id = 0;
    /** @var string The visitors ip address */
    private $ip_address = 0;
    /** @var string The visitors forwarded ip address */
    private $ip_forwarded = 0;
    /** @var timestamp The starting time when the page is loaded */
    private $benchmark = 0;
    /** @var boolean Tracks status of current visitor (true = banned) */
    private $banned = false;
    /** $var boolean The status of the plugin (true = enabled */
    private $status = true;
    
    /** 
     * Constructor for Visitor_Audit plugin class.
     * Loads all required data for class to operate.
     *
     * @todo Explore ways to avoid the timestamp db query - user config value created on activation perhaps? 
     * @return void
     */
    public function __construct()
    {
        $this->benchmark = microtime(true);
        $this->db = $GLOBALS['wpdb'];
        $db_timestamp = $this->db->get_row("SELECT NOW() as timestamp;");
        $this->db_timestamp = $db_timestamp->timestamp; //current_time() relies on server time
        $this->version = get_option('visitor_audit_version', '1.0');
        $this->ip_address = $this->ip_address();
        $this->ip_forwarded = $this->ip_forwarded();
        $this->config();
    }
    
    /**
     * Sets the status (if the system runs) for the plugin
     *
     * @param boolean What to set the status of the system to
     * @return void
     */
    public function admin()
    {
        if (current_user_can('manage_options')){
            $this->status = false;
        }
    }
    
    /**
     * Initializes the Visitor Audit system.
     * Blocks all banned visitors then assigns all remaining visitors an id
     * 
     * @return void
     */
    public function init()
    {
        $this->database_maintenance("banned");
        $this->banned();
    }
    
    /**
     * Loads in the user defined configuration variables.
     * This will overwrite the default configuration assigned within visitor-audit.config.php
     * 
     * @return void
     */
    public function config()
    {
        foreach($this->config_valid as $c) {
            $this->$c = get_option("visitor_audit_".$c);
        }
    }
	
    /**
     * Handles clearing out all stale data.
     *
     * @todo Explore setting up crons to prevent this from having to be run during pageload
     *
     * @param string $mode Which database queries to perform (all/banned/audit)
     * @return void
     */
    private function database_maintenance($mode = "all")
    {
        if ($this->banned_retention > 0 AND ($mode == "all" || $mode == "banned")){
            $sql_visitor_maintenance = "DELETE FROM " . $this->db->prefix . "visitor_audit_banned WHERE visitor_audit_banned_type = 0 AND visitor_audit_banned_timestamp < DATE_SUB(NOW(), INTERVAL ".$this->banned_retention." MINUTE)";
            $this->db->query($sql_visitor_maintenance);
        }
        if ($this->history_retention > 0 AND ($mode == "all" || $mode == "audit")){
            $sql_visitor_maintenance = "DELETE FROM " . $this->db->prefix . "visitor_audit WHERE visitor_audit_timestamp < DATE_SUB(NOW(), INTERVAL ".$this->history_retention." MINUTE)";
            $this->db->query($sql_visitor_maintenance);
            $sql_visitor_maintenance = "DELETE FROM " . $this->db->prefix . "visitor_audit_history WHERE visitor_audit_history_timestamp < DATE_SUB(NOW(), INTERVAL ".$this->history_retention." MINUTE)";
            $this->db->query($sql_visitor_maintenance);
        }
    }
	
    /**
     * Handles the database interaction for banned visitors (visitor_audit_banned)
     * 
     * @param boolean $insert_ip Insert the current visitor into banned list
     * @param int $type Which type of ban is being performed (0 = standard, 1 = forever)
     * @param boolean $implement_ban Should a block be performed immediately?
     * @return void
     */
    private function banned($insert_ip = false, $type = 0, $implement_ban = true)
    {
        $banned_status = false;
        if ($insert_ip){
            //add visitor to block list
            $banned_status = true;
            $query_check = $this->db->prepare("SELECT * FROM ". $this->db->prefix . "visitor_audit_banned
                WHERE visitor_audit_banned_ip = %s AND visitor_audit_banned_ip_forwarded = %s LIMIT 1", $this->ip_address, $this->ip_forwarded);		
            $result_check = $this->db->get_results($query_check);
            if (empty($result_check[0]->visitor_audit_banned_ip)){
                $insert = array();
                $insert["visitor_audit_banned_type"] = $type;
                $insert["visitor_audit_banned_ip"] = $this->ip_address;
                $insert["visitor_audit_banned_ip_forwarded"] = $this->ip_forwarded;
                $this->db->insert($this->db->prefix . "visitor_audit_banned", $insert);
            }
        } else {
            //check if in banned database
            $query = $this->db->prepare("SELECT visitor_audit_banned_ip, visitor_audit_banned_type FROM ". $this->db->prefix . "visitor_audit_banned
                WHERE visitor_audit_banned_ip = %s AND visitor_audit_banned_ip_forwarded = %s LIMIT 1", $this->ip_address, $this->ip_forwarded);		
            $result = $this->db->get_results($query);
            if (!empty($result[0]->visitor_audit_banned_ip)){
                $banned_status = true;
                if ($result[0]->visitor_audit_banned_type == 1){ $this->banned_retention = 0; } //indefinite
            }
        }
        //if visitor meets critiria block them
        if ($implement_ban AND $banned_status){
            $this->banned = true;
            //if a user returns to the site during their ban reset their timestamp
            if ($this->visitor_extends_ban){
                $this->db->update($this->db->prefix . "visitor_audit_banned",
                    array("visitor_audit_banned_timestamp" => $this->db_timestamp),
                    array("visitor_audit_banned_ip" => $this->ip_address, "visitor_audit_banned_ip_forwarded" => $this->ip_forwarded));
            }
            $this->banned_execute($this->ban_method, $this->banned_retention, $this->visitor_extends_ban);
        }
    }
	
    /**
     * Enacts blocking mechinism upon the visitor
     *
     * @todo Explore server level blocking (CSF) to save resources
     * @todo Setup redirect ban method
     * 
     * @param string $method Which approach to blocking the visitor with be executed (message, exit, sleep, redirect)
     * @param int $retention_period How long until the visitor
     * @param boolean $extend_ban If the visitor returns during the banned time period does their ban get extended
     * @return void
     */
    private function banned_execute($method, $retention_period = 0, $extend_ban = false)
    {	
        if ($method == "slow"){
            sleep(1);
        } else if ($method == "sleep"){
            sleep(3600);
            exit();
        } else if ($method == "message"){
            echo "Unfortunately our security system has determined your behavior to be malicious.";
            if ($retention_period > 0){
                echo "  Please return in " . $retention_period . " minutes.";
                if ($extend_ban){ echo "  Returning to the site before this time is completed will reset your ban timer."; }
            }
            exit();
        } else {
            exit();
            /**
            if(!headers_sent()) {
                header('Location: '.$method);
                exit();
            } else {
                echo '<script type="text/javascript">';
                echo 'window.location.href="'.$method.'";';
                echo '</script>';
                echo '<noscript>';
                echo '<meta http-equiv="refresh" content="0;url='.$method.'" />';
                echo '</noscript>';
                exit();
            }
            **/
        }
    }
	
    /**
     * Checks in database if visitor already has a Visitor Audit session established
     * 
     * @return boolean
     */
    private function exist()
    {
        $query = $this->db->prepare("SELECT * FROM ". $this->db->prefix . "visitor_audit
            WHERE visitor_audit_ip = %s AND visitor_audit_ip_forwarded = %s LIMIT 1", $this->ip_address, $this->ip_forwarded);		
        $result = $this->db->get_results($query);
        if (!empty($result[0]->visitor_audit_id)){
            $this->id = $result[0]->visitor_audit_id;
            return true;
        } else {
            return false;
        }
    }
	
    /**
     * Creates an entry in visitor_audit table for visitor tracking
     * 
     * @return void
     */
    private function create()
    {
        $insert = array();
        $insert["visitor_audit_ip"] = $this->ip_address;
        $insert["visitor_audit_ip_forwarded"] = $this->ip_forwarded;		
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $insert["visitor_audit_useragent"] = preg_replace("/[^a-zA-Z0-9-_. ]/", "", $_SERVER['HTTP_USER_AGENT']);
        }		
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $insert["visitor_audit_referer"] = preg_replace("/[^a-zA-Z0-9-_. ]/", "", $_SERVER['HTTP_REFERER']);
        }			
        $this->db->insert($this->db->prefix . "visitor_audit", $insert);
        $this->id = $this->db->insert_id;
    }

    /**
     * Added entry to visitor_audit_history which indicates a visitor has performed an
     * action that should be monitored (ie. page load)
     * 
     * @return void
     */
    public function log()
    {
        if ($this->status AND $this->banned == false){
            $this->exist();
            if ($this->id == 0){ $this->create(); }            
            if ($this->id != 0){
                $insert = array();				
                $insert["visitor_audit_id"] = $this->id;
                $insert["visitor_audit_history_url"] = $_SERVER["REQUEST_URI"];
                if (!empty($_SERVER["QUERY_STRING"])){ $insert["visitor_audit_history_url"] .= $_SERVER["QUERY_STRING"]; }
                $insert["visitor_audit_history_status"] = http_response_code();
                $insert["visitor_audit_history_benchmark"] = (microtime(true) - $this->benchmark)*10;
                $this->db->insert($this->db->prefix . "visitor_audit_history", $insert);
                
                $this->db->update($this->db->prefix . "visitor_audit", array("visitor_audit_timestamp" => $this->db_timestamp), array("visitor_audit_id" => $this->id));
                if ($this->slow_page_reporting){
                    $this->slow_page($insert["visitor_audit_history_url"],$insert["visitor_audit_history_benchmark"]);
                }
            }
            if ($this->automatic_banning){
                $audit_response = $this->audit("automatic");
                if ($audit_response["result"] == "fail"){
                    $this->banned(true, 0, false);
                }
            }
            $this->database_maintenance("audit");
        }
    }
	
    /**
     * Conducts all checks to see if the current url loaded slowly
     * and if it should be reported
     *
     * @param string $url The url of the current page
     * @param int $benchmark The time the current page took to load
     * @return void
     */
    public function slow_page($url, $benchmark)
    {
        if ($this->slow_page_reporting AND $this->slow_page_time_limit > 0){
            if ($benchmark > $this->slow_page_time_limit){
                if (count($this->slow_page_exclusions)){
                    if (!in_array($url, $this->slow_page_exclusions)){
                        $this->slow_page_email($url, $benchmark);
                    }
                } else {
                    $this->slow_page_email($url, $benchmark);
                }
            }	
        }
    }
	
    /**
     * Sends out an slow page report email to the admin of the site
     * 
     * @param string $url The url of the current page
     * @param int $benchmark The time the current page took to load
     * @return void
     */
    public function slow_page_email($url, $benchmark)
    {
        if (!empty($this->slow_page_email) AND !empty($this->slow_page_email_system)){
            $subject = 'Slow Page Report';
            $message = 'URL : ' . $url. ' loaded in ' . $benchmark . ' seconds' . "\n\n";
            $message .= 'Your system is setup to report pages that take longer then ' . $this->slow_page_time_limit . ' to load';
            $headers = 'From: ' . $this->slow_page_email_system . "\r\n" .
                'Reply-To: ' . $this->slow_page_email_system . "\r\n" .
                'X-Mailer: PHP/' . phpversion();			
            mail($this->slow_page_email, $subject, $message, $headers);
        }
    }
	
    /**
     * Consolidated all visitor historical data to determine if their behavior is suspicious.
     * Handles all audit database interaction
     *
     * @param string $mode Is this audit done via the admin area (manual) or via pageload (automatic)
     * @return array The result of the audit and statistics from it.
     */
    public function audit($mode = "manual")
    {
        $audit_status = array();
        $audit_status["result"] = "pass";
        $audit_status["count_history"] = 0;
        $audit_status["count_error"] = 0;
        if ($this->id != 0 AND ($this->page_views_per_minute != 0 || $this->error_status_per_minute != 0)){
            //get all required audit history for visitor
            $timestamp_start = substr($this->db_timestamp, 0, 17)."00";					
            if ($mode == "automatic"){
                $sql_history = "SELECT visitor_audit_history_timestamp,visitor_audit_history_status FROM ". $this->db->prefix . "visitor_audit_history";
                $sql_history .= " WHERE visitor_audit_id = %s AND visitor_audit_history_analyzed = 0 AND visitor_audit_history_timestamp < '".$timestamp_start."'";
            } else {
                $sql_history = "SELECT visitor_audit_history_timestamp,visitor_audit_history_status FROM ". $this->db->prefix . "visitor_audit_history";
                $sql_history .= " WHERE visitor_audit_id = %s AND visitor_audit_history_timestamp < '".$timestamp_start."'";
            }
            $query = $this->db->prepare($sql_history, $this->id);
            $result = $this->db->get_results($query);			
            if (isset($result) AND is_array($result) AND count($result)){
                //update history entries so we don't auto process same history twice
                if ($mode != "manual" AND $this->automatic_banning){
                    $sql_update_history = "UPDATE ". $this->db->prefix . "visitor_audit_history SET visitor_audit_history_analyzed = 1";
                    $sql_update_history .= " WHERE visitor_audit_id = %s AND visitor_audit_history_analyzed = 0 AND visitor_audit_history_timestamp < '".$timestamp_start."'";
                    $query = $this->db->prepare($sql_update_history, $this->id);		
                    $this->db->query($query);
                }
                $minute_current = 0;
                $minute_prior = 0;
                $count_history = 0;
                $count_error = 0;
                //loop through each history entry
                foreach ($result as $history){
                    //determine what minute we are in history
                    $minute_current = substr($history->visitor_audit_history_timestamp, 14, 2);
                    //if we are on a new history check status of audit
                    if ($minute_prior == 0){
                        $minute_prior = $minute_current;
                    } else if ($minute_current != $minute_prior){
                        //perform audit checks
                        if ($this->audit_calculations($count_history, $count_error)){ $audit_status["result"] = "fail"; }
                        if ($count_history > $audit_status["count_history"]){ $audit_status["count_history"] = $count_history; }
                        if ($count_error > $audit_status["count_error"]){ $audit_status["count_error"] = $count_error; }
                        //reset counters
                        $count_history = 0;
                        $count_error = 0;
                        $minute_prior = $minute_current;		
                    }
                    //add history record to counter
                    $count_history++;
                    if ($history->visitor_audit_history_status != 200){
                        $count_error++;
                    }
                }
                //get results on last minute of history
                if ($this->audit_calculations($count_history, $count_error)){ $audit_status["result"] = "fail"; }
                if ($count_history > $audit_status["count_history"]){ $audit_status["count_history"] = $count_history; }
                if ($count_error > $audit_status["count_error"]){ $audit_status["count_error"] = $count_error; }
            }
        }
        return $audit_status;
    }
	
    /**
     * Handles all calculates to determine if visitor is considered suspicious
     * 
     * @return boolean 
     */
    private function audit_calculations($count_history, $count_error)
    {
        $ban = false;
        if ($this->page_views_per_minute != 0 AND $count_history > $this->page_views_per_minute){
            $ban = true;
        } else if ($this->error_status_per_minute != 0 AND $count_error > $this->error_status_per_minute){
            $ban = true;
        }
        return $ban;
    }
	
    /**
     * Utility method to determine visitors IP address
     * 
     * @return string IP address
     */
    private function ip_address()
    {
        if (!empty($_SERVER['REMOTE_ADDR'])){
            return preg_replace("/[^0-9:. ]/", "", $_SERVER['REMOTE_ADDR']);
        } else {
            return 0;			
        }
    }

    /**
     * Utility method to determine visitors Forwarded IP address
     * 
     * @return string IP address
     */
    private function ip_forwarded()
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
            return preg_replace("/[^0-9:. ]/", "", $_SERVER['HTTP_X_FORWARDED_FOR']);
        } else {
            return 0;			
        }
    }
}
