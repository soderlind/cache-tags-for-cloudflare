import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';

vi.mock( '../src/api', () => ( {
	getSettings: vi.fn(),
	getGroups: vi.fn(),
	saveSettings: vi.fn(),
	verifyToken: vi.fn(),
	purge: vi.fn(),
	getTerms: vi.fn(),
} ) );

import App from '../src/App';
import * as api from '../src/api';

const settings = {
	header_enabled: true,
	purge_enabled: true,
	debug: false,
	zone_id: '',
	has_token: false,
	token_from_constant: false,
	zone_from_constant: false,
};

const groups = {
	post_types: [ { slug: 'post', label: 'Post' } ],
	taxonomies: [ { slug: 'category', label: 'Category', rest_base: 'categories' } ],
};

describe( 'App', () => {
	beforeEach( () => {
		vi.clearAllMocks();
		api.getSettings.mockResolvedValue( settings );
		api.getGroups.mockResolvedValue( groups );
		api.purge.mockResolvedValue( { success: true, message: 'Purged.' } );
		api.saveSettings.mockResolvedValue( settings );
	} );

	it( 'renders the settings and tools once loaded', async () => {
		render( <App /> );

		expect( await screen.findByText( 'Settings' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Purge tools' ) ).toBeInTheDocument();
	} );

	it( 'purges everything after confirmation', async () => {
		window.confirm = vi.fn( () => true );
		render( <App /> );

		const button = await screen.findByRole( 'button', { name: 'Purge everything' } );
		fireEvent.click( button );

		await waitFor( () =>
			expect( api.purge ).toHaveBeenCalledWith( { mode: 'everything' } )
		);
	} );

	it( 'does not purge when confirmation is cancelled', async () => {
		window.confirm = vi.fn( () => false );
		render( <App /> );

		const button = await screen.findByRole( 'button', { name: 'Purge everything' } );
		fireEvent.click( button );

		expect( api.purge ).not.toHaveBeenCalled();
	} );

	it( 'saves settings', async () => {
		render( <App /> );

		const button = await screen.findByRole( 'button', { name: 'Save settings' } );
		fireEvent.click( button );

		await waitFor( () => expect( api.saveSettings ).toHaveBeenCalledTimes( 1 ) );
	} );
} );
