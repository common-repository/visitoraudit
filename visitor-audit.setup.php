<?php
/**
 * Setup class for Visitor Audit
 *
 * @version 1.0.0
 * @author Justin Campo <admin@visitoraudit.com>
 * @license GPL2
 */
namespace Visitor_Audit;
/**
 * This class stores all methods for installing/uninstalling
 * the plugin from wordpress
 */	
class Visitor_Audit_Setup extends \Visitor_Audit\Vistor_Audit_Config
{
    /**
     * Method that runs to install plugin
     * 
     * @return void
     */
    public function activate()
    {
	//avoiding using dbDelta because I hate running an include inside a class
	//may be needed when upgrading db in a future version - might just handle it myself			
        $charset_collate = $this->db->get_charset_collate();  
        if ($this->version == '1.0'){		
            $sql_visitor_audit = "CREATE TABLE ". $this->db->prefix . "visitor_audit (
		visitor_audit_id bigint(20) NOT NULL AUTO_INCREMENT,
		visitor_audit_ip varchar(55) NOT NULL,
		visitor_audit_ip_forwarded varchar(55) NOT NULL,
		visitor_audit_timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		visitor_audit_useragent varchar(255) NOT NULL,
		visitor_audit_referer varchar(255) NOT NULL,
		UNIQUE KEY visitor_audit_id (visitor_audit_id)
		) $charset_collate;";
	    $this->db->query($sql_visitor_audit);
	    
	    $sql_visitor_audit_history = "CREATE TABLE ". $this->db->prefix . "visitor_audit_history (
		visitor_audit_history_id bigint(20) NOT NULL AUTO_INCREMENT,
		visitor_audit_id bigint(20) NOT NULL,
		visitor_audit_history_timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		visitor_audit_history_url varchar(512) NOT NULL,
		visitor_audit_history_status int(3),
		visitor_audit_history_analyzed tinyint(1) NOT NULL DEFAULT 0,
		visitor_audit_history_benchmark int(12) NOT NULL,
		UNIQUE KEY visitor_audit_history_id (visitor_audit_history_id),
		KEY visitor_audit_id (visitor_audit_id)
		) $charset_collate;";
	    $this->db->query($sql_visitor_audit_history);
	    
	    $sql_visitor_audit_banned = "CREATE TABLE ". $this->db->prefix . "visitor_audit_banned (
		visitor_audit_banned_id bigint(20) NOT NULL AUTO_INCREMENT,
		visitor_audit_banned_ip varchar(55) NOT NULL,
		visitor_audit_banned_ip_forwarded varchar(55) NOT NULL DEFAULT 0,
		visitor_audit_banned_type tinyint(1) NOT NULL DEFAULT 0,
		visitor_audit_banned_timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		UNIQUE KEY visitor_audit_banned_id (visitor_audit_banned_id),
		KEY visitor_audit_banned_ip (visitor_audit_banned_ip),
                KEY visitor_audit_banned_ip_forwarded (visitor_audit_banned_ip_forwarded)
		) $charset_collate;";
	    $this->db->query($sql_visitor_audit_banned);
        }
	
	foreach($this->config_valid as $c) {
	    add_option($opt, "visitor_audit_".$c);
        }
    }

    /** 
     * Method that runs to uninstall plugin
     * 
     * @return void
     */
    public function deactivate()
    {
	$sql_visitor_audit = "DROP TABLE ". $this->db->prefix . "visitor_audit;";			
	$this->db->query($sql_visitor_audit);
	
	$sql_visitor_audit_history = "DROP TABLE ". $this->db->prefix . "visitor_audit_history;";
	$this->db->query($sql_visitor_audit_history);
	
	$sql_visitor_audit_banned = "DROP TABLE ". $this->db->prefix . "visitor_audit_banned;";
	$this->db->query($sql_visitor_audit_banned);
    }
}
