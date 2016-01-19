<?php

/*
Plugin Name: Most Shared Posts Widget
Plugin URI: http://github.com/digimix/most-shared-posts
Description: Displays most shared posts in an widget
Author: Christine Cardoso + Narayan Prusty
*/



function msp_is_post() {
	if(is_single() && !is_attachment()) {
	    global $post;

	    $last_update = get_post_meta($post->ID, "msp_last_update", true);

		if($last_update) {
			if(time() - 21600 > $last_update) {
				msp_update($post->ID);
			}
		} else {
			msp_update($post->ID);
		}
	}
}

add_action("wp", "msp_is_post");


/**
 * Register thumbnail sizes.
 *
 * @return void
 */
function mostshared_add_image_size() {
	$sizes = get_option( 'mostshared_thumb_sizes' );
	if ( $sizes ) {
		foreach ( $sizes as $id => $size ) {
			add_image_size( 'mostshared_thumb_size' . $id, $size[0], $size[1], true );
		}
	}
}
add_action( 'init',  'mostshared_add_image_size' );


function msp_update($id) {
	$url = get_permalink($id);

	//facebook shares
	$response = wp_remote_get("https://api.facebook.com/method/links.getStats?format=json&urls=" . $url);
	$body = $response["body"];
	$body = json_decode($body);

	if($body[0]->share_count) {
		$facebook_count = $body[0]->share_count;
	} else {
		$facebook_count = 0;
	}


	//twitter shares
	$response = wp_remote_get("http://urls.api.twitter.com/1/urls/count.json?url=" . $url);
	$body = $response["body"];
	$body = json_decode($body);

	if($body->count) {
		$twitter_count = $body->count;
	} else {
		$twitter_count = 0;
	}

	$total = $facebook_count + $twitter_count;

	update_post_meta($id, "msp_share_count", $total);
	update_post_meta($id, "msp_last_update", time());
}

/**
 * Adds Most_Shared widget.
 */

class Most_Shared_Post_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
    public function __construct() {
        parent::__construct(
	        "Most_Shared_Post_Widget",
	        "Display Most Shared Posts",
	        array("description" => __("This plugin displays ten most shared posts in an widget"))
        );
    }

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
    public function widget($args, $instance) {
        extract($args);
		$sizes = get_option( 'mostshared_thumb_sizes' );

        echo $before_widget;

		// Widget Title
		if ( ! empty( $instance['title'] ) ) {
			$title = apply_filters( 'widget_title', $instance['title'] );
			echo $args['before_title'] . $title . $args['after_title'];
		}

		$posts = get_transient('msp');

		if($posts === false) {

			$args = array("posts_per_page" => $instance['number'], "meta_key" => "msp_share_count", "orderby" => "meta_value");
			$posts = get_posts($args);
			$json_posts = json_encode($posts);

		    echo '<ul class="nav nav-pills nav-stacked">';

		    foreach($posts as $post) { ?>

				<li>
					<a href="<?php get_permalink($post->ID); ?>" title="<?php get_the_title_attribute($post->ID); ?>">
						<span class="num"><?php echo get_post_meta($post->ID, 'msp_share_count', 1); ?></span>
						<?php if ( current_theme_supports( "post-thumbnails" )  && ! empty( $instance['thumb'] ) && has_post_thumbnail() ) : ?>
								<?php echo get_the_post_thumbnail([$instance['thumb_w'], $instance['thumb_h']], $post->ID ); ?>
						<?php endif; ?>
						<span class="post-title"><?php get_the_title() ? the_title() : the_ID(); ?></span>
					</a>
				</li>
		    <?php
		    }

		    echo '</ul>';

		    if(count($posts) >= 10) {
				set_transient("msp", $json_posts, 21600);
		    }
		} else {
		    $posts = json_decode($posts);

		    echo '<ul class="nav nav-pills nav-stacked">';

		    foreach($posts as $post) { ?>
				<li>
					<a href="<?php get_permalink($post->ID); ?>" title="<?php get_the_title_attribute($post->ID); ?>">
						<span class="num"><?php echo get_post_meta($post->ID, 'msp_share_count', 1); ?></span>
						<?php if ( current_theme_supports( "post-thumbnails" )  && ! empty( $instance['thumb'] ) && has_post_thumbnail() ) : ?>
								<?php echo get_the_post_thumbnail([$instance['thumb_w'], $instance['thumb_h']], $post->ID ); ?>
						<?php endif; ?>
						<span class="post-title"><?php get_the_title() ? the_title() : the_ID(); ?></span>
					</a>
				</li>
			<?php
		    }

		    echo '</ul>';
		}

        echo $after_widget;
    }


	/**
	 * Update widget
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */

    public function update($new_instance, $old_instance) {
        $instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['number'] = ( ! empty( $new_instance['number'] ) ) ? strip_tags( $new_instance['number'] ) : '';
		$instance['thumb'] = ( ! empty( $new_instance['thumb'] ) ) ? strip_tags( $new_instance['thumb'] ) : '';
		$instance['thumb_w'] = ( ! empty( $new_instance['thumb_w'] ) ) ? strip_tags( $new_instance['thumb_w'] ) : '';
		$instance['thumb_h'] = ( ! empty( $new_instance['thumb_h'] ) ) ? strip_tags( $new_instance['thumb_h'] ) : '';

		$sizes = get_option( 'mostshared_thumb_sizes' );
		if ( !$sizes ) {
			$sizes = array( );
		}
		$sizes[$this->id] = array( $new_instance['thumb_w'], $new_instance['thumb_h'] );
		update_option( 'mostshared_thumb_sizes', $sizes );

        return $instance;
    }

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
    public function form($instance) {
		// Output admin widget options form
		$title 			= ! empty( $instance['title'] ) ? $instance['title'] : __( '', 'text_domain' );
		$number 		= ! empty( $instance['number'] ) ? $instance['number'] : __( '5', 'text_domain' );
		$thumb 			= ! empty( $instance['thumb'] ) ? $instance['thumb'] : __( 'checked', 'text_domain' );
		$thumb_w 		= ! empty( $instance['thumb_w'] ) ? $instance['thumb_w'] : __( '36', 'text_domain' );
		$thumb_h 		= ! empty( $instance['thumb_h'] ) ? $instance['thumb_h'] : __( '36', 'text_domain' );
        ?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Number of posts to show:' ); ?></label>
			<input class="tiny-text" id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" type="number" step="1" min="1" size="3" value="<?php echo esc_attr( $number ); ?>">
		</p>
		<?php if ( function_exists( 'the_post_thumbnail' ) && current_theme_supports( "post-thumbnails" ) ) : ?>
			<p>
				<label for="<?php echo $this->get_field_id( 'thumb' ); ?>">
					<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('thumb'); ?>" name="<?php echo $this->get_field_name('thumb'); ?>"<?php checked( ( bool ) esc_attr( $thumb ), true ); ?> />
					<?php _e( 'Show post thumbnail' ); ?>
				</label>
			</p>
			<p>
				<label>
					<?php _e( 'Thumbnail dimensions (in pixels)' ); ?>:<br />
					<label for="<?php echo $this->get_field_id( "thumb_w" ); ?>">
						Width: <input class="widefat" style="width:30%;" type="text" id="<?php echo $this->get_field_id( "thumb_w" ); ?>" name="<?php echo $this->get_field_name( "thumb_w" ); ?>" value="<?php echo esc_attr( $thumb_w ); ?>" />
					</label>

					<label for="<?php echo $this->get_field_id( "thumb_h" ); ?>">
						Height: <input class="widefat" style="width:30%;" type="text" id="<?php echo $this->get_field_id( "thumb_h" ); ?>" name="<?php echo $this->get_field_name( "thumb_h" ); ?>" value="<?php echo esc_attr( $thumb_h ); ?>" />
					</label>
				</label>
			</p>
		<?php endif; ?>

        <?php
    }
}



