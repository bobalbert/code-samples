<?php
/******************************************************************************
Plugin Name:		Korrio Custom Fields Component
Plugin URI:			http://korrio.com
Description:		Adds support for "custom fields" for groups during registration.
Version:			0.1
Revision Date:		December 14, 2012
Requires at least:	WPMU 2.8.4, BuddyPress 1.2
Tested up to:		WPMU 2.8.4, BuddyPress 1.2
License:			(c) 2012 Korrio, Inc. - All Rights Reserved
Author:				Bob Albert
Author URI:			http://korrio.com
Site Wide Only:		true
*******************************************************************************/

/* Define a constant that can be checked to see if the component is installed or not. */
define ('KORRIO_CUSTOMFIELDS_IS_INSTALLED', 1);

/* Define a constant that will hold the current version number of the component */
define ('KORRIO_CUSTOMFIELDS_VERSION', '0.1');

/* Define a constant that will hold the database version number that can be used for upgrading the DB
 *
 * NOTE: When table definitions change and you need to upgrade,
 * make sure that you increment this constant so that it runs the install function again.
 *
 * Also, if you have errors when testing the component for the first time, make sure that you check to
 * see if the table(s) got created. If not, you'll most likely need to increment this constant as
 * KORRIO_CUSTOMFIELDS_DB_VERSION was written to the wp_sitemeta table and the install function will not be
 * triggered again unless you increment the version to a number higher than stored in the meta data.
 */
define ('KORRIO_CUSTOMFIELDS_DB_VERSION', '0.22');

require_once( dirname(__FILE__) . '/korrio-custom-fields-includes.php' );

/**
 * conditionally load the scripts used in this plugin.
 */
function korrio_customfields_enqueue_scripts() {
	global $bp;
	if ( ( $bp->action_variables[0] == 'custom-fields' ) && !is_admin() ) {
		wp_enqueue_script( 'korrio-custom-fields', plugins_url( '/resources/js/korrio-custom-fields.js', __FILE__ ), array('jquery'), '206' );
		wp_enqueue_script('jquery-validate');
	}
}
add_action('wp_enqueue_scripts', 'korrio_customfields_enqueue_scripts');

/**
 * conditionally load the styles used in this plugin.
 */
function korrio_customfields_enqueue_styles() {
	global $bp;
	if ( ( $bp->action_variables[0] == 'custom-fields' ) && !is_admin() ) {
		wp_enqueue_style('korrio-custom-fields', plugins_url( '/resources/css/korrio-custom-fields.css', __FILE__ ), array(), '1' );
	}
}
add_action('wp_print_styles', 'korrio_customfields_enqueue_styles');

/**
 * Installs and/or upgrades the database tables.
 */
function korrio_customfields_install()
{
	//error_log("korrio_customfields_install()");

	$sql = array();

	$sql[] = "CREATE TABLE wp_korrio_custom_fields (
    		  id bigint(20) unsigned NOT NULL auto_increment,
    		  group_id bigint(20) unsigned NOT NULL,
    		  title VARCHAR(100),
    		  text TEXT,
    		  description TEXT,
    		  type ENUM('text', 'textarea', 'radio', 'checkbox', 'yes/no', 'file') NOT NULL DEFAULT 'text',
    		  is_visible TINYINT(1) unsigned not null DEFAULT 1,
    		  is_required TINYINT(1) unsigned not null DEFAULT 0,
    		  value TEXT,
    		  state ENUM('active', 'inactive', 'deleted') NOT NULL DEFAULT 'active',
              updated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              created datetime DEFAULT NULL,
    		  PRIMARY KEY (id),
    		  KEY group_id (group_id)
		 	  ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

	$sql[] = "CREATE TABLE wp_korrio_group_custom_fields (
    		  id bigint(20) unsigned NOT NULL auto_increment,
    		  group_id bigint(20) unsigned NOT NULL,
    		  customfield_id bigint(20) unsigned NOT NULL,
    		  deleted TINYINT(1) unsigned not null DEFAULT 0,
    		  display_order TINYINT(1) unsigned not null DEFAULT 0,
              updated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              created datetime DEFAULT NULL,
    		  PRIMARY KEY (id),
    		  KEY group_id (group_id),
    		  KEY customfield_id (customfield_id)
		 	  ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

	$sql[] = "CREATE TABLE korrio_user_custom_fields (
              id int(11) NOT NULL auto_increment,
              parent_group_id int(11) NOT NULL,
              program_id int(11) NOT NULL,
              user_id int(11) NOT NULL,
              custom_field_id int(11) NOT NULL,
              custom_field_answer mediumtext NOT NULL,
              updated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              created datetime DEFAULT NULL,
              PRIMARY KEY (id),
              KEY parent_group_id (parent_group_id),
              KEY program_id (program_id),
              KEY user_id (user_id)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

    $sql[] = "CREATE TABLE korrio_user_custom_fields_migration_status (
              id int(11) NOT NULL auto_increment,
              old_custom_field_id int(11) NOT NULL,
              new_custom_field_id int(11) NOT NULL,
              status VARCHAR(20) NOT NULL,
              notes VARCHAR(100),
              updated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              created datetime DEFAULT NULL,
              PRIMARY KEY (id),
              UNIQUE KEY old_custom_field_id (old_custom_field_id),
              KEY new_custom_field_id (new_custom_field_id)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$result = dbDelta($sql);

	update_site_option( 'korrio-customfields-db-version', KORRIO_CUSTOMFIELDS_DB_VERSION );
}

/**
 * Sets up global variables.
 */
function korrio_customfields_setup_globals() {
	global $bp, $wpdb;

	/* For internal identification */
	$bp->korrio_custom_fields->custom_fields = $wpdb->base_prefix . 'korrio_custom_fields';
	$bp->korrio_custom_fields->group_custom_fields = $wpdb->base_prefix . 'korrio_group_custom_fields';

	/**************************************************************************
	 * Register all the cache groups that should be setup as globals
	 **************************************************************************/
	if (function_exists('wp_cache_add_global_groups')) {
		//wp_cache_add_global_groups(array('names_of_caches_here'));
	}
}
add_action('plugins_loaded', 'korrio_customfields_setup_globals', 5);

/**
 * Check to see if the DB tables exist or if you are running an old version.
 */
function korrio_customfields_check_installed() {

	if (!is_site_admin()) {
		return false;
	}

	$customfields_db_version = get_site_option('korrio-customfields-db-version');

	error_log('korrio_customfields_check_installed() - DB version is ' . $customfields_db_version);
	if ( $customfields_db_version !== KORRIO_CUSTOMFIELDS_DB_VERSION ) {
		korrio_customfields_install();
	}
}
add_action('admin_menu', 'korrio_customfields_check_installed');

