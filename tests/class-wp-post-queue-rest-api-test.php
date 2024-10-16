<?php

use WP_Post_Queue\REST_API;

/**
 * Test the REST_API class.
 * Which is responsible for the REST API side of the plugin.
 */
class Test_WP_Post_Queue_REST_API extends WP_UnitTestCase {
    private $rest_api;
    private $settings;

    public function setUp(): void {
        parent::setUp();
        $this->settings = [
            'publishTimes' => 2,
            'startTime' => '12 am',
            'endTime' => '1 am',
            'wpQueuePaused' => false,
        ];
        $this->rest_api = new REST_API($this->settings);
    }

    public function tearDown(): void {
        parent::tearDown();
    }

    /**
     * Test that the get_settings method returns the correct settings from the database.
     */
    public function test_get_settings() {
        $settings = $this->rest_api->get_settings();

        $this->assertIsArray($settings);
        $this->assertArrayHasKey('publishTimes', $settings);
        $this->assertArrayHasKey('startTime', $settings);
        $this->assertArrayHasKey('endTime', $settings);
        $this->assertArrayHasKey('wpQueuePaused', $settings);
    }

    /**
     * Test that the update_settings method updates the settings in the database correctly.
     */
    public function test_update_settings() {
        $request = new WP_REST_Request('POST', '/wp-post-queue/v1/settings');
        $request->set_param('publish_times', 3);
        $request->set_param('start_time', '1 am');
        $request->set_param('end_time', '2 am');
        $request->set_param('wp_queue_paused', true);

        $response = $this->rest_api->update_settings($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $updated_settings = $this->rest_api->get_settings();
        $this->assertEquals(3, $updated_settings['publishTimes']);
        $this->assertEquals('1 am', $updated_settings['startTime']);
        $this->assertEquals('2 am', $updated_settings['endTime']);
        $this->assertTrue($updated_settings['wpQueuePaused']);
    }

    /**
     * Test that the recalculate_publish_times_rest_callback method recalculates the publish times for all posts in the queue correctly.
     */
    public function test_recalculate_publish_times_rest_callback() {
        $post_ids = [
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
        ];

        $request = new WP_REST_Request('POST', '/wp-post-queue/v1/recalculate');
        $request->set_param('order', array_reverse($post_ids));

        $response = $this->rest_api->recalculate_publish_times_rest_callback($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertCount(3, $data);
        $this->assertEquals($post_ids[2], $data[0]['ID']);
    }

    /**
     * Test that the endpoint returns a 200 status code and data when called correctly
     * Note that we check the actual sorting logic in the Manager class, instead of trying to test it twice.
     */
    public function test_shuffle_queue() {
        $post_ids = [
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
        ];

        $response = $this->rest_api->shuffle_queue();

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertCount(3, $data);
    }
}
