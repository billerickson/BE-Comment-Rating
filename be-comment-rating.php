<?php
/**
 * Plugin Name: BE Comment Rating
 * Plugin URI:	https://github.com/billerickson/BE-Comment-Rating/
 * Description: Star rating field for comments that's lean and AMP-compatible
 * Version:     1.0.0
 * Author:      Bill Erickson
 * Author URI:  https://www.billerickson.net
 * Requires at least: 5.0
 * License:     MIT
 * License URI: http://www.opensource.org/licenses/mit-license.php
 */

 class BE_Comment_Rating {

 	/**
 	 * Instance of the class.
 	 * @var object
 	 */
 	private static $instance;

	/**
	 * Plugin Version
	 * @var string
	 */
	private $plugin_version = '1.0.0';


	/**
	 * Meta Key
	 * @var string
	 */
	private $meta_key;

 	/**
 	 * Class Instance.
 	 * @return BE_Comment_Rating
 	 */
 	public static function instance() {
 		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof BE_Comment_Rating ) ) {
 			self::$instance = new BE_Comment_Rating();

			add_action( 'wp_head', [ self::$instance, 'set_meta_key'] );
			add_action( 'wp_enqueue_scripts', [ self::$instance, 'register_styles'] );
			add_filter( 'comment_text', [ self::$instance, 'add_stars_to_comment' ], 10, 2 );
			add_action( 'comment_form_field_comment', [ self::$instance, 'comment_form_field' ] );
			add_action( 'comment_post', [ self::$instance, 'save_comment_rating'] );

			// Update rating on comment status change
			add_action( 'trashed_comment', [ self::$instance, 'update_comment_rating_on_change' ] );
			add_action( 'spammed_comment', [ self::$instance, 'update_comment_rating_on_change' ] );
			add_action( 'unspammed_comment', [ self::$instance, 'update_comment_rating_on_change' ] );
			add_action( 'comment_unapproved_', [ self::$instance, 'update_comment_rating_on_change' ] );
			add_action( 'comment_approved_', [ self::$instance, 'update_comment_rating_on_change' ] );

			add_action( 'wp_head', function() { ea_pp( get_post_meta( get_the_ID() ) ); });
  		}
 		return self::$instance;
 	}

	/**
	 * Set Meta Key
	 *
	 */
	public function set_meta_key() {
		$key = apply_filters( 'be_comment_rating_meta_key', 'be_comment_rating' );
		$this->meta_key = $key;
	}

	/**
	 * Register Scripts
	 *
	 */
	public function register_styles() {
		wp_register_style(
			'be-comment-rating',
			plugins_url( 'be-comment-rating.css', __FILE__ ),
			[],
			$this->plugin_version
		);

		$include_css = apply_filters( 'be_comment_rating_include_css', true );
		if( is_singular() && $this->post_type_support() && $include_css )
			wp_enqueue_style( 'be-comment-rating' );

	}

	/**
	 * Supported Post Types
	 *
	 */
	public function post_type_support( $post_id = false ) {

		$post_id = $post_id ? intval( $post_id ) : ( is_singular() ? get_queried_object_id() : false );
		$post_types = apply_filters( 'be_comment_rating_post_types', [ 'post' ] );
		return in_array( get_post_type( $post_id ), $post_types );
	}

	/**
	 * Add stars to comment
	 *
	 */
	public function add_stars_to_comment( $text, $comment = null ) {
		if ( null !== $comment ) {
			$rating = $this->get_rating_for( $comment->comment_ID );

			$rating_html = '';
			if ( $rating ) {
				$star = apply_filters( 'be_comment_rating_star', '★' );
				$rating_html = '<p class="comment-rating">';
				for( $i = 0; $i < $rating; $i++ ) {
					$rating_html .= $star;
				}
				$rating_html .= '</p>';
			}

			$location = apply_filters( 'be_comment_rating_location', 'before' );
			$text = 'before' === $location ? $rating_html . $text : $text . $rating_html;
		}

		return $text;

	}

	/**
	 * Star rating field in comment form
	 *
	 */
	public function comment_form_field( $comment_field ) {

		if( ! $this->post_type_support() )
			return $comment_field;

		if( ! apply_filters( 'be_comment_rating_field_display', true ) )
			return $comment_field;

		$label = apply_filters( 'be_comment_rating_field_label', __( 'Rating', 'be_comment_rating' ) );

		$rating_field = '<label for="' . $this->meta_key . '">' . $label . '</label><fieldset class="be-comment-rating">
		    <input name="' . $this->meta_key . '"
		      type="radio"
		      id="rating5"
		      value="5">
		    <label for="rating5"
		      title="5 stars">☆</label>

		    <input name="' . $this->meta_key . '"
		      type="radio"
		      id="rating4"
		      value="4">
		    <label for="rating4"
		      title="4 stars">☆</label>

		    <input name="' . $this->meta_key . '"
		      type="radio"
		      id="rating3"
		      value="3">
		    <label for="rating3"
		      title="3 stars">☆</label>

		    <input name="' . $this->meta_key . '"
		      type="radio"
		      id="rating2"
		      value="2">
		    <label for="rating2"
		      title="2 stars">☆</label>

		    <input name="' . $this->meta_key . '"
		      type="radio"
		      id="rating1"
		      value="1">
		    <label for="rating1"
		      title="1 stars">☆</label>
		  </fieldset>';
		return $rating_field . $comment_field;
	}

	/**
	 * Save comment rating
	 *
	 */
	public function save_comment_rating( $comment_id ) {
		if( ! apply_filters( 'be_comment_rating_save_data', true ) )
			return;

		$this->set_meta_key();
		$rating = isset( $_POST[ $this->meta_key ] ) ? intval( $_POST[ $this->meta_key ] ) : 0; // Input var okay.
		$this->add_or_update_rating_for( $comment_id, $rating );
	}

	/**
	 * Update recipe rating when comment changes.
	 *
	 */
	public function update_comment_rating_on_change( $comment_id ) {
		if( ! apply_filters( 'be_comment_rating_save_data', true ) )
			return;

		// Force update in case approval state changed.
		$rating = $this->get_rating_for( $comment_id );
		$this->add_or_update_rating_for( $comment_id, $rating );

		// Recalculate post rating.
		$this->update_recipe_rating_for_post( $comment_id );
	}

	/**
	 * Get Rating For
	 *
	 */
	public function get_rating_for( $comment_id ) {
		$rating = get_comment_meta( $comment_id, $this->meta_key, true );
		return intval( $rating );
	}

	/**
	 * Add or Update Rating
	 *
	 */
	public function add_or_update_rating_for( $comment_id, $comment_rating ) {
		$comment_id = intval( $comment_id );
		$comment_rating = intval( $comment_rating );

		update_comment_meta( $comment_id, $this->meta_key, $comment_rating );
		$this->update_recipe_rating_for_post( $comment_id );
	}

	/**
	 * Update Recipe Rating affected by comment
	 *
	 */
	public function update_recipe_rating_for_post( $comment_id ) {
		$initial_comment = get_comment( $comment_id );
		$post_id = $initial_comment->comment_post_ID;
		$comments = get_approved_comments( $post_id );

		$post_rating = [
			'count' 	=> 0,
			'total'		=> 0,
			'average'	=> 0,
		];

		foreach( $comments as $comment ) {
			$rating = intval( $this->get_rating_for( $comment->comment_ID ) );
			if( ! $rating )
				continue;

			$post_rating['count']++;
			$post_rating['total'] += intval( $rating );
		}

		// Calculate average.
		if ( $post_rating['count'] > 0 ) {
			$post_rating['average'] = ceil( $post_rating['total'] / $post_rating['count'] * 100 ) / 100;
		}

		// Update recipe rating and average (to sort by).
		update_post_meta( $post_id, $this->meta_key, $post_rating );
		update_post_meta( $post_id, $this->meta_key . '_average', $post_rating['average'] );
	}

 }

 /**
  * The function provides access to the class methods.
  *
  * Use this function like you would a global variable, except without needing
  * to declare the global.
  *
  * @return object
  */
 function be_comment_rating() {
 	return BE_Comment_Rating::instance();
 }
 be_comment_rating();
