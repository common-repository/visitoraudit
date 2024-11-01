<?php
/**
 * Admin class for Visitor Audit
 *
 * @version 1.0.0
 * @author Justin Campo <admin@visitoraudit.com>
 * @license GPL2
 */
namespace Visitor_Audit;
/**
 * This class stores all methods for running the
 * Visitor Audit administration area
 */	
class Vistor_Audit_Admin extends \Visitor_Audit\Vistor_Audit_Config
{
     /** @var object Stores local database object */
    protected $db;
    /** @var string Store database timestamp value (result of NOW()) */
    protected $db_timestamp;
    /** @var object Stores the primary Visitor Audit class */
    protected $system;
    
    /** 
     * Constructor for Visitor_Audit_Admin plugin class.
     * Loads all required data for class to operate.
     *
     * @todo Explore ways to avoid the timestamp db query - user config value created on activation perhaps?
     * @param object The primary visitor audit system (so audits can be performed within admin area)
     * @return void
     */
    public function __construct($system)
    {
        $this->db = $GLOBALS['wpdb'];
	$db_timestamp = $this->db->get_row("SELECT NOW() as timestamp;");
	$this->db_timestamp = $db_timestamp->timestamp; //current_time() relies on server time
        $this->system = $system;
    }
    
    /**
     * Add menu option to dashboard.
     * 
     * @return void
     */
    public function add_menu()
    {
        add_menu_page("Visitor Audit",'Visitor Audit','manage_options','visitor_audit_admin', array($this, 'page_admin'),'dashicons-admin-users',2);
        add_submenu_page('visitor_audit_admin','Banned Visitors','Banned Visitors','manage_options','visitor_audit_banned',array($this, 'page_banned'));
        add_submenu_page('visitor_audit_admin','Configuration','Configuration','manage_options','visitor_audit_config',array($this, 'page_config'));
    }
        
    /**
     * Add javascript files (and their required variables) to the admin area
     * 
     * @return void
     */
    public function javascript()
    {
        wp_enqueue_script('ajax-script', plugins_url( '/js/visitor-audit.js', __FILE__ ), array('jquery'));
        wp_localize_script('ajax-script', 'ajax_object',array('ajax_url' => admin_url('admin-ajax.php')));
    }
	
    /**
     * Registers all the configurable settings.
     * 
     * @return void
     */
    public function settings()
    {
	foreach($this->config_valid as $c) {
	    register_setting("visitor_audit_config","visitor_audit_".$c);
        }
    }
       
    /**
     * The output of this method renders the admin page
     * 
     * @return void
     */
    public function page_admin()
    {
        $table = array();
        $table["columns"] = array("ID", "IP Address","IP Fowarded", "Last Visit", "Page/Error Per Minute", "Status", "Actions");
        $table["rows"] = array();
        $results = $this->db->get_results("SELECT * FROM ". $this->db->prefix . "visitor_audit LIMIT 1000");
        if (isset($results) AND is_array($results) AND count($results)){
            $original_id = $this->system->id;
            foreach ($results as $result){
        
                $this->system->id = $result->visitor_audit_id;
                $audit_result = $this->system->audit();
                
                $row = array();
                $row[] = $result->visitor_audit_id;
                $row[] = $result->visitor_audit_ip;
                $row[] = $result->visitor_audit_ip_forwarded;
                $row[] = $result->visitor_audit_timestamp;
                
                if (!empty($audit_result["count_history"])){ $stats = $audit_result["count_history"]."/"; }
                else { $stats = "0/"; }
                if (!empty($audit_result["count_error"])){ $stats .= $audit_result["count_error"]; }
                else { $stats .= "0"; }
                $row[] = $stats;
                
                if (!empty($audit_result["result"])){
                    if ($audit_result["result"] == "fail"){ $row[] = "FAIL"; }
                    else { $row[] = "PASS"; }
                } else {
                    $row[] = "NA";
                }
                
                $select = "<select onchange=\"visitor_audit_action(".$result->visitor_audit_id.",this.options[this.selectedIndex].value);\">";;
                $select .= "<option value=\"\" selected=\"selected\"> - Select Action - </option>";
                $select .= "<option value=\"visitor_audit_details\">View Additional Info</option>";
                $select .= "<option value=\"visitor_audit_history\">View History</option>";
                $select .= "<option value=\"visitor_audit_ban_temp\">Ban Temporarily</option>";
                $select .= "<option value=\"visitor_audit_ban_perm\">Ban Permanently</option>";
                $select .= "</select>";
                $row[] = $select;
                $table["rows"][] = $row;
            }
            $this->system->id = $original_id;
        }
        $this->table_page("Visitor Audit", $table);
        $this->modal();
    }
        
