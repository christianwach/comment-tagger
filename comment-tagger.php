<?php
/**
 * Plugin Name: Comment Tagger
 * Description: Lets logged-in readers tag comments.
 * Version: 0.1.4
 * Author: Christian Wach
 * Author URI: https://haystack.co.uk
 * Plugin URI: https://github.com/christianwach/comment-tagger
 * Text Domain: comment-tagger
 * Domain Path: /languages
 *
 * @package Comment_Tagger
 */

// Define version - bumping this refreshes CSS and JS.
define( 'COMMENT_TAGGER_VERSION', '0.1.4' );

// Define Taxonomy name.
if ( ! defined( 'COMMENT_TAGGER_TAX' ) ) {
	define( 'COMMENT_TAGGER_TAX', 'comment_tags' );
}

// Define Taxonomy prefix for Select2.
if ( ! defined( 'COMMENT_TAGGER_PREFIX' ) ) {
	// This is a "unique-enough" prefix so we can distinguish between new tags
	// and the selection of pre-existing ones when the Comment Form is posted.
	define( 'COMMENT_TAGGER_PREFIX', 'cmmnt_tggr' );
}

/**
 * Comment Tagger class.
 *
 * A class for encapsulating plugin functionality.
 *
 * @since 0.1
 *
 * @package Comment_Tagger
 */
class Comment_Tagger {

	/**
	 * Returns a single instance of this object when called.
	 *
	 * @since 0.1
	 *
	 * @return object $instance Comment_Tagger instance.
	 */
	public static function instance() {

		// Store the instance locally to avoid private static replication.
		static $instance = null;

		// Instantiate and initialise.
		if ( null === $instance ) {
			$instance = new Comment_Tagger();
			$instance->register_hooks();
		}

		// Always return instance.
		return $instance;

	}

	/**
	 * Actions to perform on plugin activation.
	 *
	 * @since 0.1.1
	 */
	public function activate() {

		// Flush rules.
		flush_rewrite_rules();

	}

	/**
	 * Actions to perform on plugin deactivation.
	 *
	 * For actions that are performed on plugin deletion, see 'uninstall.php'.
	 *
	 * @since 0.1.1
	 */
	public function deactivate() {

		// Flush rules.
		flush_rewrite_rules();

	}

	/**
	 * Register the hooks that our plugin needs.
	 *
	 * @since 0.1
	 */
	private function register_hooks() {

		// Enable translation.
		add_action( 'plugins_loaded', [ $this, 'enable_translation' ] );

		// Create Taxonomy.
		add_action( 'init', [ $this, 'create_taxonomy' ], 0 );

		// Admin hooks.

		// Add admin menu item.
		add_action( 'admin_menu', [ $this, 'admin_page' ] );

		// Hack the menu parent.
		add_filter( 'parent_file', [ $this, 'parent_menu' ] );

		// Add admin styles.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );

		// Register a meta box.
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );

		// Intercept Comment save process.
		add_action( 'comment_post', [ $this, 'intercept_comment_save' ], 20, 2 );

		// Allow Comment Authors to assign Terms.
		add_filter( 'map_meta_cap', [ $this, 'enable_comment_terms' ], 10, 4 );

		// Intercept Edit Comment process in WordPress admin.
		add_action( 'edit_comment', [ $this, 'update_comment_terms' ] );

		// Intercept Edit Comment process in CommentPress front-end.
		add_action( 'edit_comment', [ $this, 'edit_comment_terms' ] );

		// Intercept Delete Comment process.
		add_action( 'delete_comment', [ $this, 'delete_comment_terms' ] );

		// Front-end hooks.

		// Register any public styles.
		add_action( 'wp_enqueue_scripts', [ $this, 'front_end_enqueue_styles' ], 20 );

		// Register any public scripts.
		add_action( 'wp_enqueue_scripts', [ $this, 'front_end_enqueue_scripts' ], 20 );

		// Add tags to Comment content.
		add_filter( 'get_comment_text', [ $this, 'front_end_tags' ], 10, 2 );

		// Add UI to CommentPress Comments.
		add_filter( 'comment_id_fields', [ $this, 'front_end_markup' ] );

		// Optionally replace with CommentPress Comment hooks.
		if ( defined( 'COMMENTPRESS_VERSION' ) ) {
			if ( version_compare( COMMENTPRESS_VERSION, '3.9.20', '>' ) ) {
				add_action( 'commentpress/core/loaded', [ $this, 'commentpress_loaded' ] );
			} else {
				add_action( 'commentpress_loaded', [ $this, 'commentpress_loaded' ] );
			}
		}

