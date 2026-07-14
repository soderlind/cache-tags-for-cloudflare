import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock( '@wordpress/api-fetch', () => ( {
	default: vi.fn( () => Promise.resolve( {} ) ),
} ) );

import apiFetch from '@wordpress/api-fetch';
import {
	getSettings,
	saveSettings,
	getGroups,
	verifyToken,
	purge,
	getTerms,
} from '../src/api';

describe( 'api', () => {
	beforeEach( () => {
		apiFetch.mockClear();
	} );

	it( 'getSettings reads the settings route', () => {
		getSettings();
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/cache-tags-for-cloudflare/v1/settings',
		} );
	} );

	it( 'saveSettings posts the settings payload', () => {
		const data = { header_enabled: true };
		saveSettings( data );
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/cache-tags-for-cloudflare/v1/settings',
			method: 'POST',
			data,
		} );
	} );

	it( 'getGroups reads the groups route', () => {
		getGroups();
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/cache-tags-for-cloudflare/v1/groups',
		} );
	} );

	it( 'verifyToken posts to the verify route', () => {
		verifyToken();
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/cache-tags-for-cloudflare/v1/verify',
			method: 'POST',
		} );
	} );

	it( 'purge posts the purge payload', () => {
		const data = { mode: 'everything' };
		purge( data );
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/cache-tags-for-cloudflare/v1/purge',
			method: 'POST',
			data,
		} );
	} );

	it( 'getTerms reads core terms for a rest base', () => {
		getTerms( 'categories' );
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/wp/v2/categories?per_page=100&_fields=id,slug,name&orderby=name&order=asc',
		} );
	} );
} );
