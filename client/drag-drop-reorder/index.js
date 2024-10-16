import Sortable from 'sortablejs';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { updateTable } from '../utils';
import './index.css'


/**
 * This file handles the drag-and-drop reordering functionality for the WP Post Queue plugin.
 * It uses the SortableJS library to enable drag-and-drop sorting of posts in the queue.
 * When the order of posts is changed, it sends the new order to the server to recalculate publish times and update the table without reloading the page.
 */

document.addEventListener('DOMContentLoaded', function() {
    const list = document.getElementById('the-list');
    if (list) {
        Sortable.create(list, {
            animation: 150,
            onEnd: function(evt) {
                const order = Array.from(list.children).map(item => item.id);
                recalculatePublishTimes(order);
            }
        });
    }
});

function recalculatePublishTimes(newOrder) {
    apiFetch({
        path: '/wp-post-queue/v1/recalculate',
        method: 'POST',
        data: { order: newOrder },
    })
    .then(response => {
        updateTable(response);
    })
    .catch(error => {
        console.error('Error recalculating publish times:', error);
    });
}
