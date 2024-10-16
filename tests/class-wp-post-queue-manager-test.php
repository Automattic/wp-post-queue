<?php

use WP_Post_Queue\Manager;

class MockPost {
    public $ID;
    public $post_date;
}

/**
 * Test the Manager class.
 * Which is responsible for the queueing and publishing logic.
 */
class Test_WP_Post_Queue_Manager extends WP_UnitTestCase {
    private $manager;
    private $settings;

    public function setUp(): void {
        parent::setUp();
        $this->settings = [
            'publishTimes' => 2,
            'startTime' => '12 am',
            'endTime' => '1 am',
            'wpQueuePaused' => false,
        ];

        $this->manager = $this->getMockBuilder(Manager::class)
            ->setConstructorArgs([$this->settings])
            ->onlyMethods(['get_current_time'])
            ->getMock();
    }

    public function tearDown(): void {
        parent::tearDown();
    }

    /**
     * Test that handle_post_status_change queues a post when transitioning to queued status.
     */
    public function test_handle_post_status_change_queues_post() {
        $post_id = $this->factory->post->create(['post_status' => 'draft']);
        
        wp_update_post([
            'ID' => $post_id,
            'post_status' => 'queued'
        ]);

        do_action('transition_post_status', 'queued', 'draft', get_post($post_id));

        $next_scheduled = wp_next_scheduled('publish_queued_post', array($post_id));
        $this->assertNotFalse($next_scheduled, 'The post should be scheduled for publishing.');
    }

    /**
     * Data provider for test_calculate_next_publish_time.
     * We want to test a variety of scenarios to ensure the publish times are calculated correctly.
     */
    public function publishTimeProvider() {
        return [
            'single post scheduled for next day' => [
                'settings' => ['publishTimes' => 1, 'startTime' => '12:00 AM', 'endTime' => '1:00 AM'],
                'expected_times' => [
                    strtotime('tomorrow 00:30:00')
                ],
                'queued_posts' => [1]
            ],
            'two posts evenly distributed' => [
                'settings' => ['publishTimes' => 2, 'startTime' => '12:00 AM', 'endTime' => '1:00 AM'],
                'expected_times' => [
                    strtotime('tomorrow 00:20:00'),
                    strtotime('tomorrow 00:40:00')
                ],
                'queued_posts' => [1, 2]
            ],
            'three posts evenly distributed' => [
                'settings' => ['publishTimes' => 3, 'startTime' => '12:00 AM', 'endTime' => '1:00 AM'],
                'expected_times' => [
                    strtotime('tomorrow 00:15:00'),
                    strtotime('tomorrow 00:30:00'),
                    strtotime('tomorrow 00:45:00')
                ],
                'queued_posts' => [1, 2, 3]
            ],
            'four posts evenly distributed' => [
                'settings' => ['publishTimes' => 4, 'startTime' => '12:00 AM', 'endTime' => '1:00 AM'],
                'expected_times' => [
                    strtotime('tomorrow 00:12:00'),
                    strtotime('tomorrow 00:24:00'),
                    strtotime('tomorrow 00:36:00'),
                    strtotime('tomorrow 00:48:00')
                ],
                'queued_posts' => [1, 2, 3, 4]
            ],
            'wrapping to next day' => [
                'settings' => ['publishTimes' => 4, 'startTime' => '9:00 AM', 'endTime' => '5:00 PM'],
                'expected_times' => [
                    strtotime('tomorrow 10:36:00'),
                    strtotime('tomorrow 12:12:00'),
                    strtotime('tomorrow 13:48:00'),
                    strtotime('tomorrow 15:24:00')
                ],
                'queued_posts' => [1, 2, 3, 4]
            ],
            'open slots available today' => [
                'settings' => ['publishTimes' => 3, 'startTime' => '12:00 PM', 'endTime' => '6:00 PM'],
                'expected_times' => [
                    strtotime('today 13:30:00'),
                    strtotime('today 15:00:00'),
                    strtotime('today 16:30:00'),
                ],
                'queued_posts' => [1, 2, 3],
                'current_time' => strtotime('today 01:00:00'),
            ],
            'queue post at start of day' => [
                'settings' => ['publishTimes' => 2, 'startTime' => '12:00 AM', 'endTime' => '1:00 AM'],
                'expected_times' => [
                    strtotime('today 00:20:00')
                ],
                'queued_posts' => [1],
                'current_time' => strtotime('today 00:00:00')
            ],
            'overflow to next day' => [
                'settings' => ['publishTimes' => 3, 'startTime' => '12:00 PM', 'endTime' => '6:00 PM'],
                'expected_times' => [
                    strtotime('today 13:30:00'),
                    strtotime('today 15:00:00'),
                    strtotime('today 16:30:00'),
                    strtotime('tomorrow 13:30:00'),
                    strtotime('tomorrow 15:00:00'),
                ],
                'queued_posts' => [1, 2, 3, 4, 5],
                'current_time' => strtotime('today 00:00:00'),
            ],
            'midday with open slots' => [
                'settings' => ['publishTimes' => 3, 'startTime' => '9:00 AM', 'endTime' => '5:00 PM'],
                'expected_times' => [
                    strtotime('today 13:00:00'),
                    strtotime('today 15:00:00'),
                    strtotime('tomorrow 11:00:00'),
                ],
                'queued_posts' => [1, 2, 3],
                'current_time' => strtotime('today 12:00:00'),
            ],
            'midday with no open slots' => [
                'settings' => ['publishTimes' => 3, 'startTime' => '9:00 AM', 'endTime' => '5:00 PM'],
                'expected_times' => [
                    strtotime('tomorrow 11:00:00'),
                    strtotime('tomorrow 13:00:00'),
                    strtotime('tomorrow 15:00:00'),
                ],
                'queued_posts' => [1, 2, 3],
                'current_time' => strtotime('today 18:00:00'),
            ],
            'late day wrapping to next day' => [
                'settings' => ['publishTimes' => 4, 'startTime' => '9:00 AM', 'endTime' => '5:00 PM'],
                'expected_times' => [
                    strtotime('tomorrow 10:36:00'),
                    strtotime('tomorrow 12:12:00'),
                    strtotime('tomorrow 13:48:00'),
                    strtotime('tomorrow 15:24:00'),
                ],
                'queued_posts' => [1, 2, 3, 4],
                'current_time' => strtotime('today 16:00:00'),
            ],
        ];
    }