    /**
     * The output of this method renders the banned listing page
     * 
     * @return void
     */
    public function page_banned()
    {
        $table = array();
        $table["columns"] = array("ID", "IP Address", "IP Fowarded", "Banned Time", "Action");
        $table["rows"] = array();
        $results = $this->db->get_results("SELECT * FROM ". $this->db->prefix . "visitor_audit_banned LIMIT 1000");
        if (isset($results) AND is_array($results) AND count($results)){
            foreach ($results as $result){
                $row = array();
                $row[] = $result->visitor_audit_banned_id;
                $row[] = $result->visitor_audit_banned_ip;
                $row[] = $result->visitor_audit_banned_ip_forwarded;
                $row[] = $result->visitor_audit_banned_timestamp;
                $row[] = "<input type=\"button\" onclick=\"visitor_audit_action(".$result->visitor_audit_banned_id.",'visitor_audit_ban_remove')\" value=\" Delete \" class=\"button action\">";
                $table["rows"][] = $row;
            }
        }
        $this->table_page("Visitor Audit Banned Visitors", $table);
        $this->modal();
    }

    /**
     * The output of this method renders the configuration page.
     * 
     * @return void
     */
    public function page_config()
    {
        echo '<div id="visitor_audit-wrap" class="wrap">';
        echo '<h2>Visitor Audit Configuration</h2>';

        echo '<form method="post" action="options.php">';
                
        settings_fields('visitor_audit_config' );
        do_settings_sections('visitor_audit_config');
            
        echo '<table class="form-table">';
		
	$this->config_number("History Retention","visitor_audit_history_retention",
	    "How long to store visitor history for (in minutes)(0 = infinite)");
		
        $this->config_number("Banned Retention","visitor_audit_banned_retention",
	    "How long to maintain ban for (in minutes)(0 = infinite)");
		
        $this->config_number("Page Views Per Minute","visitor_audit_page_views_per_minute",
	    "How many pages per minute a visitor is allowed before being flagged suspicious (0 = infinite)");
		
        $this->config_number("Error Status Per Minute","visitor_audit_error_status_per_minute",
	    "How many error statuses per minute a visitor is allowed before being flagged suspicious (0 = infinite)");
		
	$ban_methods = Array(
	    "Slow (make visitor wait one second per page)" => "slow",
	    "Sleep (1 hour wait then die)" => "sleep",
	    "Message (Display ban message then die)" => "message",
	    "Exit (White screen then die)" => "exit"
	);
        $this->config_radio("Ban Method","visitor_audit_ban_method",$ban_methods,
	    "The method the plugin uses to stop the visitor");	
	    
	$this->config_toggle("Visitor Extends Ban","visitor_audit_visitor_extends_ban",
	    "Will the visitor be able to extend their own banned time");
	    
	$this->config_toggle("Automatic Banning","visitor_audit_automatic_banning",
	    "This system is not recommend to be enabled.  There are many situations when valid traffic".
	    "(for example search engine bots) will be banned which will be detrimental to your site.");
	    
	$this->config_toggle("Slow Page Reporting","visitor_audit_slow_page_reporting",
	    "Enable/Disable slow page reporting");
    
	$this->config_number("Page Time Limit","visitor_audit_slow_page_time_limit",
	    "How long for a page to load until slow page system triggered (in seconds)(0 = infinite)");
    
	$this->config_text("Slow Page E-Mail System","visitor_audit_slow_page_email_system",
	    "The email address the slow reports will be sent as");
		
        $this->config_text("Slow Page E-Mail","visitor_audit_slow_page_email",
	    "The email address the slow reports will be sent to");
                
        echo '</table>';

        submit_button();

        echo '</form></div>';
		
    }
    
