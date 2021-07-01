<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class LTPLE_Sponsorship extends LTPLE_Client_Object {
	
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
	
	var $plans 			= NULL;
	var $invitations 	= NULL;
	
	// offline request
	
	var $plan_id 		= NULL;
	var $user_email 	= NULL;
	var $login_url 		= NULL;
	var $register_url 	= NULL;
	
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
		$this->assets_url 	= home_url( trailingslashit( str_replace( ABSPATH, '', $this->dir ))  . 'assets/' );
		
		//$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$this->script_suffix = '';

		register_activation_hook( $this->file, array( $this, 'install' ) );
		
		// Load frontend JS & CSS
		
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		// Load admin JS & CSS
		
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ), 10, 1 );		
		
		$this->settings = new LTPLE_Sponsorship_Settings( $this->parent );
		
		$this->admin 	= new LTPLE_Sponsorship_Admin_API( $this );
		
		// handle sponsor payment
		
		add_filter( 'ltple_one_time_payment', array( $this, 'handle_sponsor_payment'));
		
		// add profile tabs		

		add_filter( 'ltple_profile_tabs', array( $this, 'add_profile_tabs'),10,1);
	
		// add shortcodes
		
		add_shortcode('ltple-sponsorship-notification', array( $this , 'get_inbox_notification' ) );
	}
	
	public function handle_sponsor_payment(){
		
		if( !empty($this->parent->plan->data['sponsored']) ){
			
			if( $sponsored = get_user_by('email',$this->parent->plan->data['sponsored']) ){
			
				$this->parent->plan->message = '<div class="alert alert-success">';
					
					$this->parent->plan->message .= 'Congratulations, the account of <b>' . ucfirst($sponsored->nickname) . '</b> was successfully credited!';

				$this->parent->plan->message .= '</div>';
			}
		}
	}
	
	public function get_inbox_notification( $atts ){
		
		$atts = shortcode_atts( array(
		
			'fee'		 		=> 0,
			'currency' 			=> '$',
			
		), $atts, 'ltple-sponsorship-notification' );		
		
		$content = '';
		
		if(  !is_null($atts['fee']) && is_numeric($atts['fee']) ){
			
			$content .= '<div style="background:#fff;color:' . $this->parent->settings->mainColor . ';padding:10px;border-radius:6px;">';
			
				$content .= '<b>' . $atts['currency'] . $atts['fee'] . '</b> credits added to your account!';
			
			$content .= '</div>';
		}
		
		return $content;
	
	}
	
	public function init_sponsorship(){
		
		$this->parent->user->is_sponsorship = $this->parent->programs->has_program('sponsor', $this->parent->user->ID, $this->parent->user->programs);
		
		if( is_admin() ){

			global $pagenow;
				
			if( $pagenow == 'users.php' ){
		
				// add tab in user panel
				
				add_action( 'ltple_user_tab', array($this, 'get_sponsorship_tab' ) );

				if( $this->parent->users->view == 'sponsors' ){
				
					// filter sponsorship users
					
					add_filter( 'pre_get_users', array( $this, 'filter_sponsors') );

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
				
				// subscription plan fields
				
				add_filter("add_subscription_plan_fields", array( $this, 'get_sponsored_plan_fields' ));
				
				add_filter("sponsor-invitation_custom_fields", array( $this, 'get_sponsor_invitation_fields' ));
				
				// save user programs
				
				add_action( 'personal_options_update', array( $this, 'save_user_sponsorship' ) );
				add_action( 'edit_user_profile_update', array( $this, 'save_user_sponsorship' ) );		
			}
		}
		else{
			
			//parse offline invitation
			
			if( !empty($_GET['su']) ){
				
				$request = $this->parent->ltple_decrypt_uri($_GET['su']);
				
				list($this->plan_id,$this->user_email,$time) = explode('-', $request);
			
				//get user id
				
				if($user = get_user_by( 'email', $this->user_email)){
			
					if( $user_seen = get_user_meta($user->ID,$this->parent->_base . '_last_seen',true)){
				
						$this->login_url = 	add_query_arg( array(
							
							'loe' 			=> $this->parent->ltple_encrypt_uri( $this->user_email ),
							'redirect_to' 	=> $this->parent->urls->current,
							
						), wp_login_url( 'http://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'] ));
					}
					else{
						
						global $wpdb, $wp_hasher;
						
						// get reset password url
						
						$key = wp_generate_password( 20, false );
						
						do_action( 'retrieve_password_key', $user->user_login, $key );
						
						// Now insert the key, hashed, into the DB.
						
						if ( empty( $wp_hasher ) ) {
							
							require_once ABSPATH . WPINC . '/class-phpass.php';
							
							$wp_hasher = new PasswordHash( 8, true );
						}
						
						$hashed = time() . ':' . $wp_hasher->HashPassword( $key );
						
						$wpdb->update( $wpdb->users, array( 'user_activation_key' => $hashed ), array( 'user_login' => $user->user_login ) );

						$this->register_url = 	add_query_arg( array(
							
							'action' 		=> 'rp',
							'key' 			=> $key,
							'login' 		=> rawurlencode($user->user_login),
							'redirect_to' 	=> urlencode(get_permalink($this->plan_id)),
							
						), network_site_url('wp-login.php','login') );					
					}
				}
			}
			
			// add shortcode
			
			add_shortcode('sponsored-plan', array( $this, 'get_sponsored_plan_shortcode' ) );
			
			// add invitations in plan schortcode
				
			add_action( 'ltple_plan_shortcode', array($this, 'get_invitations_dropdown' ) );
				
			// add sponsored plans in invitation form
			
			add_action( 'ltple_get_sponsorship_message', array($this, 'get_invitation_message' ) );
							
			// add sponsored plans in invitation form
			
			add_action( 'ltple_prepend_sponsorship_form', array($this, 'get_invitation_inputs' ) );
			
			// add update user after checkout
			
			add_action( 'ltple_update_user_plan', array( $this, 'update_sponsor_licenses'));
			
			//Add Custom API Endpoints
			
			add_action( 'rest_api_init', function(){
				
				register_rest_route( 'ltple-sponsored/v1', '/licenses/', array(
					
					'methods' 	=> 'GET',
					'callback' 	=> array($this,'get_sponsored_plan_rows'),
				));
				
				register_rest_route( 'ltple-sponsored/v1', '/invitations/', array(
					
					'methods' 	=> 'GET',
					'callback' 	=> array($this,'get_sponsored_users_rows'),
				));
			});
			
			// handle invitation acceptation
			
			if( !empty($_POST['sponsor_invitation_id']) ){
				
				if( $invitation_id = intval($_POST['sponsor_invitation_id']) ){
					
					// get invitation

					if( $q = get_posts(array(
								
						'post_type' 	=> 'sponsor-invitation',
						'ID' 			=> $invitation_id,
						'meta_query' 	=> array(	
							array(
							
								'key' 		=> 'sponsored_user_email',
								'value' 	=> $this->parent->user->user_email,									
							),
						)
					
					))){
						
						// get plan id
						
						if( $plan_id = intval(get_post_meta( $q[0]->ID, 'sponsored_plan_id' ,true )) ){
							
							// check last approval

							$unlocks = get_post_meta($q[0]->ID,'sponsored_plan_unlocked',true);
							
							$unlocked 	= false;
							$today		= date('Y-m-d');
							
							if( !empty($unlocks['date']) ){
								
								$date = end($unlocks['date']);

								$dStart = new DateTime($date);
								$dEnd  	= new DateTime($today);
								
								$dDiff = $dStart->diff($dEnd);
								
								if( 29 > $dDiff->days ){
									
									$unlocked = true;
								}
							}

							if( !$unlocked ){
							
								// check remaining licenses
									
								if( $sponsor_licenses = intval( get_user_meta($q[0]->post_author,$this->parent->_base . 'sponsored_licenses_'.$plan_id,true) ) ){
									
									// get plan options
									
									if( $plan_options = get_post_meta( $plan_id, 'plan_options', true ) ){
										
										// get plan title
										
										$plan_title = get_the_title($plan_id);
										
										// parse plan options
										
										$options = $this->parent->plan->get_layer_custom_taxonomies_options();

										foreach( $options as $taxonomy => $terms ) {
											
											$update_terms=[];
											$update_taxonomy='';
											
											foreach($terms as $i => $term){

												if ( in_array( $term->slug, $plan_options ) ) {
													
													$update_terms[]= $term->term_id;
													$update_taxonomy=$term->taxonomy;
												}
											}
											
											// update current user custom taxonomy
											
											$user_plan_id = $this->parent->plan->get_user_plan_id( $this->parent->user->ID, true );
											
											$append = true;

											$response = wp_set_object_terms( $user_plan_id, $update_terms, $update_taxonomy, $append );

											clean_object_term_cache( $user_plan_id, $update_taxonomy );
										}

										// discount sponsor license

										$sponsor_licenses = $sponsor_licenses - 1;
										
										update_user_meta($q[0]->post_author,$this->parent->_base . 'sponsored_licenses_'.$plan_id,$sponsor_licenses);
																				
										// unlock 

										$this->parent->plan->unlock_output_request('+30 days');
										
										// store unlock date
										
										$unlocks['date'][] = $today;
										
										update_post_meta($q[0]->ID,'sponsored_plan_unlocked',$unlocks);										
													
										// get sponsor data
										
										$sponsor = get_user_by('id',$q[0]->post_author);
									
										// output message
										
										$_SESSION['message'] ='<div class="alert alert-success">';
										
											$_SESSION['message'] .='Congratulations, thanks to <b>'. ucfirst($sponsor->data->nickname) .'</b> you have successfully unlocked <b>'. ucfirst($plan_title).'</b> for 30 days!';
										
										$_SESSION['message'] .='</div>';
									
										// send notification to sponsor
										
										$company	= ucfirst(get_bloginfo('name'));
										
										$dashboard_url = $this->parent->urls->editor . '?sponsorship';
										
										$title 		= 'Invitation accepted by ' . ucfirst($this->parent->user->nickname) . ' for ' . $plan_title;
										
										$content 	= '';
										$content 	.= 'Congratulations ' . ucfirst($sponsor->data->nickname) . '! ' . PHP_EOL . PHP_EOL;
										
										$content 	.= ucfirst($this->parent->user->nickname) . ' just accepted your invitation to use "' . $plan_title . '" for 30 days.' . PHP_EOL . PHP_EOL;
										
										if( !empty($_POST['sponsor_message']) ){
											
											if($sponsor_message = trim(strip_tags($_POST['sponsor_message']))){
											
												$content 	.= 'Additional message from ' . ucfirst($this->parent->user->nickname) . ': ' . PHP_EOL . PHP_EOL;
												
												$content 	.= '____________' . PHP_EOL . PHP_EOL;
												
													$content 	.= '' . $sponsor_message . '' . PHP_EOL . PHP_EOL;
												
												$content 	.= '____________' . PHP_EOL . PHP_EOL;
											}
										}
										
										$content 	.= 'You have now ' . $sponsor_licenses . ' remaining license'. ( $sponsor_licenses == 1 ? '' : 's' ) . ' for "' . $plan_title . '".' . PHP_EOL . PHP_EOL;
										
										$content 	.= 'We\'ll be here to help you with any step along the way. You can find answers to most questions and get in touch with us at ' . PHP_EOL . PHP_EOL;

										$content 	.= $dashboard_url . PHP_EOL . PHP_EOL;
										
										$content 	.= 'Yours,' . PHP_EOL;
										$content 	.= 'The ' . $company . ' team' . PHP_EOL . PHP_EOL;

										$content 	.= '==== Invitation Summary ====' . PHP_EOL . PHP_EOL;

										$content 	.= 'Plan unlocked: ' . $plan_title . PHP_EOL;
										$content 	.= 'Invited email: ' . $this->parent->user->user_email . PHP_EOL;
										$content 	.= 'Remaining licenses: ' . $sponsor_licenses . PHP_EOL;
										
										wp_mail($sponsor->data->user_email, $title, $content);
										
										if( $this->parent->settings->options->emailSupport != $sponsor->data->user_email ){
											
											wp_mail($this->parent->settings->options->emailSupport, $title, $content);
										}										
										
										// remove invitation
										
										//wp_delete_post( $invitation_id );
									}
								}
								else{
									
									$_SESSION['message'] ='<div class="alert alert-warning">';
									
										$_SESSION['message'] .='Expired invitation, please contact billing support...';
									
									$_SESSION['message'] .='</div>';
								}
							}
							else{
								
								$_SESSION['message'] ='<div class="alert alert-info">';
								
									$_SESSION['message'] .='You have already unlocked this plan using this invitation, use it again in <b>' . ( 30 - $dDiff->days ) . ' days</b>...';
								
								$_SESSION['message'] .='</div>';								
							}
						}
					}
				}
			}
			elseif( !$this->parent->user->loggedin && !empty($this->user_email) ){
				
				$_SESSION['message'] = '<div style="font-size:20px;padding:20px;margin:0px;" class="alert alert-warning">';
					
					$_SESSION['message'] .= 'An invitation is waiting for you but you need to log in first...';
					
					$_SESSION['message'] .= '<div class="pull-right">';
						
						if(!empty($this->login_url)){
						
							$_SESSION['message'] .= '<a style="margin:0 2px;" class="btn-lg btn-primary" href="' . $this->login_url . '">Let\'s go!</a>';
						}
						elseif(!empty($this->register_url)){
						
							$_SESSION['message'] .= '<a style="margin:0 2px;" class="btn-lg btn-primary" href="' . $this->register_url . '">Let\'s do it!</a>';
						}
						
					$_SESSION['message'] .= '</div>';
					
				$_SESSION['message'] .= '</div>';				
			}
		}
	}
	
	public function add_profile_tabs($tabs){
		
		// get tab position
		
		$tabs['sponsor']['position'] = 3;
		
		// get tab name
		
		$tabs['sponsor']['name'] = 'Sponsor';
		
		// get sponsor content
		
		if( $this->parent->profile->tab == 'sponsor' ){
		
			// get sponsorship plans
			
			$sponsorship_plans = array(
			
				array(
					
					'title' 	=> 'Send a smile',
					'content' 	=> 'If you have only one smile in you give it to the people you love.',
					'icon'		=> 'far fa-smile-wink',
					'price' 	=> 0,
					'fee' 		=> 5,
					
				),
				array(
					
					'title' 	=> 'Send support',
					'content' 	=> 'Anything is possible when you have the right people there to support you.',
					'icon'		=> 'fas fa-hand-holding-heart',
					'price' 	=> 0,
					'fee' 		=> 10,
					
				),	
				array(
					
					'title' 	=> 'Send a gift',
					'content' 	=> 'Love the giver more than the gift.',
					'icon'		=> 'fa fa-gift',
					'price' 	=> 0,
					'fee' 		=> 20,
					
				),	
				array(
					
					'title' 	=> 'Contribute',
					'content' 	=> 'It is through accomplishment that one makes his contribution and contribution is life’s greatest reward.',
					'icon'		=> 'fa fa-road',
					'price' 	=> 0,
					'fee' 		=> 50,
					
				),
				array(
					
					'title' 	=> 'Become a sponsor',
					'content' 	=> 'If you’re going to stay in the Olympics, you’ve got to be entertaining and get sponsorship.',
					'icon'		=> 'fa fa-ship',
					'price' 	=> 0,
					'fee' 		=> 100,
					
				),
			);

			$sponsored_id 		= $this->parent->profile->user->ID;
			$sponsored_email 	= $this->parent->profile->user->user_email;
			
			$currency = '$';		
		
			add_action( 'wp_enqueue_scripts',function(){

				wp_register_style( $this->parent->_token . '-sponsor-me', false, array());
				wp_enqueue_style( $this->parent->_token . '-sponsor-me' );
			
				wp_add_inline_style( $this->parent->_token . '-sponsor-me', '

					#sponsor {
						
						margin-top:15px;
					}
					
				');

			},10 );
			
			$tabs['sponsor']['content'] = '<div style="margin:0 15px;">';
				
				$tabs['sponsor']['content'] .= '<div class="col-xs-12 col-sm-5 pull-right">';
				
					$tabs['sponsor']['content'] .= '<div class="alert alert-info" style="margin: 40px 0px !important;">';

						$tabs['sponsor']['content'] 	.= 'Sponsor credits can only be used to purchase services on the platform. They are non-refundable & non-exchangeable.';
					
					$tabs['sponsor']['content'] 	.= '</div>';
				
				$tabs['sponsor']['content'] 	.= '</div>';
				
				$tabs['sponsor']['content'] .= '<div class="col-xs-12 col-sm-7">';

				$tabs['sponsor']['content'] .= '<h3>One time sponsorship</h3>';
				
				foreach( $sponsorship_plans as $plan ){
					 
					if( !empty($plan) ){
						 
						// get agreement url
						
						$agreement_url = $this->parent->plan->get_agreement_url( array(
						
							'id' 		=> 'sponsorship_' . $sponsored_id . '_' . $plan['price'] . '_' . $plan['fee'],
							'name' 		=> 'Sponsorship program',
							'price' 	=> $plan['price'],
							'fee' 		=> $plan['fee'],
							'currency' 	=> $currency,
							'sponsored'	=> $sponsored_email,
						));
											
						$modal_id='modal_'.md5($agreement_url);
						
						$tabs['sponsor']['content'] .='<div class="row well">';
							
							$tabs['sponsor']['content'] .='<div class="col-xs-2">';
								
								$tabs['sponsor']['content'] .='<i style="font-size:4.5rem;color:' . $this->parent->settings->mainColor . ' !important" class="'.$plan['icon'].'" aria-hidden="true"></i>';
								
							$tabs['sponsor']['content'] .='</div>';

							$tabs['sponsor']['content'] .='<div class="col-xs-7">';
								
								$tabs['sponsor']['content'] .= $plan['content'];
								
							$tabs['sponsor']['content'] .='</div>';					
							
							$tabs['sponsor']['content'] .='<div class="col-xs-3 text-center">';

								$tabs['sponsor']['content'] .='<div class="text-center" style="font-size:19px;font-weight:bold;">';
									
									$tabs['sponsor']['content'] .= $currency . $plan['fee'];
								
								$tabs['sponsor']['content'] .='</div>';
									
								//$tabs['sponsor']['content'] .='<hr style="margin-top: 7px;margin-bottom: 12px;border-top: 1px solid #dcdcdc;">';	
									
								$tabs['sponsor']['content'] .='<button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#'.$modal_id.'">'.PHP_EOL;
									
									$tabs['sponsor']['content'] .='Send'.PHP_EOL;

								$tabs['sponsor']['content'] .='</button>'.PHP_EOL;									

								$tabs['sponsor']['content'] .='<div class="modal fade" id="'.$modal_id.'" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">'.PHP_EOL;
									
									$tabs['sponsor']['content'] .='<div class="modal-dialog modal-lg" role="document">'.PHP_EOL;
										
										$tabs['sponsor']['content'] .='<div class="modal-content">'.PHP_EOL;
										
											$tabs['sponsor']['content'] .='<div class="modal-header">'.PHP_EOL;
												
												$tabs['sponsor']['content'] .='<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>'.PHP_EOL;
												
												$tabs['sponsor']['content'] .= '<h4 class="modal-title" id="myModalLabel">';
												
													$tabs['sponsor']['content'] .= $plan['title'];
												
												$tabs['sponsor']['content'] .= '</h4>'.PHP_EOL;
											
											$tabs['sponsor']['content'] .='</div>'.PHP_EOL;

											if( $this->parent->user->loggedin ){
												
												$tabs['sponsor']['content'] .= '<iframe data-src="' . $agreement_url . '" style="width: 100%;position:relative;top:-50px;margin-bottom:-60px;bottom: 0;border:0;height:' . $this->parent->plan->iframe_height . 'px;overflow: hidden;"></iframe>';
											}
											else{
												
												$tabs['sponsor']['content'] .='<div class="modal-body">'.PHP_EOL;
												
													$tabs['sponsor']['content'] .= '<div style="font-size:20px;padding:20px;margin:0px;" class="alert alert-warning">';
														
														$tabs['sponsor']['content'] .= 'You need to log in first...';
														
														$tabs['sponsor']['content'] .= '<div class="pull-right">';

															$tabs['sponsor']['content'] .= '<a style="margin:0 2px;" class="btn-lg btn-success" href="' . wp_login_url( 'http://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'] ) . '">Login</a>';
															
															$tabs['sponsor']['content'] .= '<a style="margin:0 2px;" class="btn-lg btn-info" href="'. wp_login_url( 'http://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'] ) .'&action=register">Register</a>';
														
														$tabs['sponsor']['content'] .= '</div>';
														
													$tabs['sponsor']['content'] .= '</div>';
												
												$tabs['sponsor']['content'] .='</div>'.PHP_EOL;
											}

										$tabs['sponsor']['content'] .='</div>'.PHP_EOL;
										
									$tabs['sponsor']['content'] .='</div>'.PHP_EOL;
									
								$tabs['sponsor']['content'] .='</div>'.PHP_EOL;
							
							$tabs['sponsor']['content'] .='</div>';	
							
						$tabs['sponsor']['content'] .='</div>';
					}
				}
				
				$tabs['sponsor']['content'] 	.= '</div>';
				
			$tabs['sponsor']['content'] 	.= '</div>';
		}
		
		return $tabs;
	}
	
	public function get_sponsored_plan_fields(){
			
		$this->parent->plan->fields[]=array(
		
			"metabox" =>
				array('name'=> "sponsor_packages"),
				'type'				=> 'values',
				'id'				=> 'sponsor_packages',
				'description'		=> '',
				'values'			=> array(
				
					'package' => array(
					
						'type' => 'text',
						'name' => 'Package',
					),
					'quantity' => array(
					
						'type' => 'number',
						'name' => 'Quantity',
					),
					'percent_off' => array(
					
						'type' => 'number',
						'name' => 'Percent Off',
					),
					'amount_off' => array(
					
						'type' => 'number',
						'name' => 'Amount Off',
					),
				),
		);
	}
	
	public function get_sponsor_invitation_fields(){
			
		$sponsored_plan = $this->get_sponsored_plans('post_title');
			
		$fields=[];
			
		$fields[]=array(
		
			"metabox" =>
			
				array('name'=> "sponsored_plan_id"),
				'type'				=> 'select',
				'id'				=> 'sponsored_plan_id',
				'description'		=> '',
				'options'			=> $sponsored_plan,
		);
		
		$fields[]=array(
		
			"metabox" =>
			
				array('name'=> "sponsored_user_email"),
				'type'				=> 'text',
				'id'				=> 'sponsored_user_email',
				'description'		=> '',
				'placeholder'		=> 'example@gmail.com',
		);
		
		$fields[]=array(
		
			"metabox" =>
			
			array('name'	=>"sponsored_plan_unlocked"),
			'id'			=>"sponsored_plan_unlocked",
			'name'			=>"sponsored_plan_unlocked",
			'type'			=>'values',
			'description'	=>'',
			'values'		=> array(
			
				'date' => array(
				
					'type' => 'text',
					'name' => 'Date',
				),
			),
		);
		
		return $fields;
	}
	
	public function get_sponsored_plans( $fields = 'all' ){
		
		// get and filter plans
		
		if( is_null($this->plans) ){
			
			if($q = get_posts(array(
			
				'post_type'   => 'subscription-plan',
				'post_status' => 'publish',
				'numberposts' => -1
				
			))){
			
				foreach( $q as $plan ){
					
					if( $packages = get_post_meta($plan->ID,'sponsor_packages',true)){
					
						if( !empty($packages['package']) && !empty($packages['package'][0]) ){
							
							$this->plans[$plan->ID] = $plan;
						}
					}
				}
			}			
		}
		
		// get fields
		
		if( !empty($this->plans) ){
			
			if( $fields == 'all' ){
				
				$sponsored_plans = $this->plans;
			}
			else{
			
				foreach( $this->plans as $plan ){

					if( is_string($fields) ){
						
						if( isset($plan->{$fields}) ){
							
							$sponsored_plans[$plan->ID] = $plan->{$fields};
						}
					}
					elseif( is_array($fields) ){
						
						foreach($fields as $field){
							
							if( isset($plan->{$field}) ){
								
								$sponsored_plans[$plan->ID][$field] = $plan->{$field};
							}								
						}
					}				
				}
			}
		}
		
		return $sponsored_plans;
	}
	
	public function get_invitation_message(){

		if( empty($this->parent->email->invitationMessage) ){
			
			$company = ucfirst(get_bloginfo('name'));
			
			$this->parent->email->invitationMessage = 'Hello, ' . PHP_EOL . PHP_EOL;
			
			$this->parent->email->invitationMessage .= 'I would like to become your sponsor on ' . $company . '.' . PHP_EOL . PHP_EOL;
			
			$this->parent->email->invitationMessage .= 'What do you think?' . PHP_EOL . PHP_EOL;
			
			$this->parent->email->invitationMessage .= 'Yours,' . PHP_EOL;
			$this->parent->email->invitationMessage .= ucfirst( $this->parent->user->nickname ) . PHP_EOL;
		}	
	}
	
	public function get_invitation_inputs(){
		
		if( $plans = $this->get_sponsored_plans('post_title') ){
			
			foreach($plans as $plan_id => $plan_title){
				
				$sponsor_licenses = intval( get_user_meta($this->parent->user->ID,$this->parent->_base . 'sponsored_licenses_'.$plan_id,true) );
			
				if( $sponsor_licenses > 0 ){
					
					$plans[$plan_id] = $plan_title . ' ( x'. $sponsor_licenses .' licenses ) ';
				}
				else{
					
					unset($plans[$plan_id]);
				}
			}
		}
		
		if( !empty($plans)){
			
			$this->parent->email->invitationForm .= '<h5 style="padding:15px 0 5px 0;font-weight:bold;">Sponsored Plan</h5>';
						
			$this->parent->email->invitationForm .= $this->parent->admin->display_field( array(
			
				'id' 			=> 'importPlanId',
				'label'			=> 'Sponsored Plan',
				'description'	=> '',
				'default'		=> ( !empty($_POST['importPlanId']) ? $_POST['importPlanId'] : ''),
				'class'			=> 'form-control',
				'type'			=> 'select',
				'options'		=> $plans,
			), false, false );	
		}
		else{
			
			$this->parent->email->invitationForm .= '<div class="alert alert-warning">';
			
				$this->parent->email->invitationForm .= 'You don\'t have any remaining licenses left...';
			
			$this->parent->email->invitationForm .= '</div>';
		}
		
		$this->parent->email->invitationForm .= '<hr/>';
	}
	
	public function get_sponsor_invitations($plan_id){
		
		$sponsor_invitations = [];
		
		if( is_null($this->invitations) ){
		
			if($invitations = get_posts(array(
								
				'post_type' 	=> 'sponsor-invitation',

				'meta_query' 	=> array(
					array(
					
						'key' 		=> 'sponsored_plan_id',
						'value' 	=> $plan_id,
					),	
					array(
					
						'key' 		=> 'sponsored_user_email',
						'value' 	=> $this->parent->user->user_email,									
					),
				)
							
			))){
				
				foreach( $invitations as $invitation ){
					
					if( $metadata = get_post_meta($invitation->ID) ){
						
						foreach( $metadata as $meta => $data ){
							
							if( isset($data[0]) ){
								
								$invitation->{$meta} = $data[0];
							}
						}
					}

					$this->invitations[] = $invitation;
				}
			}
		}
		
		// filter plan
		
		if( !empty($this->invitations) && !empty($plan_id)){
			
			foreach($this->invitations as $invitation){
				
				if( !empty($invitation->sponsored_plan_id) && intval($invitation->sponsored_plan_id) == $plan_id ){
					
					// check last approval

					$unlocks = unserialize($invitation->sponsored_plan_unlocked);

					$unlocked 	= false;
					$today		= date('Y-m-d');
					
					if( !empty($unlocks['date']) ){
						
						$date = end($unlocks['date']);

						$dStart = new DateTime($date);
						$dEnd  	= new DateTime($today);
						
						$dDiff = $dStart->diff($dEnd);
						
						if( 29 > $dDiff->days ){
							
							$unlocked = true;
						}
					}					
					
					
					
					if(!$unlocked){
					
						// check remaining licenses
						
						if( $sponsor_licenses = intval( get_user_meta($invitation->post_author,$this->parent->_base . 'sponsored_licenses_'.$invitation->sponsored_plan_id,true) ) ){
						
							$sponsor_invitations[$invitation->ID] = 'x1 invitation from ' . ucfirst(get_the_author_meta('nickname',$invitation->post_author)).' ';
						}
					}
				}
			}
		}

		return $sponsor_invitations;
	}
	
	public function filter_sponsors( $query ) {

		// alter the user query to add my meta_query
		
		$query->set( 'meta_query', array(
		
			array(
			
				'key' 		=> $this->parent->_base . 'user-programs',
				'value' 	=> 'sponsor',
				'compare' 	=> 'LIKE'
			),
		));
	}
	
	public function get_sponsorship_tab(){
		
		echo '<a class="nav-tab ' . ( $this->parent->users->view == 'sponsors' ? 'nav-tab-active' : '' ) . '" href="users.php?ltple_view=sponsors">Sponsors</a>';
	}
	
	public function update_sponsors_table($column) {
		
		$column=[];
		$column["cb"]			= '<input type="checkbox" />';
		$column["username"]		= 'Username';
		$column["email"]		= 'Email';
		
		return $column;
	}
	
	public function modify_sponsors_table_row($val, $column_name, $user_id) {
		
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

	public function save_user_sponsorship( $user_id ) {
		
		if(isset($_POST[$this->parent->_base . 'user-programs'])){

			if( in_array( 'sponsor', $_POST[$this->parent->_base . 'user-programs']) ){
				
				//$this->parent->email->schedule_trigger( 'sponsorship-approved',  $user_id);
			}
		}
	}
	
	public function get_sponsored_users() {
		
		$sponsored_users = array();
		
		if( $invitations = get_posts(array(
								
			'post_type' 	=> 'sponsor-invitation',
			'author' 		=> $this->parent->user->ID,
			'post_status' 	=> 'publish',
			'posts_per_page'=> -1,
			'fields'		=> 'ids',
		))){
			
			foreach($invitations as $invitation_id){
				
				if( $user_email = get_post_meta($invitation_id,'sponsored_user_email',true)){

					if( $user = get_user_by('email',$user_email)){
				
						$sponsored_users[] = $user->data;
					}
				}
			}
		}

		return $sponsored_users;
	}
	
	public function get_sponsored_users_rows($request) {

		$sponsor_users = [];

		if( $this->parent->user->loggedin ){
		
			if($sponsored_users = $this->get_sponsored_users()){
			
				foreach( $sponsored_users as $user ){
					
					$user_seen = get_user_meta($user->ID,$this->parent->_base . '_last_seen',true);
					
					$item = [];
					
					if( !empty( $user->display_name ) ){
						
						$item['name'] 	= $user->display_name;
					}
					else{
						
						$item['name'] 	= $user->nickname;
					}
					
					$item['email'] 		= $user->user_email;
					
					$item['last_seen'] 	= $this->parent->users->time_ago( '@' . $user_seen );

					$sponsor_users[] = $item;
				}
			}
		}
		
		return $sponsor_users;
	}

	public function get_sponsored_plan_rows($request) {

		$rows = [];

		if( $this->parent->user->loggedin ){
		
			$sponsor_id = $this->parent->user->ID;
		
			if($sponsored_plans = $this->get_sponsored_plans()){
		
				foreach( $sponsored_plans as $plan ){

					$plan_thumb = $this->parent->plan->get_thumb_url($plan->ID);
			
					$user_licenses = intval(get_user_meta($sponsor_id,$this->parent->_base . 'sponsored_licenses_'.$plan->ID,true));
			
					// get item
			
					$item = [];

					$item['plan'] 	= '<b>' . $plan->post_title . '</b>';
					
					$item['cover'] 	= '<div style="width:250px;"><img src="'.$plan_thumb.'" style="width:100%;" /></div>';
					
					if( !empty($plan->post_excerpt) ){
							
						$item['description'] = $plan->post_excerpt;
					}
					else{
						
						$item['description'] = strip_tags($plan->post_content,'<span>');
					}
					
					$item['licenses'] = '<div style="width:90px;text-align:center;padding-top:15px;"><span class="label '.($user_licenses > 0 ? 'label-success' : 'label-warning').'" style="font-size:20px;">' . $user_licenses . '</span></div>';

					// get purchase button
					
					$iframe_height = 500;
					
					$plan_url = get_permalink($plan->ID) . '?sc=sponsored-plan&output=widget';

					$modal_id='modal_'.md5($plan_url);
					
					$item['action'] = '<div style="width:100px;text-align:center;padding:10px 10px 0 10px;">';
					
						$item['action'].='<button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#'.$modal_id.'">'.PHP_EOL;
						
							$item['action'].='Purchase'.PHP_EOL;

						$item['action'].='</button>'.PHP_EOL;
						
					$item['action'] .= '</div>';
					
					$item['action'] .='<div class="modal fade" id="'.$modal_id.'" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">'.PHP_EOL;
						
						$item['action'] .='<div class="modal-dialog modal-lg" role="document">'.PHP_EOL;
							
							$item['action'] .='<div class="modal-content">'.PHP_EOL;
							
								$item['action'] .='<div class="modal-header">'.PHP_EOL;
									
									$item['action'] .='<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>'.PHP_EOL;
									
									$item['action'] .= '<h4 class="modal-title" id="myModalLabel">';
									
										$item['action'] .= $plan->post_title;
										
									$item['action'] .= '</h4>'.PHP_EOL;
								
								$item['action'] .='</div>'.PHP_EOL;

								$item['action'] .= '<iframe src="'.$plan_url.'" style="width:100%;bottom: 0;border:0;height:' . ($iframe_height - 10 ) . 'px;overflow: hidden;"></iframe>';

							$item['action'] .='</div>'.PHP_EOL;
							
						$item['action'] .='</div>'.PHP_EOL;
						
					$item['action'] .='</div>'.PHP_EOL;						
					
					/*
					$item['action'] .= '<div style="width:100px;text-align:center;padding:10px 10px 0 10px;">';
					
						$item['action'] .= '<a style="width:100%;" href="#" class="btn btn-sm btn-info">Invite</a>';
					
					$item['action'] .= '</div>';
					*/
					
					$rows[] = $item;
				}
			}
		}
		
		return $rows;
	}
	
	
	public function get_sponsored_plan_shortcode($atts){
		
		$atts = shortcode_atts( array(
		
			'id'		 	=> NULL,
			'widget' 		=> 'false',
			
		), $atts, 'sponsored-plan' );	
		
		$sponsored_plan='';
		
		if(!is_null($atts['id'])&&is_numeric($atts['id'])){
			
			$id = intval($atts['id']);	
			
			if( $plan = get_post($id) ){
			
				if( $plan->post_status == 'publish' && $plan->post_type == 'subscription-plan' ){
					
					if( $packages = get_post_meta($plan->ID,'sponsor_packages',true)){
					
						if( !empty($packages['package']) && !empty($packages['package'][0]) ){
							
							$plan_fee_amount 		= 0;
							$plan_price_amount 		= 0;
							
							$total_price_amount 	= 0;
							$total_fee_amount 		= 0;
							
							$total_price_period		='month';
							$total_fee_period		='once';
							$total_price_currency	='$';
							
							$option_name='plan_options';
							
							$options = $this->parent->plan->get_layer_custom_taxonomies_options();
							
							if($data = get_post_meta( $id, $option_name, true )){							
								
								// get total_price_amount & total_storage
								
								foreach( $options as $taxonomy => $terms ) {
									
									$taxonomy_options = [];
									
									foreach($terms as $i => $term){

										$taxonomy_options[$i] = $this->parent->layer->get_options( $taxonomy, $term );
										
										if ( in_array( $term->slug, $data ) ) {						
											
											$plan_price_amount = $this->parent->plan->sum_custom_taxonomy_total_price_amount( $plan_price_amount, $taxonomy_options[$i], $total_price_period);	
											$plan_fee_amount 	= $this->parent->plan->sum_custom_taxonomy_total_price_amount( $plan_fee_amount, $taxonomy_options[$i], $total_fee_period);				
											$total_storage 		= $this->parent->plan->sum_custom_taxonomy_total_storage( $total_storage, $taxonomy_options[$i]);
										}
									}
								}
								
								// round plan amounts
								
								$plan_fee_amount 	= round($plan_fee_amount, 2);
								$plan_price_amount 	= round($plan_price_amount, 2);
								
								ksort($total_storage);
										
								$iframe_height = 500;		
										
								// output table	
										
								$sponsored_plan.= '<div class="row panel-body" style="background:#fff;">';
								
									$sponsored_plan.= '<div class="col-xs-12 col-md-8">';
										
										$sponsored_plan.= '<div class="page-header" style="margin:0px;padding-bottom: 5px;">';
										
											$sponsored_plan.= '<h2>Sponsored "' . $plan->post_title . '" licenses</h2>';
											
										$sponsored_plan.= '</div>';

										$sponsored_plan.= '<table class="table table-striped">';
										$sponsored_plan.= '<thead>';
										
										$sponsored_plan.= '<tr>';
										
											$sponsored_plan.= '<th><b>Package</b></th>';
											
											$sponsored_plan.= '<th style="text-align:center;"><b>Base</b></th>';
											
											$sponsored_plan.= '<th style="text-align:center;"><b>Quantity</b></th>';
											$sponsored_plan.= '<th style="text-align:center;"><b>Percent off</b></th>';
											//$sponsored_plan.= '<th style="text-align:center;"><b>Amount off</b></th>';
											
											$sponsored_plan.= '<th style="text-align:center;"><b>Price</b></th>';
											$sponsored_plan.= '<th style="text-align:center;"><b>TOTAL</b></th>';
											$sponsored_plan.= '<th></th>';
											
										$sponsored_plan.= '</tr>';
										
										$sponsored_plan.= '</thead>';
										$sponsored_plan.= '<tbody>';
										
											foreach( $packages['package'] as $i => $package_name){
												
												$plan_quantity 		= intval( $packages['quantity'][$i] );
												$plan_percent_off 	= intval( $packages['percent_off'][$i] );
												$plan_amount_off 	= intval( $packages['amount_off'][$i] );
												
												// get total fee amount
												
												$discounted_fee_amount 	= round(( ( ( $plan_fee_amount + $plan_price_amount ) * ( 1 - ( $plan_percent_off / 100 ) ) ) - $plan_amount_off ), 2);
												
												$total_fee_amount 	= round(( $discounted_fee_amount * $plan_quantity ), 2);

												//get plan_data

												$plan_data = [];
												
												$plan_data['id'] 			= $plan->ID;
												$plan_data['name'] 			= $package_name;
												$plan_data['price'] 		= 0;
												$plan_data['fee'] 			= $discounted_fee_amount;
												$plan_data['currency']		= $total_price_currency;
												$plan_data['period'] 		= $total_price_period;
												$plan_data['fperiod']		= $total_fee_period;
												$plan_data['storage'] 		= $total_storage;
												$plan_data['quantity'] 		= $plan_quantity;
												$plan_data['percent_off'] 	= $plan_percent_off;
												$plan_data['amount_off'] 	= $plan_amount_off;
												$plan_data['subscriber']	= $this->parent->user->user_email;
												$plan_data['client']		= $this->parent->client->url;
												//$plan_data['meta']		= ( !empty($_SESSION['pm_' . $plan->ID]) ? $_SESSION['pm_' . $plan->ID] : '' );
												
												$plan_data = esc_attr( json_encode( $plan_data ) );
												
												//var_dump($plan_data);exit;

												$plan_key=md5( 'plan' . $plan_data . $this->parent->_time . $this->parent->user->user_email );	

												//get agreement url				
												
												$agreement_url = $this->parent->server->url . '/agreement/?pk='.$plan_key.'&pd='.$this->parent->base64_urlencode($plan_data) . '&_=' . $this->parent->_time;
													
												//get modal id
													
												$modal_id='modal_'.md5($agreement_url);
												
												// output row
												
												$sponsored_plan.= '<tr>';
													
													$sponsored_plan.= '<td>'.$package_name.'</td>';
													
													$sponsored_plan.= '<td style="width:95px;text-align:center;">'.$total_price_currency.( $plan_fee_amount + $plan_price_amount ).'</td>';
													
													$sponsored_plan.= '<td style="width:95px;text-align:center;">'.$plan_quantity.'</td>';
													$sponsored_plan.= '<td style="width:95px;text-align:center;">'.$plan_percent_off.'%</td>';
													//$sponsored_plan.= '<td style="width:95px;text-align:center;">'.$total_price_currency.$plan_amount_off.'</td>';
													
													$sponsored_plan.= '<td style="width:95px;text-align:center;">'.$total_price_currency.$discounted_fee_amount.'</td>';
													$sponsored_plan.= '<td style="width:95px;text-align:center;"><b>'.$total_price_currency.$total_fee_amount.'</b></td>';
													
													$sponsored_plan.= '<td style="width:40px;">';
														
														if( !empty($_GET['output']) && $_GET['output'] == 'widget' ){
															
															$sponsored_plan.='<a href="'.$agreement_url.'" type="button" class="btn btn-primary btn-xs">';
																
																$sponsored_plan.='Purchase';
																
															$sponsored_plan.='</a>';															
														}
														else{
															
															$sponsored_plan.='<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#'.$modal_id.'">';
																
																$sponsored_plan.='Purchase';
																
															$sponsored_plan.='</button>';

															$sponsored_plan.='<div class="modal fade" id="'.$modal_id.'" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">'.PHP_EOL;
																
																$sponsored_plan.='<div class="modal-dialog modal-lg" role="document">'.PHP_EOL;
																	
																	$sponsored_plan.='<div class="modal-content">'.PHP_EOL;
																	
																		$sponsored_plan.='<div class="modal-header">'.PHP_EOL;
																			
																			$sponsored_plan.='<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>'.PHP_EOL;
																			
																			$sponsored_plan.= '<h4 class="modal-title" id="myModalLabel">';
																			
																				$sponsored_plan.= $plan->post_title;
																				
																				$sponsored_plan.= ' - '.$package_name;
																				
																			$sponsored_plan.= '</h4>'.PHP_EOL;
																		
																		$sponsored_plan.='</div>'.PHP_EOL;

																		if( $this->parent->user->loggedin ){
																			
																			$sponsored_plan.= '<iframe data-src="' . $agreement_url . '" style="width: 100%;position:relative;top:-50px;margin-bottom:-60px;bottom: 0;border:0;height:'.$iframe_height.'px;overflow: hidden;"></iframe>';
																		}
																		else{
																			
																			$sponsored_plan.='<div class="modal-body">'.PHP_EOL;
																			
																				$sponsored_plan.= '<div style="font-size:20px;padding:20px;margin:0px;" class="alert alert-warning">';
																					
																					$sponsored_plan.= 'You need to log in first...';
																					
																					$sponsored_plan.= '<div class="pull-right">';

																						$sponsored_plan.= '<a style="margin:0 2px;" class="btn-lg btn-success" href="' . wp_login_url( 'http://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'] ) . '">Login</a>';
																						
																						$sponsored_plan.= '<a style="margin:0 2px;" class="btn-lg btn-info" href="'. wp_login_url( 'http://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'] ) .'&action=register">Register</a>';
																					
																					$sponsored_plan.= '</div>';
																					
																				$sponsored_plan.= '</div>';
																			
																			$sponsored_plan.='</div>'.PHP_EOL;
																		}

																	$sponsored_plan.='</div>'.PHP_EOL;
																	
																$sponsored_plan.='</div>'.PHP_EOL;
																
															$sponsored_plan.='</div>'.PHP_EOL;														
														}
														
													$sponsored_plan.= '</td>';
													
												$sponsored_plan.= '</tr>';
											}

										$sponsored_plan.= '</tbody>';
										$sponsored_plan.= '</table>';

									$sponsored_plan.= '</div>';
									
								$sponsored_plan.= '</div>';
							}
						}
					}
				}
			}
		}
		
		return $sponsored_plan;
	}
	
	public function update_sponsor_licenses(){
		
		if( !empty($this->parent->plan->data['name']) && !empty($this->parent->plan->data['id']) && !empty($this->parent->plan->data['quantity']) ){
		
			$last_pk = get_user_meta( $this->parent->user->ID, $this->parent->_base . 'last_sponsor_pk',true);
			
			if( empty($last_pk) || $last_pk != $_GET['pk'] ){
				
				//set last plan key
				
				$last_pk = $_GET['pk'];
				
				// get user licenses 
				
				$licenses = intval(get_user_meta( $this->parent->user->ID, $this->parent->_base . 'sponsored_licenses_'.$this->parent->plan->data['id'],true));
				
				// add new licenses 
				
				$licenses += intval($this->parent->plan->data['quantity']);
				
				// update licenses
				
				update_user_meta( $this->parent->user->ID, $this->parent->_base . 'sponsored_licenses_'.$this->parent->plan->data['id'], $licenses);
				
				// update last pk
				
				update_user_meta( $this->parent->user->ID, $this->parent->_base . 'last_sponsor_pk', $last_pk);
				
				// output success message
				
				$this->parent->plan->message .= '<div class="alert alert-success">';
					
					$this->parent->plan->message .= 'Thanks for purchasing '.$this->parent->plan->data['name'].'!';

				$this->parent->plan->message .= '</div>';	
			}
			elseif( !empty($last_pk) && $last_pk == $_GET['pk'] ){
				
				// output success message
				
				$this->parent->plan->message .= '<div class="alert alert-success">';
					
					$this->parent->plan->message .= 'Thanks for purchasing '.$this->parent->plan->data['name'].'!';

				$this->parent->plan->message .= '</div>';	
			}
		}
	}
	
	public function get_invitations_dropdown($plan_id){
	
		if( empty($this->parent->plan->buttons[$plan_id]['sponsored']) ){
		
			$sponsored_plan = $this->get_sponsored_plans('post_title');
		
			if( !empty($sponsored_plan[$plan_id]) ){
				
				if($this->parent->user->loggedin){

					// get sponsor invitations
					
					$sponsor_invitations = $this->get_sponsor_invitations($plan_id);
					
					if( !empty($sponsor_invitations) ){

						$button = '<a class="btn btn-lg btn-primary" href="#sponsorInvitation" data-toggle="dialog" data-target="#sponsorInvitation">Active Invitation</a>';

						$button .='<div id="sponsorInvitation" title="Sponsor invitation">';
							
							$button .= '<form action="" method="post" class="center-block">';
								
								$button .= '<b>Pending invitations</b>';
								
								$button .= $this->parent->admin->display_field( array(
								
									'type'				=> 'select',
									'id'				=> 'sponsor_invitation_id',
									'name'				=> 'sponsor_invitation_id',
									'options' 			=> $sponsor_invitations,
									'style' 			=> 'text-align:center;',
									'description'		=> '<i style="font-size:11px;">* Free License (30 days)</i>',
									
								), false, false );
								
								$button .= '<hr/>';
								
								$button .= '<b>Additional message</b>';
							
								$button .= $this->parent->admin->display_field( array(
								
									'type'				=> 'textarea',
									'id'				=> 'sponsor_message',
									'name'				=> 'sponsor_message',
									'default'			=> 'Thanks for inviting me!',
									'style'				=> 'height:100px;',
									'description'		=> '',
									
								), false, false );							
							
								$button .= '<hr/>';
							
								$button .='<div class="ui-helper-clearfix ui-dialog-buttonset">';

									$button .='<button class="btn btn-xs btn-primary pull-right" type="submit" id="duplicateBtn" style="border-radius:3px;margin-top: 5px;">Accept invitation</button>';
							 
								$button .='</div>';
							
							$button .= '</form>';							
							
						$button .='</div>';
						
						$this->parent->plan->buttons[$plan_id]['sponsored'] = $button;
					}
				}
				elseif( !empty($this->plan_id) && $this->plan_id == $plan_id){
					
					if(!empty($this->login_url)){
						
						$url = $this->login_url;
					}
					elseif(!empty($this->register_url)){
						
						$url = $this->register_url;
					}
					
					$button = '<a class="btn btn-lg btn-primary" href="' . $url . '">Active Invitation</a>';

					$this->parent->plan->buttons[$plan_id]['sponsored'] = $button;
				}
			}
		}
	}
	
	public function schedule_invitations(){
		
		// get users
				
		$users = array();
		
		if(!empty($this->parent->email->imported['imported'])){
			
			$users = $this->parent->email->imported['imported'];
		}
		
		if(!empty($this->parent->email->imported['already registered'])){
		
			$users = array_merge($users,$this->parent->email->imported['already registered']);
		}
		
		if(!empty($users)){
			
			// get sponsored plan
					
			if( !empty($_POST['importPlanId']) ){
				
				if( $plan = get_post($_POST['importPlanId']) ){
					
					// get plan thumb
				
					$plan_thumb = $this->parent->plan->get_thumb_url($plan->ID);
					
					// get company name
					
					$company = ucfirst(get_bloginfo('name'));
					
					// make invitations
					
					$m = 0;
					
					foreach($users as $i => $user){
						
						// get plan permalink
					
						$plan_url = add_query_arg( array(
							
							'su' 	=> $this->parent->ltple_encrypt_uri( $_POST['importPlanId'] . '-' . $user['email'] . '-' . time() ),
							'ri' 	=> $this->parent->user->refId,
							
						), get_permalink($plan->ID) ); 
						
						$can_spam = get_user_meta( $user['id'], $this->parent->_base . '_can_spam',true);

						if( $can_spam !== 'false' ){
						
							//get invitation title
							
							$invitation_title = 'Sponsor invitation - ' . ucfirst($this->parent->user->nickname) . ' wants to become your sponsor for ' . $plan->post_title . ' on ' . $company . ' ';
							
							//check if invitation exists

							if( !$invitation = get_posts(array(
								
								'post_type' 	=> 'sponsor-invitation',
								'author' 		=> $this->parent->user->ID,

								'meta_query' 	=> array(
									array(
									
										'key' 		=> 'sponsored_plan_id',
										'value' 	=> strval($plan->ID),
									),	
									array(
									
										'key' 		=> 'sponsored_user_email',
										'value' 	=> $user['email'],									
									),
								)
							
							))){

								//get invitation content
								
								$invitation_content = '<table style="width: 100%; max-width: 100%; min-width: 320px; background-color: #f1f1f1;margin:0;padding:40px 0 45px 0;margin:0 auto;text-align:center;border:0;">';
											
									$invitation_content .= '<tr>';
										
										$invitation_content .= '<td>';
											
											$invitation_content .= '<table style="width: 100%; max-width: 600px; min-width: 320px; background-color: #FFFFFF;border-radius:5px 5px 0 0;-moz-border-radius:5px 5px 0 0;-ms-border-radius:5px 5px 0 0;-o-border-radius:5px 5px 0 0;-webkit-border-radius:5px 5px 0 0;text-align:center;border:0;margin:0 auto;font-family: Arial, sans-serif;">';
												
												$invitation_content .= '<tr>';
													
													$invitation_content .= '<td style="text-align:center;background-color:#ffffff;border-radius:5px 5px 0 0;-moz-border-radius:5px 5px 0 0;-ms-border-radius:5px 5px 0 0;-o-border-radius:5px 5px 0 0;-webkit-border-radius:5px 5px 0 0;background-image: url('.$plan_thumb.');background-repeat:no-repeat;background-size:100% auto;background-position:top center;overflow:hidden;">';
														
														$invitation_content .= '<a href="'.$plan_url.'" target="_blank" title="'.$company.'" style="display:block;width:90%;height:350px;text-align:left;overflow:hidden;font-size:24px;color:#FFFFFF!important;text-decoration:none;font-weight:bold;padding:16px 14px 9px;font-family:Arial, Helvetica, sans-serif;position:reltive;margin:0 auto;">&nbsp;</a>';
														
													$invitation_content .= '</td>';
												
												$invitation_content .= '</tr>';
												
												$invitation_content .= '<tr>';
													
													$invitation_content .= '<td style="font-family: Arial, sans-serif;padding:10px 0 15px 0;font-size:19px;color:#888888;font-weight:bold;border-bottom:1px solid #cccccc;text-align:center;background-color:#FFFFFF;">';
														
														$invitation_content .= 'Sponsorship Invitation';
														
													$invitation_content .= '</td>';
												
												$invitation_content .= '</tr>';
												
												$invitation_content .= '<tr>';	

													$invitation_content .= '<td style="line-height: 25px;font-family: Arial, sans-serif;padding:20px;font-size:15px;color:#666666;text-align:left;font-weight: normal;border:0;background-color:#FFFFFF;">';
														
														$invitation_content .= 'Hello *|FNAME|*,' . PHP_EOL . PHP_EOL;
														
														$invitation_content .= ucfirst($this->parent->user->nickname) . ' wants to become your sponsor and offer you a free access to <b>' . $plan->post_title . '</b> on <b>' . $company . '</b> during <b>30 days</b>!' . PHP_EOL . PHP_EOL;
														
													$invitation_content .=  '</td>';
																
												$invitation_content .= '</tr>';
														
												if( !empty($_POST['importMessage']) ){
												
													$invitation_content .= '<tr>';	

														$invitation_content .= '<td style="line-height: 25px;font-family: Arial, sans-serif;padding:10px 20px ;font-size:15px;color:#666666;text-align:left;font-weight: normal;border:0;background-color:#FFFFFF;">';
																												
															$invitation_content .= 'Additional message from ' . ucfirst($this->parent->user->nickname) . ': ' . PHP_EOL;
																
														$invitation_content .=  '</td>';
																
													$invitation_content .= '</tr>';

													$invitation_content .= '<tr>';													
																
														$invitation_content .= '<td style="background: rgb(248, 248, 248);display:block;padding:20px;margin:20px;text-align:left;border-left: 5px solid #888;">';
																
															$invitation_content .= $_POST['importMessage'];
														
														$invitation_content .=  '</td>';
																
													$invitation_content .= '</tr>';														
												}

												$invitation_content .= '<tr>';	

													$invitation_content .= '<td style="font-family: Arial, sans-serif;height:150px;font-size:16px;color:#666666;text-align:center;border:0;background-color:#FFFFFF;">';
																													
														$invitation_content .=  '<a style="background: #ff9800;color: #fff;padding: 17px;text-decoration: none;border-radius: 5px;font-weight: bold;font-size: 20px;" href="'.$plan_url.'">See my invitation </a>' . PHP_EOL . PHP_EOL;

													$invitation_content .=  '</td>';
												$invitation_content .=  '</tr>';
											$invitation_content .=  '</table>';
											
										$invitation_content .=  '<td>';
									$invitation_content .=  '<tr>';
								$invitation_content .=  '</table>';
								
								$invitation_content = str_replace(PHP_EOL,'<br/>',$invitation_content);
								
								//insert invitation
								
								if($invitation_id = wp_insert_post( array(
								
									'post_type'     	=> 'sponsor-invitation',
									'post_title' 		=> $invitation_title,
									'post_content' 		=> $invitation_content,
									'post_status' 		=> 'publish',
									'menu_order' 		=> 0
								))){
									
									update_post_meta($invitation_id,'sponsored_user_email',$user['email']);
									
									update_post_meta($invitation_id,'sponsored_plan_id',$plan->ID);
									
									if( $i == 0 ){
									
										$this->parent->email->send_model($invitation_id,$user['email']);
									}
									else{
										
										wp_schedule_single_event( ( time() + ( 60 * $m ) ) , $this->parent->_base . 'send_email_event' , [$invitation_id,$user['email']] );
									}
									
									if ($i % 10 == 0) {
										
										++$m;
									}									
								}
							}
						}
					}
				}
			}
		}
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
		
		//wp_register_style( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'css/frontend.css', array(), $this->_version );
		//wp_enqueue_style( $this->_token . '-frontend' );
	
	} // End enqueue_styles ()

	/**
	 * Load frontend Javascript.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function enqueue_scripts () {
		
		//wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );
		//wp_enqueue_script( $this->_token . '-frontend' );
	
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
