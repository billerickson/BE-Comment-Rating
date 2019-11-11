<?php
/**
 * Plugin Name: BE Comment Rating
 * Plugin URI:  https://github.com/billerickson/BE-Comment-Rating/
 * Description: Star rating field for comments that's lean and AMP-compatible
 * Version:     1.0.0
 * Author:      Bill Erickson
 * Author URI:  https://www.billerickson.net
 * Requires at least: 5.0
 * License:     MIT
 * License URI: http://www.opensource.org/licenses/mit-license.php
 *
 * @package BE_Comment_Rating
 */

/**
 * Class BE_Comment_Rating
 */
class BE_Comment_Rating {

	/**
	 * Instance of the class.
	 *
	 * @var object
	 */
	private static $instance;

	/**
	 * Plugin Version.
	 *
	 * @var string
	 */
	private $plugin_version = '1.0.0';

	/**
	 * Meta Key.
	 *
	 * @var string
	 */
	private $meta_key;

	/**
	 * Class Instance.
	 *
	 * @return BE_Comment_Rating
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof BE_Comment_Rating ) ) {
			self::$instance = new BE_Comment_Rating();

			add_action( 'wp_head', [ self::$instance, 'set_meta_key' ] );
			add_action( 'wp_enqueue_scripts', [ self::$instance, 'register_styles' ] );
			add_filter( 'comment_text', [ self::$instance, 'add_stars_to_comment' ], 10, 2 );
			add_action( 'comment_form_field_comment', [ self::$instance, 'comment_form_field' ] );
			add_action( 'comment_post', [ self::$instance, 'save_comment_rating' ] );

			// Update rating on comment status change.
			add_action( 'trashed_comment', [ self::$instance, 'update_comment_rating_on_change' ] );
			add_action( 'spammed_comment', [ self::$instance, 'update_comment_rating_on_change' ] );
			add_action( 'unspammed_comment', [ self::$instance, 'update_comment_rating_on_change' ] );
			add_action( 'comment_unapproved_', [ self::$instance, 'update_comment_rating_on_change' ] );
			add_action( 'comment_approved_', [ self::$instance, 'update_comment_rating_on_change' ] );
		}

		return self::$instance;
	}

	/**
	 * Set Meta Key.
	 */
	public function set_meta_key() {
		/**
		 * Filters the meta key used for storing the rating.
		 *
		 * @param string $key Meta key.
		 */
		$key            = apply_filters( 'be_comment_rating_meta_key', 'be_comment_rating' );
		$this->meta_key = $key;
	}

	/**
	 * Register Scripts
	 */
	public function register_styles() {
		wp_register_style(
			'be-comment-rating',
			plugins_url( 'be-comment-rating.css', __FILE__ ),
			[],
			$this->plugin_version
		);

		/**
		 * Filters whether the CSS is included.
		 *
		 * @param bool $include_css Include CSS.
		 */
		$include_css = apply_filters( 'be_comment_rating_include_css', true );
		if ( is_singular() && $this->post_type_support( get_queried_object() ) && $include_css ) {
			wp_enqueue_style( 'be-comment-rating' );
		}
	}

	/**
	 * Supported Post Types.
	 *
	 * @param int|WP_Post $post Post.
	 * @return bool Post type supported.
	 */
	public function post_type_support( $post = null ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return false;
		}

