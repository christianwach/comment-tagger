<?php /*
================================================================================
Comment Tagger Uninstaller
================================================================================
AUTHOR: Christian Wach <needle@haystack.co.uk>
--------------------------------------------------------------------------------
NOTES
=====


--------------------------------------------------------------------------------
*/



// kick out if uninstall not called from WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit(); }



/**
 * Delete a custom taxonomy and all its data.
 *
 * After calling this function, you should also call flush_rewrite_rules() to
 * remove the registered rewrite slug.
 *
 * @see https://gist.github.com/wpsmith/9285391#file-uninstall-terms-taxonomy-2-php
 *
 * @param str $taxonomy The name of the taxonomy to delete
 */
function comment_tagger_delete_taxonomy( $taxonomy ) {

	// access DB object
	global $wpdb;

	// construct SQL
	$sql = $wpdb->prepare(
		"SELECT t.*, tt.* FROM $wpdb->terms AS t ".
		"INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id ".
		"WHERE tt.taxonomy IN ('%s') ".
		"ORDER BY t.name ASC",
		$taxonomy
	);

	// get terms
	$terms = $wpdb->get_results( $sql );

    // did we get any?
	if ( count( $terms ) > 0 ) {

		// delete each one in turn
		foreach( $terms as $term ) {

			// delete data
			$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $term->term_taxonomy_id ) );
			$wpdb->delete( $wpdb->term_relationships, array( 'term_taxonomy_id' => $term->term_taxonomy_id ) );
			$wpdb->delete( $wpdb->terms, array( 'term_id' => $term->term_id ) );

		}
	}

	// delete the taxonomy itself
	$wpdb->delete( $wpdb->term_taxonomy, array( 'taxonomy' => $taxonomy ), array( '%s' ) );

}



// remove our custom taxonomy
comment_tagger_delete_taxonomy( 'comment_tags' );

// lastly, flush rules
flush_rewrite_rules();