    /**
     * @dataProvider publishTimeProvider
     * 
     * Tests that the calculate_next_publish_time method calculates the next publish time for a post in the queue
     * and is the core logic for the queue and plugin.
     */
    public function test_calculate_next_publish_time($settings, $expected_times, $queued_posts, $current_time = null) {
        $this->manager = $this->getMockBuilder(Manager::class)
            ->setConstructorArgs([$settings])
            ->onlyMethods(['get_current_time'])
            ->getMock();

        $current_time = $current_time ?? strtotime('today 23:59:59');
        $this->manager->method('get_current_time')
            ->willReturn($current_time);

        $calculated_times = [];
        $post_mocks = [];

        // Create a mock for each post
        foreach ($queued_posts as $post_id) {
            $post_mock = new MockPost();
            $post_mock->ID = $post_id;
            $post_mocks[$post_id] = $post_mock;
        }

        foreach ($queued_posts as $index => $post_id) {
            $last_publish_time = strtotime($settings['startTime']);
            if ($index > 0) {
                $previous_post = $post_mocks[$queued_posts[$index - 1]];
                if ($previous_post) {
                    $last_publish_time = strtotime($previous_post->post_date);
                }
            }

            $next_publish_time = $this->invokeMethod($this->manager, 'calculate_next_publish_time', [$index, $last_publish_time]);
            $post_mocks[$post_id]->post_date = date('Y-m-d H:i:s', $next_publish_time);

            $calculated_times[] = $next_publish_time;
            $this->assertEquals($expected_times[$index], $next_publish_time, "The post should be scheduled correctly for index $index.");
        }
    }

