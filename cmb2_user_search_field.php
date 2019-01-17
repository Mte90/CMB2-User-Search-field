<?php
/*
  Plugin Name: CMB2 User Search field
  Plugin URI: http://mte90.net
  Description: Custom field for CMB2 which adds a user-search dialog for searching/attaching user IDs
  Author: Mte90
  Author URI: http://mte90.net
  Version: 0.2.0
  License: GPLv2
 */

function cmb2_user_search_render_field( $field, $escaped_value, $object_id, $object_type, $field_type ) {
	$select_type = $field->args( 'select_type' );
	if ( is_array( $field->args( 'roles' ) ) ) {
		$roles = implode( ',', $field->args( 'roles' ) );
	} else {
		$roles = '';
	}
	echo $field_type->input( array(
	    'data-roles' => $roles,
	    'data-selecttype' => 'radio' == $select_type ? 'radio' : 'checkbox',
	    'autocomplete' => 'off',
	    'style' => 'display:none'
	) );
	echo '<ul style="cursor:move">';
	if ( !empty( $field->escaped_value ) ) {
		$list = explode( ',', $field->escaped_value );
		foreach ( $list as $value ) {
			$user = get_user_by( 'id', $value );
			$name = $user->display_name ? $user->display_name : $user->user_login;
			echo '<li data-id="' . trim( $value ) . '"><b>' . __( 'Name' ) . ':</b> ' . $name;
			echo '<div title="' . __( 'Remove' ) . '" style="color: #999;margin: -0.1em 0 0 2px; cursor: pointer;" class="cmb-user-search-remove dashicons dashicons-no"></div>';
			echo '</li>';
		}
	}
	echo '</ul>';
	
	// JS needed for modal
	// wp_enqueue_media();
	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'wp-backbone' );
	wp_enqueue_script( 'jquery-ui-sortable' );

	if ( !is_admin() ) {
		// Will need custom styling!
		// @todo add styles for front-end
		require_once( ABSPATH . 'wp-admin/includes/template.php' );
		do_action( 'cmb2_user_search_field_add_find_users_div' );
	}

	// markup needed for modal
	add_action( 'admin_footer', 'find_users_div' );

	$error = __( 'An error has occurred. Please reload the page and try again.' );
	$find = __( 'Find Posts or Pages' );

	// @TODO this should really be in its own JS file.
	?>
	<script type="text/javascript">
	        jQuery(document).ready(function ($) {
	          'use strict';

	          var l10n = {
	            'error': '<?php echo esc_js( $error ); ?>',
	            'find': '<?php echo esc_js( $find ) ?>'
	          };

	          var UserSearchView<?php echo str_replace( '-', '_', $field->args( 'id' ) ) ?> = window.Backbone.View.extend({
	            el: '#find-users',
	            overlaySet: false,
	            $overlay: false,
	            $idInput: false,
	            $checked: false,
	            $checkedLabel: false,
	            events: {
	              'keypress .find-box-search :input': 'maybeStartSearch',
	              'keyup #find-users-input': 'escClose',
	              'click #find-users-submit': 'selectPost',
	              'click #find-users-search': 'send',
	              'click #find-users-close': 'close'
	            },
	            initialize: function () {
	              this.$spinner = this.$el.find('.find-box-search .spinner');
	              this.$input = this.$el.find('#find-users-input');
	              this.$response = this.$el.find('#find-users-response');
	              this.$overlay = $('#find-users-ui-find-overlay');

	              this.listenTo(this, 'open', this.open);
	              this.listenTo(this, 'close', this.close);
	            },
	            escClose: function (evt) {
	              if (evt.which && 27 === evt.which) {
	                this.close();
	              }
	            },
	            close: function () {
	              this.$overlay.hide();
	              this.$el.hide();
	            },
	            open: function () {
	              this.$response.html('');

	              this.$el.show();

	              this.$input.focus();

	              if (!this.$overlay.length) {
	                var number = Math.floor( Math.random() * 10 );
	                $('body').append('<div id="find-users-ui-find-overlay' + number + '" class="ui-find-overlay"></div>');
	                this.$overlay = $('#find-users-ui-find-overlay' + number);
	              }

	              this.$overlay.show();

	              // Pull some results up by default
	              this.send();

	              return false;
	            },
	            maybeStartSearch: function (evt) {
	              if (13 === evt.which) {
	                this.send();
	                return false;
	              }
	            },
	            send: function () {

	              var search = this;
	              search.$spinner.show();

	              $.ajax(ajaxurl, {
	                type: 'POST',
	                dataType: 'json',
	                data: {
	                  ps: search.$input.val(),
	                  action: 'find_users',
	                  cmb2_user_search: true,
	                  roles: search.roles,
	                  _ajax_nonce: $('#find-users #_ajax_nonce').val()
	                }
	              }).always(function () {

	                search.$spinner.hide();

	              }).done(function (response) {

	                if (!response.success) {
	                  search.$response.text(l10n.error);
	                }

	                var data = response.data;

	                if ('checkbox' === search.selectType) {
	                  data = data.replace(/type="radio"/gi, 'type="checkbox"');
	                }

	                search.$response.html(data);

	              }).fail(function () {
	                search.$response.text(l10n.error);
	              });
	            },
	            selectPost: function (evt) {
	              evt.preventDefault();

	              this.$checked = $('#find-users-response input[type="' + this.selectType + '"]:checked');

	              var checked = this.$checked.map(function () {
	                return this.value;
	              }).get();

	              if (!checked.length) {
	                this.close();
	                return;
	              }

	              var label = [];
	              $.each(checked, function (index, value) {
	                label.push($('#find-users-response label[for="found-' + value + '"]').html());
	              });
	              this.$checkedLabel = label;
	              this.handleSelected(checked);
	            },
	            handleSelected: function (checked) {
	              var existing = this.$idInput.val();
	              existing = existing ? existing + ', ' : '';
	              var newids = checked.join(', ');
	              var ids = existing + newids;
	              this.$idInput.val(ids);

	              var labels = this.$checkedLabel;
	              if (newids.indexOf(',') !== -1) {
	                ids = newids.split(',');
	                $.each(ids, function (index, value) {
	                  var cleaned = value.trim().toString();
	                  if ($('.cmb-type-user-search-text.cmb2-id-<?php echo str_replace( '_', '-', sanitize_html_class( $field->args( 'id' ) ) ) ?> ul li[data-id="' + cleaned + '"]').length === 0) {
	                    $('.cmb-type-user-search-text.cmb2-id-<?php echo str_replace( '_', '-', sanitize_html_class( $field->args( 'id' ) ) ) ?> ul').append('<li data-id="' + cleaned + '"><b><?php _e( 'Title' ) ?>:</b> ' + labels[index] + '<div title="<?php _e( 'Remove' ) ?>" style="color: #999;margin: -0.1em 0 0 2px; cursor: pointer;" class="cmb-user-search-remove dashicons dashicons-no"></div></li>');
	                  }
	                });
	              } else {
	                if ($('.cmb-type-user-search-text.cmb2-id-<?php echo str_replace( '_', '-', sanitize_html_class( $field->args( 'id' ) ) ) ?> ul li[data-id="' + newids + '"]').length === 0) {
	                  $('.cmb-type-user-search-text.cmb2-id-<?php echo str_replace( '_', '-', sanitize_html_class( $field->args( 'id' ) ) ) ?> ul').append('<li data-id="' + newids + '"><b><?php _e( 'Name' ) ?>:</b> ' + this.$checkedLabel[0] + '<div title="<?php _e( 'Remove' ) ?>" style="color: #999;margin: -0.1em 0 0 2px; cursor: pointer;" class="cmb-user-search-remove dashicons dashicons-no"></div></li>');
	                }
	              }

	              this.close();
	            }

	          });

	          window.cmb2_user_search<?php echo str_replace( '-', '_', $field->args( 'id' ) ) ?> = new UserSearchView<?php echo str_replace( '-', '_', $field->args( 'id' ) ) ?>();

	          $('.cmb-type-user-search-text.cmb2-id-<?php echo str_replace( '_', '-', sanitize_html_class( $field->args( 'id' ) ) ) ?> .cmb-th label').after('<div title="' + l10n.find + '" style="position:relative;left:30%;color: #999;cursor: pointer;" class="dashicons dashicons-search"></div>');

	          $('.cmb-type-user-search-text.cmb2-id-<?php echo str_replace( '_', '-', sanitize_html_class( $field->args( 'id' ) ) ) ?> .cmb-th .dashicons-search').on('click', openSearch);

	          function openSearch(evt) {
	            var search = window.cmb2_user_search<?php echo str_replace( '-', '_', $field->args( 'id' ) ) ?>;
	            search.$idInput = $(evt.currentTarget).parents('.cmb-type-user-search-text').find('.cmb-td input[type="text"]');
	            search.roles = search.$idInput.data('roles');
	            search.selectType = 'radio' === search.$idInput.data('selecttype') ? 'radio' : 'checkbox';

	            search.trigger('open');
	          }

	          $('.cmb-type-user-search-text.cmb2-id-<?php echo str_replace( '_', '-', sanitize_html_class( $field->args( 'id' ) ) ) ?>').on('click', '.cmb-user-search-remove', function () {
	            var ids = $('.cmb-type-user-search-text.cmb2-id-<?php echo str_replace( '_', '-', sanitize_html_class( $field->args( 'id' ) ) ) ?>').find('.cmb-td input[type="text"]').val();
	            var $choosen = $(this);
	            if (ids.indexOf(',') !== -1) {
	              ids = ids.split(',');
	              var loopids = ids.slice(0);
	              $.each(loopids, function (index, value) {
	                var cleaned = value.trim().toString();
	                if (String($choosen.parent().data('id')) === cleaned) {
	                  $choosen.parent().remove();
	                  ids.splice(index, 1);
	                }
	              });
	              $('.cmb-type-user-search-text.cmb2-id-<?php echo str_replace( '_', '-', sanitize_html_class( $field->args( 'id' ) ) ) ?>').find('.cmb-td input[type="text"]').val(ids.join(','));
	            } else {
	              $choosen.parent().remove();
	              $('.cmb-type-user-search-text.cmb2-id-<?php echo str_replace( '_', '-', sanitize_html_class( $field->args( 'id' ) ) ) ?>').find('.cmb-td input[type="text"]').val('');
	            }
	          });

	          $(".cmb-type-user-search-text.cmb2-id-<?php echo str_replace( '_', '-', sanitize_html_class( $field->args( 'id' ) ) ) ?> ul").sortable({
	            update: function (event, ui) {
	              var ids = [];
	              $('.cmb-type-user-search-text.cmb2-id-<?php echo str_replace( '_', '-', sanitize_html_class( $field->args( 'id' ) ) ) ?> ul li').each(function (index, value) {
	                ids.push($(this).data('id'));
	              });
	              $('.cmb-type-user-search-text.cmb2-id-<?php echo str_replace( '_', '-', sanitize_html_class( $field->args( 'id' ) ) ) ?>').find('.cmb-td input[type="text"]').val(ids.join(', '));
	            }
	          });

	        });
	</script>
	<?php
}

