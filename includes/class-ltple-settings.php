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
		
		foreach($this->parent->settings->tabs as $i => $tabs){
			
			if( isset($tabs['email-model']) ){
				
				$this->parent->settings->tabs[$i]['sponsor-invitation'] = array( 'name' => 'Sponsorship', 'post-type' => 'sponsor-invitation' );
			}
		}		

		add_action( 'ltple_admin_menu' , array( $this, 'add_menu_items' ) );	
	}
	
	/**
	 * Add settings page to admin menu
	 * @return void
	 */
	public function add_menu_items () {

	}
}
