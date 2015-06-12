<?php /*
--------------------------------------------------------------------------------
Plugin Name: Comment Tagger
Description: Lets logged-in readers tag comments.
Version: 0.1
Author: Christian Wach
Author URI: http://haystack.co.uk
Plugin URI: https://github.com/christianwach/comment-tagger
Text Domain: comment-tagger
Domain Path: /languages
--------------------------------------------------------------------------------
*/



// define version (bumping this refreshes CSS and JS)
define( 'COMMENT_TAGGER_VERSION', '0.1' );

// define taxonomy name
if ( ! defined( 'COMMENT_TAGGER_TAX' ) ) {
	define( 'COMMENT_TAGGER_TAX', 'classification' );
}

// define taxonomy prefix for Select2
if ( ! defined( 'COMMENT_TAGGER_PREFIX' ) ) {

	// this is a "unique-enough" prefix so we can distinguish between new tags
	// and the selection of pre-existing ones when the comment form is posted
	define( 'COMMENT_TAGGER_PREFIX', 'cmmnt_tggr' );

}



/*
--------------------------------------------------------------------------------
Comment_Tagger Class
--------------------------------------------------------------------------------
*/
class Comment_Tagger {



	/**
	 * Returns a single instance of this object when called
	 *
	 * @return object $instance Comment_Tagger instance
	 */
	public static function instance() {

		// store the instance locally to avoid private static replication
		static $instance = null;

		// do we have it?
		if ( null === $instance ) {

			// instantiate
			$instance = new Comment_Tagger;

			// initialise
			$instance->register_hooks();

		}

		// always return instance
		return $instance;

	}



	/**
	 * Register the hooks that our plugin needs
	 *
	 * @return void
	 */
	private function register_hooks() {

		// enable translation
		add_action( 'plugins_loaded', array( $this, 'enable_translation' ) );

		// create taxonomy
		add_action( 'init', array( $this, 'create_taxonomy' ), 0 );

		// admin hooks

		// add admin page
		add_action( 'admin_menu', array( $this, 'admin_page' ) );

		// hack the menu parent
		add_filter( 'parent_file', array( $this, 'parent_menu' ) );

		// add admin styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		// register a meta box
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

		// intercept comment save process
		add_action( 'comment_post', array( $this, 'intercept_comment_save' ), 20, 2 );

		// intercept comment edit process
		add_action( 'edit_comment', array( $this, 'update_comment_terms' ) );

		// intercept comment delete process
		add_action( 'delete_comment', array( $this, 'delete_comment_terms' ) );

		// front-end hooks

		// register any public styles
		add_action( 'wp_enqueue_scripts', array( $this, 'front_end_enqueue_styles' ), 20 );

		// register any public scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'front_end_enqueue_scripts' ), 20 );

		// add UI to CommentPress comments
		add_action( 'commentpress_comment_form_pre_comment_id_fields', array( $this, 'front_end_markup' ) );

		// fwiw, broadcast to other plugins
		do_action( 'comment_tagger_loaded' );

	}



	/**
	 * Load translation files
	 *
	 * A good reference on how to implement translation in WordPress:
	 * http://ottopress.com/2012/internationalization-youre-probably-doing-it-wrong/
	 *
	 * @return void
	 */
	public function enable_translation() {

		// load translations if they exist
		load_plugin_textdomain(

			// unique name
			'comment-tagger',

			// deprecated argument
			false,

			// relative path to directory containing translation files
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'

		);

	}



