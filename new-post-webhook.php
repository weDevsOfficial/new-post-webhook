<?php
/**
 * Plugin Name: New Post Webhook
 * Description: Sends a webhook request when a new post is published
 * Plugin URI: https://tareq.co
 * Author: Tareq Hasan
 * Author URI: https://tareq.co
 * Version: 1.0
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * New Post Webhook
 */
class New_Post_Webhook {

    function __construct() {
        add_action( 'transition_post_status', [ $this, 'fire_hook' ], 99, 3 );

        add_action( 'admin_init', [ $this, 'add_settings_field' ] );
        add_action( 'admin_footer-options-writing.php', [ $this, 'admin_footer_script' ] );

        add_action( 'wp_ajax_new-post-webhook-test', [ $this, 'send_test_event' ] );

        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'add_action_links' ] );
    }

    /**
     * Check if webhook is active
     *
     * If we have a valid URL, that means it's active
     *
     * @return boolean
     */
    public function is_active() {
        $url = $this->get_webhook_url();

        return !empty( $url );
    }

    /**
     * Get the webhook URL
     *
     * @return string
     */
    public function get_webhook_url() {
        return get_option( 'new_post_webhook', '' );
    }

    /**
     * Fire a callback only when posts are transitioned to 'publish'.
     *
     * @param string  $new_status New post status.
     * @param string  $old_status Old post status.
     * @param WP_Post $post       Post object.
     */
    function fire_hook( $new_status, $old_status, $post ) {

        if ( ! $this->is_active() ) {
            return;
        }

        if ( ( 'publish' === $new_status && 'publish' !== $old_status ) && 'post' === $post->post_type ) {
            $url = $this->get_webhook_url();

            wp_remote_post( $url, [
                // 'blocking' => false,
                'headers' => [
                    'content-type' => 'application/json',
                ],
                'timeout'  => 30,
                'body'     => json_encode( $this->get_post_data( $post ) )
            ] );
        }
    }

    /**
     * Get the post data
     *
     * @param  WP_Post $post
     *
     * @return array
     */
    public function get_post_data( $post ) {
        $tags     = get_the_terms( $post->ID, 'post_tag' );
        $category = get_the_terms( $post->ID, 'category' );

        return [
            'id'         => $post->ID,
            'title'      => $post->post_title,
            'url'        => get_permalink( $post ),
            'content'    => apply_filters( 'the_content', get_the_content( null, false, $post ) ),
            'excerpt'    => get_the_excerpt( $post ),
            'tags'       => wp_list_pluck( $tags, 'name', null ),
            'categories' => wp_list_pluck( $category, 'name', null ),
            'author'     => [
                'name' => get_the_author_meta( 'display_name', $post->post_author ),
                'url'  => get_author_posts_url( $post->post_author )
            ],
            'date' => [
                'raw'       => $post->post_date,
                'formatted' => get_the_date( '', $post )
            ]
        ];
    }

    /**
     * Add settings field and register it
     */
    function add_settings_field() {
        add_settings_field(
            'new_post_webhook',
            __( 'New Post Webhook', 'new-post-webhook' ),
            [ $this, 'render_settings_field' ],
            'writing',
            'remote_publishing',
            [
                'label_for' => 'new_post_webhook',
                'name' => 'new_post_webhook',
            ]
        );

        register_setting( 'writing', 'new_post_webhook', [
            'sanitize_callback' => 'esc_url_raw'
        ] );
    }

    /**
     * Render the URL field
     *
     * @param  array $args
     *
     * @return void
     */
    function render_settings_field( $args ) {
        $url = esc_url( $this->get_webhook_url(), null, 'display' );

        echo '<input type="url" placeholder="https://" name="new_post_webhook" id="new_post_webhook" value="' . $url . '" class="regular-text" />';
        echo '<p class="description">Enter the webhook URL to ping when a new post is published. Keep empty to disable.</p>';
        echo '<p><button type="button" id="btn-post-webhook-test">Send a Test</button></p>';
    }

    /**
     * Link settings page from plugins screen
     *
     * @param array $actions
     *
     * @return array
     */
    function add_action_links( $actions ) {
       $link = array(
          '<a href="' . admin_url( 'options-writing.php' ) . '">Settings</a>',
       );

       return array_merge( $actions, $link );
    }

    /**
     * Send a test event via AJAX
     *
     * @return void
     */
    public function send_test_event() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return wp_send_json_error( 'You don\'t have permission.');
        }

        $url = $this->get_webhook_url();
        $posts = get_posts( [
            'numberposts' => 1,
            'post_type'   => 'post',
        ] );

        if ( ! $posts ) {
            return wp_send_json_error( 'No posts found to send a test.');
        }

        wp_remote_post( $url, [
            'headers' => [
                'content-type' => 'application/json',
            ],
            'timeout'  => 30,
            'body'     => json_encode( $this->get_post_data( $posts[0] ) )
        ] );

        wp_send_json_success( 'Test webhook sent successfully.' );
    }

    /**
     * Perform the test event
     *
     * @return void
     */
    public function admin_footer_script() {
        ?>
        <script type="text/javascript">
            jQuery(function($) {
                $('button#btn-post-webhook-test').on('click', function(e) {
                    e.preventDefault();

                    var url = $('#new_post_webhook').val().trim();

                    if (!url) {
                        alert( 'Please provide a valid URL' );
                    } else {
                        $.post(ajaxurl, {
                            action: 'new-post-webhook-test'
                        }, function(data, textStatus, xhr) {
                            alert(data.data);
                        }).fail(function() {
                            alert('Sending request failed');
                        });
                    }
                });
            });
        </script>
        <?php
    }
}

new New_Post_Webhook();
