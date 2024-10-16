import { registerPlugin } from '@wordpress/plugins';
import { useEffect, useCallback, useRef } from '@wordpress/element';
import { withSelect, withDispatch, select } from '@wordpress/data';
import { compose } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';

/**
 * Unfortunately, a status registered via register_post_status in WordPress does not automatically show up in Gutenberg's post status dialog.
 * This file is responsible for adding a custom status option "Queued" to the post status selector in the WordPress editor via DOM manipulation.
 * The QueueEditorPanel component ensures that the "Queued" status is available for selection and handles the logic for updating the post's status accordingly.
 */
const QueueEditorPanel = ({ isQueued, onUpdateQueuedStatus }) => {
    const currentStatusRef = useRef(select('core/editor').getEditedPostAttribute('status'));

    const addQueueStatusOption = useCallback(() => {
        const statusSelect = document.querySelector('.editor-change-status__content');

        if (statusSelect && !statusSelect.querySelector('input[value="queued"]')) {
            const options = statusSelect.querySelectorAll('.components-radio-control__option');
            options.forEach((option) => {
                const input = option.querySelector('input');
                if (input && input.value !== 'queued') {
                    input.addEventListener('click', () => {
                        const queuedInput = statusSelect.querySelector('input[value="queued"]');
                        if (queuedInput) {
                            queuedInput.checked = false;
                            onUpdateQueuedStatus(false, input.value);
                        }
                    });
                }
            });

            const lastOption = options[options.length - 1];

            if (lastOption) {
                const queuedOption = lastOption.cloneNode(true);
                const input = queuedOption.querySelector('input');
                const label = queuedOption.querySelector('label');
                const span = queuedOption.querySelector('span');

                input.id = `inspector-radio-control-0-${options.length}`;
                input.value = 'queued';
                label.setAttribute('for', input.id);
                label.childNodes[0].nodeValue = __('Queued', 'wp-post-queue');
                span.textContent = __('Added to your publishing queue.', 'wp-post-queue');
                input.checked = currentStatusRef.current === 'queued';
                input.removeAttribute('name');

                input.addEventListener('change', (event) => {
                    onUpdateQueuedStatus(event.target.checked);
                });

                lastOption.parentNode.appendChild(queuedOption);

                return () => {
                    input.removeEventListener('change', handleChange);
                };
            }
        }
    }, [onUpdateQueuedStatus]);

    useEffect(() => {
        const bodyObserver = new MutationObserver(() => {
            if (document.querySelector('.editor-change-status__content')) {
                const cleanup = addQueueStatusOption();
                return () => cleanup && cleanup();
            }
        });

        bodyObserver.observe(document.body, { childList: true, subtree: true });

        return () => {
            bodyObserver.disconnect();
        };
    }, [addQueueStatusOption]);

    const updateStatusLabel = useCallback(() => {
        const postStatusButton = document.querySelector('.editor-post-status button');
        const postStatus = select('core/editor').getEditedPostAttribute('status');

        if (!postStatusButton) {
            return;
        }

        const isQueued = postStatus === 'queued';
        const needsUpdate = postStatus !== currentStatusRef.current;

        // Update status label if it has changed or if it's the first load with "queued" status
        if (needsUpdate || (postStatusButton.textContent === '' && currentStatusRef.current === 'queued')) {
            currentStatusRef.current = postStatus;

            if (isQueued) {
                setTimeout(() => {
                    postStatusButton.textContent = __('Queued', 'wp-post-queue');
                }, 100);
            } else {
                postStatusButton.textContent = '';
            }
        }
    }, []);

    useEffect(() => {
        const { subscribe } = wp.data;
        updateStatusLabel();

        const unsubscribe = subscribe(updateStatusLabel);

        return () => unsubscribe();
    }, [updateStatusLabel]);
};

const mapSelectToProps = (select) => ({
    isQueued: select('core/editor').getEditedPostAttribute('status') === 'queued',
});

const mapDispatchToProps = (dispatch) => ({
    onUpdateQueuedStatus: (isQueued, status = 'draft') => {
        dispatch('core/editor').editPost({ status: isQueued ? 'queued' : status });
    },
});

const EnhancedQueuePluginPanel = compose(
    withSelect(mapSelectToProps),
    withDispatch(mapDispatchToProps)
)(QueueEditorPanel);

registerPlugin('wp-post-queue', {
    render: EnhancedQueuePluginPanel,
    icon: 'calendar',
});