	/**
	 * Create a free-tagging taxonomy for comments
	 *
	 * @return void
	 */
	public function create_taxonomy() {

		register_taxonomy(

			COMMENT_TAGGER_TAX,
			'comment',

			array(

				// general
				'public' => true,
				'hierarchical' => false,

				// labels
				'labels' => array(
					'name' => __( 'Comment Tags', 'comment-tagger' ),
					'singular_name' => __( 'Comment Tag', 'comment-tagger' ),
					'menu_name' => __( 'Comment Tags', 'comment-tagger' ),
					'search_items' => __( 'Search Comment Tags', 'comment-tagger' ),
					'popular_items' => __( 'Popular Comment Tags', 'comment-tagger' ),
					'all_items' => __( 'All Comment Tags', 'comment-tagger' ),
					'edit_item' => __( 'Edit Comment Tag', 'comment-tagger' ),
					'update_item' => __( 'Update Comment Tag', 'comment-tagger' ),
					'add_new_item' => __( 'Add New Comment Tag', 'comment-tagger' ),
					'new_item_name' => __( 'New Comment Tag Name', 'comment-tagger' ),
					'separate_items_with_commas' => __( 'Separate Comment Tags with commas', 'comment-tagger' ),
					'add_or_remove_items' => __( 'Add or remove Comment Tag', 'comment-tagger' ),
					'choose_from_most_used' => __( 'Choose from the most popular Comment Tags', 'comment-tagger' ),
				),

				// permalinks
				'rewrite' => array(
					'with_front' => true,
					'slug' => apply_filters( 'comment_tagger_tax_slug', 'comments/tags' )
				),

				/*
				// capabilities
				'capabilities' => array(
					'manage_terms' => 'edit_users', // Using 'edit_users' cap to keep this simple.
					'edit_terms' => 'edit_users',
					'delete_terms' => 'edit_users',
					'assign_terms' => 'read',
				),
				*/

				// custom function to update the count
				'update_count_callback' => array( $this, 'update_tag_count' ),

			)

		);

		// register any hooks/filters that rely on knowing the taxonomy now
		add_filter( 'manage_edit-' . COMMENT_TAGGER_TAX . '_columns', array( $this, 'set_comment_column' ) );
		add_action( 'manage_' . COMMENT_TAGGER_TAX . '_custom_column', array( $this, 'set_comment_column_values'), 10, 3 );

	}



	/**
	 * Force update the number of comments for a taxonomy term
	 *
	 * @return void
	 */
	public function refresh_tag_count() {

		$terms = get_terms( COMMENT_TAGGER_TAX, array( 'hide_empty' => false ) );
		$tids = array();
		foreach( $terms AS $term ) {
			$tids[] = $term->term_taxonomy_id;
		}
		wp_update_term_count_now( $tids, COMMENT_TAGGER_TAX );

	}



