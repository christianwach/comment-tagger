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
					'edit_terms'   => 'edit_users',
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

		/*
		$terms = get_terms( COMMENT_TAGGER_TAX, array( 'hide_empty' => false ) );
		$tids = array();
		foreach( $terms AS $term ) {
			$tids[] = $term->term_taxonomy_id;
		}
		wp_update_term_count_now( $tids, COMMENT_TAGGER_TAX );
		*/

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

		/*
		trigger_error( print_r( array(
			'terms' => $terms,
			'taxonomy' => $taxonomy,
		), true ), E_USER_ERROR ); die();
		*/

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

			/*
			print_r( array(
				//'terms' => $terms,
				//'taxonomy' => $taxonomy,
				'term' => $term,
				'sql' => $sql,
				'count' => $count,
			) ); die();
			*/

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

		// add basic stylesheet
		wp_enqueue_style(
			'comment_tagger_css',
			plugin_dir_url( __FILE__ ) . 'comment-tagger.css',
			false,
			COMMENT_TAGGER_VERSION, // version
			'all' // media
		);

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

		// add meta box
		add_meta_box(
			'comment_tagger_meta_box',
			__( 'Comment Tags', 'comment-tagger' ),
			array( $this, 'comment_meta_box' ),
			'comment',
			'normal'
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
		//print_r( $comment ); die();

		$tax = get_taxonomy( COMMENT_TAGGER_TAX );

		// make sure the user can assign terms
		if ( ! current_user_can( $tax->cap->assign_terms ) ) return;

		// get the terms
		$terms = get_terms( COMMENT_TAGGER_TAX, array( 'hide_empty' => false ) );
		//print_r( $terms ); die();

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

		// is this a multi-term taxonomy?
		if ( is_array( $_POST[COMMENT_TAGGER_TAX] ) ) {

			// yes, get terms and validate
			$term = array_map( 'esc_attr', $_POST[COMMENT_TAGGER_TAX] );

		} else {

			// no, get single term
			$term = array( esc_attr( $_POST[COMMENT_TAGGER_TAX] ) );

		}

		// save and clear cache
		wp_set_object_terms( $comment_id, $term, COMMENT_TAGGER_TAX, false );
		clean_object_term_cache( $comment_id, COMMENT_TAGGER_TAX );

	}



	/**
	 * Delete comment terms when a comment is deleted
	 *
	 * @param int $comment_id The ID of the comment being saved
	 * @return void
	 */
	function delete_comment_terms( $comment_id ) {

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
