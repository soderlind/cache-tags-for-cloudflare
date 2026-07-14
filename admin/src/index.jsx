/**
 * Entry point: mount the admin app.
 */
import { createRoot } from '@wordpress/element';
import App from './App';
import './style.scss';

const container = document.getElementById( 'cache-tags-for-cloudflare-app' );

if ( container ) {
	createRoot( container ).render( <App /> );
}
