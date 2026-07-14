/**
 * REST API helpers for the Cache Tags for Cloudflare admin app.
 */
import apiFetch from '@wordpress/api-fetch';

const NS = 'cache-tags-for-cloudflare/v1';

export const getSettings = () => apiFetch( { path: `/${ NS }/settings` } );

export const saveSettings = ( data ) =>
	apiFetch( { path: `/${ NS }/settings`, method: 'POST', data } );

export const getGroups = () => apiFetch( { path: `/${ NS }/groups` } );

export const verifyToken = () =>
	apiFetch( { path: `/${ NS }/verify`, method: 'POST' } );

export const purge = ( data ) =>
	apiFetch( { path: `/${ NS }/purge`, method: 'POST', data } );

export const getTerms = ( restBase ) =>
	apiFetch( {
		path: `/wp/v2/${ restBase }?per_page=100&_fields=id,slug,name&orderby=name&order=asc`,
	} );
