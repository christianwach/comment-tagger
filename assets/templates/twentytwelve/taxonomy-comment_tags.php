<?php
/**
 * A sample template for displaying Comment Tagger tag archives.
 *
 * Copy this template to your Twenty Twelve child theme's directory in order to
 * display comments assigned to a tag. You can adapt the markup to your needs if
 * you want to - the markup below should help you get started.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package Comment_Tagger
 */

get_header(); ?>

	<section id="primary" class="site-content">
		<div id="content" role="main">

		<header class="archive-header">
			<h1 class="archive-title"><?php printf( __( 'Comments Tagged &#8216;%s&#8217;', 'comment-tagger' ), '<span>' . single_tag_title( '', false ) . '</span>' ); ?></h1>

		<?php if ( tag_description() ) : // Show an optional tag description ?>
			<div class="archive-meta"><?php echo tag_description(); ?></div>
		<?php endif; ?>
		</header><!-- .archive-header -->

		<?php $comments = comment_tagger_get_tagged_comments_content(); ?>

		<?php if ( ! empty( $comments ) ) : ?>
			<?php echo $comments; ?>
		<?php else : ?>
			<p><?php _e( 'Sorry, but there are no comments for this tag.', 'comment-tagger' ); ?></p>
		<?php endif; ?>

		</div><!-- #content -->
	</section><!-- #primary -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