	/**
	 * Manually update the number of comments for a taxonomy term
	 *
	 * @see	_update_post_term_count()
	 *
	 * @param array $terms List of Term taxonomy IDs
	 * @param object $taxonomy Current taxonomy object of terms
	 * @return void
	 */
	public function update_tag_count( $terms, $taxonomy ) {

		// access db wrapper
		global $wpdb;

		// loop through each term...
		foreach( (array) $terms AS $term ) {

			// construct sql
			$sql = $wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->comments " .
				"WHERE $wpdb->term_relationships.object_id = $wpdb->comments.comment_ID " .
				"AND $wpdb->term_relationships.term_taxonomy_id = %d",
				$term
			);

			// get count
			$count = $wpdb->get_var( $sql );

			// update
			do_action( 'edit_term_taxonomy', $term, $taxonomy );
			$wpdb->update( $wpdb->term_taxonomy, array( 'count' => $count ), array( 'term_taxonomy_id' => $term ) );
			do_action( 'edited_term_taxonomy', $term, $taxonomy );

		}

	}



	/**
	 * Creates the admin page for the taxonomy under the 'Comments' menu.
	 *
	 * @return void
	 */
	public function admin_page() {

		// get taxonomy object
		$tax = get_taxonomy( COMMENT_TAGGER_TAX );

		// add as subpage of 'Comments' menu item
		add_comments_page(
			esc_attr( $tax->labels->menu_name ),
			esc_attr( $tax->labels->menu_name ),
			$tax->cap->manage_terms,
			'edit-tags.php?taxonomy=' . $tax->name
		);

	}



	/**
	 * Enqueue CSS in WP admin to tweak the appearance of various elements
	 *
	 * @return void
	 */
	public function enqueue_styles() {

		global $pagenow;

		// if we're on our taxonomy page
		if ( ! empty( $_GET['taxonomy'] ) AND $_GET['taxonomy'] == COMMENT_TAGGER_TAX AND $pagenow == 'edit-tags.php' ) {

			// add basic stylesheet
			wp_enqueue_style(
				'comment_tagger_css',
				plugin_dir_url( __FILE__ ) . 'assets/css/comment-tagger-admin.css',
				false,
				COMMENT_TAGGER_VERSION, // version
				'all' // media
			);

		}

		// if we're on the "Edit Comment" page
		if (  $pagenow == 'comment.php' AND ! empty( $_GET['action'] ) AND $_GET['action'] == 'editcomment' ) {

			// the tags meta box requires this script
			wp_enqueue_script( 'post' );

		}

	}



	/**
	 * Fix a bug with highlighting the parent menu item
	 *
	 * By default, when on the edit taxonomy page for a user taxonomy, the "Posts" tab
	 * is highlighted. This will correct that bug
	 *
	 * @param string $parent The existing parent menu item
	 * @return string $parent The modified parent menu item
	 */
	public function parent_menu( $parent = '' ) {

		global $pagenow;

		// if we're editing our comment taxonomy highlight the Comments menu
		if ( ! empty( $_GET['taxonomy'] ) AND $_GET['taxonomy'] == COMMENT_TAGGER_TAX AND $pagenow == 'edit-tags.php' ) {
			$parent	= 'edit-comments.php';
		}

		// --<
		return $parent;

	}



	/**
	 * Correct the column name for comment taxonomies - replace "Posts" with "Comments"
	 *
	 * @param array $columns An array of columns to be shown in the manage terms table
	 * @return array $columns Modified array of columns to be shown in the manage terms table
	 */
	public function set_comment_column( $columns ) {

		// replace column
		unset($columns['posts']);
		$columns['comments'] = __( 'Comments', 'comment-tagger' );

		// --<
		return $columns;

	}



	/**
	 * Set values for custom columns in comment taxonomies
	 *
	 * @param string $display WP just passes an empty string here.
	 * @param string $column The name of the custom column.
	 * @param int $term_id The ID of the term being displayed in the table.
	 * @return void
	 */
	public function set_comment_column_values( $display, $column, $term_id ) {

		if ( 'comments' === $column ) {
			$term = get_term( $term_id, $_GET['taxonomy'] );
			echo $term->count;
		}

	}



	/**
	 * Register a meta box for the comment edit screen
	 *
	 * @return void
	 */
	public function add_meta_box() {

		/*
		// add meta box
		add_meta_box(
			'comment_tagger_meta_box',
			__( 'Comment Tagger', 'comment-tagger' ),
			array( $this, 'comment_meta_box' ),
			'comment',
			'normal'
		);
		*/

		// let's use the built-in tags metabox
		add_meta_box(
			'tagsdiv-post_tag',
			__( 'Comment Tags', 'comment-tagger' ), // custom name
			'comment_tagger_post_tags_meta_box', // custom callback
			'comment',
			'normal',
			'default',
			array( 'taxonomy' => COMMENT_TAGGER_TAX )
		);

	}



	/**
	 * Add a meta box to the comment edit screen
	 *
	 * @return void
	 */
	public function comment_meta_box() {

		// access comment
		global $comment;

		$tax = get_taxonomy( COMMENT_TAGGER_TAX );

		// make sure the user can assign terms
		if ( ! current_user_can( $tax->cap->assign_terms ) ) return;

		// get the terms
		$terms = get_terms( COMMENT_TAGGER_TAX, array( 'hide_empty' => false ) );

		// get terms for this comment
		$object_terms = wp_get_object_terms( $comment->comment_ID, COMMENT_TAGGER_TAX );
		$stack = array();
		foreach( $object_terms AS $object_term ) {
			$stack[] = $object_term->slug;
		}

		?>

		<table class="form-table">

			<tr>

			<th><label for="<?php echo COMMENT_TAGGER_TAX; ?>"><?php _e( 'Select Tag', 'comment-tagger' ); ?></label></th>

			<td><?php

			// if there are any terms...
			if ( ! empty( $terms ) ) {

				// loop through them and display checkboxes
				foreach( $terms AS $term ) {

					// construct identifier
					$identifier = COMMENT_TAGGER_TAX . '-' . esc_attr( $term->slug );

					// init unchecked
					$checked = '';

					// get checked value
					if ( in_array( $term->slug, $stack ) ) {
						$checked = ' checked="checked"';
					}

					// construct input
					$input = '<input type="checkbox" name="' . COMMENT_TAGGER_TAX . '[]" id="' . $identifier . '" value="' . esc_attr( $term->slug ) . '"' . $checked . ' />';

					// construct label
					$label = '<label for="' . $identifier . '"> ' . esc_html( $term->name ) . '</label>';

					// print to screen
					echo $input . ' ' . $label . ' <br />';

				}

			} else {

				// if there are no terms, display a message
				_e( 'There are no comment tags available.', 'comment-tagger' );

			}

			?></td>

			</tr>

		</table>

		<?php

	}



	/**
	 * Intercept the comment save process and maybe update terms
	 *
	 * @param int $comment_id The numeric ID of the comment
	 * @param str $comment_status The status of the comment
	 * @return void
	 */
	public function intercept_comment_save( $comment_id, $comment_status ) {

		// bail if we didn't receive any terms
		if ( ! isset( $_POST['comment_tagger_tags'] ) ) return;

		// bail if the terms array is somehow invalid
		if ( ! is_array( $_POST['comment_tagger_tags'] ) ) return;
		if ( count( $_POST['comment_tagger_tags'] ) === 0 ) return;

		// init "existing" and "new" arrays
		$existing_term_ids = array();
		$new_term_ids = array();
		$new_terms = array();

		// parse the received terms
		foreach( $_POST['comment_tagger_tags'] AS $term ) {

			// does the term contain our prefix?
			if ( strstr( $term, COMMENT_TAGGER_PREFIX ) ) {

				// it's an existing term
				$tmp = explode( '-', $term );

				// get term ID
				$term_id = isset( $tmp[1] ) ? intval( $tmp[1] ) : 0;

				// add to existing
				if ( $term_id !== 0 ) $existing_term_ids[] = $term_id;

			} else {

				// add term to new
				$new_terms[] = $term;

			}

		}

		// do we have any *new* terms?
		if ( count( $new_terms ) > 0 ) {

			// get sanitised term IDs
			$new_term_ids = $this->sanitise_comment_terms( $new_terms );

		}

		// combine arrays
		$term_ids = array_unique( array_merge( $existing_term_ids, $new_term_ids ) );

		// did we get any?
		if ( ! empty( $term_ids ) ) {

			// overwrite with new terms
			wp_set_object_terms( $comment_id, $term_ids, COMMENT_TAGGER_TAX, false );

		}

	}



	/**
	 * Save data returned by our comment meta box
	 *
	 * @param int $comment_id The ID of the comment being saved
	 * @return void
	 */
	public function update_comment_terms( $comment_id ) {

		$tax = get_taxonomy( COMMENT_TAGGER_TAX );

		// make sure the user can assign terms
		if ( ! current_user_can( $tax->cap->assign_terms ) ) return;

		// init "existing" and "new" arrays
		$existing_term_ids = array();
		$new_term_ids = array();

		// do we have any *existing* terms?
		if ( isset( $_POST['tax_input'][COMMENT_TAGGER_TAX] ) ) {

			// get sanitised term IDs
			$existing_term_ids = $this->sanitise_comment_terms( $_POST['tax_input'][COMMENT_TAGGER_TAX] );

		}

		// do we have any *new* terms?
		if ( isset( $_POST['newtag'][COMMENT_TAGGER_TAX] ) ) {

			// get sanitised term IDs
			$new_term_ids = $this->sanitise_comment_terms( $_POST['newtag'][COMMENT_TAGGER_TAX] );

		}

		// combine arrays
		$term_ids = array_unique( array_merge( $existing_term_ids, $new_term_ids ) );

		// did we get any?
		if ( ! empty( $term_ids ) ) {

			// overwrite with new terms
			wp_set_object_terms( $comment_id, $term_ids, COMMENT_TAGGER_TAX, false );

		}

		// clear cache
		clean_object_term_cache( $comment_id, COMMENT_TAGGER_TAX );

	}



	/**
	 * Sanitise comment terms
	 *
	 * @param mixed $raw_terms The term names as retrieved from $_POST
	 * @return void
	 */
	private function sanitise_comment_terms( $raw_terms ) {

		// is this a multi-term taxonomy?
		if ( is_array( $raw_terms ) ) {

			// yes, get terms and validate
			$terms = array_map( 'esc_attr', $raw_terms );

		} else {

			// we should receive a comma-delimited array of term names
			$terms = array_map( 'esc_attr', explode( ',', $raw_terms ) );

		}

		// init term IDs
		$term_ids = array();

		// loop through them
		foreach( $terms AS $term ) {

			// does the term exist?
			$exists = term_exists( $term, COMMENT_TAGGER_TAX );

			// if it does...
			if ( $exists !== 0 AND $exists !== null ) {

				// should be array e.g. array('term_id'=>12,'term_taxonomy_id'=>34)
				// since we specify the taxonomy.

				// add term ID to array
				$term_ids[] = $exists['term_id'];

			} else {

				// let's add the term - but note: return value is either:
				// WP_Error or array e.g. array('term_id'=>12,'term_taxonomy_id'=>34)
				$new_term = wp_insert_term( $term, COMMENT_TAGGER_TAX );

				// skip if error
				if ( is_wp_error( $new_term ) ) {

					// there was an error somewhere and the terms couldn't be set:
					// we should let people know at some point...

				} else {

					// add term ID to array
					$term_ids[] = $new_term['term_id'];

				}

			}

		}

		// did we get any?
		if ( ! empty( $term_ids ) ) {

			// sanity checks
			$term_ids = array_map( 'intval', $term_ids );
			$term_ids = array_unique( $term_ids );

		}

		// --<
		return $term_ids;

	}



	/**
	 * Delete comment terms when a comment is deleted
	 *
	 * @param int $comment_id The ID of the comment being saved
	 * @return void
	 */
	public function delete_comment_terms( $comment_id ) {

		wp_delete_object_term_relationships( $comment_id, COMMENT_TAGGER_TAX );
		clean_object_term_cache( $comment_id, COMMENT_TAGGER_TAX );

	}



	/**
	 * Show front-end version of tags metabox in CommentPress
	 *
	 * @return void
	 */
	public function front_end_markup() {

		// only our taxonomy
		$taxonomies = array( COMMENT_TAGGER_TAX );

		// config
		$args = array(
			'orderby' => 'count',
			'order' => 'DESC',
			'number' => 5,
		);

		// get top 5 most used tags
		$tags = get_terms( $taxonomies, $args );

		// construct default options for Select2
		$most_used_tags_array = array();
		foreach( $tags AS $tag ) {
			$most_used_tags_array[] = '<option value="' . COMMENT_TAGGER_PREFIX . '-' . $tag->term_id . '">' . esc_html( $tag->name ) . '</option>';
		}
		$most_used_tags = implode( "\n", $most_used_tags_array );

		// use Select2 in "tag" mode
		echo '<div class="comment_tagger_select2_container">
				<h5>' . __( 'Tag this comment', 'comment-tagger' ) . '</h5>
				<select class="comment_tagger_select2" name="comment_tagger_tags[]" id="comment_tagger_tags" multiple="multiple" style="width: 100%;">
					' . $most_used_tags . '
				</select>
			 </div>';

	}



	/**
	 * Add our front-end stylesheets
	 *
	 * @return void
	 */
	public function front_end_enqueue_styles() {

		// register Select2 styles
		wp_register_style(
			'comment_tagger_select2_css',
			set_url_scheme( 'http://cdnjs.cloudflare.com/ajax/libs/select2/4.0.0/css/select2.min.css' )
		);

		// enqueue Select2 styles
		wp_enqueue_style( 'comment_tagger_select2_css' );

	}



	/**
	 * Add our front-end Javascripts
	 *
	 * @return void
	 */
	public function front_end_enqueue_scripts() {

		// register Select2
		wp_register_script(
			'comment_tagger_select2_js',
			set_url_scheme( 'http://cdnjs.cloudflare.com/ajax/libs/select2/4.0.0/js/select2.min.js' ),
			array( 'jquery' )
		);

		// enqueue Select2
		wp_enqueue_script( 'comment_tagger_select2_js' );

		// enqueue js
		wp_enqueue_script(
			'comment_tagger_select2_custom_js',
			plugin_dir_url( __FILE__ ) . 'assets/js/comment-tagger.js',
			array( 'comment_tagger_select2_js' ),
			COMMENT_TAGGER_VERSION
		);

		// localisation array
		$vars = array(
			'localisation' => array(),
			'data' => array(),
		);

		// localise with wp function
		wp_localize_script(
			'comment_tagger_select2_custom_js',
			'CommentTaggerSettings',
			$vars
		);

	}



} // end class Comment_Tagger



