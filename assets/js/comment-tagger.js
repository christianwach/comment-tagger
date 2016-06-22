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

	// store object refs
	var me = this,
		$ = jQuery.noConflict();

	// init group ID
	//this.group_id = false;

	// override if we have our localisation object
	if ( 'undefined' !== typeof CommentTaggerSettings ) {
		//this.group_id = CommentTaggerSettings.data.group_id;
	}

	/**
	 * Setter for group ID
	 */
	this.set_group_id = function( val ) {
		this.group_id = val;
	};

	/**
	 * Getter for group ID
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

	// store object refs
	var me = this,
		$ = jQuery.noConflict();

	/**
	 * Initialise "Read With".
	 *
	 * This method should only be called once.
	 *
	 * @since 0.1
	 *
	 * @return void
	 */
	this.init = function() {

	};

	/**
	 * Do setup when jQuery reports that the DOM is ready.
	 *
	 * This method should only be called once.
	 *
	 * @since 0.1
	 *
	 * @return void
	 */
	this.dom_ready = function() {

		// init Select2
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

	// store object refs
	var me = this,
		$ = jQuery.noConflict();

	/**
	 * Select2 init.
	 *
	 * @since 0.1
	 */
	this.init = function() {

		/**
		 * Select2 init
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
		 * @param object event The event (unused)
		 * @param int comment_id The new comment ID
		 * @return void
		 */
		$(document).on(
			'commentpress-ajax-comment-added',
			function( event, comment_id ) {

				// reset Select2
				$('.comment_tagger_select2').val( null ).trigger( "change" );

			} // end function
		);

	};

};



/* -------------------------------------------------------------------------- */



// do immediate actions
CommentTagger.comments.init();



/* -------------------------------------------------------------------------- */



/**
 * Define what happens when the page is ready.
 *
 * @since 0.1
 */
jQuery(document).ready( function($) {

	// document ready!
	CommentTagger.comments.dom_ready();

}); // end document.ready



