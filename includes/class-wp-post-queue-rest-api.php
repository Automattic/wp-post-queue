<?php

namespace WP_Post_Queue;

/**
 * This class is responsible for the REST API side of the plugin.
 * It registers the REST routes and handles the requests to the API.
 */
class REST_API {
    /**
     * The settings for the plugin.
     * 
     * @var array
     */
    private $settings;

    /**
     * Constructor for the REST_API class.
     * 
     * @param array $settings The settings for the plugin.
     * @return void
     */
    public function __construct($settings) {
        $this->settings = $settings;
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Register all of the REST routes.
     * 
     * @return void
     */
    public function register_rest_routes() {
        register_rest_route('wp-post-queue/v1', '/settings', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_settings'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ));

        register_rest_route('wp-post-queue/v1', '/settings', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_settings'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ));
        
        register_rest_route('wp-post-queue/v1', '/recalculate', array(
            'methods' => 'POST',
            'callback' => array($this, 'recalculate_publish_times_rest_callback'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));    
        
        register_rest_route('wp-post-queue/v1', '/shuffle', array(
            'methods' => 'POST',
            'callback' => array($this, 'shuffle_queue'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));
    }

    /**
     * Get the settings for the queue.
     * 
     * @return array The settings for the queue.
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Update the settings for the queue.   
     * 
     * Endpoint: /wp-post-queue/v1/settings
     * Method: POST
     * Params:
     * - publish_times: int
     * - start_time: string 
     * - end_time: string
     * - wp_queue_paused: bool
     * 
     * If the queue is paused, it will be resumed and vice versa.
     * When settings are updated, the queue is recalculated and the publish times are updated.
     * 
     * @param \WP_REST_Request $request The request object.
     * @return array The updated settings.
     */
    public function update_settings(\WP_REST_Request $request) {
        $publish_times = $request->get_param('publish_times');
        $start_time = $request->get_param('start_time');
        $end_time = $request->get_param('end_time');
        $queue_paused = $request->get_param('wp_queue_paused');

        $current_settings = $this->settings;

        if ($publish_times !== null) {
            update_option('wp_queue_publish_times', $publish_times);
        }
        if ($start_time !== null) {
            update_option('wp_queue_start_time', $start_time);
        }
        if ($end_time !== null) {
            update_option('wp_queue_end_time', $end_time);
        }
        if ($queue_paused !== null) {
            update_option('wp_queue_paused', $queue_paused);
        }

        $this->settings = array(
            'publishTimes' => get_option('wp_queue_publish_times'),
            'startTime' => get_option('wp_queue_start_time'),
            'endTime' => get_option('wp_queue_end_time'),
            'wpQueuePaused' => get_option('wp_queue_paused'),
        );

        $queue_manager = new Manager($this->settings);

        // Only execute pause/resume if the setting has changed
        if ($queue_paused !== null && $queue_paused !== $current_settings['wpQueuePaused']) {
            if ($queue_paused) {
                $queue_manager->pause_queue();
            } else {
                $queue_manager->resume_queue();
            }
        }

        // Recalculate publish times regardless of pause setting change
        $current_queue = $queue_manager->get_current_order();
        $updated_order = $queue_manager->recalculate_publish_times(array_column($current_queue, 'ID'));

        return new \WP_REST_Response($updated_order, 200);
    }

    /**
     * Recalculate the publish times for all posts in the queue.
     * 
     * Endpoint: /wp-post-queue/v1/recalculate
     * Method: POST
     * Params:
     * - order: array of post IDs
     * 
     * @param \WP_REST_Request $request The request object.
     * @return array The updated publish times.
     */
    function recalculate_publish_times_rest_callback(\WP_REST_Request $request) {
        $new_order = $request->get_param('order');

        $queue_manager = new Manager($this->settings);
        $updated_order = $queue_manager->recalculate_publish_times($new_order);

        return new \WP_REST_Response($updated_order, 200);
    }

    /**
     * Shuffle the queue of posts.
     * 
     * Endpoint: /wp-post-queue/v1/shuffle
     * Method: POST
     * 
     * @param \WP_REST_Request $request The request object.
     * @return array The new order of the posts.
     */
    function shuffle_queue() {
        $manager = new Manager($this->settings);
        $new_order = $manager->shuffle_queued_posts();

        return new \WP_REST_Response($new_order, 200);
    }
}
