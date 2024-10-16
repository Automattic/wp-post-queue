<?php
/*
 * Plugin Name: WordPress Post Queue
 * Description: A plugin to add a Tumblr-like queue feature for WordPress posts.
 * Version: 1.0
 * Author: Automattic
 * Text Domain: wp-post-queue
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once plugin_dir_path(__FILE__) . 'includes/class-wp-post-queue.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-wp-post-queue-rest-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-wp-post-queue-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-wp-post-queue-manager.php';

use WP_Post_Queue\WP_Post_Queue;

$plugin = new WP_Post_Queue();
$plugin->run();
