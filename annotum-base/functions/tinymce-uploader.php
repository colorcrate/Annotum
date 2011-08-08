<?php 

function anno_tinymce_enqueue() {
	wp_enqueue_script('swfupload-all');
	wp_enqueue_script('swfupload-handlers');
	wp_enqueue_script('anno_upload_handlers', trailingslashit(get_bloginfo('template_directory')).'functions/tinymce-upload/handlers.js', array('jquery'));	
}
add_action('admin_enqueue_scripts', 'anno_tinymce_enqueue');

function anno_media_upload_form() {
		global $type, $tab, $pagenow;

		$flash_action_url = admin_url('async-upload.php');

		// If Mac and mod_security, no Flash. :(
		$flash = true;
		if ( false !== stripos($_SERVER['HTTP_USER_AGENT'], 'mac') && apache_mod_loaded('mod_security') )
			$flash = false;

		$flash = apply_filters('flash_uploader', $flash);
		$post_id = isset($_REQUEST['post_id']) ? intval($_REQUEST['post_id']) : 0;

		$upload_size_unit = $max_upload_size =  wp_max_upload_size();
		$sizes = array( 'KB', 'MB', 'GB' );
		for ( $u = -1; $upload_size_unit > 1024 && $u < count( $sizes ) - 1; $u++ )
			$upload_size_unit /= 1024;
		if ( $u < 0 ) {
			$upload_size_unit = 0;
			$u = 0;
		} else {
			$upload_size_unit = (int) $upload_size_unit;
		}
	?>
	<script type="text/javascript">
	//<![CDATA[
	var uploaderMode = 0;
	jQuery(document).ready(function($){
		uploaderMode = getUserSetting('uploader');
		$('.upload-html-bypass a').click(function(){deleteUserSetting('uploader');uploaderMode=0;swfuploadPreLoad();return false;});
		$('.upload-flash-bypass a').click(function(){setUserSetting('uploader', '1');uploaderMode=1;swfuploadPreLoad();return false;});
	});
	//]]>
	</script>
	<div id="media-upload-notice">
	<?php if (isset($errors['upload_notice']) ) { ?>
		<?php echo $errors['upload_notice']; ?>
	<?php } ?>
	</div>
	<div id="media-upload-error">
	<?php if (isset($errors['upload_error']) && is_wp_error($errors['upload_error'])) { ?>
		<?php echo $errors['upload_error']->get_error_message(); ?>
	<?php } ?>
	</div>
	<?php
	// Check quota for this blog if multisite
	if ( is_multisite() && !is_upload_space_available() ) {
		echo '<p>' . sprintf( __( 'Sorry, you have filled your storage quota (%s MB).' ), get_space_allowed() ) . '</p>';
		return;
	}

	do_action('pre-upload-ui');

	if ( $flash ) :

	// Set the post params, which SWFUpload will post back with the file, and pass
	// them through a filter.
	$post_params = array(
			'post_id' => anno_get_post_id(),
			'auth_cookie' => (is_ssl() ? $_COOKIE[SECURE_AUTH_COOKIE] : $_COOKIE[AUTH_COOKIE]),
			'logged_in_cookie' => $_COOKIE[LOGGED_IN_COOKIE],
			'_wpnonce' => wp_create_nonce('media-form'),
			'type' => $type,
			'tab' => $tab,
			'short' => '1',
			'action' => 'tinymce_upload',
	);
	$post_params = apply_filters( 'swfupload_post_params', $post_params );
	$p = array();
	foreach ( $post_params as $param => $val )
		$p[] = "\t\t'$param' : '$val'";
	$post_params_str = implode( ", \n", $p );

	// #8545. wmode=transparent cannot be used with SWFUpload
	if ( 'media-new.php' == $pagenow ) {
		$upload_image_path = get_user_option( 'admin_color' );
		if ( 'classic' != $upload_image_path )
			$upload_image_path = 'fresh';
		$upload_image_path = admin_url( 'images/upload-' . $upload_image_path . '.png?ver=20101205' );
	} else {
		$upload_image_path = includes_url( 'images/upload.png?ver=20100531' );
	}

	?>
	<script type="text/javascript">
	//<![CDATA[
	var swfu;
	SWFUpload.onload = function() {
		var settings = {
				button_text: '<span class="button"><?php _e('Select Files'); ?><\/span>',
				button_text_style: '.button { text-align: center; font-weight: bold; font-family:"Lucida Grande",Verdana,Arial,"Bitstream Vera Sans",sans-serif; font-size: 11px; text-shadow: 0 1px 0 #FFFFFF; color:#464646; }',
				button_height: "23",
				button_width: "132",
				button_text_top_padding: 3,
				button_image_url: '<?php echo $upload_image_path; ?>',
				button_placeholder_id: "flash-browse-button",
				upload_url : "<?php echo esc_attr( $flash_action_url ); ?>",
				flash_url : "<?php echo includes_url('js/swfupload/swfupload.swf'); ?>",
				file_post_name: "async-upload",
				file_types: "<?php echo apply_filters('upload_file_glob', '*.*'); ?>",
				post_params : {
					<?php echo $post_params_str; ?>
				},
				file_size_limit : "<?php echo $max_upload_size; ?>b",
				file_dialog_start_handler : fileDialogStart,
				file_queued_handler : annoFileQueued,
				upload_start_handler : uploadStart,
				upload_progress_handler : uploadProgress,
				upload_error_handler : uploadError,
				upload_success_handler : <?php echo apply_filters( 'swfupload_success_handler', 'uploadSuccess' ); ?>,
				upload_complete_handler : uploadComplete,
				file_queue_error_handler : fileQueueError,
				file_dialog_complete_handler : fileDialogComplete,
				swfupload_pre_load_handler: swfuploadPreLoad,
				swfupload_load_failed_handler: swfuploadLoadFailed,
				custom_settings : {
					degraded_element_id : "html-upload-ui", // id of the element displayed when swfupload is unavailable
					swfupload_element_id : "flash-upload-ui" // id of the element displayed when swfupload is available
				},
				debug: false
			};
			swfu = new SWFUpload(settings);
	};
	//]]>
	</script>

	<div id="flash-upload-ui" class="hide-if-no-js">
	<?php do_action('pre-flash-upload-ui'); ?>

		<div>
		<?php _e( 'Choose files to upload' ); ?>
		<div id="flash-browse-button"></div>
		<span><input id="cancel-upload" disabled="disabled" onclick="cancelUpload()" type="button" value="<?php esc_attr_e('Cancel Upload'); ?>" class="button" /></span>
		</div>
	<?php do_action('post-flash-upload-ui'); ?>
	</div>
	<?php endif; // $flash ?>

	<div id="html-upload-ui" <?php if ( $flash ) echo 'class="hide-if-js"'; ?>>
	<?php do_action('pre-html-upload-ui'); ?>
		<p id="async-upload-wrap">
			<label class="screen-reader-text" for="async-upload"><?php _e('Upload'); ?></label>
			<input type="file" name="async-upload" id="async-upload" />
			<?php submit_button( __( 'Upload' ), 'button', 'html-upload', false ); ?>
			<a href="#" onclick="try{top.tb_remove();}catch(e){}; return false;"><?php _e('Cancel'); ?></a>
		</p>
		<div class="clear"></div>
		<p class="media-upload-size"><?php printf( __( 'Maximum upload file size: %d%s' ), $upload_size_unit, $sizes[$u] ); ?></p>
		<?php if ( is_lighttpd_before_150() ): ?>
		<p><?php _e('If you want to use all capabilities of the uploader, like uploading multiple files at once, please update to lighttpd 1.5.'); ?></p>
		<?php endif;?>
	<?php do_action('post-html-upload-ui', $flash); ?>
	</div>
	<?php do_action('post-upload-ui'); ?>
	<?php	
}