function msp_register_most_shared_widget() {
  register_widget('Most_Shared_Post_Widget');
}

add_action('widgets_init', 'msp_register_most_shared_widget');





function msp_display_widget() {
	$posts = get_transient("msp");

	if($posts === false) {

		$args = array("posts_per_page" => $instance['number'], "meta_key" => "msp_share_count", "orderby" => "meta_value");
		$posts = get_posts($args);
		$json_posts = json_encode($posts);

	    echo '<ul class="nav nav-pills nav-stacked">';

	    foreach($posts as $post) { ?>

			<li>
				<a href="<?php get_permalink($post->ID); ?>" title="<?php get_the_title_attribute($post->ID); ?>">
					<span class="num"><?php echo get_post_meta($post->ID, 'msp_share_count', 1); ?></span>
					<?php if ( current_theme_supports( "post-thumbnails" )  && ! empty( $instance['thumb'] ) && has_post_thumbnail() ) : ?>
							<?php echo get_the_post_thumbnail([$instance['thumb_w'], $instance['thumb_h']], $post->ID ); ?>
					<?php endif; ?>
					<span class="post-title"><?php get_the_title() ? the_title() : the_ID(); ?></span>
				</a>
			</li>
	    <?php
	    }

	    echo '</ul>';

	    if(count($posts) >= 10) {
			set_transient("msp", $json_posts, 21600);
	    }
	} else {
	    $posts = json_decode($posts);

	    echo '<ul class="nav nav-pills nav-stacked">';

	    foreach($posts as $post) { ?>
			<li>
				<a href="<?php get_permalink($post->ID); ?>" title="<?php get_the_title_attribute($post->ID); ?>">
					<span class="num"><?php echo get_post_meta($post->ID, 'msp_share_count', 1); ?></span>
					<?php if ( current_theme_supports( "post-thumbnails" )  && ! empty( $instance['thumb'] ) && has_post_thumbnail() ) : ?>
							<?php echo get_the_post_thumbnail([$instance['thumb_w'], $instance['thumb_h']], $post->ID ); ?>
					<?php endif; ?>
					<span class="post-title"><?php get_the_title() ? the_title() : the_ID(); ?></span>
				</a>
			</li>
		<?php
	    }

	    echo '</ul>';
	}
}