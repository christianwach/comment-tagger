/*
================================================================================
Comment Tagger Javascript
================================================================================
AUTHOR: Christian Wach <needle@haystack.co.uk>
--------------------------------------------------------------------------------
NOTES
=====

--------------------------------------------------------------------------------
*/



/**
 * Create global namespace
 */
var CommentTagger = CommentTagger || {};



/* -------------------------------------------------------------------------- */



/**
 * Create settings class
 *
 * Unused at present, but kept as a useful template.
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
 * Create comments class
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
	 * @return void
	 */
	this.init = function() {

	};

	/**
	 * Do setup when jQuery reports that the DOM is ready.
	 *
	 * This method should only be called once.
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
 * Create comments Select2 class
 */
CommentTagger.comments.select2 = new function() {

	// store object refs
	var me = this,
		$ = jQuery.noConflict();

	/**
	 * Select2 init
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
	 * Select2 listeners
	 */
	this.listeners = function() {

		/**
		 * Hook into CommentPress AJAX new comment added
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
 * Define what happens when the page is ready
 *
 * @return void
 */
jQuery(document).ready( function($) {

	// document ready!
	CommentTagger.comments.dom_ready();

}); // end document.ready



