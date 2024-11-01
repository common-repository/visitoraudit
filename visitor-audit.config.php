<?php
/**
 * Configuration class for Visitor Audit
 *
 * @version 1.0.0
 * @author Justin Campo <admin@visitoraudit.com>
 * @license GPL2
 */
namespace Visitor_Audit;
/**
 * This stores all default settings used with this plugin.
 * These values will be overwritten by user settings.
 */	
class Vistor_Audit_Config
{
    //
    // Plugin Configuration Settings
    //
    /** @var int How long to store visitor history for (in minutes)(0 = infinite) */
    protected $history_retention = 10;
    /** @var int How long to maintain ban for (in minutes)(0 = infinite) */
    protected $banned_retention = 30;
    /** @var int How many pages per minute a visitor is allowed before being flagged suspicious (0 = infinite) */
    protected $page_views_per_minute = 60;
    /** @var int How many error statuses per minute a visitor is allowed before being flagged suspicious (0 = infinite) */
    protected $error_status_per_minute = 20;
    /** @var string The method the plugin uses to stop the visitor (message, exit, sleep) */
    protected $ban_method = "message";    
    /** @var boolean Will the visitor be able to extend their own banned time */
    protected $visitor_extends_ban = true;
    
    /**
     * Automatic Banning
     * @var boolean
     * Controls if the plugin will automatically ban visitors
     * 
     * This system is not recommend to be enabled.  There are many situations when valid traffic
     * (for example search engine bots) will be banned which will be detrimental to your site
     */
    protected $automatic_banning = false;
    
    //
    // Slow Page Settings
    //
    /** @var boolean Enable/Disable slow page reporting */
    protected $slow_page_reporting = false;
    /** @var int How long for a page to load until slow page system triggered (in seconds)(0 = infinite) */
    protected $slow_page_time_limit = 6;
    /** @var string The email address the slow reports will be sent as */
    protected $slow_page_email_system = "";
    /** @var string The email address the slow reports will be sent to */
    protected $slow_page_email = "";
    /** @var array A list of urls that the system should exclude (will not send report even if reported slow) */
    protected $slow_page_exclusions = array();
    
    //
    // System Settings : Should not be changed
    //
    /** @var array A list of all valid settings */
    protected $config_valid = array(
        "history_retention",
        "banned_retention",
        "automatic_banning",
        "page_views_per_minute",
        "error_status_per_minute",
        "visitor_extends_ban",
        "ban_method",
        "slow_page_reporting",
        "slow_page_time_limit",
        "slow_page_email_system",
        "slow_page_email",
        "slow_page_exclusions"
    );
    
    /** @var array A list of all valid settings with their corresponding data types */
    protected $config_type = array(
        "history_retention" => "int",
        "banned_retention" => "int",
        "automatic_banning" => "boolean",
        "page_views_per_minute" => "int",
        "error_status_per_minute" => "int",
        "visitor_extends_ban" => "int",
        "ban_method" => "string",
        "slow_page_reporting" => "boolean",
        "slow_page_time_limit" => "int",
        "slow_page_email_system" => "string",
        "slow_page_email" => "string",
        "slow_page_exclusions" => "array"
    );
}