function anno_upload_form($type = 'image', $errors = null, $id = null) {
	$post_id = anno_get_post_id();

	$form_action_url = admin_url("media-upload.php?type=$type&tab=type&post_id=$post_id");
	$form_action_url = apply_filters('media_upload_form_url', $form_action_url, $type);
?>

<form enctype="multipart/form-data" method="post" action="<?php echo esc_attr($form_action_url); ?>" class="media-upload-form type-form validate" id="<?php echo $type; ?>-form">
<?php submit_button( '', 'hidden', 'save', false ); ?>
<input type="hidden" name="post_id" id="post_id" value="<?php echo (int) $post_id; ?>" />
<?php wp_nonce_field('media-form'); ?>

<?php anno_media_upload_form( $errors ); ?>

<script type="text/javascript">
//<![CDATA[
jQuery(function($){
	var preloaded = $(".media-item.preloaded");
	if ( preloaded.length > 0 ) {
		preloaded.each(function(){prepareMediaItem({id:this.id.replace(/[^0-9]/g, '')},'');});
	}
	updateMediaForm();
});
//]]>
</script>

<p class="savebutton ml-submit">
<?php submit_button( __( 'Save all changes' ), 'button', 'save', false ); ?>
</p>
</form>
<?php 
}

function anno_get_media_items( $post_id, $errors ) {
	$attachments = array();
	if ( $post_id ) {
		$post = get_post($post_id);
		if ( $post && $post->post_type == 'attachment' )
			$attachments = array($post->ID => $post);
		else
			$attachments = get_children( array( 'post_parent' => $post_id, 'post_type' => 'attachment', 'orderby' => 'menu_order ASC, ID', 'order' => 'DESC') );
	} else {
		if ( is_array($GLOBALS['wp_the_query']->posts) )
			foreach ( $GLOBALS['wp_the_query']->posts as $attachment )
				$attachments[$attachment->ID] = $attachment;
	}

	$output = '';
	foreach ( (array) $attachments as $id => $attachment ) {
		if ( $attachment->post_status == 'trash' )
			continue;
		if ( $item = anno_get_media_item( $id, array( 'errors' => isset($errors[$id]) ? $errors[$id] : null) ) )
			$output .= "\n<div id='media-item-$id' class='media-item child-of-$attachment->post_parent preloaded'><div class='progress'><div class='bar'></div></div><div id='media-upload-error-$id'></div><div class='filename'></div>$item\n</div>";
	}

	return $output;
}

