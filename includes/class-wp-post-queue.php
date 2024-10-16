<?php

namespace WP_Post_Queue;

/**
 * The main class for the WP Post Queue plugin.
 * 
 * This class is responsible for loading the settings, initializing the admin and manager classes,
 * and running the plugin.
 */
class WP_Post_Queue {
    private $settings;
    private $rest_api;
    private $admin;
    private $manager;

    /**
     * Constructor for the WP_Post_Queue class.
     * Loads all of the classes.
     * 
     * @return void
     */
    public function __construct() {
        $this->settings = $this->load_settings();
        $this->rest_api = new REST_API($this->settings);
        $this->admin = new Admin($this->settings);
        $this->manager = new Manager($this->settings);
    }

    /**
     * Initializes the admin and manager classes.
     * 
     * @return void
     */
    public function run() {
        $this->admin->init();
        $this->manager->init();
    }

    /**
     * Loads the settings from the database.
     * 
     * @return array The settings.
     */
    private function load_settings() {
        return array(
            'publishTimes' => get_option('wp_queue_publish_times', 2),
            'startTime' => get_option('wp_queue_start_time', '12 am'),
            'endTime' => get_option('wp_queue_end_time', '1 am'),
            'wpQueuePaused' => get_option('wp_queue_paused', false),
        );
    }
}
