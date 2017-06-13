<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class LTPLE_Sponsorship_Settings {

	/**
	 * The single instance of LTPLE_Sponsorship_Settings.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	/**
	 * The main plugin object.
	 * @var 	object
	 * @access  public
	 * @since 	1.0.0
	 */
	public $parent = null;

	/**
	 * Prefix for plugin settings.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $base = '';

	/**
	 * Available settings for plugin.
	 * @var     array
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = array();

	public function __construct ( $parent ) {
		
		$this->parent = $parent;
		
		$this->plugin 		 	= new stdClass();
		$this->plugin->slug  	= 'live-template-editor-sponsorship';
		
		add_action('ltple_plugin_settings', array($this, 'plugin_info' ) );
		
		add_action('ltple_plugin_settings', array($this, 'settings_fields' ) );
		
		add_action( 'ltple_admin_menu' , array( $this, 'add_menu_items' ) );	
	}
	
	public function plugin_info(){
		
		$this->parent->settings->addons['sponsorship-program'] = array(
			
			'title' 		=> 'Sponsorship Program',
			'addon_link' 	=> 'https://github.com/rafasashi/live-template-editor-sponsorship',
			'addon_name' 	=> 'live-template-editor-sponsorship',
			'source_url' 	=> 'https://github.com/rafasashi/live-template-editor-sponsorship/archive/master.zip',
			'description'	=> 'Sponsorship program including management and purchase of licenses in bulk.',
			'author' 		=> 'Rafasashi',
			'author_link' 	=> 'https://profiles.wordpress.org/rafasashi/',
		);		
	}

	/**
	 * Build settings fields
	 * @return array Fields to be displayed on settings page
	 */
	public function settings_fields () {

		$settings = [];
		
		/*
		$settings['sponsorship'] = array(
		
			'title'					=> __( 'Sponsorship', $this->plugin->slug ),
			'description'			=> __( 'Sponsorship settings', $this->plugin->slug ),
			'fields'				=> array(		
				array(
					'id' 			=> 'sponsorship_banners',
					'name' 			=> 'sponsorship_banners',
					'label'			=> __( 'Sponsorship banners' , $this->plugin->slug ),
					'description'	=> '',
					'inputs'		=> 'string',
					'type'			=> 'key_value',
					'placeholder'	=> ['key'=>'image title', 'value'=>'url'],
				),
			)
		);
		*/
		
		if( !empty($settings) ){
			
			// merge settings
		
			foreach( $settings as $slug => $data ){
				
				if( isset($this->parent->settings->settings[$slug]['fields']) && !empty($data['fields']) ){
					
					$fields = $this->parent->settings->settings[$slug]['fields'];
					
					$this->parent->settings->settings[$slug]['fields'] = array_merge($fields,$data['fields']);
				}
				else{
					
					$this->parent->settings->settings[$slug] = $data;
				}
			}
		}
	}
	
	/**
	 * Add settings page to admin menu
	 * @return void
	 */
	public function add_menu_items () {
		
		//add menu in wordpress dashboard
		
		/*
		add_submenu_page(
			'live-template-editor-client',
			__( 'Sponsorship Commissions', $this->plugin->slug ),
			__( 'Sponsorship Commissions', $this->plugin->slug ),
			'edit_pages',
			'edit.php?post_type=sponsorship-commission'
		);
		*/
		
		add_users_page( 
			'All Companies', 
			'All Companies', 
			'edit_pages',
			'users.php?' . $this->parent->_base .'view=companies'
		);
	}
}