    /**
     * The output which details modal popup on the admin page
     * Note : This is called via an ajax call
     * 
     * @return void
     */    
    public function ajax_details()
    {
        $id = 0;
        if (!empty($_REQUEST["visitor_audit_id"])){ $id = (int)$_REQUEST["visitor_audit_id"]; }
        if ($id){
            $divider = "<br/>----------------------------------------------------------------------------<br/>";
            $query = $this->db->prepare("SELECT * FROM ". $this->db->prefix . "visitor_audit WHERE visitor_audit_id = %d", $id);
            $result = $this->db->get_results($query);          
            
            $os = "";
            if(preg_match('/Linux/',$result[0]->visitor_audit_useragent)) $os = 'Linux';
            elseif(preg_match('/Win/',$result[0]->visitor_audit_useragent)) $os = 'Windows';
            elseif(preg_match('/Mac/',$result[0]->visitor_audit_useragent)) $os = 'Mac';
            
            $browser = "";
            if(preg_match('/MSIE/',$result[0]->visitor_audit_useragent)) $browser = 'Internet Explorer';
            elseif(preg_match('/Trident/',$result[0]->visitor_audit_useragent)) $browser = 'Internet Explorer';
            elseif(preg_match('/Firefox/',$result[0]->visitor_audit_useragent)) $browser = 'Mozilla Firefox';
            elseif(preg_match('/Chrome/',$result[0]->visitor_audit_useragent)) $browser = 'Google Chrome';
            elseif(preg_match('/Opera/',$result[0]->visitor_audit_useragent)) $browser = 'Opera';
            elseif(preg_match('/Safari/',$result[0]->visitor_audit_useragent)) $browser = 'Safari';
            
            $geolocation = file_get_contents("http://ipinfo.io/".$result[0]->visitor_audit_ip);
            if (!empty($geolocation)){ $geolocation = json_decode($geolocation, true); }
            
            if (!empty($result[0]->visitor_audit_id)){ echo "<strong>ID</strong> : ".$result[0]->visitor_audit_id.$divider; }
            if (!empty($result[0]->visitor_audit_ip)){ echo "<strong>IP</strong> : ".$result[0]->visitor_audit_ip.$divider; }
            if (!empty($result[0]->visitor_audit_ip_forwarded)){ echo "<strong>Forwarded IP</strong> : ".$result[0]->visitor_audit_ip_forwarded.$divider; }
            if (!empty($result[0]->visitor_audit_timestamp)){ echo "<strong>Last Visit</strong> : ".$result[0]->visitor_audit_timestamp.$divider; }
            if (!empty($os)){ echo "<strong>Operating System</strong> : ".$os.$divider; }
            if (!empty($browser)){ echo "<strong>Browser</strong> : ".$browser.$divider; }
            if (!empty($geolocation["ip"])){
                if (!empty($geolocation["city"])){ echo "<strong>City</strong> : ".$geolocation["city"]."<br>"; }
                if (!empty($geolocation["region"])){ echo "<strong>Region</strong> : ".$geolocation["region"]."<br>"; }
                if (!empty($geolocation["country"])){ echo "<strong>Country</strong> : ".$geolocation["country"]."<br>"; }
                if (!empty($geolocation["postal"])){ echo "<strong>Postal Code</strong> : ".$geolocation["postal"]."<br>"; }
                echo "----------------------------------------------------------------------------<br>";
            }
            if (!empty($geolocation["org"])){ echo "<strong>ISP</strong> : ".$geolocation["org"].$divider; }
            if (!empty($result[0]->visitor_audit_useragent)){ echo "<strong>Useragent</strong> : ".$result[0]->visitor_audit_useragent.$divider; }
            if (!empty($result[0]->visitor_audit_referer)){ echo "<strong>Referer</strong> : ".$result[0]->visitor_audit_referer; }                      
        }
        exit();
    }
    