		/**
		 * Broadcast that this plugin has loaded.
		 *
		 * @since 0.1
		 */
		do_action( 'comment_tagger_loaded' );

	}

	/**
	 * Customise CommentPress when it is loaded.
	 *
	 * @since 0.1
	 */
	public function commentpress_loaded() {

		// Remove WordPress hooks.
		remove_filter( 'get_comment_text', [ $this, 'front_end_tags' ], 10, 2 );
		remove_filter( 'comment_id_fields', [ $this, 'front_end_markup' ] );

		// Add tags to Comment content.
		add_filter( 'commentpress_comment_identifier_append', [ $this, 'front_end_tags' ], 10, 2 );

		// Add UI to CommentPress Comments.
		add_action( 'commentpress_comment_form_pre_comment_id_fields', [ $this, 'front_end_markup_commentpress' ] );

		// Add tag data to AJAX-edit Comment data.
		add_filter( 'commentpress_ajax_get_comment', [ $this, 'filter_ajax_get_comment' ], 10, 1 );

		// Add tag data to AJAX-edited Comment data.
		add_filter( 'commentpress_ajax_edited_comment', [ $this, 'filter_ajax_edited_comment' ], 10, 1 );

	}

	/**
	 * Load translation files.
	 *
	 * A good reference on how to implement translation in WordPress:
	 * http://ottopress.com/2012/internationalization-youre-probably-doing-it-wrong/
	 *
	 * @since 0.1
	 */
	public function enable_translation() {

		// Load translations if they exist.
		// phpcs:ignore: WordPress.WP.DeprecatedParameters.Load_plugin_textdomainParam2Found
		load_plugin_textdomain(
			'comment-tagger', // Unique name.
			false, // Deprecated argument.
			dirname( plugin_basename( __FILE__ ) ) . '/languages/' // Relative path to translation files.
		);

	}

	/**
	 * Create a free-tagging Taxonomy for Comments.
	 *
	 * @since 0.1
	 */
	public function create_taxonomy() {

		// Define Taxonomy arguments.
		$args = [

			// General.
			'public' => true,
			'hierarchical' => false,

			// Labels.
			'labels' => [
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
			],

			// Permalinks.
			'rewrite' => [
				//'with_front' => true,
				'slug' => apply_filters( 'comment_tagger_tax_slug', 'comments/tags' ),
			],

			// Capabilities.
			'capabilities' => [
				'manage_terms' => 'manage_categories',
				'edit_terms' => 'manage_categories',
				'delete_terms' => 'manage_categories',
				'assign_terms' => 'assign_' . COMMENT_TAGGER_TAX,
			],

			// Custom function to update the count.
			'update_count_callback' => [ $this, 'update_tag_count' ],

		];

		register_taxonomy( COMMENT_TAGGER_TAX, 'comment', $args );

		// Register any hooks/filters that rely on knowing the Taxonomy now.
		add_filter( 'manage_edit-' . COMMENT_TAGGER_TAX . '_columns', [ $this, 'set_comment_column' ] );
		add_action( 'manage_' . COMMENT_TAGGER_TAX . '_custom_column', [ $this, 'set_comment_column_values' ], 10, 3 );

	}

	/**
	 * Force update the number of Comments for a Taxonomy Term.
	 *
	 * @since 0.1
	 */
	public function refresh_tag_count() {

		// Find all the Term IDs.
		$terms = get_terms( COMMENT_TAGGER_TAX, [ 'hide_empty' => false ] );
		$tids = [];
		foreach ( $terms as $term ) {
			$tids[] = $term->term_taxonomy_id;
		}

		// Do update.
		wp_update_term_count_now( $tids, COMMENT_TAGGER_TAX );

	}

	/**
	 * Manually update the number of Comments for a Taxonomy Term.
	 *
	 * @see _update_post_term_count()
	 *
	 * @since 0.1
	 *
	 * @param array $terms List of Term Taxonomy IDs.
	 * @param object $taxonomy Current Taxonomy object of Terms.
	 */
	public function update_tag_count( $terms, $taxonomy ) {

		// Access DB wrapper.
		global $wpdb;

		// Loop through each Term.
		foreach ( (array) $terms as $term ) {

			// Get count.
			// phpcs:ignore: WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->comments " .
				"WHERE $wpdb->term_relationships.object_id = $wpdb->comments.comment_ID " .
				"AND $wpdb->term_relationships.term_taxonomy_id = %d",
				$term
			) );

			// Fire WordPress action.
			do_action( 'edit_term_taxonomy', $term, $taxonomy );

			// Update database directly.
			// phpcs:ignore: WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $wpdb->term_taxonomy, [ 'count' => $count ], [ 'term_taxonomy_id' => $term ] );

			// Fire WordPress action.
			do_action( 'edited_term_taxonomy', $term, $taxonomy );

		}

	}

	/**
	 * Creates the admin menu item for the Taxonomy under the 'Comments' menu.
	 *
	 * @since 0.1
	 */
	public function admin_page() {

		// Get Taxonomy object.
		$tax = get_taxonomy( COMMENT_TAGGER_TAX );

		// Add as subpage of 'Comments' menu item.
		add_comments_page(
			esc_attr( $tax->labels->menu_name ),
			esc_attr( $tax->labels->menu_name ),
			$tax->cap->manage_terms,
			'edit-tags.php?taxonomy=' . $tax->name
		);

	}

	/**
	 * Enqueue CSS in WP admin to tweak the appearance of various elements.
	 *
	 * @since 0.1
	 */
	public function enqueue_styles() {

		global $pagenow;

		// If we're on our Taxonomy Page.
		$taxonomy_page = isset( $_GET['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) : '';
		if ( ! empty( $taxonomy_page ) && $taxonomy_page === COMMENT_TAGGER_TAX && $pagenow == 'edit-tags.php' ) {

			// Add basic stylesheet.
			wp_enqueue_style(
				'comment_tagger_css',
				plugin_dir_url( __FILE__ ) . 'assets/css/comment-tagger-admin.css',
				false,
				COMMENT_TAGGER_VERSION, // Version.
				'all' // Media.
			);

		}

		// If we're on the "Edit Comment" Page.
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		if ( $pagenow == 'comment.php' && ! empty( $_GET['action'] ) && $_GET['action'] === 'editcomment' ) {

			// The tags meta box requires this script.
			wp_enqueue_script( 'post' );

		}

	}

	/**
	 * Fix a bug with highlighting the parent menu item.
	 *
	 * By default, when on the edit Taxonomy Page for a user Taxonomy, the "Posts" tab
	 * is highlighted. This will correct that bug.
	 *
	 * @since 0.1
	 *
	 * @param string $parent The existing parent menu item.
	 * @return string $parent The modified parent menu item.
	 */
	public function parent_menu( $parent = '' ) {

		global $pagenow;

		// If we're editing our Comment Taxonomy highlight the Comments menu.
		if ( ! empty( $_GET['taxonomy'] ) && $_GET['taxonomy'] == COMMENT_TAGGER_TAX && $pagenow == 'edit-tags.php' ) {
			$parent = 'edit-comments.php';
		}

		// --<
		return $parent;

	}

	/**
	 * Correct the column name for Comment Taxonomies - replace "Posts" with "Comments".
	 *
	 * @since 0.1
	 *
	 * @param array $columns An array of columns to be shown in the manage Terms table.
	 * @return array $columns Modified array of columns to be shown in the manage Terms table.
	 */
	public function set_comment_column( $columns ) {

		// Replace column.
		unset( $columns['posts'] );
		$columns['comments'] = __( 'Comments', 'comment-tagger' );

		// --<
		return $columns;

	}

	/**
	 * Set values for custom columns in Comment Taxonomies.
	 *
	 * @since 0.1
	 *
	 * @param string $display WP just passes an empty string here.
	 * @param string $column The name of the custom column.
	 * @param int $term_id The ID of the Term being displayed in the table.
	 */
	public function set_comment_column_values( $display, $column, $term_id ) {

		if ( 'comments' === $column ) {
			$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) : '';
			if ( ! empty( $taxonomy ) ) {
				$term = get_term( $term_id, $taxonomy );
				echo $term->count;
			}
		}

	}

	/**
	 * Register a meta box for the Edit Comment screen.
	 *
	 * @since 0.1
	 */
	public function add_meta_box() {

		// Let's use the built-in tags metabox.
		add_meta_box(
			'tagsdiv-post_tag',
			__( 'Comment Tags', 'comment-tagger' ), // Custom name.
			'comment_tagger_post_tags_meta_box', // Custom callback.
			'comment',
			'normal',
			'default',
			[ 'taxonomy' => COMMENT_TAGGER_TAX ]
		);

	}

	/**
	 * Intercept the Save Comment process and maybe update Terms.
	 *
	 * @since 0.1
	 *
	 * @param int $comment_id The numeric ID of the Comment.
	 * @param str $comment_status The status of the Comment.
	 */
	public function intercept_comment_save( $comment_id, $comment_status ) {

		// Bail if we didn't receive any Terms.
		if ( ! isset( $_POST['comment_tagger_tags'] ) ) {
			return;
		}

		// Bail if the Terms array is somehow invalid.
		if ( ! is_array( $_POST['comment_tagger_tags'] ) ) {
			return;
		}
		if ( count( $_POST['comment_tagger_tags'] ) === 0 ) {
			return;
		}

		// Check and sanitise Terms array.
		$comment_tagger_tags = filter_input( INPUT_POST, 'comment_tagger_tags', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		if ( ! empty( $comment_tagger_tags ) ) {
			array_walk(
				$comment_tagger_tags,
				function( &$item ) {
					$item = sanitize_text_field( $item );
				}
			);
		}

		// Init "existing" and "new" arrays.
		$existing_term_ids = [];
		$new_term_ids = [];
		$new_terms = [];

		// Parse the received Terms.
		foreach ( $comment_tagger_tags as $term ) {

			// Does the Term contain our prefix?
			if ( strstr( $term, COMMENT_TAGGER_PREFIX ) ) {

				// It's an existing Term.
				$tmp = explode( '-', $term );

				// Get Term ID.
				$term_id = isset( $tmp[1] ) ? intval( $tmp[1] ) : 0;

				// Add to existing.
				if ( $term_id !== 0 ) {
					$existing_term_ids[] = $term_id;
				}

			} else {

				// Add Term to new.
				$new_terms[] = $term;

			}

		}

		// Get sanitised Term IDs for any *new* Terms.
		if ( count( $new_terms ) > 0 ) {
			$new_term_ids = $this->sanitise_comment_terms( $new_terms );
		}

		// Combine arrays.
		$term_ids = array_unique( array_merge( $existing_term_ids, $new_term_ids ) );

		// Overwrite with new Terms if there are some.
		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $comment_id, $term_ids, COMMENT_TAGGER_TAX, false );
		}

	}

	/**
	 * Add capability to assign tags.
	 *
	 * @since 0.1.2
	 *
	 * @param array $caps The existing capabilities array for the WordPress user.
	 * @param str $cap The capability in question.
	 * @param int $user_id The numeric ID of the WordPress user.
	 * @param array $args The additional arguments.
	 * @return array $caps The modified capabilities array for the WordPress user.
	 */
	public function enable_comment_terms( $caps, $cap, $user_id, $args ) {

		// Only apply caps to queries for edit_comment cap.
		if ( 'assign_' . COMMENT_TAGGER_TAX != $cap ) {
			return $caps;
		}

		// Always allow.
		$caps = [ 'exist' ];

		// --<
		return $caps;

	}

	/**
	 * Save data returned by our Comment metabox in WordPress admin.
	 *
	 * @since 0.1
	 *
	 * @param int $comment_id The ID of the Comment being saved.
	 */
	public function update_comment_terms( $comment_id ) {

		// If there's no nonce then there's no Comment meta data.
		if ( ! isset( $_POST['_wpnonce'] ) ) {
			return;
		}

		// Get our Taxonomy.
		$tax = get_taxonomy( COMMENT_TAGGER_TAX );

		// Make sure the user can assign Terms.
		if ( ! current_user_can( $tax->cap->assign_terms ) ) {
			return;
		}

		// Init "existing" and "new" arrays.
		$existing_term_ids = [];
		$new_term_ids = [];

		// Get sanitised Term IDs for any *existing* Terms.
		if ( isset( $_POST['tax_input'][ COMMENT_TAGGER_TAX ] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$existing_term_ids = $this->sanitise_comment_terms( wp_unslash( $_POST['tax_input'][ COMMENT_TAGGER_TAX ] ) );
		}

		// Get sanitised Term IDs for any *new* Terms.
		if ( isset( $_POST['newtag'][ COMMENT_TAGGER_TAX ] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$new_term_ids = $this->sanitise_comment_terms( wp_unslash( $_POST['newtag'][ COMMENT_TAGGER_TAX ] ) );
		}

		// Combine arrays.
		$term_ids = array_unique( array_merge( $existing_term_ids, $new_term_ids ) );

		// Overwrite with new Terms if there are any.
		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $comment_id, $term_ids, COMMENT_TAGGER_TAX, false );
			clean_object_term_cache( $comment_id, COMMENT_TAGGER_TAX );
		} else {
			$this->delete_comment_terms( $comment_id );
		}

	}

	/**
	 * Save data returned by our tags select in CommentPress front-end.
	 *
	 * @since 0.1.3
	 *
	 * @param int $comment_id The ID of the Comment being saved.
	 */
	public function edit_comment_terms( $comment_id ) {

		// If there's no nonce then there's no Comment meta data.
		if ( ! isset( $_POST['cpajax_comment_nonce'] ) ) {
			return;
		}

		// Get our Taxonomy.
		$tax = get_taxonomy( COMMENT_TAGGER_TAX );

		// Make sure the user can assign Terms.
		if ( ! current_user_can( $tax->cap->assign_terms ) ) {
			return;
		}

		// Init "existing" and "new" arrays.
		$existing_term_ids = [];
		$new_term_ids = [];
		$new_terms = [];

		// Check and sanitise Terms array.
		$comment_tagger_tags = filter_input( INPUT_POST, 'comment_tagger_tags', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		if ( ! empty( $comment_tagger_tags ) ) {
			array_walk(
				$comment_tagger_tags,
				function( &$item ) {
					$item = sanitize_text_field( $item );
				}
			);
		}

		// Sanity check.
		if ( ! empty( $comment_tagger_tags ) && is_array( $comment_tagger_tags ) ) {

			// Parse the received Terms.
			foreach ( $comment_tagger_tags as $term ) {

				// Does the Term contain our prefix?
				if ( strstr( $term, COMMENT_TAGGER_PREFIX ) ) {

					// It's an existing Term.
					$tmp = explode( '-', $term );

					// Get Term ID.
					$term_id = isset( $tmp[1] ) ? intval( $tmp[1] ) : 0;

					// Add to existing.
					if ( $term_id !== 0 ) {
						$existing_term_ids[] = $term_id;
					}

				} else {

					// Add Term to new.
					$new_terms[] = $term;

				}

			}

		}

		// Get sanitised Term IDs for any *new* Terms.
		if ( count( $new_terms ) > 0 ) {
			$new_term_ids = $this->sanitise_comment_terms( $new_terms );
		}

		// Combine arrays.
		$term_ids = array_unique( array_merge( $existing_term_ids, $new_term_ids ) );

		// Overwrite with new Terms if there are any.
		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $comment_id, $term_ids, COMMENT_TAGGER_TAX, false );
			clean_object_term_cache( $comment_id, COMMENT_TAGGER_TAX );
		} else {
			$this->delete_comment_terms( $comment_id );
		}

	}

	/**
	 * Sanitise Comment Terms.
	 *
	 * @since 0.1
	 *
	 * @param mixed $raw_terms The Term names as retrieved from $_POST.
	 * @return array $term_ids The array of numeric Term IDs.
	 */
	private function sanitise_comment_terms( $raw_terms ) {

		// Is this a multi-term Taxonomy?
		if ( is_array( $raw_terms ) ) {

			// Yes, get Terms and validate.
			$terms = array_map( 'esc_attr', $raw_terms );

		} else {

			// We should receive a comma-delimited array of Term names.
			$terms = array_map( 'esc_attr', explode( ',', $raw_terms ) );

		}

		// Init Term IDs.
		$term_ids = [];

		// Loop through them.
		foreach ( $terms as $term ) {

			// Does the Term exist?
			$exists = term_exists( $term, COMMENT_TAGGER_TAX );

			// If it does.
			if ( $exists !== 0 && $exists !== null ) {

				/*
				 * Should be array e.g. array( 'term_id' => 12, 'term_taxonomy_id' => 34 )
				 * since we specify the Taxonomy.
				 */

				// Add Term ID to array.
				$term_ids[] = $exists['term_id'];

			} else {

				/*
				 * Let's add the Term - but note: return value is either:
				 * WP_Error or array e.g. array( 'term_id' => 12, 'term_taxonomy_id' => 34 )
				 */
				$new_term = wp_insert_term( $term, COMMENT_TAGGER_TAX );

				/*
				 * Add Term ID to array if there's no error.
				 *
				 * If there was an error somewhere and the Terms couldn't be set
				 * then we should let people know at some point.
				 */
				if ( ! is_wp_error( $new_term ) ) {
					$term_ids[] = $new_term['term_id'];
				}

			}

		}

		// Sanity checks if we have Term IDs.
		if ( ! empty( $term_ids ) ) {
			$term_ids = array_map( 'intval', $term_ids );
			$term_ids = array_unique( $term_ids );
		}

		// --<
		return $term_ids;

	}

	/**
	 * Delete Comment Terms when a Comment is deleted.
	 *
	 * @since 0.1
	 *
	 * @param int $comment_id The ID of the Comment being saved.
	 */
	public function delete_comment_terms( $comment_id ) {

		wp_delete_object_term_relationships( $comment_id, COMMENT_TAGGER_TAX );
		clean_object_term_cache( $comment_id, COMMENT_TAGGER_TAX );

	}

	/**
	 * Show tags on front-end, appended to Comment text.
	 *
	 * @since 0.1
	 *
	 * @param str $text The content to prepend to the Comment identifer.
	 * @param object $comment The WordPress Comment object.
	 * @return str $text The markup showing the tags for a Comment.
	 */
	public function front_end_tags( $text = '', $comment = null ) {

		// Sanity check.
		if ( ! isset( $comment->comment_ID ) ) {
			return $text;
		}

		// Get Terms for this Comment.
		$terms = wp_get_object_terms( $comment->comment_ID, COMMENT_TAGGER_TAX );

		// Did we get any?
		if ( count( $terms ) > 0 ) {

			// Init tag list.
			$tag_list = [];

			// Create markup for each.
			foreach ( $terms as $term ) {

				// Get URL.
				$term_href = get_term_link( $term, COMMENT_TAGGER_TAX );

				// Construct link.
				$term_link = '<a class="comment_tagger_tag_link" href="' . $term_href . '">' . esc_html( $term->name ) . '</a>';

				// Wrap and add to list.
				$tag_list[] = '<span class="comment_tagger_tag">' . $term_link . '</span>';

			}

			// Wrap in identifying div.
			$tags = '<div class="comment_tagger_tags">' .
				'<p>' . __( 'Tagged: ', 'comment-tagger' ) . implode( ' ', $tag_list ) . '</p>' .
			'</div>' . "\n\n";

		} else {

			// Add placeholder div.
			$tags = '<div class="comment_tagger_tags"></div>' . "\n\n";

		}

		// Prepend to text.
		$text = $tags . $text;

		// --<
		return $text;

	}

	/**
	 * Show front-end version of tags metabox.
	 *
	 * @since 0.1
	 *
	 * @param str $content The existing content.
	 * @return str $html The markup for the tags metabox.
	 */
	public function front_end_markup( $content = '' ) {

		// Only our Taxonomy.
		$taxonomies = [ COMMENT_TAGGER_TAX ];

		// Config.
		$args = [
			'orderby' => 'count',
			'order' => 'DESC',
			'number' => 5,
		];

		// Get top 5 most used tags.
		$tags = get_terms( $taxonomies, $args );

		// Construct default options for Select2.
		$most_used_tags_array = [];
		foreach ( $tags as $tag ) {
			$most_used_tags_array[] = '<option value="' . COMMENT_TAGGER_PREFIX . '-' . $tag->term_id . '">' .
				esc_html( $tag->name ) .
			'</option>';
		}
		$most_used_tags = implode( "\n", $most_used_tags_array );

		// Use Select2 in "tag" mode.
		$html = '<div class="comment_tagger_select2_container">
			<h5 class="comment_tagger_select2_heading">' . __( 'Tag this comment', 'comment-tagger' ) . '</h5>
			<p class="comment_tagger_select2_description">' .
				__( 'Select from existing tags or add your own.', 'comment-tagger' ) .
				'<br />' .
				__( 'Separate new tags with a comma.', 'comment-tagger' ) .
			'</p>
			<select class="comment_tagger_select2" name="comment_tagger_tags[]" id="comment_tagger_tags" multiple="multiple" style="width: 100%;">
				' . $most_used_tags . '
			</select>
		 </div>';

		// --<
		return $content . $html;

	}

	/**
	 * Show front-end version of tags metabox in CommentPress.
	 *
	 * @since 0.1
	 */
	public function front_end_markup_commentpress() {

		// Get content and echo.
		echo $this->front_end_markup();

	}

	/**
	 * Add our front-end stylesheets.
	 *
	 * Currently using the 4.0.0 version of Select2. The incuded directory is a
	 * copy of the 'dist' directory.
	 *
	 * @see https://github.com/select2/select2/tags
	 *
	 * @since 0.1
	 */
	public function front_end_enqueue_styles() {

		// Default to minified scripts.
		$debug = '.min';

		// Use uncompressed scripts when debugging.
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG === true ) {
			$debug = '';
		}

		// Enqueue Select2 stylesheet.
		wp_enqueue_style(
			'comment_tagger_select2_css',
			plugin_dir_url( __FILE__ ) . 'assets/external/select2/css/select2' . $debug . '.css',
			false,
			COMMENT_TAGGER_VERSION, // Version.
			'all' // Media.
		);

	}

	/**
	 * Add our front-end Javascripts.
	 *
	 * Currently using the 4.0.0 version of Select2. The incuded directory is a
	 * copy of the 'dist' directory
	 *
	 * @see https://github.com/select2/select2/tags
	 *
	 * @since 0.1
	 */
	public function front_end_enqueue_scripts() {

		// Default to minified scripts.
		$debug = '.min';

		// Use uncompressed scripts when debugging.
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG === true ) {
			$debug = '';
		}

		// Enqueue Select2.
		wp_enqueue_script(
			'comment_tagger_select2_js',
			plugin_dir_url( __FILE__ ) . 'assets/external/select2/js/select2' . $debug . '.js',
			[ 'jquery' ],
			COMMENT_TAGGER_VERSION,
			false
		);

		// Enqueue our custom Javascript.
		wp_enqueue_script(
			'comment_tagger_select2_custom_js',
			plugin_dir_url( __FILE__ ) . 'assets/js/comment-tagger.js',
			[ 'comment_tagger_select2_js' ],
			COMMENT_TAGGER_VERSION,
			false
		);

		// Localisation array.
		$vars = [
			'localisation' => [],
			'data' => [],
		];

		// Localise with WordPress function.
		wp_localize_script(
			'comment_tagger_select2_custom_js',
			'CommentTaggerSettings',
			$vars
		);

	}

	/**
	 * Filter the Comment data returned via AJAX when editing a Comment.
	 *
	 * @since 0.1.3
	 *
	 * @param array $data The existing array of Comment data.
	 * @return array $data The modified array of Comment data.
	 */
	public function filter_ajax_get_comment( $data ) {

		// Sanity check.
		if ( ! isset( $data['id'] ) ) {
			return $data;
		}

		// Get Terms for this Comment.
		$terms = wp_get_object_terms( $data['id'], COMMENT_TAGGER_TAX );

		// Bail if empty.
		if ( count( $terms ) === 0 ) {
			return $data;
		}

		// Build array of simple Term objects.
		$term_ids = [];
		foreach ( $terms as $term ) {
			$obj = new stdClass();
			$obj->id = COMMENT_TAGGER_PREFIX . '-' . $term->term_id;
			$obj->name = $term->name;
			$term_ids[] = $obj;
		}

		// Add to array.
		$data['comment_tagger_tags'] = $term_ids;

		// --<
		return $data;

	}

	/**
	 * Filter the Comment data returned via AJAX when a Comment has been edited.
	 *
	 * @since 0.1.3
	 *
	 * @param array $data The existing array of Comment data.
	 * @return array $data The modified array of Comment data.
	 */
	public function filter_ajax_edited_comment( $data ) {

		// Sanity check.
		if ( ! isset( $data['id'] ) ) {
			return $data;
		}

		// Add tag data.
		$data = $this->filter_ajax_get_comment( $data );

		// Get Comment.
		$comment = get_comment( $data['id'] );

		// Get markup.
		$markup = $this->front_end_tags( '', $comment );

		// Add to array.
		$data['comment_tagger_markup'] = $markup;

		// --<
		return $data;

	}

}

