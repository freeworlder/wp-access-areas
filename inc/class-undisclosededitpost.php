<?php
/**
* @package WP_AccessAreas
* @version 1.0.0
*/ 

// ----------------------------------------
//	This class provides an UI for assining 
//	WP-Roles and user-labels to posts.
// ----------------------------------------

if ( ! class_exists('UndisclosedEditPost') ) :
class UndisclosedEditPost {

	static function init() {
		if ( is_admin() ) {
			// edit post
			add_filter('wp_insert_post_data', array(__CLASS__ , 'edit_post') , 10 ,2 );
			add_action('add_meta_boxes' , array( __CLASS__ , 'add_meta_boxes' ) );

			// list views
			add_filter('manage_posts_columns' , array(__CLASS__ , 'add_disclosure_column'));
			add_filter('manage_posts_custom_column' , array(__CLASS__ , 'manage_disclosure_column') , 10 ,2 );

			add_filter('manage_pages_columns' , array(__CLASS__ , 'add_disclosure_column'));
			add_filter('manage_pages_custom_column' , array(__CLASS__ , 'manage_disclosure_column') , 10 ,2 );
			add_action('bulk_edit_custom_box' , array(__CLASS__,'bulk_edit_fields') , 10 , 2 );
		}
		add_action( 'load-edit.php' , array( __CLASS__ , 'load_style' ) );
		add_action( 'load-post.php' , array( __CLASS__ , 'load_style' ) );
		add_action( 'load-post-new.php' , array( __CLASS__ , 'load_style' ) );
	}
	static function load_style() {
		wp_enqueue_style( 'disclosure-admin' );
	}
	
	// --------------------------------------------------
	// add meta boxes to all post content
	// --------------------------------------------------
	static function add_meta_boxes() {
		global $wp_post_types;
		foreach ( array_keys($wp_post_types) as $post_type )
			add_meta_box( 'post-disclosure' , __('Access','wpundisclosed') , array(__CLASS__,'disclosure_box_info') , $post_type , 'side' , 'high' );
	}
	// --------------------------------------------------
	// saving post
	// --------------------------------------------------
	static function edit_post( $data, $postarr ) {
		if ( $data['post_status'] == 'auto-draft' )
			return $data;

		$post_type_object 	= get_post_type_object($data["post_type"]);
		if ( $post_type_object->public )
			$data['post_view_cap']	= isset($postarr['post_view_cap']) ? $postarr['post_view_cap'] : 'exist';
		$data['post_edit_cap']	= isset($postarr['post_edit_cap']) ? $postarr['post_edit_cap'] : 'exist';
		if ( post_type_supports( $data["post_type"] , 'comments' ) )
			$data['post_comment_cap']	= isset($postarr['post_comment_cap']) ? $postarr['post_comment_cap'] : 'exist';
		
		return $data;
	}
	
	// --------------------------------------------------
	// edit post - the meta box
	// --------------------------------------------------
	static function disclosure_box_info() {
		global $wp_roles;
		$post 				= get_post(get_the_ID());
		$post_type_object 	= get_post_type_object($post->post_type);
		$editing_cap 		= $post_type_object->cap->edit_posts;

		// <select> with - Evereybody, Logged-in only, list WP-Roles, list discosure-groups
		$roles	 			= $wp_roles->get_names();
		$groups 			= UndisclosedUserlabel::get_label_array( );
		$user_role_caps 	= wpaa_get_user_role_caps();

		$rolenames 			= array();
		$edit_rolenames		= array();
		foreach ( $roles as $role => $rolename ) {
			if ( wpaa_user_can_role( $role , $user_role_caps ) ) {
				$rolenames[$role] = $rolename;
				if ( get_role( $role )->has_cap( $editing_cap ) ) {
					$edit_rolenames[$role] = $rolename;
				}
			}
		}
		
		if ( $post_type_object->public ) {
			?><div class="disclosure-view-select misc-pub-section">
				<label for="post_view_cap-select"><strong><?php _e( 'Who can read:' , 'wpundisclosed') ?></strong></label><br />
				<?php 
					self::access_area_dropdown( $rolenames , $groups , $post->post_view_cap , 'post_view_cap' );
				?>
			</div><?php
		}
		?><div class="disclosure-edit-select misc-pub-section">
			<label for="post_edit_cap-select"><strong><?php _e( 'Who can edit:' , 'wpundisclosed') ?></strong></label><br />
			<?php 
				self::access_area_dropdown( $edit_rolenames , $groups , $post->post_edit_cap , 'post_edit_cap' );
			?>
		</div><?php

		if ( post_type_supports( $post->post_type , 'comments' ) ) {
			?><div class="disclosure-comment-select misc-pub-section">
				<label for="post_comment_cap-select"><strong><?php _e( 'Who can comment:' , 'wpundisclosed') ?></strong></label><br />
				<?php 
					self::access_area_dropdown( $rolenames , $groups , $post->post_comment_cap , 'post_comment_cap' );
				?>
			</div><?php
		}
//*/
	}
	
