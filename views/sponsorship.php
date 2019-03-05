<?php 

	if(isset($_SESSION['message'])){ 
	
		//output message
	
		echo $_SESSION['message'];
		
		//reset message
		
		$_SESSION['message'] ='';
	}
 
	if( $this->parent->user->is_sponsorship ){
						
		$sponsorship_name = ucfirst(get_bloginfo('name'));
	
		$currentTab = 'licenses';
	
		if( !empty($_GET['sponsorship']) ){
			
			$currentTab = $_GET['sponsorship'];
		}

		// ------------- output panel --------------------
		
		echo'<div id="media_library" class="wrapper">';

			echo '<div id="sidebar">';
			
				echo'<ul class="nav nav-tabs tabs-left">';
					
					echo'<li class="gallery_type_title">Sponsorship Program</li>';
					
					//echo'<li'.( $currentTab == 'overview' ? ' class="active"' : '' ).'><a href="'.$this->parent->urls->editor . '?sponsorship=overview">Overview</a></li>';
					
					echo'<li'.( $currentTab == 'licenses' ? ' class="active"' : '' ).'><a href="'.$this->parent->urls->editor . '?sponsorship=licenses">Licenses</a></li>';
					
					echo'<li'.( $currentTab == 'invitations' ? ' class="active"' : '' ).'><a href="'.$this->parent->urls->editor . '?sponsorship=invitations">Invitations</a></li>';

				echo'</ul>';
				
			echo'</div>';

			echo'<div id="content" style="border-left: 1px solid #ddd;background:#fff;padding-bottom:15px;min-height:500px;">';
				
				echo'<div class="tab-content">';
					
					/*
					if( $currentTab == 'overview' ){
						
						//overview

						echo'<div class="tab-pane active" id="overview">';
						
							echo'<div class="bs-callout bs-callout-primary">';
							
								echo'<h4>';
								
									echo'Overview';
									
								echo'</h4>';
							
								echo'<p>';
								
									echo 'List of licenses';
								
								echo'</p>';	

							echo'</div>';							

							echo'<div class="tab-content row">';

								echo'<div class="col-xs-12">';

										// get table of invited people

										$this->parent->api->get_table(
										
											$this->parent->urls->api . 'ltple-sponsored/v1/users', 
											array(
											
												array(
				
													'field' 	=> 'name',
													'sortable' 	=> 'true',
													'content' 	=> 'Name',
												),
												array(
				
													'field' 	=> 'email',
													'sortable' 	=> 'true',
													'content' 	=> 'Email',
												),
												array(
				
													'field' 	=> 'last_seen',
													'sortable' 	=> 'true',
													'content' 	=> 'Seen',
												),
											), 
											$trash		= false,
											$export		= false,
											$search		= true,
											$toggle		= false,
											$columns	= true,
											$header		= true,
											$pagination	= true,
											$form		= false,
											$toolbar 	= 'toolbar'
										);									
								
								echo'</div>';
								
							echo'</div>';						
							
						echo'</div>'; //overview
						
					}
					*/
					if( $currentTab == 'licenses' ){
						
						echo'<div class="tab-pane active" id="licenses">';
						
							echo'<div class="bs-callout bs-callout-primary">';
							
								echo'<h4>';
								
									echo'Licenses';
									
								echo'</h4>';
							
								echo'<p>';
								
									echo 'Purchase Licenses in bulk';
								
								echo'</p>';	

							echo'</div>';							

							echo'<div class="tab-content row" style="padding-top:30px;">';

								echo'<div class="col-xs-12">';

										// get table of invited people

										$this->parent->api->get_table(
										
											$this->parent->urls->api . 'ltple-sponsored/v1/licenses', 
											array(
											
												array(
				
													'field' 	=> 'plan',
													'sortable' 	=> 'true',
													'content' 	=> 'Plan',
												),
												array(
				
													'field' 	=> 'cover',
													'sortable' 	=> 'true',
													'content' 	=> 'Cover',
												),
												array(
				
													'field' 	=> 'description',
													'sortable' 	=> 'true',
													'content' 	=> 'Description',
												),
												array(
				
													'field' 	=> 'licenses',
													'sortable' 	=> 'true',
													'content' 	=> 'My licenses',
												),
												array(
				
													'field' 	=> 'action',
													'sortable' 	=> 'true',
													'content' 	=> 'Action',
												),											
											), 
											$trash		= false,
											$export		= false,
											$search		= true,
											$toggle		= false,
											$columns	= true,
											$header		= true,
											$pagination	= true,
											$form		= false,
											$toolbar 	= 'toolbar'
										);									
								
								echo'</div>';
								
							echo'</div>';						
							
						echo'</div>'; //licenses 
					}
					elseif( $currentTab == 'invitations' ){
						
						echo'<div class="tab-pane active" id="invitations">';
						
							echo'<div class="bs-callout bs-callout-primary">';
							
								echo'<h4>';
								
									echo'Invitations';
									
								echo'</h4>';
							
								echo'<p>';
								
									echo 'Send invitations to people by email';
								
								echo'</p>';	

							echo'</div>';							

							echo'<div class="tab-content row">';

								echo'<div class="col-xs-12">';
									
									// get import emails
									
									echo $this->parent->email->get_invitation_form('sponsorship');	
									
								echo'</div>';
								

								echo'<div class="col-xs-12">';

										// get table of invited people

										$this->parent->api->get_table(
										
											$this->parent->urls->api . 'ltple-sponsored/v1/invitations', 
											array(
											
												array(
				
													'field' 	=> 'name',
													'sortable' 	=> 'true',
													'content' 	=> 'Name',
												),
												array(
				
													'field' 	=> 'email',
													'sortable' 	=> 'true',
													'content' 	=> 'Email',
												),
												array(
				
													'field' 	=> 'last_seen',
													'sortable' 	=> 'true',
													'content' 	=> 'Last Seen',
												),
										
											), 
											$trash		= false,
											$export		= false,
											$search		= true,
											$toggle		= false,
											$columns	= true,
											$header		= true,
											$pagination	= true,
											$form		= false,
											$toolbar 	= 'toolbar'
										);									
								
								echo'</div>';
								
							echo'</div>';						
							
						echo'</div>'; //invitations
					}

				echo'</div>';
				
			echo'</div>';	

		echo'</div>';
	}
	else{
		
		echo '<div class="panel-body" style="min-height:300px;">';
		
			echo '<div class="alert alert-warning">';
			
				echo 'You need to be a member of the Sponsorship Program to access this area. Please contact us.';
			
			echo '</div>';
			
		echo '</div>';
	}
	
	?>
	
	<script>

		;(function($){		
			
			$(document).ready(function(){
				
				
			});
			
		})(jQuery);

	</script>