<?php
/**
 * Comment Tagger Uninstaller.
 *
 * @package Comment_Tagger
 */

// Kick out if uninstall not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

/**
 * Delete a custom taxonomy and all its data.
 *
 * After calling this function, you should also call flush_rewrite_rules() to
 * remove the registered rewrite slug.
 *
 * @see https://gist.github.com/wpsmith/9285391#file-uninstall-terms-taxonomy-2-php
 *
 * @param str $taxonomy The name of the taxonomy to delete.
 */
function comment_tagger_delete_taxonomy( $taxonomy ) {

	// Access DB object.
	global $wpdb;

	// Get terms.
	// phpcs:ignore: WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$terms = $wpdb->get_results( $wpdb->prepare(
		"SELECT t.*, tt.* FROM $wpdb->terms AS t " .
		"INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id " .
		'WHERE tt.taxonomy IN (%s) ' .
		'ORDER BY t.name ASC',
		$taxonomy
	) );

	// Did we get any?
	if ( count( $terms ) > 0 ) {

		// Delete each one in turn.
		foreach ( $terms as $term ) {

			// Delete data.
			// phpcs:ignore: WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $wpdb->term_taxonomy, [ 'term_taxonomy_id' => $term->term_taxonomy_id ] );
			// phpcs:ignore: WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $wpdb->term_relationships, [ 'term_taxonomy_id' => $term->term_taxonomy_id ] );
			// phpcs:ignore: WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $wpdb->terms, [ 'term_id' => $term->term_id ] );

		}
	}

	// Delete the taxonomy itself.
	// phpcs:ignore: WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->delete( $wpdb->term_taxonomy, [ 'taxonomy' => $taxonomy ], [ '%s' ] );

}

// Remove our custom taxonomy.
comment_tagger_delete_taxonomy( 'comment_tags' );

// Lastly, flush rules.
flush_rewrite_rules();
