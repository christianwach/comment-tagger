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
 * Deletes a custom Taxonomy and all its data.
 *
 * After calling this function, you should also call flush_rewrite_rules() to
 * remove the registered rewrite slug.
 *
 * @see https://gist.github.com/wpsmith/9285391#file-uninstall-terms-taxonomy-2-php
 *
 * @param str $taxonomy The name of the Taxonomy to delete.
 */
function comment_tagger_delete_taxonomy( $taxonomy ) {

	// Bail if we have CommentPress 4.0.x.
	if ( defined( 'COMMENTPRESS_VERSION' ) ) {
		if ( version_compare( COMMENTPRESS_VERSION, '3.9.20', '>' ) ) {
			return;
		}
	}

	// Access DB object.
	global $wpdb;

	// Get Terms.
	// phpcs:ignore: WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$terms = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT t.*, tt.* FROM {$wpdb->terms} AS t " .
			"INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id " .
			'WHERE tt.taxonomy IN (%s) ' .
			'ORDER BY t.name ASC',
			$taxonomy
		)
	);

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

	// Delete the Taxonomy itself.
	// phpcs:ignore: WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->delete( $wpdb->term_taxonomy, [ 'taxonomy' => $taxonomy ], [ '%s' ] );

}

// Remove our custom Taxonomy.
comment_tagger_delete_taxonomy( 'comment_tags' );

// Lastly, flush rules.
flush_rewrite_rules();