	static function access_area_dropdown( $roles , $groups , $selected_cap , $fieldname , $first_item_value = null , $first_item_label = ''  ) {
		// comments should be: wp-defaults, logged in users
		// 
		?>
		<select id="<?php echo $fieldname ?>-select" name="<?php echo $fieldname ?>"><?php
			if ( ! is_null( $first_item_value ) && ! is_null( $first_item_label ) ) {
				?><option value="<?php $first_item_value ?>"><?php echo $first_item_label ?></option><?php
			}
		
			?><option value="exist" <?php selected($selected_cap , 'exist') ?>><?php _e( 'WordPress default' , 'wpundisclosed' ) ?></option><?php
			if ( $fieldname != 'post_edit_cap' ) {
				?><option value="read" <?php selected($selected_cap , 'read') ?>><?php _e( 'Logged in Users' , 'wpundisclosed' ) ?></option><?php
			}
			
			?><optgroup label="<?php _e( 'WordPress roles' , 'wpundisclosed') ?>">
			<?php foreach ($roles as $role => $rolename) {
				if ( ! wpaa_user_can_role( $role , $user_role_caps ) )
					continue;
				?>
				<option value="<?php echo $role ?>" <?php selected($selected_cap , $role) ?>><?php _ex( $rolename, 'User role' ) ?></option>
			<?php } ?>
			</optgroup>
			<?php if ( count($groups) ) { ?>
				<optgroup label="<?php _e( 'Users with Access to' , 'wpundisclosed') ?>">
				<?php foreach ($groups as $group=>$groupname) { 
					if ( ! wpaa_user_can_accessarea($group) )
						continue;
					?>
					<option value="<?php echo $group ?>" <?php selected($selected_cap , $group) ?>><?php _e( $groupname , 'wpundisclosed' ) ?></option>
				<?php } /* foreach( $groups ) */ ?>
				</optgroup>
			<?php }  /* if count( $groups ) */ ?>
		</select>
		<?php
	}
	
	static function bulk_edit_fields( $column_name, $post_type ) {
		global $wp_roles;
		if ($column_name == 'view_cap') {
			$post_type_object	= get_post_type_object($post_type);
			$editing_cap 		= $post_type_object->cap->edit_posts;
			$current_user 		= wp_get_current_user();
			$roles	 			= $wp_roles->get_names();
			$groups 			= UndisclosedUserlabel::get_label_array( );
		
			$user_role_caps 	= wpaa_get_user_role_caps();

			$rolenames 			= array();
			$edit_rolenames		= array();
			foreach ( $roles as $role => $rolename ) {
				if ( wpaa_user_can_role( $role , $user_role_caps ) ) {
					$rolenames[$role] = $rolename;
					if ( get_role( $role )->has_cap( $editing_cap ) ) {
						$edit_rolenames[$role] = $rolename;
					}
				}
			}
		
			?><fieldset class="inline-edit-col-access-areas">
				<div class="inline-edit-col"><?php
					if ( $post_type_object->public ) {
						?><div class="inline-edit-group">
							<label>
								<span class="title"><?php _e( 'Who can read:' , 'wpundisclosed') ?></span>
								<?php 
								self::access_area_dropdown( $rolenames , $groups , '' , 'post_view_cap' , -1 , __( '&mdash; No Change &mdash;' ) );
								?>
							</label>
						</div><?php
					}
					?><div class="inline-edit-group">
						<label>
							<span class="title"><?php _e( 'Who can edit:' , 'wpundisclosed') ?></span>
							<?php 
							self::access_area_dropdown( $edit_rolenames , $groups , '' , 'post_edit_cap'  , -1 , __( '&mdash; No Change &mdash;' )  );
							?>
						</label>
					</div><?php
					if ( post_type_supports( $post_type , 'comments' ) ) {
						?><div class="inline-edit-group">
							<label>
								<span class="title"><?php _e( 'Who can comment:' , 'wpundisclosed') ?></span>
								<?php 
								self::access_area_dropdown( $rolenames , $groups , '' , 'post_comment_cap'  , -1 , __( '&mdash; No Change &mdash;' ) );
								?>
							</label>
						</div><?php
					}
				?></div>
			</fieldset><?php
		}
	}
		
	
	
	// --------------------------------------------------
	// admin list views
	// --------------------------------------------------
	static function add_disclosure_column($columns) {
		$cols = array();
		foreach ($columns as $k=>$v) {
			$cols[$k] = $v;
			if ($k=='author') {
				$cols['view_cap'] = __('Visible to','wpundisclosed');
				$cols['comment_cap'] = __('Commentable to','wpundisclosed');
			}
		}
		return $cols;
	}
	static function manage_disclosure_column($column, $post_ID) {
		global $wp_roles;
		switch ( $column ) {
			case 'view_cap':
				$names = array_merge(array('exist' => __( 'Everybody' , 'wpundisclosed' ), 'read' => __( 'Blog users' , 'wpundisclosed' )) , UndisclosedUserlabel::get_label_array( ), $wp_roles->get_names());
				$names[''] = $names['exist'];
				$val = get_post($post_ID)->post_view_cap;
				_e($names[$val]);
				break;
			case 'comment_cap':
				$names = array_merge(array('exist' => __( 'Everybody' , 'wpundisclosed' ), 'read' => __( 'Blog users' , 'wpundisclosed' )) , UndisclosedUserlabel::get_label_array( ), $wp_roles->get_names());
				$names[''] = $names['exist'];
				$val = get_post($post_ID)->post_comment_cap;
				_e($names[$val]);
				break;
		}
	}

}
UndisclosedEditPost::init();
endif;




?>