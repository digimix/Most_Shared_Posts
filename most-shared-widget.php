<?php
/*
Plugin Name: Most Shared Posts Widget
Plugin URI: http://github.com/digimix/most-shared-posts
Description: Displays most shared posts in an widget
Version:     1.0.2
Author: Christine Cardoso + Narayan Prusty
Author URI: https://digimix.co/
*/

function msp_is_post() {
	if(is_single() && !is_attachment()) {
		global $post;

		$last_update = get_post_meta($post->ID, 'msp_last_update', true);

		if($last_update) {
			if(time() - 260 > $last_update) {
				msp_update($post->ID);
			}
		} else {
			msp_update($post->ID);
		}
	}
}
add_action('wp', 'msp_is_post');

function msp_update($id) {
	$url = get_permalink($id);

	//facebook shares
	$response = wp_remote_get("https://api.facebook.com/method/links.getStats?format=json&urls=" . $url);
	$body = $response["body"];
	$body = json_decode($body);

	if($body[0]->total_count) {
		$facebook_count = $body[0]->total_count;
	} else {
		$facebook_count = 1;
	}

	/**
	 * Social Share Count API: Uses facebook, google, linkedin, pinterest
	 *
	 * @see https://donreach.com/social-share-count
	 *
	 */

	//facebook, google, linkedin, pinterest shares
	$response = wp_remote_get("https://count.donreach.com/?url=" . $url);
	$body = $response["body"];
	$body = json_decode($body);

	if($body->total) {
		$donreach_count = $body->total;
	} else {
		$donreach_count = 1;
	}

	//$total = $facebook_count + $twitter_count;
	$total = $facebook_count + $donreach_count;

    date_default_timezone_set('America/New_York');

	update_post_meta($id, "msp_share_count", $total);
	update_post_meta($id, "msp_last_update", time());
}

class Most_Shared_Post_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct("Most_Shared_Post_Widget", "Display Most Shared Posts", array("description" => __("This plugin displays ten most shared posts in an widget")));
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
        if($instance) {
            $title = esc_attr($instance["title"]);
    		$number = ! empty( $instance['number'] ) ? $instance['number'] : __( '5', 'text_domain' );

        } else {
            $title = "";
            $number = "5";
        }
        ?>

        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php echo "Title"; ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
        </p>
		<p>
			<label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Number of posts to show:' ); ?></label>
			<input class="tiny-text" id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" type="number" step="1" min="1" size="3" value="<?php echo esc_attr( $number ); ?>">
		</p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['number'] = ( ! empty( $new_instance['number'] ) ) ? strip_tags( $new_instance['number'] ) : '';
        return $instance;
    }

    public function widget($args, $instance)
    {
        extract($args);
        $title = apply_filters('widget_title', $instance['title']);
        echo $before_widget;

        if($title) {
            echo $before_title . $title . $after_title;
        }

		//display widget
		$posts = get_transient('msp');
		if($posts === false) {
			$args = array('posts_per_page' => $instance['number'], 'order' => 'DESC', 'orderby' => 'meta_value meta_value_num', 'meta_key' => 'msp_share_count',);
			$posts = get_posts($args);
			$json_posts = json_encode($posts);

		    echo '<ul class="nav nav-pills nav-stacked">';

			foreach($posts as $post) {
				$share_count = get_post_meta($post->ID, 'msp_share_count', true);

				if ($share_count == '0') {
					$share_count = '1';
				}

				echo '<li><a href="' . get_permalink($post->ID) . '"><span class="num">'. $share_count .'</span>' . get_the_post_thumbnail($post->ID, ['36','36']) .  $post->post_title . '<br/><time class="hidden">' . gmdate("j, Y, g:i a",get_post_meta($post->ID, "msp_last_update", true)) .'</time></a></li>';
			}

		    echo '</ul>';

			if(count($posts) >= 10) {
				set_transient('msp', $json_posts, 260);
			}
		} else {
		    $posts = json_decode($posts);

		    echo '<ul class="nav nav-pills nav-stacked">';

		    foreach($posts as $post) {
				$share_count = get_post_meta($post->ID, 'msp_share_count', true);
				if ($share_count == '0') { $share_count = '1';}

				echo '<li><a href="' . get_permalink($post->ID) . '">' . $share_count .  $post->post_title . '</a></li>';
		    }
		    echo '</ul>';
		} /* end display widget */

        echo $after_widget;
    }
}

function msp_register_most_shared_widget() {
	register_widget('Most_Shared_Post_Widget');
}
add_action('widgets_init', 'msp_register_most_shared_widget');

function msp_display_widget() {
	$posts = get_transient('msp');
	if($posts === false) {
		$args = array('posts_per_page' => 10, 'order' => 'DESC', 'orderby' => 'meta_value meta_value_num', 'meta_key' => 'msp_share_count', );
		$posts = get_posts($args);
		$json_posts = json_encode($posts);

	    echo '<ul class="nav nav-pills nav-stacked">';

		foreach($posts as $post) {
			$share_count = get_post_meta($post->ID, 'msp_share_count', true);
			if ($share_count == '0') { $share_count = '1';}

			echo '<li><a href="' . get_permalink($post->ID) . '"><span class="num">'. $share_count .'</span>' . get_the_post_thumbnail($post->ID, ['36','36']) .  $post->post_title . '<br/><time class="hidden">' . gmdate("j, Y, g:i a",get_post_meta($post->ID, "msp_last_update", true)) .'</time></a></li>';
		}

	    echo '</ul>';

		if(count($posts) >= 10) {
			set_transient('msp', $json_posts, 260);
		}
	} else {
	    $posts = json_decode($posts);

	    echo '<ul class="nav nav-pills nav-stacked">';

	    foreach($posts as $post) {
			$share_count = get_post_meta($post->ID, 'msp_share_count', true);
			if ($share_count == '0') { $share_count = '1';}

			echo '<li><a href="' . get_permalink($post->ID) . '">' . $share_count .  $post->post_title . '</a></li>';
	    }
	    echo '</ul>';
	}
}