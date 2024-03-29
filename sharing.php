<?php

class Sharing_Admin {
	public function __construct() {
		if ( !defined( 'WP_SHARING_PLUGIN_URL' ) ) {
			define( 'WP_SHARING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
			define( 'WP_SHARING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}
		
		require_once WP_SHARING_PLUGIN_DIR.'sharing-service.php';

		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_action( 'admin_menu', array( &$this, 'subscription_menu' ) );

		// Insert our CSS and JS
		add_action( 'load-settings_page_sharing', array( &$this, 'sharing_head' ) );

		// Catch AJAX
		add_action( 'wp_ajax_sharing_save_services', array( &$this, 'ajax_save_services' ) );
		add_action( 'wp_ajax_sharing_save_options', array( &$this, 'ajax_save_options' ) );
		add_action( 'wp_ajax_sharing_new_service', array( &$this, 'ajax_new_service' ) );
		add_action( 'wp_ajax_sharing_delete_service', array( &$this, 'ajax_delete_service' ) );
	}
	
	public function sharing_head() {
		wp_enqueue_script( 'sharing-js', WP_SHARING_PLUGIN_URL.'admin-sharing.js', array( 'jquery-ui-draggable', 'jquery-ui-droppable', 'jquery-ui-sortable', 'jquery-form' ), 1 );
		wp_enqueue_style( 'sharing', WP_SHARING_PLUGIN_URL.'admin-sharing.css', false, WP_SHARING_PLUGIN_VERSION );

		add_thickbox();
	}
	
	public function admin_init() {
		if ( isset( $_GET['page'] ) && ( $_GET['page'] == 'sharing.php' || $_GET['page'] == 'sharing' ) )
			$this->process_requests();
	}

	public function process_requests() {
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'sharing-options' ) ) {
			$sharer = new Sharing_Service();
			$sharer->set_global_options( $_POST );
			do_action( 'sharing_admin_update' );
			
			wp_redirect( admin_url( 'options-general.php?page=sharing&update=saved' ) );
			die();
		}
	}
	
	public function subscription_menu( $user ) {
		add_submenu_page( 'options-general.php', __( 'Sharing Settings', 'sharedaddy' ), __( 'Sharing', 'sharedaddy' ), 'manage_options', 'sharing', array( &$this, 'management_page' ) );
	}
	
	public function ajax_save_services() {
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'sharing-options' ) && isset( $_POST['hidden'] ) && isset( $_POST['visible'] ) ) {
			$sharer = new Sharing_Service();
			
			$sharer->set_blog_services( explode( ',', $_POST['visible'] ), explode( ',', $_POST['hidden'] ) );
			die();
		}
	}
	
	public function ajax_new_service() {
		if ( isset( $_POST['_wpnonce'] ) && isset( $_POST['sharing_name'] ) && isset( $_POST['sharing_url'] ) && isset( $_POST['sharing_icon'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'sharing-new_service' ) ) {
			$sharer = new Sharing_Service();
			if ( $service = $sharer->new_service( $_POST['sharing_name'], $_POST['sharing_url'], $_POST['sharing_icon'] ) ) {
				$this->output_service( $service->get_id(), $service );
				echo '<!--->';
				$service->button_style = 'icon-text';
				$this->output_preview( $service );

				die();
			}
		}

		// Fail
		die( '1' );
	}
	
	public function ajax_delete_service() {
		if ( isset( $_POST['_wpnonce'] ) && isset( $_POST['service'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'sharing-options_'.$_POST['service'] ) ) {
			$sharer = new Sharing_Service();
			$sharer->delete_service( $_POST['service'] );
		}
	}
	
	public function ajax_save_options() {
		if ( isset( $_POST['_wpnonce'] ) && isset( $_POST['service'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'sharing-options_'.$_POST['service'] ) ) {
			$sharer = new Sharing_Service();
			$service = $sharer->get_service( $_POST['service'] );

			if ( $service && $service instanceof Sharing_Advanced_Source ) {
				$service->update_options( $_POST );

				$sharer->set_service( $_POST['service'], $service );
			}

			$this->output_service( $service->get_id(), $service, true );
			echo '<!--->';
			$service->button_style = 'icon-text';
			$this->output_preview( $service );
			die();
		}
	}
	
	public function output_preview( $service ) {
		$klasses = array( 'advanced', 'preview-item');
		
		if ( $service->button_style != 'text' || $service->has_custom_button_style() ) {
			$klasses[] = 'preview-'.$service->get_class();
			
			if ( $service->get_class() != $service->get_id() )	
				$klasses[] = 'preview-'.$service->get_id();
		}
			
		echo '<li class="'.implode( ' ', $klasses ).'">';
		$service->display_preview();
		echo '</li>';
	}
	
	public function output_service( $id, $service, $show_dropdown = false ) {
?>
	<li class="service advanced<?php if ( $show_dropdown ) echo ' options'; ?> share-<?php echo $service->get_class(); ?>" id="<?php echo $service->get_id(); ?>">
		<span class="options-left"><?php echo esc_html( $service->get_name() ); ?></span><?php if ( $service->has_advanced_options() ) : ?><span class="options-toggle" style="background: url(<?php echo admin_url( '/images/menu-bits.gif' ); ?>) no-repeat 0px -110px;">&nbsp;</span>
			<br style="clear:both;" />
			<div class="advanced-form">
				<form method="post" action="<?php echo admin_url( 'admin-ajax.php' ); ?>">
					<?php $service->display_options(); ?>
					
					<input type="hidden" name="action" value="sharing_save_options" />
					<input type="hidden" name="service" value="<?php echo esc_attr( $id ); ?>" />
					
					<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'sharing-options_'.$id );?>" />
				</form>
			</div>
		<?php endif; ?>
	</li>
<?php
	}

	public function management_page() {
		$sharer  = new Sharing_Service();
		$enabled = $sharer->get_blog_services();
		$global  = $sharer->get_global_options();
		if ( false == function_exists( 'mb_stripos' ) ) {
			echo '<div id="message" class="updated fade"><h3>' . __( 'Warning! Multibyte support missing!', 'sharedaddy' ) . '</h3>';
			echo "<p>" . sprintf( __( 'This plugin will work without it, but multibyte support is used <a href="%s">if available</a>. You may see minor problems with Tweets and other sharing services.', 'sharedaddy' ), "http://www.php.net/manual/en/mbstring.installation.php" ) . '</p></div>';
		}

		if ( isset( $_GET['update'] ) && $_GET['update'] == 'saved' )
			echo '<div class="updated"><p>'.__( 'Settings have been saved', 'sharedaddy' ).'</p></div>';
?>

	<div class="wrap">
	  	<div class="icon32" id="icon-options-general"><br /></div>
	  	<h2><?php _e( 'Sharing Settings', 'sharedaddy' ); ?></h2>
	  	
	  	<div id="services-config">
	  		<table id="available-services">
					<tr>
		  			<td class="description">
		  				<h3><?php _e( 'Available Services', 'sharedaddy' ); ?></h3>
		  				<p><?php _e( "Drag and drop the services you'd like to enable into the box below.", 'sharedaddy' ); ?></p>
		  				<p><a href="#TB_inline?height=395&amp;width=600&amp;inlineId=new-service" title="<?php echo esc_attr( __( 'Add a new service', 'sharedaddy' ) ); ?>" class="thickbox"><?php _e( 'Add a new service', 'sharedaddy' ); ?></a></p>
		  			</td>
		  			<td class="services">
		  				<ul class="services-available" style="height: 100px;">
	  						<?php foreach ( $sharer->get_all_services_blog() AS $id => $service ) : ?>
	  							<?php
	  								if ( !isset( $enabled['all'][$id] ) )
											$this->output_service( $id, $service );
									?>
	  						<?php endforeach; ?>
		  				</ul>
		  				<br class="clearing" />
		  			</td>
					</tr>
	  		</table>
	
  			<table id="enabled-services">
  				<tr>
  					<td class="description">
						<h3>
							<?php _e( 'Enabled Services', 'sharedaddy' ); ?>
							<img src="<?php echo admin_url( 'images/loading.gif' ); ?>" width="16" height="16" alt="loading" style="vertical-align: middle; display: none" />
						</h3>
						<p><?php _e( 'Services dragged here will appear individually.', 'sharedaddy' ); ?></p>
  					</td>
	  				<td class="services" id="share-drop-target">
			  				<h2 id="drag-instructions" <?php if ( count( $enabled['visible'] ) > 0 ) echo ' style="display: none"'; ?>><?php _e( 'Drag and drop available services here', 'sharedaddy' ); ?></h2>
			  				
								<ul class="services-enabled">
									<?php foreach ( $enabled['visible'] AS $id => $service ) : ?>
										<?php $this->output_service( $id, $service, true ); ?>
									<?php endforeach; ?>
									
									<li class="end-fix"></li>
								</ul>
					</td>	  			
					<td id="hidden-drop-target" class="services">
			  				<p><?php _e( 'Services dragged here will be hidden behind a share button.', 'sharedaddy' ); ?></p>
			  				
			  				<ul class="services-hidden">
									<?php foreach ( $enabled['hidden'] AS $id => $service ) : ?>
										<?php $this->output_service( $id, $service, true ); ?>
									<?php endforeach; ?>
									<li class="end-fix"></li>
			  				</ul>
					</td>
				</tr>
			</table>						  			
				
			<table id="live-preview">
				<tr>
					<td class="description">
						<h3><?php _e( 'Live Preview', 'sharedaddy' ); ?></h3>
					</td>
					<td class="services">
						<h2<?php if ( count( $enabled['all'] ) > 0 ) echo ' style="display: none"'; ?>><?php _e( 'Sharing is off. Please add services above to enable', 'sharedaddy' ); ?></h2>
						
						<ul class="preview">
							<?php if ( count( $enabled['all'] ) > 0 ) : ?>
							<li class="sharing-label"><?php echo esc_html( $global['sharing_label'] ); ?></li>
							<?php endif; ?>
							
							<?php foreach ( $enabled['visible'] AS $id => $service ) : ?>
								<?php $this->output_preview( $service ); ?>
							<?php endforeach; ?>
	
							<?php if ( count( $enabled['hidden'] ) > 0 ) : ?>
							<li class="share-custom">
								<a href="#" class="sharing-anchor"><?php _ex( 'Share', 'dropdown button', 'sharedaddy' ); ?></a>
								
								<div class="sharing-hidden">
									<div class="inner" style="display: none;">
										<ul>
											<?php
												$count = 1;
												
												foreach ( $enabled['hidden'] AS $id => $service ) {
													$this->output_preview( $service );

			                    if ( ( $count % 2 ) == 0 )
			                        echo '<li class="share-end"></li>';
													
													$count++;
												}
											?>
											<li class="share-end"></li>
										</ul>
									</div>
								</div>
							</li>
							<?php endif; ?>
						</ul>

						<ul class="archive" style="display: none">
							<li class="sharing-label"><?php echo esc_html( $global['sharing_label'] ); ?></li>

							<?php foreach ( $sharer->get_all_services_blog() AS $id => $service ) : ?>
								<?php
									if ( isset( $enabled['visible'][$id] ) )
										$service = $enabled['visible'][$id];
									elseif ( isset( $enabled['hidden'][$id] ) )
										$service = $enabled['hidden'][$id];

									$service->button_style = 'icon-text';   // The archive needs the full text, which is removed in JS later
									$this->output_preview( $service );
								?>
							<?php endforeach; ?>
	
							<li class="share-custom">
								<a href="#" class="sharing-anchor"><?php _ex( 'Share', 'dropdown button', 'sharedaddy' ); ?></a>
								
								<div class="sharing-hidden">
									<div class="inner" style="display: none;">
										<ul>
											<li/>
										</ul>
									</div>
								</div>
							</li>
						</ul>
						<br class="clearing" />
					</td>
				</tr>
			</table>
				
				<form method="post" action="<?php echo admin_url( 'admin-ajax.php' ); ?>" id="save-enabled-shares">
					<input type="hidden" name="action" value="sharing_save_services" />
					<input type="hidden" name="visible" value="<?php echo implode( ',', array_keys( $enabled['visible'] ) ); ?>" />
					<input type="hidden" name="hidden" value="<?php echo implode( ',', array_keys( $enabled['hidden'] ) ); ?>" />
					<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'sharing-options' );?>" />
				</form>
	  	</div>

	  	<form method="post" action="">
	  		<table class="form-table">
	  			<tbody>
	  				<tr valign="top">
	  					<th scope="row"><label><?php _e( 'Default button style', 'sharedaddy' ); ?></label></th>
	  					<td>
	  						<select name="button_style">
	  							<option<?php if ( $global['button_style'] == 'icon-text' ) echo ' selected="selected"';?> value="icon-text"><?php _e( 'Icon + text', 'sharedaddy' ); ?></option>
	  							<option<?php if ( $global['button_style'] == 'icon' ) echo ' selected="selected"';?> value="icon"><?php _e( 'Icon only', 'sharedaddy' ); ?></option>
	  							<option<?php if ( $global['button_style'] == 'text' ) echo ' selected="selected"';?> value="text"><?php _e( 'Text only', 'sharedaddy' ); ?></option>
	  						</select>
	  					</td>
	  				</tr>
	  				<tr valign="top">
	  					<th scope="row"><label><?php _e( 'Sharing label', 'sharedaddy' ); ?></label></th>
	  					<td>
	  						<input type="text" name="sharing_label" value="<?php echo esc_attr( $global['sharing_label'] ); ?>" />
	  					</td>
	  				</tr>
                   	<tr valign="top">
	  					<th scope="row"><label><?php _e( 'Display buttons', 'sharedaddy' ); ?></label></th>
	  					<td>
	  						<select name="display_buttons">
	  							<option<?php if ( $global['display_buttons'] == 'before' ) echo ' selected="selected"';?> value="before"><?php _e( 'Before post text', 'sharedaddy' ); ?></option>
	  							<option<?php if ( $global['display_buttons'] == 'after' ) echo ' selected="selected"';?> value="after"><?php _e( 'After post text', 'sharedaddy' ); ?></option>
                               	<option<?php if ( $global['display_buttons'] == 'both' ) echo ' selected="selected"';?> value="both"><?php _e( 'Both before and after post text', 'sharedaddy' ); ?></option>
	  						</select>
	  					</td>
	  				</tr> 
	  				<tr valign="top">
	  					<th scope="row"><label><?php _e( 'Open links in', 'sharedaddy' ); ?></label></th>
	  					<td>
	  						<select name="open_links">
	  							<option<?php if ( $global['open_links'] == 'new' ) echo ' selected="selected"';?> value="new"><?php _e( 'New window', 'sharedaddy' ); ?></option>
	  							<option<?php if ( $global['open_links'] == 'same' ) echo ' selected="selected"';?> value="same"><?php _e( 'Same window', 'sharedaddy' ); ?></option>
	  						</select>
	  					</td>
	  				</tr>
	  				<tr valign="top">
	  					<th scope="row"><label><?php _e( 'Show sharing buttons on', 'sharedaddy' ); ?></label></th>
	  					<td>
	  						<select name="show">
	  							<option<?php if ( $global['show'] == 'posts-index' ) echo ' selected="selected"';?> value="posts-index"><?php _e( 'Posts, pages, and index pages', 'sharedaddy' ); ?></option>
	  							<option<?php if ( $global['show'] == 'posts' ) echo ' selected="selected"';?> value="posts"><?php _e( 'Posts and pages only', 'sharedaddy' ); ?></option>
	  							<option<?php if ( $global['show'] == 'index' ) echo ' selected="selected"';?> value="index"><?php _e( 'Index pages only', 'sharedaddy' ); ?></option>
	  						</select>
	  					</td>
	  				</tr>
	  				
	  				<?php do_action( 'sharing_global_options' ); ?>
	  			</tbody>
	  		</table>
	  	
		  	<p class="submit">
					<input type="submit" name="submit" class="button-primary" value="<?php _e( 'Save Changes', 'sharedaddy' ); ?>" />
				</p>
				
				<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'sharing-options' );?>" />
	  	</form>
	  
	  <div id="new-service" style="display: none">
	  	<form method="post" action="<?php echo admin_url( 'admin-ajax.php' ); ?>" id="new-service-form">
	  		<table class="form-table">
	  			<tbody>
	  				<tr valign="top">
	  					<th scope="row" width="100"><label><?php _e( 'Service name', 'sharedaddy' ); ?></label></th>
	  					<td>
	  						<input type="text" name="sharing_name" size="40" />
	  					</td>
	  				</tr>
	  				<tr valign="top">
	  					<th scope="row" width="100"><label><?php _e( 'Sharing URL', 'sharedaddy' ); ?></label></th>
	  					<td>
	  						<input type="text" name="sharing_url" size="40" />
	  						
	  						<p><?php _e( 'You can add the following variables to your service sharing URL:', 'sharedaddy' ); ?><br/>
	  						<code>%post_title%</code>, <code>%post_url%</code>, <code>%post_full_url%</code>, <code>%post_excerpt%</code>, <code>%post_full_url%</code>, <code>%post_tags%</code></p>
	  					</td>
	  				</tr>
	  				<tr valign="top">
	  					<th scope="row" width="100"><label><?php _e( 'Icon URL', 'sharedaddy' ); ?></label></th>
	  					<td>
	  						<input type="text" name="sharing_icon" size="40" />
	  						<p><?php _e( 'Enter the URL of a 16x16px icon you want to use for this service.', 'sharedaddy' ); ?></p>
	  					</td>
	  				</tr>
	  				<tr valign="top" width="100">
	  					<th scope="row"></th>
	  					<td>
								<input type="submit" class="button-secondary" value="<?php _e( 'Create Share', 'sharedaddy' ); ?>" />
	  						<img src="<?php echo admin_url( 'images/loading.gif' ); ?>" width="16" height="16" alt="loading" style="vertical-align: middle; display: none" />
	  					</td>
	  				</tr>
	  				
	  				<?php do_action( 'sharing_new_service_form' ); ?>
	  			</tbody>
	  		</table>

				<div class="inerror" style="display: none; margin-top: 15px">
					<p><?php _e( 'An error occurred creating your new sharing service - please check you gave valid details.', 'sharedaddy' ); ?></p>
				</div>
	  	
	  		<input type="hidden" name="action" value="sharing_new_service" />
				<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'sharing-new_service' );?>" />
	  	</form>
	   </div>
	</div>
	
	<script type="text/javascript">
		var sharing_loading_icon = '<?php echo esc_js( admin_url( "/images/loading.gif" ) ); ?>';
	</script>
<?php
	}
}

function sharing_admin_init() {
	global $sharing_admin;

	$sharing_admin = new Sharing_Admin();
}

add_action( 'init', 'sharing_admin_init' );