/**
 * Instantiate plugin object.
 *
 * @since 0.1
 *
 * @return object Comment_Tagger The plugin instance.
 */
function comment_tagger() {
	return Comment_Tagger::instance();
}

// Init Comment Tagger.
comment_tagger();

// Activation.
register_activation_hook( __FILE__, [ comment_tagger(), 'activate' ] );

// Deactivation.
register_deactivation_hook( __FILE__, [ comment_tagger(), 'deactivate' ] );

/**
 * This is a clone of `post_tags_meta_box` which is usually used to display post
 * tags form fields. It has been modified so that the Terms are assigned to the
 * Comment not the Post. The capability check has also been changed to see if a
 * user can edit the Comment - this may be changed to assign custom capabilities
 * to the Taxonomy itself and then use the 'map_meta_caps' filter to make the
 * decision.
 *
 * NB: there's a to-do note on the original that suggests that it should be made
 * more compatible with general Taxonomies...
 *
 * @todo Create taxonomy-agnostic wrapper for this.
 *
 * @see post_tags_meta_box
 *
 * @since 0.1
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

	// Access Comment.
	global $comment;

	// Parse the passed in arguments.
	$defaults = [ 'taxonomy' => 'post_tag' ];
	if ( ! isset( $box['args'] ) || ! is_array( $box['args'] ) ) {
		$args = [];
	} else {
		$args = $box['args'];
	}
	$r = wp_parse_args( $args, $defaults );

	// Get Taxonomy data.
	$tax_name = esc_attr( $r['taxonomy'] );
	$taxonomy = get_taxonomy( $r['taxonomy'] );
	$user_can_assign_terms = current_user_can( $taxonomy->cap->assign_terms );
	$comma = _x( ',', 'tag delimiter', 'comment-tagger' );
	?>
	<div class="tagsdiv" id="<?php echo $tax_name; ?>">
		<div class="jaxtag">
		<div class="nojs-tags hide-if-js">
		<p><?php echo $taxonomy->labels->add_or_remove_items; ?></p>
		<textarea name="<?php echo "tax_input[$tax_name]"; ?>" rows="3" cols="20" class="the-tags" id="tax-input-<?php echo $tax_name; ?>" <?php disabled( ! $user_can_assign_terms ); ?>><?php echo str_replace( ',', $comma . ' ', get_terms_to_edit( $comment->comment_ID, $tax_name ) ); /* textarea_escaped by esc_attr() */ ?></textarea></div>
		<?php if ( $user_can_assign_terms ) : ?>
		<div class="ajaxtag hide-if-no-js">
			<label class="screen-reader-text" for="new-tag-<?php echo $tax_name; ?>"><?php echo $box['title']; ?></label>
			<p><input type="text" id="new-tag-<?php echo $tax_name; ?>" name="newtag[<?php echo $tax_name; ?>]" class="newtag form-input-tip" size="16" autocomplete="off" value="" />
			<input type="button" class="button tagadd" value="<?php esc_attr_e( 'Add', 'comment-tagger' ); ?>" /></p>
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
 * Utility function to get tagged Comments for a Taxonomy Archive Page.
 *
 * @since 0.1
 *
 * @return array $comments The Comments.
 */