    /**
     * Test that getting the current order of posts in the queue works correctly.
     */
    public function test_get_current_order() {
        $post_ids = [
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
        ];

        // Sort the expected post IDs by their creation date
        usort($post_ids, function($a, $b) {
            return get_post($a)->post_date <=> get_post($b)->post_date;
        });

        $current_order = $this->manager->get_current_order();

        // Extract only the IDs from the current order
        $current_order_ids = array_column($current_order, 'ID');

        $this->assertIsArray($current_order_ids);
        $this->assertCount(3, $current_order_ids);
        $this->assertEquals($post_ids, $current_order_ids);
    }

    /**
     * Test that recalculating the publish times for a new order works correctly.
     */
    public function test_recalculate_publish_times() {
        $post_ids = [
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
        ];
        
        $new_order = array_reverse($post_ids);
        $updated_posts = $this->manager->recalculate_publish_times($new_order);
        
        $this->assertIsArray($updated_posts);
        $this->assertCount(3, $updated_posts);
        $this->assertEquals($new_order[0], $updated_posts[0]['ID']);
    }

    /**
     * Test that shuffling the queue changes the order of the posts.
     */
    public function test_shuffle_queued_posts() {
        $post_ids = [
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
        ];

        $original_order = $post_ids;
        $shuffled = false;
        $attempts = 10;

        // Attempt to shuffle multiple times to ensure a change in order
        // Otherwise this test can fail randomly due to the order being the same sometimes for a small number of posts
        for ($i = 0; $i < $attempts; $i++) {
            $shuffled_posts = $this->manager->shuffle_queued_posts();
            $shuffled_order = wp_list_pluck($shuffled_posts, 'ID');

            if ($original_order !== $shuffled_order) {
                $shuffled = true;
                break;
            }
        }

        $this->assertTrue($shuffled, 'The post order should be shuffled.');
    }

    /**
     * Test recalculation of publish times when a post is removed from the queue.
     */
    public function test_recalculate_publish_times_on_post_removal() {
        // Create queued posts
        $post_ids = [
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
        ];

        // Simulate removing the second post from the queue
        wp_update_post(['ID' => $post_ids[1], 'post_status' => 'draft']);
        do_action('transition_post_status', 'draft', 'queued', get_post($post_ids[1]));

        // Fetch the updated queue
        $updated_queue = $this->manager->get_current_order();

        // Assert that the queue has been recalculated
        $this->assertCount(2, $updated_queue, 'There should be 2 posts remaining in the queue.');

        // Check that the publish times have been recalculated
        $first_post_time = strtotime($updated_queue[0]['post_date']);
        $second_post_time = strtotime($updated_queue[1]['post_date']);
        $this->assertLessThan($second_post_time, $first_post_time, 'The first post should be scheduled before the second post.');
    }

    /**
     * Test that pausing the queue removes all scheduled events for queued posts.
     */
    public function test_pause_queue() {
        $post_ids = [
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
        ];

        $this->manager->pause_queue();

        foreach ($post_ids as $post_id) {
            $next_scheduled = wp_next_scheduled('publish_queued_post', array($post_id));
            $this->assertFalse($next_scheduled, 'The post should not be scheduled for publishing when paused.');
        }
    }

    public function test_resume_queue() {
        $post_ids = [
            $this->factory->post->create(['post_status' => 'queued']),
            $this->factory->post->create(['post_status' => 'queued']),
        ];

        $this->manager->pause_queue();
        $this->manager->resume_queue();

        foreach ($post_ids as $post_id) {
            $next_scheduled = wp_next_scheduled('publish_queued_post', array($post_id));
            $this->assertNotFalse($next_scheduled, 'The post should be rescheduled for publishing when resumed.');
        }
    }

    /**
     * Helper method to invoke private or protected methods.
     */
    protected function invokeMethod(&$object, $methodName, array $parameters = array()) {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
