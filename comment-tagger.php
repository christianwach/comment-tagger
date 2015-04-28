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

// define taxonomy
define( 'COMMENT_TAGGER_TAX', 'classification' );



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

		// add admin page
		add_action( 'admin_menu', array( $this, 'admin_page' ) );

		// hack the menu parent
		add_filter( 'parent_file', array( $this, 'parent_menu' ) );

		// add admin styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		// register a meta box
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

		// intercept comment edit process
		add_action( 'edit_comment', array( $this, 'save_comment_terms' ) );

		// intercept comment delete process
		add_action( 'delete_comment', array( $this, 'delete_comment_terms' ) );

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

				/*
				// permalinks
				'rewrite' => array(
					'with_front' => true,
					'slug' => 'comments/tags'
				),
				*/

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
				plugin_dir_url( __FILE__ ) . 'comment-tagger.css',
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
	function add_meta_box() {

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
	function comment_meta_box() {

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
	 * Save data returned by our comment meta box
	 *
	 * @param int $comment_id The ID of the comment being saved
	 * @return void
	 */
	function save_comment_terms( $comment_id ) {

		$tax = get_taxonomy( COMMENT_TAGGER_TAX );

		// make sure the user can assign terms
		if ( ! current_user_can( $tax->cap->assign_terms ) ) return;

		// do we have any *existing* terms?
		if ( isset( $_POST['tax_input'][COMMENT_TAGGER_TAX] ) ) {

			// get sanitised term IDs
			$existing_term_ids = $this->sanitise_comment_terms( $_POST['tax_input'][COMMENT_TAGGER_TAX] );

			// did we get any?
			if ( ! empty( $existing_term_ids ) ) {

				// save them
				wp_set_object_terms( $comment_id, $existing_term_ids, COMMENT_TAGGER_TAX, false );

			}

		}

		// do we have any *new* terms?
		if ( isset( $_POST['newtag'][COMMENT_TAGGER_TAX] ) ) {

			// get sanitised term IDs
			$new_term_ids = $this->sanitise_comment_terms( $_POST['newtag'][COMMENT_TAGGER_TAX] );

			// did we get any?
			if ( ! empty( $new_term_ids ) ) {

				// save them
				wp_set_object_terms( $comment_id, $new_term_ids, COMMENT_TAGGER_TAX, false );

			}

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