function comment_tagger_get_tagged_comments() {

	// Init return.
	$comments = [];

	// Get queried data.
	$comment_term_id = get_queried_object_id();
	$comment_term = get_queried_object();

	// Get Comment IDs.
	$tagged_comments = get_objects_in_term( $comment_term_id, $comment_term->taxonomy );

	// Test for empty.
	if ( ! empty( $tagged_comments ) ) {

		// Create custom query.
		$comments_query = new WP_Comment_Query();

		// Define args.
		$args = apply_filters( 'comment_tagger_get_tagged_comments_args', [
			'comment__in' => $tagged_comments,
			'status' => 'approve',
			'orderby' => 'comment_post_ID,comment_date',
			'order' => 'ASC',
		] );

		// Do the query.
		$comments = $comments_query->query( $args );

	}

	// --<
	return $comments;

}

/**
 * General tagged Comments Page display function.
 *
 * Use this function as a starting point to adapt this plugin to your theme.
 *
 * @since 0.1
 *
 * @return str $html The Comments.
 */
function comment_tagger_get_tagged_comments_content() {

	// Init output.
	$html = '';

	// Get all Comments for this Archive.
	$all_comments = comment_tagger_get_tagged_comments();

	// Kick out if none.
	if ( count( $all_comments ) == 0 ) {
		return $html;
	}

	// Build list of Posts to which they are attached.
	$posts_with = [];
	$post_comment_counts = [];
	foreach ( $all_comments as $comment ) {

		// Add to Posts with Comments array.
		if ( ! in_array( $comment->comment_post_ID, $posts_with ) ) {
			$posts_with[] = $comment->comment_post_ID;
		}

		// Increment counter.
		if ( ! isset( $post_comment_counts[ $comment->comment_post_ID ] ) ) {
			$post_comment_counts[ $comment->comment_post_ID ] = 1;
		} else {
			$post_comment_counts[ $comment->comment_post_ID ]++;
		}

	}

	// Kick out if none.
	if ( count( $posts_with ) == 0 ) {
		return $html;
	}

	// Create args.
	$args = [
		'orderby' => 'comment_count',
		'order' => 'DESC',
		'post_type' => 'any',
		'post__in' => $posts_with,
		'posts_per_page' => -1,
		'ignore_sticky_posts' => 1,
		'post_status' => [
			'publish',
			'inherit',
		],
	];

	// Create query.
	$query = new WP_Query( $args );

	// Did we get any?
	if ( $query->have_posts() ) {

		// Open ul.
		$html .= '<ul class="comment_tagger_posts">' . "\n";

		while ( $query->have_posts() ) {

			$query->the_post();

			// Open li.
			$html .= '<li class="comment_tagger_post">' . "\n";

			// Define Comment count.
			$comment_count_text = sprintf( _n(
				// Singular.
				'<span class="comment_tagger_count">%d</span> comment',
				// Plural.
				'<span class="comment_tagger_count">%d</span> comments',
				// Number.
				$post_comment_counts[ get_the_ID() ],
				// Domain.
				'comment-tagger'
				// Substitution.
			), $post_comment_counts[ get_the_ID() ] );

			// Show it.
			$html .= '<h4>' . get_the_title() . ' <span>(' . $comment_count_text . ')</span></h4>' . "\n";

			// Open Comments div.
			$html .= '<div class="comment_tagger_comments">' . "\n";

			// Check for password-protected.
			if ( post_password_required( get_the_ID() ) ) {

				// Add notice.
				$html .= '<div class="comment_tagger_notice">' . __( 'Password protected', 'comment-tagger' ) . '</div>' . "\n";

			} else {

				// Build array of Comments for this Post.
				$comments_to_show = [];
				foreach ( $all_comments as $comment ) {
					if ( $comment->comment_post_ID == get_the_ID() ) {
						$comments_to_show[] = $comment;
					}
				}

				// Open list if we have some.
				if ( ! empty( $comments_to_show ) ) {
					$html .= '<ul>' . "\n";
				}

				// Add to output.
				$html .= wp_list_comments( [ 'echo' => false ], $comments_to_show );

				// Close list if we have some.
				if ( ! empty( $comments_to_show ) ) {
					$html .= '</ul>' . "\n";
				}

			}

			// Close Comments div.
			$html .= '</div>' . "\n";

			// Close li.
			$html .= '</li>' . "\n";

		}

		// Close ul.
		$html .= '</ul>' . "\n";

		// Reset.
		wp_reset_postdata();

	} // End have_posts()

	// --<
	return $html;

}

