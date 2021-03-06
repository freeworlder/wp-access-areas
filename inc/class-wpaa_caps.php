<?php
/**
* @package WP_AccessAreas
* @version 1.0.0
*/ 

// ----------------------------------------
//	This class provides an interface for editing access areas
// ----------------------------------------

if ( ! class_exists('WPAA_Caps' ) ) :

class WPAA_Caps {
	
	static function init( ) {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__ , 'user_menu' ));
			if ( is_accessareas_active_for_network() )
				add_action( 'network_admin_menu', array( __CLASS__ , 'user_menu' ));
		}
	}

	
	static function user_menu() { // @ admin_menu
		if ( (is_network_admin() && ! current_user_can( 'manage_network_users' )) || ( ! current_user_can( 'promote_users' ) ) )
			return;
		
		add_users_page(__('Manage Access Areas','wp-access-areas'), __('Access Areas','wp-access-areas'), 'promote_users', 'user_labels', array(__CLASS__,'manage_userlabels_page'));
		add_action( 'load-users_page_user_labels' , array( __CLASS__ , 'do_userlabel_actions' ) );
	}
	
	static function do_userlabel_actions() {
	
		if ( ! current_user_can( 'promote_users' ) ) 
			wp_die( __('You do not have permission to do this.' , 'wp-access-areas' ) );
		
		wp_enqueue_style( 'wpaa-admin' );
		
		$table = new AccessAreas_List_Table();
		$table->process_bulk_action();
		$redirect_url = false;
		if (isset($_REQUEST['action'])) {
			// do actions
			$data = self::_sanitize_userlabel_data( $_POST );
			
			// integrity check.
			if ( !empty($_POST) && ! $data['cap_title'] ) 
				wp_die( __('Please enter a Label.' , 'wp-access-areas' ) );
			if (( ! empty( $_POST ) && ! wp_verify_nonce(@$_REQUEST['_wpnonce'],'userlabel-'.$_REQUEST['action'] ) ) ||
			 		( ! $data['blog_id'] && ! current_user_can('manage_network_users') ))
				wp_die( __('You do not have permission to edit network wide user labels.' , 'wp-access-areas' ) );
			
			switch ( $_REQUEST['action'] ) {
				case 'new':
					// do create action
					if ( ! empty( $_POST ) )  {
						if ( $edit_id = WPAA_AccessArea::create_userlabel( $data ) )
							$redirect_url =  add_query_arg( array('page'=>'user_labels' , 'action' => 'new' , 'message' => 1 ) , $_SERVER['SCRIPT_NAME'] );
							// $redirect_url = add_query_arg(array('page'=>'user_labels' , 'message' => 1 ),$_SERVER['SCRIPT_NAME']);
						else 
							$redirect_url = add_query_arg(array('page'=>'user_labels' , 'action' => 'new' , 'message' => WPAA_AccessArea::what_went_wrong() , 'cap_title'=>$_POST['cap_title'] ),$_SERVER['SCRIPT_NAME']);
					}
					break;
				case 'edit':
					// update and redirect
					if ( ! empty( $_POST ) ) {
						if ( $edit_id = WPAA_AccessArea::update_userlabel( $data ) )
							$redirect_url = add_query_arg( array('id' => $edit_id , 'message' => 2 ) );
						else 
							$redirect_url = add_query_arg( array('id' => $edit_id , 'message' => WPAA_AccessArea::what_went_wrong() , 'cap_title'=>$_POST['cap_title'] ) );
					}
					if ( ! isset( $_GET['id'] ) ) 
						$redirect_url = add_query_arg( array('page'=>'user_labels' ) , $_SERVER['SCRIPT_NAME'] );
						
					break;
				case 'delete':
					// delete and redirect
					if ( isset( $_REQUEST['id'] )  ) {
						if ( $deleted = WPAA_AccessArea::delete_userlabel( $_REQUEST['id'] ) ) {
							$redirect_url = add_query_arg(array('page'=>'user_labels' , 'message' => 3 , 'deleted' => $deleted ) , $_SERVER['SCRIPT_NAME'] );
						} else {
							$redirect_url = add_query_arg(array('page'=>'user_labels' , 'message' => WPAA_AccessArea::what_went_wrong() ) , $_SERVER['SCRIPT_NAME'] );
						}
					}
						
					break;
				default:
					wp_redirect( remove_query_arg('action') );
			}
		}
		if ( $redirect_url )
			wp_redirect( $redirect_url );
		
	}
	static function manage_userlabels_page( ) {
		if (isset($_REQUEST['action'])) {
			// do actions
			// display forms
			switch ( $_REQUEST['action'] ) {
				case 'new':
					return self::edit_userlabels_screen(  );
				case 'edit':
					return self::edit_userlabels_screen( $_GET['id'] );
			}
		}
		return self::list_userlabels_screen();
	}
	
	static function edit_userlabels_screen( $userlabel_id = 0 ) {
		global $wpdb;
		if ( $userlabel_id ) 
			$userlabel = WPAA_AccessArea::get_userlabel( $userlabel_id );
		else
			$userlabel = (object) array(
				'cap_title' => '',
				'blog_id'	=> get_current_blog_id(),
			) ;
		$cap_title = $userlabel->cap_title;
		if ( ! $cap_title && isset( $_REQUEST['cap_title'] ) )
			$cap_title = $_REQUEST['cap_title'];
		
		?><div class="wrap"><?php
		?><div id="icon-undisclosed-userlabel" class="icon32"><br></div><?php
		?><h2><?php
			if ( $userlabel_id ) { 
				_e('Edit Access Area','wp-access-areas');
			} else {
				_e('Create Access Area','wp-access-areas');
			}
		?></h2>
		<?php self::_put_message( ) ?>
		<?php
			?><form id="create-user-label" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<!-- Now we can render the completed list table -->
			<?php if ( $userlabel_id ) { ?>
				<input type="hidden" name="id" value="<?php echo $userlabel_id ?>" />
			<?php } ?>

			<?php wp_nonce_field( 'userlabel-'.(  $userlabel_id  ? 'edit' : 'new' ) ) ?>
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><label for="title"><?php _e('Access Area','wp-access-areas') ?></label></th>
							<td><input class="regular-text" maxlength="64" type="text" name="cap_title" value="<?php echo $cap_title ?>" id="cap_title" placeholder="<?php _e('New Access Area','wp-access-areas') ?>" autocomplete="off" /></td>
						</tr>
					</tbody>
				</table>
				
				<button type="submit" class="button button-primary button-large"><?php 
			if ( $userlabel_id ) { 
				_e('Save changes','wp-access-areas');
			}  else {
				_e( 'Create Access Area' , 'wp-access-areas' );
			}
				
				
				?></button>
			</form><?php
		?></div><?php
		
	}
	private static function _put_message( ) {
		if ( ! isset( $_REQUEST['message'] ) )
			return;
			
		$message_wrap = '<div id="message" class="updated"><p>%s</p></div>';
		switch( $_REQUEST['message'] ) {
			case 1: // created
				$message = __('Access Area created.','wp-access-areas');
				break;
			case 2: // updated
				$message = __('Access Area updated.','wp-access-areas');
				break;
			case 3: // deleted
				$message = sprintf(_n('Access Area deleted.' , '%d Access Areas deleted.' , $_REQUEST['deleted'] , 'wp-access-areas') , $_REQUEST['deleted'] );
				break;
			case 4: // exists
				$message = __('An Access Area with that Name already exists.','wp-access-areas');
				break;
			case 5: // not found
				$message = __('Could not find the specified Access Area.','wp-access-areas');
				break;
			default:
				$message = '';
				break;
		}
		if ( $message )
			printf( $message_wrap , $message );
	}
	
	static function list_userlabels_screen() {
		$listTable = new AccessAreas_List_Table( array() );
		$listTable->prepare_items();


		?><div class="wrap"><?php
		?><h2><?php 
			_e('Manage Access Areas','wp-access-areas') ?>
			<a href="<?php echo remove_query_arg('message',add_query_arg(array('action'=>'new'))) ?>" class="add-new-h2"><?php _ex('Add New','access area','wp-access-areas') ?></a>
		</h2>
		<?php self::_put_message( ) ?>
		<?php

			?><form id="camera-reservations-filter" method="get">
				<!-- Now we can render the completed list table -->
				<input type="hidden" name="page" value="user_labels" />
				<?php $listTable->display() ?>
			</form><?php
		
		

		?></div><!-- .wrap -->
		<?php

	}
	static function _sanitize_userlabel_data( $data ) {
		global $wpdb;
		$data = wp_parse_args( $data , array(
			'cap_title' => '',
			'blog_id'	=> 0,
		) );
		$data['cap_title'] = trim(strip_tags( $data['cap_title'] ));
		$data['blog_id'] = is_network_admin() ? 0 : get_current_blog_id();
		return $data;
	}
	
	

}
endif;