/**
 * Instantiate plugin object
 *
 * @return object the plugin instance
 */
function comment_tagger() {
	return Comment_Tagger::instance();
}

// init Comment Tagger
comment_tagger();



/**
 * This is a clone of `post_tags_meta_box` which is usually used to display post
 * tags form fields. It has been modified so that the terms are assigned to the
 * comment not the post. There's a to-do note on the original that suggests that
 * it should be made more compatible with general taxonomies...
 *
 * @todo Create taxonomy-agnostic wrapper for this.
 *
 * @see post_tags_meta_box
 *
 * @param WP_Post $post Post object.
 * @param array   $box {
 *     Tags meta box arguments.
 *
 *     @type string   $id       Meta box ID.
 *     @type string   $title    Meta box title.
 *     @type callback $callback Meta box display callback.
 *     @type array    $args {
 *         Extra meta box arguments.
 *
 *         @type string $taxonomy Taxonomy. Default 'post_tag'.
 *     }
 * }
 */
function comment_tagger_post_tags_meta_box( $post, $box ) {

	// access comment
	global $comment;

	$defaults = array( 'taxonomy' => 'post_tag' );
	if ( ! isset( $box['args'] ) || ! is_array( $box['args'] ) ) {
		$args = array();
	} else {
		$args = $box['args'];
	}
	$r = wp_parse_args( $args, $defaults );
	$tax_name = esc_attr( $r['taxonomy'] );
	$taxonomy = get_taxonomy( $r['taxonomy'] );
	$user_can_assign_terms = current_user_can( $taxonomy->cap->assign_terms );
	$comma = _x( ',', 'tag delimiter' );
?>
<div class="tagsdiv" id="<?php echo $tax_name; ?>">
	<div class="jaxtag">
	<div class="nojs-tags hide-if-js">
	<p><?php echo $taxonomy->labels->add_or_remove_items; ?></p>
	<textarea name="<?php echo "tax_input[$tax_name]"; ?>" rows="3" cols="20" class="the-tags" id="tax-input-<?php echo $tax_name; ?>" <?php disabled( ! $user_can_assign_terms ); ?>><?php echo str_replace( ',', $comma . ' ', get_terms_to_edit( $comment->comment_ID, $tax_name ) ); // textarea_escaped by esc_attr() ?></textarea></div>
 	<?php if ( $user_can_assign_terms ) : ?>
	<div class="ajaxtag hide-if-no-js">
		<label class="screen-reader-text" for="new-tag-<?php echo $tax_name; ?>"><?php echo $box['title']; ?></label>
		<p><input type="text" id="new-tag-<?php echo $tax_name; ?>" name="newtag[<?php echo $tax_name; ?>]" class="newtag form-input-tip" size="16" autocomplete="off" value="" />
		<input type="button" class="button tagadd" value="<?php esc_attr_e('Add'); ?>" /></p>
	</div>
	<p class="howto"><?php echo $taxonomy->labels->separate_items_with_commas; ?></p>
	<?php endif; ?>
	</div>
	<div class="tagchecklist"></div>
</div>
<?php if ( $user_can_assign_terms ) : ?>
<p class="hide-if-no-js"><a href="#titlediv" class="tagcloud-link" id="link-<?php echo $tax_name; ?>"><?php echo $taxonomy->labels->choose_from_most_used; ?></a></p>
<?php endif; ?>
<?php
}



