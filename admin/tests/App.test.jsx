import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';

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

const unconfigured = {
	header_enabled: true,
	purge_enabled: true,
	debug: false,
	zone_id: '',
	has_token: false,
	token_from_constant: false,
	zone_from_constant: false,
	is_configured: false,
	verified: false,
	verified_at: '',
};

const configured = {
	...unconfigured,
	zone_id: 'zone123',
	has_token: true,
	is_configured: true,
	verified: true,
	verified_at: '1 January 2026 10:00',
};

const groups = {
	post_types: [ { slug: 'post', label: 'Post' } ],
	taxonomies: [ { slug: 'category', label: 'Category', rest_base: 'categories' } ],
	site_id: '7',
};

describe( 'App', () => {
	beforeEach( () => {
		vi.clearAllMocks();
		api.getSettings.mockResolvedValue( configured );
		api.getGroups.mockResolvedValue( groups );
		api.purge.mockResolvedValue( { success: true, message: 'Purged.' } );
		api.saveSettings.mockResolvedValue( configured );
		api.verifyToken.mockResolvedValue( { success: true, message: 'ok' } );
	} );

	it( 'opens the settings tab and locks purging when not configured', async () => {
		api.getSettings.mockResolvedValue( unconfigured );
		render( <App /> );

		expect(
			await screen.findAllByText( /Not configured yet/ )
		).not.toHaveLength( 0 );

		fireEvent.click( screen.getByRole( 'tab', { name: 'Purge' } ) );

		expect(
			await screen.findByRole( 'button', { name: 'Purge everything' } )
		).toBeDisabled();
	} );

	it( 'opens the purge tab unlocked when credentials are already stored', async () => {
		render( <App /> );

		expect( await screen.findByText( 'Purge tools' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Purge everything' } )
		).toBeEnabled();
	} );

	it( 'previews the site-scoped tags a purge will send', async () => {
		render( <App /> );

		expect( await screen.findByText( 'Will purge tag: b7' ) ).toBeInTheDocument();

		fireEvent.change( screen.getByLabelText( 'Purge a post type' ), {
			target: { value: 'post' },
		} );

		expect(
			await screen.findByText( 'Will purge tag: b7-pt-post' )
		).toBeInTheDocument();
	} );

	it( 'purges everything after confirming in the dialog', async () => {
		render( <App /> );

		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Purge everything' } )
		);

		const dialog = await screen.findByRole( 'dialog' );
		fireEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Purge everything' } )
		);

		await waitFor( () =>
			expect( api.purge ).toHaveBeenCalledWith( { mode: 'everything' } )
		);
	} );

	it( 'does not purge when the dialog is cancelled', async () => {
		render( <App /> );

		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Purge everything' } )
		);

		const dialog = await screen.findByRole( 'dialog' );
		fireEvent.click( within( dialog ).getByRole( 'button', { name: 'Cancel' } ) );

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
		expect(
			await screen.findAllByText( /Last checked 1 January 2026 10:00/ )
		).not.toHaveLength( 0 );

		fireEvent.click( screen.getByRole( 'button', { name: 'Test connection' } ) );

		await waitFor( () => expect( api.verifyToken ).toHaveBeenCalledTimes( 1 ) );
	} );

	it( 'surfaces a failed connection test in the status banner', async () => {
		api.verifyToken.mockResolvedValue( { success: false, message: 'Bad token.' } );
		render( <App /> );

		fireEvent.click( await screen.findByRole( 'tab', { name: 'Settings' } ) );
		fireEvent.click( screen.getByRole( 'button', { name: 'Test connection' } ) );

		expect( await screen.findAllByText( 'Bad token.' ) ).not.toHaveLength( 0 );
	} );
} );