    /**
     * The output which history modal popup on the admin page
     * Note : This is called via an ajax call
     * 
     * @return void
     */      
    public function ajax_history()
    {
        $id = 0;
        if (!empty($_REQUEST["visitor_audit_id"])){ $id = (int)$_REQUEST["visitor_audit_id"]; }
        if ($id){
            $divider = "----------------------------------------------------------------------------<br>";
            $query = $this->db->prepare("SELECT * FROM ". $this->db->prefix . "visitor_audit_history WHERE visitor_audit_id = %d LIMIT 1000", $id);
            $results = $this->db->get_results($query);          
 
            foreach($results as $result) {
                foreach($result as $key=>$value) {
                    if ($key == "visitor_audit_history_timestamp") {
                        echo "<strong>Date</strong> : ".$value."<br>";
                    }
                    if ($key == "visitor_audit_history_url") {
                        echo "<strong>URL</strong> : <a href='".$value."'>".$value."</a><br>";
                    }
                    if ($key == "visitor_audit_history_benchmark") {
                        echo "<strong>Benchmark</strong> : ".$value." microseconds<br>";
                    }
                    if ($key == "visitor_audit_history_status") {
                        echo "<strong>HTTP Status Code</strong> : <a href='https://httpstatuses.com/".$value."' target='_blank'>".$value."</a><br>";
                    }
                }
                echo $divider;
            }                  
        }
        exit();
    }
    
    /**
     * This method removes a banned IP address from the visitor_audit_banned table
     * Note : This is called via an ajax call
     * 
     * @return void
     */  
    public function ajax_ban_remove()
    {
        if (!empty($_REQUEST["visitor_audit_id"])){ $id = (int)$_REQUEST["visitor_audit_id"]; }
        if ($id){
            $result = $this->db->delete($this->db->prefix . "visitor_audit_banned", array("visitor_audit_banned_id" => $id));
            $message = "Visitor ban successfully removed.<br><br>Refresh page to view results.";
        }
        if (empty($message)){
            echo "There was an error trying to remove ban.";
        } else {
            echo $message;
        }
        exit();
    } 
 
    /**
     * This method adds an ip address to the temporarily banned list (visitor_audit_banned)
     * Note : This is called via an ajax call
     * 
     * @return void
     */    
    public function ajax_ban_temp()
    {
        $this->ajax_ban("temp");
    }

    /**
     * This method adds an ip address to the permanently banned list (visitor_audit_banned)
     * Note : This is called via an ajax call
     * 
     * @return void
     */  
    public function ajax_ban_perm()
    {
        $this->ajax_ban("perm");
    }   
    