/**
 * Tagged Comments Page display function specifically designed for CommentPress.
 *
 * @since 0.1
 *
 * @return str $html The Comments.
 */
function commentpress_get_tagged_comments_content() {

	// Init output.
	$html = '';

	// Get all Comments for this Archive.
	$all_comments = comment_tagger_get_tagged_comments();

	// Kick out if none.
	if ( count( $all_comments ) == 0 ) {
		return $html;
	}

	// Build list of Posts to which they are attached.
	$posts_with = [];
	$post_comment_counts = [];
	foreach ( $all_comments as $comment ) {

		// Add to Posts with Comments array.
		if ( ! in_array( $comment->comment_post_ID, $posts_with ) ) {
			$posts_with[] = $comment->comment_post_ID;
		}

		// Increment counter.
		if ( ! isset( $post_comment_counts[ $comment->comment_post_ID ] ) ) {
			$post_comment_counts[ $comment->comment_post_ID ] = 1;
		} else {
			$post_comment_counts[ $comment->comment_post_ID ]++;
		}

	}

	// Kick out if none.
	if ( count( $posts_with ) == 0 ) {
		return $html;
	}

	// Create args.
	$args = [
		'orderby' => 'comment_count',
		'order' => 'DESC',
		'post_type' => 'any',
		'post__in' => $posts_with,
		'posts_per_page' => -1,
		'ignore_sticky_posts' => 1,
		'post_status' => [
			'publish',
			'inherit',
		],
	];

	// Create query.
	$query = new WP_Query( $args );

	// Did we get any?
	if ( $query->have_posts() ) {

		// Open ul.
		$html .= '<ul class="all_comments_listing">' . "\n\n";

		while ( $query->have_posts() ) {

			$query->the_post();

			// Open li.
			$html .= '<li class="page_li"><!-- page li -->' . "\n\n";

			// Define Comment count.
			$comment_count_text = sprintf( _n(
				// Singular.
				'<span class="cp_comment_count">%d</span> comment',
				// Plural.
				'<span class="cp_comment_count">%d</span> comments',
				// Number.
				$post_comment_counts[ get_the_ID() ],
				// Domain.
				'comment-tagger'
				// Substitution.
			), $post_comment_counts[ get_the_ID() ] );

			// Show it.
			$html .= '<h4>' . get_the_title() . ' <span>(' . $comment_count_text . ')</span></h4>' . "\n\n";

			// Open Comments div.
			$html .= '<div class="item_body">' . "\n\n";

			// Open ul.
			$html .= '<ul class="item_ul">' . "\n\n";

			// Open li.
			$html .= '<li class="item_li"><!-- item li -->' . "\n\n";

			// Check for password-protected.
			if ( post_password_required( get_the_ID() ) ) {

				// Construct notice.
				$comment_body = '<div class="comment-content">' . __( 'Password protected', 'comment-tagger' ) . '</div>' . "\n";

				// Add notice.
				$html .= '<div class="comment_wrapper">' . "\n" . $comment_body . '</div>' . "\n\n";

			} else {

				foreach ( $all_comments as $comment ) {

					// Maybe show the Comment.
					if ( $comment->comment_post_ID == get_the_ID() ) {
						$html .= commentpress_format_comment( $comment );
					}

				}

			}

			// Close li.
			$html .= '</li><!-- /item li -->' . "\n\n";

			// Close ul.
			$html .= '</ul>' . "\n\n";

			// Close Comments div.
			$html .= '</div><!-- /item_body -->' . "\n\n";

			// Close li.
			$html .= '</li><!-- /page li -->' . "\n\n\n\n";

		}

		// Close ul.
		$html .= '</ul><!-- /all_comments_listing -->' . "\n\n";

		// Reset.
		wp_reset_postdata();

	} // End have_posts() chck.

	// --<
	return $html;

}
