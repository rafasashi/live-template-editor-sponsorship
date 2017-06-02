<?php 

	if(isset($_SESSION['message'])){ 
	
		//output message
	
		echo $_SESSION['message'];
		
		//reset message
		
		$_SESSION['message'] ='';
	}

	if( $this->parent->user->is_company ){
						
		$company_name = ucfirst(get_bloginfo('name'));
	
		$tab = 1; //accordion tabs

		// ------------- output panel --------------------
		
		echo'<div id="media_library">';

			echo'<div class="col-xs-3 col-sm-2">';
			
				echo'<ul class="nav nav-tabs tabs-left">';
					
					echo'<li class="gallery_type_title">Company Program</li>';
					
					//echo'<li class="active"><a href="#overview" data-toggle="tab">Overview</a></li>';
					
					echo'<li class="active"><a href="#licenses" data-toggle="tab">Licenses</a></li>';
					
					echo'<li class=""><a href="#coupons" data-toggle="tab">Coupons</a></li>';

				echo'</ul>';
				
			echo'</div>';

			echo'<div class="col-xs-9 col-sm-10" style="border-left: 1px solid #ddd;background:#fff;padding-top:15px;padding-bottom:15px;min-height:500px;">';
				
				echo'<div class="tab-content">';

					//overview
					/*
					echo'<div class="tab-pane active" id="overview">';

						echo'<div class="bs-callout bs-callout-primary">';
						
							echo'<h4>';
							
								echo'Overview';
								
							echo'</h4>';
						
							echo'<p>';
							
								echo 'Your account snapshot licenses and company information';
							
							echo'</p>';	

						echo'</div>';							

						echo'<div class="tab-content row">';

							echo'<div class="col-xs-12">';
	
								
							echo'</div>';

						echo'</div>';
						
						echo'<div class="clearfix"></div>';	
						echo'<hr></hr>';							

						echo'<div class="row">';
						echo'<div class="col-xs-12">';
						
							echo'<div class=" panel panel-default" style="margin-bottom:0;">';
							
								echo'<table class="table table-striped table-hover">';
								
								echo'<tbody>';
									
									echo'<tr style="font-size:18px;font-weight:bold;">';
										
										echo'<td>Invoices</td>';
										
										
									echo'</tr>';
								
								echo'</tbody>';
								
								echo'</table>';
							
							echo'</div>';
							
						echo'</div>';
						echo'</div>';						

					echo'</div>';
					*/
					// ref urls
					
					echo'<div class="tab-pane active" id="licenses">';
					
						echo'<div class="bs-callout bs-callout-primary">';
						
							echo'<h4>';
							
								echo'Licenses';
								
							echo'</h4>';
						
							echo'<p>';
							
								echo 'List of licenses';
							
							echo'</p>';	

						echo'</div>';							

						echo'<div class="tab-content row">';

							echo'<div class="col-xs-12">';
								
									// get import emails
									
									echo '<div class="well" style="display:inline-block;width:100%;">';
									
										echo '<div class="col-xs-12 col-md-6">';
										
											echo '<h4>Manage Licenses</h4>';
										
											echo '<form action="' . $this->parent->urls->current . '" method="post">';
											
												$this->admin->display_field( array(
												
													'id' 			=> 'companyAction',
													'description'	=> '',
													'type'			=> 'select',
													'options'		=> array(
													
														'importEmails' => 'Import Emails',
													),

												), $this->parent->user );	

												echo '<h5 style="padding:15px 0 5px 0;font-weight:bold;">CSV list of users</h5>';
											
												$this->admin->display_field( array(
												
													'id' 			=> 'companyEmails',
													'label'			=> 'Add emails',
													'description'	=> '',
													'placeholder'	=> '',
													'default'		=> '',
													'type'			=> 'textarea',
													'style'			=> 'width:100%;height:150px;',
												), $this->parent->user );
											
												echo '<button class="btn btn-xs btn-primary pull-right" type="submit">';
													
													echo 'Start';
													
												echo '</button>';
											
											echo '</form>';
										
										echo '</div>';
										
										echo '<div class="col-xs-12 col-md-6">';
										
											echo '<table class="table table-striped table-hover">';
											
												echo '<thead>';
													echo '<tr>';
														echo '<th><b>Information</b></th>';
													echo '</tr>';
												echo '</thead>';
												
												echo '<tbody>';
													echo '<tr>';
														echo '<td>Copy paste a list of emails separated by comma you want to import.</td>';
													echo '</tr>';															
												echo '</tbody>';
												
											echo '</table>';			
										
										echo '</div>';
									
									echo '</div>';
									
									// get table
									
									$this->parent->api->get_table(
									
										$this->parent->urls->api . 'ltple-company/v1/users/', 
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
					
					echo'<div class="tab-pane" id="coupons">';
					
						echo'<div class="bs-callout bs-callout-primary">';
						
							echo'<h4>';
							
								echo'Coupons';
								
							echo'</h4>';
						
							echo'<p>';
							
								echo 'List of available coupons';
							
							echo'</p>';	

						echo'</div>';							

						echo'<div class="tab-content row" style="margin:20px;">';

							echo'<div class="col-xs-12">';
								
	
								
							echo'</div>';
							
						echo'</div>';						
						
					echo'</div>'; //coupons

				echo'</div>';
				
			echo'</div>';	

		echo'</div>';
	}
	else{
		
		echo '<div class="panel-body" style="min-height:300px;">';
		
			echo '<div class="alert alert-warning">';
			
				echo 'You need to be a member of the Company Program to access this area. Please contact us.';
			
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