		/**
		 * Filters the post types the ratings are enabled for.
		 *
		 * @param string[] $post_types Post types to enable.
		 */
		$post_types = apply_filters( 'be_comment_rating_post_types', [ 'post' ] );
		return in_array( get_post_type( $post ), $post_types, true );
	}

	/**
	 * Add stars to comment.
	 *
	 * @param string     $text    Comment text.
	 * @param WP_Comment $comment Comment object.
	 * @return string Comment text.
	 */
	public function add_stars_to_comment( $text, $comment = null ) {
		/**
		 * Filters whether to display the rating.
		 *
		 * @param bool $display Display the rating.
		 */
		$display = apply_filters( 'be_comment_rating_display', true );
		if ( ! $display ) {
			return $text;
		}

		if ( $comment instanceof WP_Comment ) {
			$rating = $this->get_rating_for( $comment->comment_ID );

			$rating_html = '';
			if ( $rating ) {
				/**
				 * Filters the star symbol used.
				 *
				 * @param string $star_char Character used for star.
				 */
				$star        = apply_filters( 'be_comment_rating_star', '★' );
				$rating_html = '<p class="comment-rating">';
				for ( $i = 0; $i < $rating; $i++ ) {
					$rating_html .= $star;
				}
				$rating_html .= '</p>';
			}

			/**
			 * Filters whether the rating location is above/below.
			 *
			 * @param string $location Either 'before' or 'after'.
			 */
			$location = apply_filters( 'be_comment_rating_location', 'before' );
			$text     = 'before' === $location ? $rating_html . $text : $text . $rating_html;
		}

		return $text;
	}

	/**
	 * Star rating field in comment form.
	 *
	 * @param string $comment_field Comment field.
	 * @return string Comment field.
	 */
	public function comment_form_field( $comment_field ) {

		if ( ! $this->post_type_support() ) {
			return $comment_field;
		}

		/**
		 * Filters whether to display the rating field.
		 *
		 * @param bool $display Whether to show the rating field.
		 */
		if ( ! apply_filters( 'be_comment_rating_field_display', true ) ) {
			return $comment_field;
		}

		/**
		 * Filters label for rating field.
		 *
		 * @param string $label Label.
		 */
		$label = apply_filters( 'be_comment_rating_field_label', __( 'Rating', 'be_comment_rating' ) );

		ob_start();
		?>

		<label for="<?php echo esc_attr( $this->meta_key ); ?>"><?php echo esc_html( $label ); ?></label>
		<fieldset class="be-comment-rating">
			<input
				name="<?php echo esc_attr( $this->meta_key ); ?>"
				type="radio"
				id="rating5"
				value="5">
			<label for="rating5" title="<?php esc_attr_e( '5 stars', 'be_comment_rating' ); ?>">☆</label>

			<input
				name="<?php echo esc_attr( $this->meta_key ); ?>"
				type="radio"
				id="rating4"
				value="4">
			<label for="rating4" title="<?php esc_attr_e( '4 stars', 'be_comment_rating' ); ?>">☆</label>

			<input
				name="<?php echo esc_attr( $this->meta_key ); ?>"
				type="radio"
				id="rating3"
				value="3">
			<label for="rating3" title="<?php esc_attr_e( '3 stars', 'be_comment_rating' ); ?>">☆</label>

			<input
				name="<?php echo esc_attr( $this->meta_key ); ?>"
				type="radio"
				id="rating2"
				value="2">
			<label for="rating2" title="<?php esc_attr_e( '2 stars', 'be_comment_rating' ); ?>">☆</label>

			<input
				name="<?php echo esc_attr( $this->meta_key ); ?>"
				type="radio"
				id="rating1"
				value="1">
			<label for="rating1" title="<?php esc_attr_e( '1 star', 'be_comment_rating' ); ?>">☆</label>
		</fieldset>

		<?php
		return ob_get_clean() . $comment_field;
	}

	/**
	 * Save comment rating
	 *
	 * @param int $comment_id Comment ID.
	 */
	public function save_comment_rating( $comment_id ) {
		/**
		 * Filters whether to save data.
		 *
		 * @param bool $save Whether to save.
		 */
		if ( ! apply_filters( 'be_comment_rating_save_data', true ) ) {
			return;
		}

		$this->set_meta_key();
		$rating = isset( $_POST[ $this->meta_key ] ) ? intval( $_POST[ $this->meta_key ] ) : 0; // Input var okay.
		$this->add_or_update_rating_for( $comment_id, $rating );
	}

	/**
	 * Update recipe rating when comment changes.
	 *
	 * @param int $comment_id Comment ID.
	 */
	public function update_comment_rating_on_change( $comment_id ) {
		/**
		 * Filters whether to save data.
		 *
		 * @param bool $save Whether to save.
		 */
		if ( ! apply_filters( 'be_comment_rating_save_data', true ) ) {
			return;
		}

		// Force update in case approval state changed.
		$rating = $this->get_rating_for( $comment_id );
		$this->add_or_update_rating_for( $comment_id, $rating );

		// Recalculate post rating.
		$this->update_recipe_rating_for_post( $comment_id );
	}

	/**
	 * Get Rating For Comment.
	 *
	 * @param int $comment_id Comment ID.
	 * @return int Rating.
	 */
	public function get_rating_for( $comment_id ) {
		$rating = get_comment_meta( $comment_id, $this->meta_key, true );

		return intval( $rating );
	}

	/**
	 * Add or Update Rating
	 *
	 * @param int $comment_id     Comment ID.
	 * @param int $comment_rating Comment rating.
	 */
	public function add_or_update_rating_for( $comment_id, $comment_rating ) {
		$comment_id     = intval( $comment_id );
		$comment_rating = intval( $comment_rating );

		update_comment_meta( $comment_id, $this->meta_key, $comment_rating );
		$this->update_recipe_rating_for_post( $comment_id );
	}

	/**
	 * Update Recipe Rating affected by comment
	 *
	 * @param int $comment_id Comment ID.
	 */
	public function update_recipe_rating_for_post( $comment_id ) {
		$initial_comment = get_comment( $comment_id );
		$post_id         = $initial_comment->comment_post_ID;
		$comments        = get_approved_comments( $post_id );

		$post_rating = [
			'count'   => 0,
			'total'   => 0,
			'average' => 0,
		];

		foreach ( $comments as $comment ) {
			$rating = intval( $this->get_rating_for( $comment->comment_ID ) );
			if ( ! $rating ) {
				continue;
			}

			$post_rating['count'] ++;
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
