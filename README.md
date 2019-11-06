# BE Comment Rating

This WordPress plugin adds a star rating field for comments that's lean, uses **no JavaScript**, and is AMP-compatible. This 100% CSS approach was inspired by the [AMP documentation](https://amp.dev/documentation/examples/interactivity-dynamic-content/star_rating/).

You can also use this plugin to add AMP compatibility to an existing rating plugin that uses JavaScript. See the [WP Recipe Maker](#wp-recipe-maker) section below.

![screenshot](https://p198.p4.n0.cdn.getcloudapp.com/items/8LuZYjKG/sample-form-large.jpg?v=251828ada15d66c6cd2d161d4a47d53e)

## Filters

`be_comment_rating_post_types`  
(array) Which post types the comment rating field appears on. Default value is `[ 'post' ]`.

`be_comment_rating_display`  
(bool) You can disable the comment rating from appearing inside each comment with `apply_filters( 'be_comment_rating_display', '__return_false' );`

`be_comment_rating_star`  
(string) The star used in the comment rating (but not the comment form). Default is ★.

`be_comment_rating_location`  
(string) Whether the star rating should appear `before` the comment text (default) or `after`.

`be_comment_rating_include_css`  
(bool) You can prevent BE Comment Rating from enqueuing its stylesheet using `apply_filters( 'be_comment_rating_include_css', '__return_false' )`. If you do this, I recommend you copy the CSS to your theme because the functionality of this plugin depends upon its very unique usage of CSS.

`be_comment_rating_field_display`  
(bool) Whether or not the comment field should appear in the comment form. Default is `true`.

`be_comment_rating_field_label`  
(string) Label for the comment rating field. Default: Rating

`be_comment_rating_save_data`  
(bool) Whether or not BE Comment Rating should save the rating data. Default is `true`. This is useful for [integrating with other plugins](#wp-recipe-maker).

`be_comment_rating_meta_key`  
(string) Used to change the comment meta key, useful for [integrating with other plugins](#wp-recipe-maker). The default is `be_comment_rating`. Note: don't change this after you have begun collecting comment ratings, as all previous comment ratings will disappear.

## WP Recipe Maker

WP Recipe Maker includes its own comment rating field, but it depends upon JavaScript so is not AMP compatible.

You can use BE Comment Rating to enable WPRM star ratings on AMP endpoints. Add the following to your theme's functions.php file:

```php
/**
 * When to use BE Comment Rating
 * Only use it for AMP endpoints, and when the current post has a recipe
 */
function ea_use_be_comment_rating() {
	$is_amp = function_exists( 'is_amp_endpoint' ) && is_amp_endpoint();
	$has_recipe = class_exists( 'WPRM_Template_Shortcodes' ) && WPRM_Template_Shortcodes::get_current_recipe_id();
	return $is_amp && $has_recipe;
}

// WPRM AMP Compatibility

// -- Don't display rating in comment, WPRM does this
add_filter( 'be_comment_rating_display', '__return_false' );

// -- Only show comment rating field for AMP endpoints
add_filter( 'be_comment_rating_field_display', 'ea_use_be_comment_rating' );

// -- Don't save data, WPRM does this
add_filter( 'be_comment_rating_save_data', '__return_false' );

// -- Change meta key to match WPRM
add_filter( 'be_comment_rating_meta_key', function( $meta_key ) {
	return 'wprm-comment-rating';
});

// -- Disable WPRM comment rating form field
add_filter( 'wprm_template_comment_rating_form', function( $file ) {
	if( ea_use_be_comment_rating() )
		$file = WP_CONTENT_DIR . '/index.php';
	return $file;
} );

```