add_action( 'cmb2_render_user_search_text', 'cmb2_user_search_render_field', 10, 5 );

/**
 * Add the find posts div via a hook so we can relocate it manually
 */
function _add_find_user_div() {
	add_action( 'wp_footer', 'find_users_div' );
}

add_action( 'cmb2_user_search_field_add_find_users_div', 'cmb2_user_search_field_add_find_users_div' );

/**
 * 
 * Based on find_posts
 * 
 * @param type $found_action
 */
function find_users_div( $found_action = '' ) {
	?>
	<style>
	    #find-users-close {
		width: 36px;
		height: 36px;
		position: absolute;
		top: 0px;
		right: 0px;
		cursor: pointer;
		text-align: center;
		color: #666;
	    }
	    #find-users-close::before {
		font: 400 20px/36px dashicons;
		vertical-align: top;
		content: "";
	    }
	    #find-users-close:hover {
		color: #00A0D2;
	    }
	</style>
	<div id="find-users" class="find-box" style="display: none;">
	    <div id="find-users-head" class="find-box-head">
		<?php _e( 'Users' ); ?>
		<div id="find-users-close"></div>
	    </div>
	    <div class="find-box-inside">
		<div class="find-box-search">
		    <?php if ( $found_action ) { ?>
			    <input type="hidden" name="found_action" value="<?php echo esc_attr( $found_action ); ?>" />
		    <?php } ?>
		    <input type="hidden" name="affected" id="affected" value="" />
		    <?php wp_nonce_field( 'find-users', '_ajax_nonce', false ); ?>
		    <label class="screen-reader-text" for="find-users-input"><?php _e( 'Search' ); ?></label>
		    <input type="text" id="find-users-input" name="ps" value="" autocomplete="off" />
		    <span class="spinner"></span>
		    <input type="button" id="find-users-search" value="<?php esc_attr_e( 'Search' ); ?>" class="button" />
		    <div class="clear"></div>
		</div>
		<div id="find-users-response"></div>
	    </div>
	    <div class="find-box-buttons">
		<?php submit_button( __( 'Select' ), 'button-primary alignright', 'find-users-submit', false ); ?>
		<div class="clear"></div>
	    </div>
	</div>
	<?php
}

