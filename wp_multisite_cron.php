<?php
/*
Plugin Name: WordPress Multisite cron
Plugin URI: https://github.com/chibani/wp_multisite_cron
Description: Plugin to handle multisite cron
Author: LoicG
Author URI: http://blog.loicg.net/
 */

add_action('init', array('wp_multisite_cron', 'init'));

class wp_multisite_cron{
	
	/**
	 * 
	 * The main 'loader'
	 */
	function init() {

		//Setup the translation
		load_plugin_textdomain('wp_multisite_cron',false, dirname(plugin_basename( __FILE__ ) ) . '/lang/');
		
		//The multisite cron action		
		add_action('wp_ajax_wp_multisite_cron_call', array('wp_multisite_cron','cron_call'));
		add_action('wp_ajax_nopriv_wp_multisite_cron_call', array('wp_multisite_cron','cron_call'));

    	// admin actions and hooks
        if (is_admin()) {
            self::admin_hooks();
        }
    }
    
    /**
     * 
     * The admin hooks
     */
    public static function admin_hooks(){
    	//Setting menu
    	add_action('admin_menu', array('wp_multisite_cron', 'admin_menu'));
    	if(is_network_admin() && self::is_ready()){
    		add_action('network_admin_menu', array('wp_multisite_cron', 'network_admin_menu'));
    	}
    }
    
    /**
     * 
     * Set up the admin menu(s)
     */
    public static function admin_menu(){
    	add_options_page("WordPress multisite cron, information page", "WP multisite cron", 'manage_options', 'wp_multisite_cron_settings', array('wp_multisite_cron', "admin_settings"));
    }
    
	/**
     * 
     * Set up the admin menu(s)
     */
    public static function network_admin_menu(){
    	add_submenu_page("settings.php","WordPress multisite cron, information page", "WP multisite cron", 'manage_options', 'wp_multisite_cron_settings', array('wp_multisite_cron', "admin_settings"));
    }
    
    
    /**
     * 
     * The admin settings page
     */
    public static function admin_settings(){
    	if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}
		?>
		<div class="wrap">
    		<div id="icon-options-general" class="icon32">
				<br />
			</div>
    		<h2>WordPress multisite cron</h2>
    		
    		<?php if (isset($_POST) && !empty($_POST)) :

	        	//Let's save some actions ...
	        
	            ?>
	            <div id="setting-error-settings_updated" class="updated settings-error">
					<p>
						<strong><?php _e('Settings saved.')?></strong>
					</p>
				</div>
	            
			<?php endif;?>
    		
    		
    		
			<?php if(!is_multisite()):?>
				<div class="tool-box error">
					<h3><?php _e('Multisite','wp_multisite_cron') ?></h3>
					<p><?php _e('Your WordPress installation is not configured as multisite','wp_multisite_cron') ?>.</p>
					<p><?php _e("Read the WordPress' documentation about multisite to learn how to set up multisite",'wp_multisite_cron')?> : <a href="http://codex.wordpress.org/Create_A_Network" target="_blank"><?php _e('here','wp_multisite_cron')?></a></p>
				</div>
			<?php endif;?>
									
						
			<?php if(!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON ):?>
				<div class="tool-box error">
					<h3><?php _e('WordPress default cron system','wp_multisite_cron') ?></h3>
					<p><?php _e('You must disable the WordPress cron system, to use this extension','wp_multisite_cron') ?>.</p>
					<p><?php _e('Add the following code in your wp-config.php','wp_multisite_cron') ?> :</p>
					<p>
						<code>define('DISABLE_WP_CRON', true);</code>
					</p>
				</div>
			<?php endif;?>
			
			<?php if(self::is_ready()): ?>
				<div class="tool-box">
					<h3><?php _e('Set-up WordPress multisite cron','wp_multisite_cron') ?></h3>
					<p><?php _e('Add the following line in your crontab','wp_multisite_cron')?> :</p>
					<p>
						<code>*/15 *<?php echo "\t" ?>* * * www-data /usr/bin/wget -qO- <?php echo admin_url('admin-ajax.php','http').'?action=wp_multisite_cron_call' ?></code>
					</p>
				</div>
			<?php endif;?>
    	</div>
    		
    	<?php 
    }
    
    /**
     * 
     * Check wether the config is ok to run real crons
     */
    public static function is_ready(){
    	return (is_multisite() && defined('DISABLE_WP_CRON') && DISABLE_WP_CRON);
    }
    
    /**
     * 
     * The real cron
     */
    public static function cron_call(){
    	
    	//load the blogs id
    	$blogs = self::get_blogs_id();
    	if(count($blogs)){
    		$mh = curl_multi_init();
	    	
	    	foreach($blogs as &$blog_id){
	    		switch_to_blog($blog_id);
	    		$cron_url = site_url().'/wp-cron.php?doing_wp_cron';

	    		//Add the url to the stack
	    		$ch=curl_init();
	    		curl_setopt($ch, CURLOPT_URL, $cron_url);
	    		curl_setopt($ch, CURLOPT_HEADER, 0);
	    		curl_multi_add_handle($mh, $ch);
	    	}
    	
	    	//Launch :)
	    	$running = null;
	    	do{
	    		curl_multi_exec($mh, $still_running);
	    	}while($still_running > 0);
	    	
	    	curl_multi_close($mh);
    	}
    	die();
    }
    
    /**
     * 
     * Get the blog_id for each active blog in the multisite
     * @return array
     */
    public static function get_blogs_id(){
    	global $wpdb;

    	//Get the blogs' ids for blogs that are public and active
    	$blogs = $wpdb->get_col('SELECT blog_id FROM '.$wpdb->blogs.' WHERE public=1 AND deleted=0');
    	return $blogs;
    }
    
	/**
	 * 
	 * Get a plugin's specific option
	 * @param string $option_name
	 */
    public static function get_option($option_name){
    	return get_option('wp_multisite_cron_'.$option_name);
    }
    
    /**
     * 
     * Set a plugin's specific option
     * @param unknown_type $option_name
     */
	public static function update_option($option_name,$option_value){
    	return update_option('wp_multisite_cron_'.$option_name,$option_value);
    }
    
	/**
     * 
     * Delete a plugin's specific option
     * @param string $option_name
     */
    public static function delete_option($option_name){
    	return delete_option('wp_multisite_cron_'.$option_name);
    }
    
    
	/**
	 * 
	 * get the plugin's path url
	 */
	public static function get_plugin_url(){
		return get_bloginfo('url') . '/' . PLUGINDIR . '/' . dirname(plugin_basename(__FILE__));
	}
}