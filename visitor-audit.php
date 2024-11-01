<?php
/**
 * Plugin Name: Visitor Audit
 * Plugin URI: http://VisitorAudit.com
 * Description: Allows you to easily view your current visitors, analyze their behaviour, deduce their experience and identify malicious behavior
 * Version: 1.0.0
 * Author: Justin Campo
 * Author URI: http://campo.cc
 * License: GPL2
 */

 

if(!class_exists('\Visitor_Audit\Visitor_Audit_Config')) {
    require_once(dirname(__FILE__) . '/visitor-audit.config.php');
}
if(!class_exists('\Visitor_Audit\Visitor_Audit_Setup')) {
    require_once(dirname(__FILE__) . '/visitor-audit.setup.php');
}
if(!class_exists('\Visitor_Audit\Visitor_Audit')) {
    require_once(dirname(__FILE__) . '/visitor-audit.class.php');
}
if(class_exists('\Visitor_Audit\Visitor_Audit')) {
    $wp_visitor_audit = new \Visitor_Audit\Visitor_Audit();
    register_activation_hook(__FILE__, array(&$wp_visitor_audit, 'activate'), 10, 0);
    register_deactivation_hook(__FILE__, array(&$wp_visitor_audit, 'deactivate'), 10, 0);
    add_action('plugins_loaded', array(&$wp_visitor_audit, 'init'), 1, 0);
    add_action('init', array(&$wp_visitor_audit, 'admin'), 1, 0);
    add_action('shutdown', array(&$wp_visitor_audit, 'log'), 10, 0);
}


if (is_admin()) {
    if(!class_exists('\Visitor_Audit\Visitor_Audit_Admin')) {
        require_once(dirname(__FILE__) . '/visitor-audit.admin.php');
    }
    if(class_exists('\Visitor_Audit\Visitor_Audit')) {
        $wp_visitor_audit_admin = new Visitor_Audit\Vistor_Audit_Admin($wp_visitor_audit);
        add_action('admin_init', array(&$wp_visitor_audit_admin, 'settings'), 10, 0);
        add_action('admin_menu', array(&$wp_visitor_audit_admin, 'add_menu'), 10, 0);
        add_action('admin_enqueue_scripts', array(&$wp_visitor_audit_admin, 'javascript'), 10, 0);
        add_action('wp_ajax_visitor_audit_details', array(&$wp_visitor_audit_admin, 'ajax_details'), 10, 0);
        add_action('wp_ajax_visitor_audit_history', array(&$wp_visitor_audit_admin, 'ajax_history'), 10, 0);
        add_action('wp_ajax_visitor_audit_ban_temp', array(&$wp_visitor_audit_admin, 'ajax_ban_temp'), 10, 0);
        add_action('wp_ajax_visitor_audit_ban_perm', array(&$wp_visitor_audit_admin, 'ajax_ban_perm'), 10, 0);
        add_action('wp_ajax_visitor_audit_ban_remove', array(&$wp_visitor_audit_admin, 'ajax_ban_remove'), 10, 0);
    }
}