/**
 * Ajax handler for querying posts for the Find Users modal.
 *
 * @see window.findPosts
 *
 * @since 3.1.0
 */
function wp_ajax_find_users() {
	check_ajax_referer( 'find-users' );

	if ( isset( $_POST[ 'roles' ] ) && !empty( $_POST[ 'roles' ] ) ) {
		$roles = explode( ',', wp_unslash( $_POST[ 'roles' ] ) );
	} else {
		$roles = '';
	}

	$s = wp_unslash( $_POST[ 'ps' ] );
	$users = array();
	if ( is_array( $roles ) ) {
		foreach ( $roles as $role ) {
			$users_query = new WP_User_Query( array(
			    'role' => $role,
			    'orderby' => 'display_name',
			    'search' => '*' . $s . '*',
			    'search_columns' => array( 'user_login', 'user_email', 'user_nicename', 'display_name' )
				) );

			$results = $users_query->get_results();
			if ( $results ) {
				$users = array_merge( $users, $results );
			}
		}
	} else {
		$user_query = new WP_User_Query( array(
		    'orderby' => 'display_name',
		    'search' => '*' . $s . '*',
		    'search_columns' => array( 'user_login', 'user_email', 'user_nicename', 'display_name' )
			) );

		$users = $user_query->get_results();
	}
	if ( !$users ) {
		wp_send_json_error( __( 'No items found.' ) );
	}

	$html = '<table class="widefat"><thead><tr><th class="found-radio"><br /></th><th>' . __( 'Name' ) . '</th><th class="no-break">' . __( 'Email' ) . '</th></tr></thead><tbody>';
	$alt = '';

	foreach ( $users as $user ) {
		$title = $user->display_name ? $user->display_name : $user->user_login;
		$alt = ( 'alternate' == $alt ) ? '' : 'alternate';

		$html .= '<tr class="' . trim( 'found-users ' . $alt ) . '"><td class="found-radio"><input type="radio" id="found-' . $user->ID . '" name="found_post_id" value="' . esc_attr( $user->ID ) . '"></td>';
		$html .= '<td><label for="found-' . $user->ID . '">' . esc_html( $title ) . '</label></td><td>' . $user->user_email . '</td></tr>' . "\n\n";
	}

	$html .= '</tbody></table>';

	wp_send_json_success( $html );
}
add_action( 'wp_ajax_find_users', 'wp_ajax_find_users' );

/**
 * Add support for search on Dsiplay Name field
 * 
 * @param array $search_columns
 * @return string
 */
function cmb2_user_search_field_display_name( $search_columns ) {
	$search_columns[] = 'display_name';
	return $search_columns;
}
add_filter( 'user_search_columns', 'cmb2_user_search_field_display_name' );
