/**
 * Custom Javascript functionality for Comment Tagger.
 *
 * @since 0.1
 *
 * @package Comment_Tagger
 */



/**
 * Create global namespace.
 *
 * @since 0.1
 */
var CommentTagger = CommentTagger || {};



/* -------------------------------------------------------------------------- */



/**
 * Create settings class.
 *
 * Unused at present, but kept as a useful template.
 *
 * @since 0.1
 */
CommentTagger.settings = new function() {

	// Store object refs.
	var me = this,
		$ = jQuery.noConflict();

	// Init group ID.
	//this.group_id = false;

	// Override if we have our localisation object.
	if ( 'undefined' !== typeof CommentTaggerSettings ) {
		//this.group_id = CommentTaggerSettings.data.group_id;
	}

	/**
	 * Setter for group ID.
	 */
	this.set_group_id = function( val ) {
		this.group_id = val;
	};

	/**
	 * Getter for group ID.
	 */
	this.get_group_id = function() {
		return this.group_id;
	};

};



/* -------------------------------------------------------------------------- */



/**
 * Create comments class.
 *
 * @since 0.1
 */
CommentTagger.comments = new function() {

	// Store object refs.
	var me = this,
		$ = jQuery.noConflict();

	/**
	 * Initialise "Read With".
	 *
	 * This method should only be called once.
	 *
	 * @since 0.1
	 */
	this.init = function() {

	};

	/**
	 * Do setup when jQuery reports that the DOM is ready.
	 *
	 * This method should only be called once.
	 *
	 * @since 0.1
	 */
	this.dom_ready = function() {

		// Init Select2.
		me.select2.init();
		me.select2.listeners();

	};

};



/* -------------------------------------------------------------------------- */



/**
 * Create comments Select2 class.
 *
 * @since 0.1
 */
CommentTagger.comments.select2 = new function() {

	// Store object refs.
	var me = this,
		$ = jQuery.noConflict();

	/**
	 * Select2 init.
	 *
	 * @since 0.1
	 */
	this.init = function() {

		/**
		 * Select2 init.
		 */
		$('.comment_tagger_select2').select2({
			tags: true,
			tokenSeparators: [','],
			multiple: true
		});

	};

	/**
	 * Select2 listeners.
	 *
	 * @since 0.1
	 */
	this.listeners = function() {

		/**
		 * Hook into CommentPress AJAX new comment added.
		 *
		 * @since 0.1
		 *
		 * @param object event The event. (unused)
		 * @param int comment_id The new comment ID.
		 */
		$(document).on(
			'commentpress-ajax-comment-added',
			function( event, comment_id ) {

				// Reset Select2.
				$('.comment_tagger_select2').val( null ).trigger( "change" );

			} // End function.
		);

	};

};



/* -------------------------------------------------------------------------- */



// Do immediate actions.
CommentTagger.comments.init();



/* -------------------------------------------------------------------------- */



/**
 * Define what happens when the page is ready.
 *
 * @since 0.1
 */
jQuery(document).ready( function($) {

	// Document ready!
	CommentTagger.comments.dom_ready();

});