function anno_get_media_item($attachment_id, $args = null) {
	global $redir_tab;

	$post = get_post($attachment_id);

	$default_args = array(
		'errors' => null,
		'send' => $post->post_parent ? post_type_supports( get_post_type( $post->post_parent ), 'editor' ) : true,
		'delete' => true,
		'toggle' => true,
		'show_title' => true,
	);	
	$args = wp_parse_args($args, $default_args);

	extract($args, EXTR_SKIP);

	$filename = esc_html(basename( $post->guid));
	$title = esc_attr($post->post_title);
	
	ob_start();
	anno_popup_images_row_display($post);
	anno_popup_images_row_edit($post);
	$display = ob_get_contents();
	ob_end_clean();
	
	return $display;
} 

// TODO Enforce IMG

// Request handler for uploading
// Then do our magic
function anno_tinymce_request_handler() {
	if (isset($_POST['tinymce_upload']) || (isset($_POST['fetch']) && isset($_POST['attachment_id']))) {
		anno_async_upload();
	}
	
}
add_action('init', 'anno_tinymce_request_handler');

function anno_async_upload() {

	// Flash often fails to send cookies with the POST or upload, so we need to pass it in GET or POST instead
	if ( is_ssl() && empty($_COOKIE[SECURE_AUTH_COOKIE]) && !empty($_REQUEST['auth_cookie']) )
		$_COOKIE[SECURE_AUTH_COOKIE] = $_REQUEST['auth_cookie'];
	elseif ( empty($_COOKIE[AUTH_COOKIE]) && !empty($_REQUEST['auth_cookie']) )
		$_COOKIE[AUTH_COOKIE] = $_REQUEST['auth_cookie'];
	if ( empty($_COOKIE[LOGGED_IN_COOKIE]) && !empty($_REQUEST['logged_in_cookie']) )
		$_COOKIE[LOGGED_IN_COOKIE] = $_REQUEST['logged_in_cookie'];
	unset($current_user);

	header('Content-Type: text/plain; charset=' . get_option('blog_charset'));

	if ( !current_user_can('upload_files') )
		wp_die(__('You do not have permission to upload files.'));

	// just fetch the detail form for that attachment
	if ( isset($_REQUEST['attachment_id']) && ($id = intval($_REQUEST['attachment_id'])) && $_REQUEST['fetch'] ) {
		$post = get_post($id);
		if ('attachment' != $post->post_type)
			wp_die(__('Unknown post type.'));
		$post_type_object = get_post_type_object('attachment');
		if (!current_user_can( $post_type_object->cap->edit_post, $id))
			wp_die(__( 'You are not allowed to edit this item.' ));

 		add_filter('attachment_fields_to_edit', 'media_post_single_attachment_fields_to_edit', 10, 2);
		echo anno_get_media_item($id);
		exit;
	}

	check_admin_referer('media-form');

	$id = media_handle_upload('async-upload', $_REQUEST['post_id']);
	if ( is_wp_error($id) ) {
		echo '<div class="error-div">
		<a class="dismiss" href="#" onclick="jQuery(this).parents(\'div.media-item\').slideUp(200, function(){jQuery(this).remove();});">' . __('Dismiss') . '</a>
		<strong>' . sprintf(__('&#8220;%s&#8221; has failed to upload due to an error'), esc_html($_FILES['async-upload']['name']) ) . '</strong><br />' .
		esc_html($id->get_error_message()) . '</div>';
		exit;
	}

	if ( $_REQUEST['short'] ) {
		// short form response - attachment ID only
		echo $id;
	} 
	else {
		// long form response - big chunk o html
		$type = $_REQUEST['type'];
		echo apply_filters("async_upload_{$type}", $id);
	}
}

?>