/**
 * Utility function to get tagged comments for a taxonomy archive page
 *
 * @return array $comments The comments
 */
function comment_tagger_get_tagged_comments() {

	// init return
	$comments = array();

	// get queried data
	$comment_term_id = get_queried_object_id();
	$comment_term = get_queried_object();

	// get comment IDs
	$tagged_comments = get_objects_in_term( $comment_term_id, $comment_term->taxonomy );

	// test for empty
	if ( ! empty( $tagged_comments ) ) {

		// create custom query
		$comments_query = new WP_Comment_Query;

		// define args
		$args = apply_filters( 'comment_tagger_get_tagged_comments_args' , array(
			'comment__in' => $tagged_comments,
			'status' => 'approve',
			'orderby' => 'comment_post_ID,comment_date',
			'order' => 'ASC',
		) );

		// do the query
		$comments = $comments_query->query( $args );
		//print_r( $comments ); //die();

	}

	// --<
	return $comments;

}



/**
 * Tagged comments page display function specifically designed for CommentPress
 *
 * @return str $html The comments
 */
function commentpress_get_tagged_comments_content() {

	// init output
	$html = '';

	// get all comments for this archive
	$all_comments = comment_tagger_get_tagged_comments();

	// kick out if none
	if ( count( $all_comments ) == 0 ) return $html;

	// build list of posts to which they are attached
	$posts_with = array();
	$post_comment_counts = array();
	foreach( $all_comments AS $comment ) {

		// add to posts with comments array
		if ( ! in_array( $comment->comment_post_ID, $posts_with ) ) {
			$posts_with[] = $comment->comment_post_ID;
		}

		// increment counter
		if ( ! isset( $post_comment_counts[$comment->comment_post_ID] ) ) {
			$post_comment_counts[$comment->comment_post_ID] = 1;
		} else {
			$post_comment_counts[$comment->comment_post_ID]++;
		}

	}

	// kick out if none
	if ( count( $posts_with ) == 0 ) return $html;

	// create args
	$args = array(
		'orderby' => 'comment_count',
		'order' => 'DESC',
		'post_type' => 'any',
		'post__in' => $posts_with,
		'posts_per_page' => 1000000,
		'ignore_sticky_posts' => 1,
		'post_status' => array( 'publish', 'inherit' )
	);

	// create query
	$query = new WP_Query( $args );

	// did we get any?
	if ( $query->have_posts() ) {

		// open ul
		$html .= '<ul class="all_comments_listing">' . "\n\n";

		while ( $query->have_posts() ) {

			$query->the_post();

			// open li
			$html .= '<li class="page_li"><!-- page li -->' . "\n\n";

			// define comment count
			$comment_count_text = sprintf( _n(

				// singular
				'<span class="cp_comment_count">%d</span> comment',

				// plural
				'<span class="cp_comment_count">%d</span> comments',

				// number
				$post_comment_counts[get_the_ID()],

				// domain
				'comment-tagger'

			// substitution
			), $post_comment_counts[get_the_ID()] );

			// show it
			$html .= '<h4>' . get_the_title() . ' <span>(' . $comment_count_text . ')</span></h4>' . "\n\n";

			// open comments div
			$html .= '<div class="item_body">' . "\n\n";

			// open ul
			$html .= '<ul class="item_ul">' . "\n\n";

			// open li
			$html .= '<li class="item_li"><!-- item li -->' . "\n\n";

			// check for password-protected
			if ( post_password_required( get_the_ID() ) ) {

				// construct notice
				$comment_body = '<div class="comment-content">' . __( 'Password protected', 'comment-tagger' ) . '</div>' . "\n";

				// add notice
				$html .= '<div class="comment_wrapper">' . "\n" . $comment_body . '</div>' . "\n\n";

			} else {

				foreach( $all_comments AS $comment ) {

					if ( $comment->comment_post_ID == get_the_ID() ) {

						// show the comment
						$html .= commentpress_format_comment( $comment );

					}

				}

			}

			// close li
			$html .= '</li><!-- /item li -->'."\n\n";

			// close ul
			$html .= '</ul>'."\n\n";

			// close item div
			$html .= '</div><!-- /item_body -->'."\n\n";

			// close li
			$html .= '</li><!-- /page li -->'."\n\n\n\n";

		}

		// close ul
		$html .= '</ul><!-- /all_comments_listing -->'."\n\n";

		// reset
		wp_reset_postdata();

	} // end have_posts()

	// --<
	return $html;

}