    /**
     * This method contains all logic to add an ip address to the banned list (visitor_audit_banned)
     * Note : This is called via an ajax call
     * 
     * @return void
     */  
    public function ajax_ban($type)
    {
        $id = 0;
        $message = "";
        if (!empty($_REQUEST["visitor_audit_id"])){ $id = (int)$_REQUEST["visitor_audit_id"]; }
        if ($id){
            $query = $this->db->prepare("SELECT * FROM ". $this->db->prefix . "visitor_audit WHERE visitor_audit_id = %d LIMIT 1", $id);
            $result = $this->db->get_results($query);  
            if (!empty($result[0]->visitor_audit_ip)){
                $query_check = $this->db->prepare("SELECT * FROM ". $this->db->prefix . "visitor_audit_banned
                    WHERE visitor_audit_banned_ip = %s AND visitor_audit_banned_ip_forwarded = %s LIMIT 1", $result[0]->visitor_audit_ip, $result[0]->visitor_audit_ip_forwarded);		
                $result_check = $this->db->get_results($query_check);
                if (!empty($result_check[0]->visitor_audit_banned_ip)){
                    $message = "Visitor has already been banned.";
                } else {
                    $insert = array();
                    $insert["visitor_audit_banned_type"] = $type;
                    $insert["visitor_audit_banned_ip"] = $result[0]->visitor_audit_ip;
                    if (!empty($result[0]->visitor_audit_ip_forwarded)){
                        $insert["visitor_audit_banned_ip_forwarded"] = $result[0]->visitor_audit_ip_forwarded;
                    }
                    $this->db->insert($this->db->prefix . "visitor_audit_banned", $insert);
                    if ($type == 'perm'){
                        $message = "You have successfully permanently banned this visitor";
                    } else if ($type == 'temp'){
                        $message = "You have successfully temporarily banned this visitor";
                    }
                }
            }      
        }
        if (!empty($message)){
            echo $message;
        } else {
            echo "There has been a system error. Ban could not be enacted.";
        }
        exit();        
    }
	
    /**
     * Create a text input.
     * 
     * @param string $label The label for the option.
     * @param string $option The option name.
     * @param string $desc The description for the option.
     * @return void
     */
    public function config_text($label,$option,$desc='')
    {
        echo '<tr valign="top"><th scope="row">'.$label.'</th>';
        echo '<td><input type="text" name="'.$option.'" value="'; 
        echo esc_attr( get_option($option) );
	echo '"/> <p id="tagline-description" class="description">'.$desc.'</p></td></tr>';
    }
	
    /**
     * Create a number input.
     * 
     * @param string $label The label for the option.
     * @param string $option The option name.
     * @param string $desc Descriptiong for the option.
     * @return void
     */
    public function config_number($label,$option,$desc='')
    {
        echo '<tr valign="top"><th scope="row">';
	echo '<label for="'.$option.'">'.$label.'</label></th>';
        echo '<td><input type="number" name="'.$option.'" value="'; 
        echo esc_attr( get_option($option) );
        echo '"/> <p id="tagline-description" class="description">'.$desc.'</p></td></tr>';
    }
	
    /**
     * Create a radio toggle.
     * 
     * @param type $label The label for the option.
     * @param type $option The option name.
     * @return void
     */
    public function config_toggle($label,$option,$desc='')
    {
        $toggle = get_option($option);
        echo '<tr valign="top"><th scope="row">';
	echo '<label for="'.$option.'">'.$label.'</label></th>';
        echo '<td><p><input type="radio" name="'.$option.'" value="1" ';
        echo ($toggle) ? 'checked=""' : '';
        echo '/> Enabled</p> <p>';
        echo '<input type="radio" name="'.$option.'" value="0" ';
        echo (!$toggle) ? 'checked=""' : '';
        echo '/> Disabled</p> ';
	echo '<p id="tagline-description" class="description">'.$desc.'</p></td></tr>';
    }
	
    /**
     * Create a set of radio options.
     * 
     * @param string $label The label for the option.
     * @param string $option The option name.
     * @param array $array The values of the radio buttons.
     * @param string $desc The description of the option.
     * @return void
     */
	public function config_radio($label,$option,$array,$desc='')
	{
	    echo '<tr valign="top"><th scope="row">'.$label.'</th><td>';
	    foreach($array as $l=>$v) {
		echo '<p><input type="radio" name="'.$option.'" value="'.$v.'" ';
		echo (get_option($option) == $v) ? 'checked=""' : '';
		echo '/> '.$l.'</p>';
	    }
	    echo '<p id="tagline-description" class="description">'.$desc.'</p></td></tr>';
	}
        
    /**
     * Adds the required modal information to a visitor audit admin page
     * 
     * @return void
     */   
    public function modal()
    {
        add_thickbox();
        echo "<div id='visitor_audit_modal' style='display:none;'></div>";
    }

    /**
     * Outputs required html to generate an admin page with a table within i
     * 
     * @return void
     */     
    public function table_page($title, $table_data)
    {
        echo '<div id="visitor_audit-wrap" class="wrap">';
        echo '<h2>'.$title.'</h2><p>';
        $this->table($table_data);
        echo '</div>';
    }
  
    /**
     * Outputs required html to generate a table
     * 
     * @return void
     */   
    public function table($data)
    {
        if (is_array($data) AND count($data)){
            $column_counter = 0;
            echo '<table class="wp-list-table widefat striped">';
            if (isset($data["columns"]) AND is_array($data["columns"]) AND count($data["columns"])){
                echo '<thead><tr>';
                foreach ($data["columns"] as $column){
                    echo "<th>".$column."</th>";
                    $column_counter++;
                }
                echo '</tr></thead>';
            }
            echo "<tbody>";
            if (isset($data["rows"]) AND is_array($data["rows"]) AND count($data["rows"])){
                foreach ($data["rows"] as $row_data){
                    echo "<tr>";
                    if (isset($row_data) AND is_array($row_data) AND count($row_data)){
                        foreach ($row_data as $row_entry){
                            echo "<td>".$row_entry."</td>";
                        }
                    }
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='".$column_counter."'>No Records Found</td></tr>";
            }
            echo "</tbody>";
            echo "</table>";            
        }
    }
}
