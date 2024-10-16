import apiFetch from '@wordpress/api-fetch';
import { createReduxStore, register } from '@wordpress/data';

/**
 * Redux store for managing the settings of the WP Post Queue plugin.
 * This store handles the state and actions related to publish times, start time, end time, and queue paused status.
 * It also includes a generator function for saving settings via an API call.
 */

const DEFAULT_STATE = {
	publishTimes: wpQueuePluginData.publishTimes,
	startTime: wpQueuePluginData.startTime,
	endTime: wpQueuePluginData.endTime,
	wpQueuePaused: wpQueuePluginData.wpQueuePaused,
};

const actions = {
	setPublishTimes( publishTimes ) {
		return {
			type: 'SET_PUBLISH_TIMES',
			publishTimes,
		};
	},
	setStartTime( startTime ) {
		return {
			type: 'SET_START_TIME',
			startTime,
		};
	},
	setEndTime( endTime ) {
		return {
			type: 'SET_END_TIME',
			endTime,
		};
	},
	setWpQueuePaused( wpQueuePaused ) {
		return {
			type: 'SET_WP_QUEUE_PAUSED',
			wpQueuePaused,
		};
	},
	receiveSettings( settings ) {
		return {
			type: 'RECEIVE_SETTINGS',
			settings,
		};
	},

	*saveSettings( settings ) {
		try {
			const result = yield controls.UPDATE_SETTINGS( settings );
			return result;
		} catch ( error ) {
			throw new Error( 'Failed to update settings' );
		}
	},
};

const reducer = ( state = DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_PUBLISH_TIMES':
			return { ...state, publishTimes: action.publishTimes };
		case 'SET_START_TIME':
			return { ...state, startTime: action.startTime };
		case 'SET_END_TIME':
			return { ...state, endTime: action.endTime };
		case 'RECEIVE_SETTINGS':
			return { ...state, ...action.settings };
		case 'SET_WP_QUEUE_PAUSED':
			return { ...state, wpQueuePaused: action.wpQueuePaused };
		default:
			return state;
	}
};

const selectors = {
	getSettings( state ) {
		return state;
	},
};

const controls = {
	FETCH_SETTINGS() {
		return apiFetch( { path: '/wp-post-queue/v1/settings' } );
	},
	UPDATE_SETTINGS( payload ) {
		return apiFetch( {
			path: '/wp-post-queue/v1/settings',
			method: 'POST',
			data: payload,
		} )
			.then( ( response ) => {
				return response;
			} )
			.catch( ( error ) => {
				console.error( 'API Error:', error );
				throw error;
			} );
	},
};

const resolvers = {
	getSettings:
		() =>
		async ( { dispatch } ) => {
			const settings = await controls.FETCH_SETTINGS();
			dispatch( actions.receiveSettings( settings ) );
		},
};

const store = createReduxStore( 'wp-post-queue/store', {
	reducer,
	actions,
	selectors,
	controls,
	resolvers,
} );

register( store );

export default store;
