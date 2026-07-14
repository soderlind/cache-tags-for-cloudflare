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
		api.verifyToken.mockResolvedValue( { success: true, message: 'ok' } );
	} );

	async function verify() {
		fireEvent.click( await screen.findByRole( 'tab', { name: 'Settings' } ) );
		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Test connection' } )
		);
		await waitFor( () => expect( api.verifyToken ).toHaveBeenCalled() );
		fireEvent.click( await screen.findByRole( 'tab', { name: 'Purge' } ) );
		await waitFor( () =>
			expect(
				screen.getByRole( 'button', { name: 'Purge everything' } )
			).toBeEnabled()
		);
	}

	it( 'shows the purge tab by default with purging locked', async () => {
		render( <App /> );

		expect( await screen.findByText( 'Purge tools' ) ).toBeInTheDocument();
		expect( screen.getByRole( 'tab', { name: 'Settings' } ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Purge everything' } )
		).toBeDisabled();
	} );

	it( 'unlocks and purges everything after verifying', async () => {
		window.confirm = vi.fn( () => true );
		render( <App /> );

		await verify();
		fireEvent.click( screen.getByRole( 'button', { name: 'Purge everything' } ) );

		await waitFor( () =>
			expect( api.purge ).toHaveBeenCalledWith( { mode: 'everything' } )
		);
	} );

	it( 'does not purge when confirmation is cancelled', async () => {
		window.confirm = vi.fn( () => false );
		render( <App /> );

		await verify();
		fireEvent.click( screen.getByRole( 'button', { name: 'Purge everything' } ) );

		expect( api.purge ).not.toHaveBeenCalled();
	} );

	it( 'saves settings and auto-verifies the connection', async () => {
		render( <App /> );

		fireEvent.click( await screen.findByRole( 'tab', { name: 'Settings' } ) );
		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Save settings' } )
		);

		await waitFor( () => expect( api.saveSettings ).toHaveBeenCalledTimes( 1 ) );
		await waitFor( () => expect( api.verifyToken ).toHaveBeenCalledTimes( 1 ) );
	} );

	it( 'tests the connection from the settings tab', async () => {
		render( <App /> );

		fireEvent.click( await screen.findByRole( 'tab', { name: 'Settings' } ) );
		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Test connection' } )
		);

		await waitFor( () => expect( api.verifyToken ).toHaveBeenCalledTimes( 1 ) );
	} );
} );
