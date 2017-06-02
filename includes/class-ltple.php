<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class LTPLE_Company extends LTPLE_Client_Object {
	
	/**
	 * The single instance of LTPLE_Addon.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;
	
	var $parent;
	var $list;
	var $status;
	
	/**
	 * Constructor function
	 */
	public function __construct ( $file='', $parent, $version = '1.0.0' ) {

		$this->parent 	= $parent;
		
		$this->_version = $version;
		$this->_token	= md5($file);
		
		$this->message = '';
		
		// Load plugin environment variables
		
		$this->file 		= $file;
		$this->dir 			= dirname( $this->file );
		$this->views   		= trailingslashit( $this->dir ) . 'views';
		$this->vendor  		= WP_CONTENT_DIR . '/vendor';
		$this->assets_dir 	= trailingslashit( $this->dir ) . 'assets';
		$this->assets_url 	= esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );
		
		//$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$this->script_suffix = '';

		register_activation_hook( $this->file, array( $this, 'install' ) );
		
		// Load frontend JS & CSS
		
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		// Load admin JS & CSS
		
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ), 10, 1 );		
		
		$this->settings = new LTPLE_Company_Settings( $this->parent );
		
		$this->admin 	= new LTPLE_Company_Admin_API( $this );
		
		/*
		$this->parent->register_post_type( 'company-commission', __( 'Company commissions', 'live-template-editor-client' ), __( 'Company commission', 'live-template-editor-client' ), '', array(

			'public' 				=> false,
			'publicly_queryable' 	=> false,
			'exclude_from_search' 	=> true,
			'show_ui' 				=> true,
			'show_in_menu'		 	=> 'company-commission',
			'show_in_nav_menus' 	=> true,
			'query_var' 			=> true,
			'can_export' 			=> true,
			'rewrite' 				=> false,
			'capability_type' 		=> 'post',
			'has_archive' 			=> false,
			'hierarchical' 			=> false,
			'show_in_rest' 			=> true,
			//'supports' 			=> array( 'title', 'editor', 'author', 'excerpt', 'comments', 'thumbnail','page-attributes' ),
			'supports' 				=> array( 'title','author' ),
			'menu_position' 		=> 5,
			'menu_icon' 			=> 'dashicons-admin-post',
		));
		*/
		
		add_action( 'add_meta_boxes', function(){
		
			/*
			$this->parent->admin->add_meta_box (
			
				'commission_amount',
				__( 'Amount', 'live-template-editor-client' ), 
				array("company-commission"),
				'side'
			);
			*/
		});
		
		add_action( 'ltple_loaded', array( $this, 'init_company' ));

		add_action( 'ltple_list_programs', function(){
			
			$this->parent->programs->list['company'] = 'Company';
		});

		add_action( 'ltple_campaign_triggers', function(){
			
			$this->parent->campaign->triggers = array_merge(
			
				$this->get_terms( $this->parent->campaign->taxonomy, array( 
							
					'company-approved' 	=> 'Company Approved',
				))			
			
			,$this->parent->campaign->triggers);
		});
		
		add_action( 'ltple_editor', function(){
		
			if( isset($_GET['company']) ){

				include($this->views . $this->parent->_dev .'/company.php');
				
				$this->parent->viewIncluded = true;
			}
		});
		
		add_action( 'ltple_view_my_profile', function(){
			
			echo'<li style="position:relative;">';
				
				echo '<a href="'. $this->parent->urls->editor .'?company"><span class="glyphicon glyphicon-briefcase" aria-hidden="true"></span> My Company</a>';

			echo'</li>';
		});
	}
	
	public function init_company(){
		
		$this->parent->user->is_company = $this->parent->programs->has_program('company', $this->parent->user->ID, $this->parent->user->programs);
		
		if( is_admin() ){

			global $pagenow;
				
			if( $pagenow == 'users.php' ){		
		
				// add tab in user panel
				
				add_action( 'ltple_user_tab', array($this, 'get_company_tab' ) );
				
				if( $this->parent->users->view == 'companies' ){
				
					// filter company users
					
					add_filter( 'pre_get_users', array( $this, 'filter_companies') );

					// custom users columns
					
					if( method_exists($this, 'update_' . $this->parent->users->view . '_table') ){
						
						add_filter('manage_users_columns', array($this, 'update_' . $this->parent->users->view . '_table'), 100, 1);
					}
					
					if( method_exists($this, 'modify_' . $this->parent->users->view . '_table_row') ){
						
						add_filter('manage_users_custom_column', array($this, 'modify_' . $this->parent->users->view . '_table_row'), 100, 3);	
					}
				}
			}
			else{
				
				// save user programs
				
				add_action( 'personal_options_update', array( $this, 'save_user_company' ) );
				add_action( 'edit_user_profile_update', array( $this, 'save_user_company' ) );
			}
		}
		else{
			
			//Add Custom API Endpoints
			
			add_action( 'rest_api_init', function () {
				
				register_rest_route( 'ltple-company/v1', '/users', array(
					
					'methods' 	=> 'GET',
					'callback' 	=> array($this,'get_company_users'),
				) );
			} );
		}
	}
	
	public function filter_companies( $query ) {

		// alter the user query to add my meta_query
		
		$query->set( 'meta_query', array(
		
			array(
			
				'key' 		=> $this->parent->_base . 'user-programs',
				'value' 	=> 'company',
				'compare' 	=> 'LIKE'
			),
		));
	}
	
	public function get_company_tab(){
		
		echo '<a class="nav-tab ' . ( $this->parent->users->view == 'companies' ? 'nav-tab-active' : '' ) . '" href="users.php?ltple_view=companies">Companies</a>';
	}
	
	public function update_companies_table($column) {
		
		$column=[];
		$column["cb"]			= '<input type="checkbox" />';
		$column["username"]		= 'Username';
		$column["email"]		= 'Email';
		
		return $column;
	}
	
	public function modify_companies_table_row($val, $column_name, $user_id) {
		
		if(!isset($this->list->{$user_id})){
		
			if( empty($this->list) ){
				
				$this->list	= new stdClass();
			}			
		
			$this->list->{$user_id} = new stdClass();

		}
		
		$row='';
		
		if ($column_name == "") { 
				
			$row .= '';
		}
		
		return $row;
	}	

	public function save_user_company( $user_id ) {
		
		if(isset($_POST[$this->parent->_base . 'user-programs'])){

			if( in_array( 'company', $_POST[$this->parent->_base . 'user-programs']) ){
				
				//$this->parent->email->schedule_trigger( 'company-approved',  $user_id);
			}
		}
	}
	
	
	public function get_company_users() {
		
		$q 	= get_users();
		
		$company_users = [];

		foreach($q as $u){
			
			$user = $u->data;
			
			$item = [];
			
			if( !empty( $user->display_name ) ){
				
				$item['name'] = $user->display_name;
			}
			else{
				
				$item['name'] = $user->user_nicename;
			}
			
			$item['email'] = $user->user_email;
			
			

			$company_users[] = $item;
		}

		
		
		return $company_users;
	}
	
	/**
	 * Wrapper function to register a new post type
	 * @param  string $post_type   Post type name
	 * @param  string $plural      Post type item plural name
	 * @param  string $single      Post type item single name
	 * @param  string $description Description of post type
	 * @return object              Post type class object
	 */
	public function register_post_type ( $post_type = '', $plural = '', $single = '', $description = '', $options = array() ) {

		if ( ! $post_type || ! $plural || ! $single ) return;

		$post_type = new LTPLE_Client_Post_Type( $post_type, $plural, $single, $description, $options );

		return $post_type;
	}

	/**
	 * Wrapper function to register a new taxonomy
	 * @param  string $taxonomy   Taxonomy name
	 * @param  string $plural     Taxonomy single name
	 * @param  string $single     Taxonomy plural name
	 * @param  array  $post_types Post types to which this taxonomy applies
	 * @return object             Taxonomy class object
	 */
	public function register_taxonomy ( $taxonomy = '', $plural = '', $single = '', $post_types = array(), $taxonomy_args = array() ) {

		if ( ! $taxonomy || ! $plural || ! $single ) return;

		$taxonomy = new LTPLE_Client_Taxonomy( $taxonomy, $plural, $single, $post_types, $taxonomy_args );

		return $taxonomy;
	}

	/**
	 * Load frontend CSS.
	 * @access  public
	 * @since   1.0.0
	 * @return void
	 */
	public function enqueue_styles () {
		
		wp_register_style( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'css/frontend.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-frontend' );
	} // End enqueue_styles ()

	/**
	 * Load frontend Javascript.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function enqueue_scripts () {
		
		wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );
		wp_enqueue_script( $this->_token . '-frontend' );
	} // End enqueue_scripts ()

	/**
	 * Load admin CSS.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function admin_enqueue_styles ( $hook = '' ) {
		
		wp_register_style( $this->_token . '-admin', esc_url( $this->assets_url ) . 'css/admin.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-admin' );
	} // End admin_enqueue_styles ()

	/**
	 * Load admin Javascript.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function admin_enqueue_scripts ( $hook = '' ) {
		
		wp_register_script( $this->_token . '-admin', esc_url( $this->assets_url ) . 'js/admin' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );
		wp_enqueue_script( $this->_token . '-admin' );
	} // End admin_enqueue_scripts ()

	/**
	 * Load plugin localisation
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_localisation () {
		
		load_plugin_textdomain( $this->settings->plugin->slug, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_localisation ()

	/**
	 * Load plugin textdomain
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_plugin_textdomain () {
		
	    $domain = $this->settings->plugin->slug;

	    $locale = apply_filters( 'plugin_locale', get_locale(), $domain );

	    load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
	    load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_plugin_textdomain ()

	/**
	 * Main LTPLE_Addon Instance
	 *
	 * Ensures only one instance of LTPLE_Addon is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see LTPLE_Addon()
	 * @return Main LTPLE_Addon instance
	 */
	public static function instance ( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}
		return self::$_instance;
	} // End instance ()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __wakeup ()

	/**
	 * Installation. Runs on activation.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function install () {
		$this->_log_version_number();
	} // End install ()

	/**
	 * Log the plugin version number.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	private function _log_version_number () {
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number ()

}
