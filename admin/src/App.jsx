/**
 * Cache Tags for Cloudflare — admin app.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	ToggleControl,
	TextControl,
	SelectControl,
	Button,
	Notice,
	Spinner,
} from '@wordpress/components';
import {
	getSettings,
	saveSettings,
	getGroups,
	verifyToken,
	purge,
	getTerms,
} from './api';

let noticeId = 0;

export default function App() {
	const [ loading, setLoading ] = useState( true );
	const [ settings, setSettings ] = useState( null );
	const [ groups, setGroups ] = useState( { post_types: [], taxonomies: [] } );
	const [ notices, setNotices ] = useState( [] );

	const addNotice = useCallback( ( status, message ) => {
		const id = ++noticeId;
		setNotices( ( current ) => [ ...current, { id, status, message } ] );
	}, [] );

	const removeNotice = useCallback( ( id ) => {
		setNotices( ( current ) => current.filter( ( n ) => n.id !== id ) );
	}, [] );

	useEffect( () => {
		Promise.all( [ getSettings(), getGroups() ] )
			.then( ( [ settingsData, groupsData ] ) => {
				setSettings( settingsData );
				setGroups( groupsData );
			} )
			.catch( () =>
				addNotice(
					'error',
					__( 'Could not load settings.', 'cache-tags-for-cloudflare' )
				)
			)
			.finally( () => setLoading( false ) );
	}, [ addNotice ] );

	if ( loading ) {
		return (
			<div className="ctcf-app">
				<Spinner />
			</div>
		);
	}

	return (
		<div className="ctcf-app">
			<h1>{ __( 'Cache Tags for Cloudflare', 'cache-tags-for-cloudflare' ) }</h1>
			<p>
				{ __(
					'Cache-Tag headers and purge-by-tag work on all Cloudflare plans; purge API rate limits scale with your plan.',
					'cache-tags-for-cloudflare'
				) }
			</p>

			{ notices.map( ( notice ) => (
				<Notice
					key={ notice.id }
					status={ notice.status }
					onRemove={ () => removeNotice( notice.id ) }
				>
					{ notice.message }
				</Notice>
			) ) }

			<SettingsCard
				settings={ settings }
				onSaved={ setSettings }
				addNotice={ addNotice }
			/>

			<ToolsCard groups={ groups } addNotice={ addNotice } />
		</div>
	);
}

function SettingsCard( { settings, onSaved, addNotice } ) {
	const [ headerEnabled, setHeaderEnabled ] = useState( settings.header_enabled );
	const [ purgeEnabled, setPurgeEnabled ] = useState( settings.purge_enabled );
	const [ debug, setDebug ] = useState( settings.debug );
	const [ token, setToken ] = useState( '' );
	const [ zone, setZone ] = useState( settings.zone_id );
	const [ saving, setSaving ] = useState( false );

	const save = () => {
		setSaving( true );

		saveSettings( {
			header_enabled: headerEnabled,
			purge_enabled: purgeEnabled,
			debug,
			zone_id: zone,
			api_token: token,
		} )
			.then( ( updated ) => {
				onSaved( updated );
				setToken( '' );
				addNotice(
					'success',
					__( 'Settings saved.', 'cache-tags-for-cloudflare' )
				);
			} )
			.catch( () =>
				addNotice(
					'error',
					__( 'Could not save settings.', 'cache-tags-for-cloudflare' )
				)
			)
			.finally( () => setSaving( false ) );
	};

	return (
		<Card>
			<CardHeader>
				<h2>{ __( 'Settings', 'cache-tags-for-cloudflare' ) }</h2>
			</CardHeader>
			<CardBody>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Emit Cache-Tag headers', 'cache-tags-for-cloudflare' ) }
					help={ __(
						'Add Cache-Tag headers to singular content.',
						'cache-tags-for-cloudflare'
					) }
					checked={ headerEnabled }
					onChange={ setHeaderEnabled }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Auto-purge on changes', 'cache-tags-for-cloudflare' ) }
					help={ __(
						'Purge Cloudflare when content changes.',
						'cache-tags-for-cloudflare'
					) }
					checked={ purgeEnabled }
					onChange={ setPurgeEnabled }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Debug logging', 'cache-tags-for-cloudflare' ) }
					help={ __(
						'Log purge activity and errors to the PHP error log.',
						'cache-tags-for-cloudflare'
					) }
					checked={ debug }
					onChange={ setDebug }
				/>

				<br />

				<TextControl
					__nextHasNoMarginBottom
					type="password"
					autoComplete="off"
					label={ __( 'API token', 'cache-tags-for-cloudflare' ) }
					help={
						settings.token_from_constant
							? __(
									'Defined in wp-config.php (CACHE_TAGS_CF_API_TOKEN).',
									'cache-tags-for-cloudflare'
							  )
							: __(
									'A scoped token with the Zone → Cache Purge permission. Leave blank to keep the current token.',
									'cache-tags-for-cloudflare'
							  )
					}
					placeholder={
						settings.has_token
							? '••••••••••••••••'
							: __( 'Enter token', 'cache-tags-for-cloudflare' )
					}
					value={ token }
					disabled={ settings.token_from_constant }
					onChange={ setToken }
				/>

				<br />

				<TextControl
					__nextHasNoMarginBottom
					label={ __( 'Zone ID', 'cache-tags-for-cloudflare' ) }
					help={
						settings.zone_from_constant
							? __(
									'Defined in wp-config.php (CACHE_TAGS_CF_ZONE_ID).',
									'cache-tags-for-cloudflare'
							  )
							: ''
					}
					value={ zone }
					disabled={ settings.zone_from_constant }
					onChange={ setZone }
				/>

				<br />

				<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
					{ __( 'Save settings', 'cache-tags-for-cloudflare' ) }
				</Button>
			</CardBody>
		</Card>
	);
}

function ToolsCard( { groups, addNotice } ) {
	const [ busy, setBusy ] = useState( '' );
	const [ postType, setPostType ] = useState( '' );
	const [ taxonomy, setTaxonomy ] = useState( '' );
	const [ terms, setTerms ] = useState( [] );
	const [ termSlug, setTermSlug ] = useState( '' );
	const [ rawTags, setRawTags ] = useState( '' );

	const run = ( action, data ) => {
		setBusy( action );

		purge( data )
			.then( ( result ) =>
				addNotice( result.success ? 'success' : 'error', result.message )
			)
			.catch( () =>
				addNotice(
					'error',
					__( 'Purge request failed.', 'cache-tags-for-cloudflare' )
				)
			)
			.finally( () => setBusy( '' ) );
	};

	const onTaxonomyChange = ( value ) => {
		setTaxonomy( value );
		setTermSlug( '' );
		setTerms( [] );

		const selected = groups.taxonomies.find( ( t ) => t.slug === value );

		if ( selected && selected.rest_base ) {
			getTerms( selected.rest_base )
				.then( ( fetched ) => setTerms( fetched ) )
				.catch( () => setTerms( [] ) );
		}
	};

	const testConnection = () => {
		setBusy( 'verify' );

		verifyToken()
			.then( ( result ) =>
				addNotice( result.success ? 'success' : 'error', result.message )
			)
			.catch( () =>
				addNotice(
					'error',
					__( 'Connection test failed.', 'cache-tags-for-cloudflare' )
				)
			)
			.finally( () => setBusy( '' ) );
	};

	const purgeEverything = () => {
		// eslint-disable-next-line no-alert
		if (
			window.confirm(
				__(
					'Purge all tagged content from Cloudflare?',
					'cache-tags-for-cloudflare'
				)
			)
		) {
			run( 'everything', { mode: 'everything' } );
		}
	};

	const postTypeOptions = [
		{ label: __( '— Select post type —', 'cache-tags-for-cloudflare' ), value: '' },
		...groups.post_types.map( ( t ) => ( { label: t.label, value: t.slug } ) ),
	];

	const taxonomyOptions = [
		{ label: __( '— Select taxonomy —', 'cache-tags-for-cloudflare' ), value: '' },
		...groups.taxonomies.map( ( t ) => ( { label: t.label, value: t.slug } ) ),
	];

	const termOptions = [
		{ label: __( '— Select term —', 'cache-tags-for-cloudflare' ), value: '' },
		...terms.map( ( t ) => ( { label: t.name, value: t.slug } ) ),
	];

	return (
		<Card>
			<CardHeader>
				<h2>{ __( 'Purge tools', 'cache-tags-for-cloudflare' ) }</h2>
			</CardHeader>
			<CardBody>
				<div className="ctcf-actions" style={ { marginBottom: '20px' } }>
					<Button
						variant="secondary"
						onClick={ testConnection }
						isBusy={ 'verify' === busy }
						disabled={ '' !== busy }
					>
						{ __( 'Test connection', 'cache-tags-for-cloudflare' ) }
					</Button>
					<Button
						isDestructive
						variant="secondary"
						onClick={ purgeEverything }
						isBusy={ 'everything' === busy }
						disabled={ '' !== busy }
					>
						{ __( 'Purge everything', 'cache-tags-for-cloudflare' ) }
					</Button>
				</div>

				<div className="ctcf-row">
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Purge a post type', 'cache-tags-for-cloudflare' ) }
						value={ postType }
						options={ postTypeOptions }
						onChange={ setPostType }
					/>
					<Button
						variant="secondary"
						disabled={ '' === postType || '' !== busy }
						isBusy={ 'post_type' === busy }
						onClick={ () => run( 'post_type', { mode: 'post_type', post_type: postType } ) }
					>
						{ __( 'Purge', 'cache-tags-for-cloudflare' ) }
					</Button>
				</div>

				<div className="ctcf-row">
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Purge a taxonomy term', 'cache-tags-for-cloudflare' ) }
						value={ taxonomy }
						options={ taxonomyOptions }
						onChange={ onTaxonomyChange }
					/>
					{ terms.length > 0 ? (
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Term', 'cache-tags-for-cloudflare' ) }
							value={ termSlug }
							options={ termOptions }
							onChange={ setTermSlug }
						/>
					) : (
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Term slug', 'cache-tags-for-cloudflare' ) }
							value={ termSlug }
							disabled={ '' === taxonomy }
							onChange={ setTermSlug }
						/>
					) }
					<Button
						variant="secondary"
						disabled={ '' === taxonomy || '' === termSlug || '' !== busy }
						isBusy={ 'taxonomy' === busy }
						onClick={ () =>
							run( 'taxonomy', {
								mode: 'taxonomy',
								taxonomy,
								terms: [ termSlug ],
							} )
						}
					>
						{ __( 'Purge', 'cache-tags-for-cloudflare' ) }
					</Button>
				</div>

				<div className="ctcf-row">
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Purge raw tags', 'cache-tags-for-cloudflare' ) }
						help={ __(
							'Comma-separated, e.g. post-id-42, category-news',
							'cache-tags-for-cloudflare'
						) }
						value={ rawTags }
						onChange={ setRawTags }
					/>
					<Button
						variant="secondary"
						disabled={ '' === rawTags.trim() || '' !== busy }
						isBusy={ 'tags' === busy }
						onClick={ () => run( 'tags', { mode: 'tags', tags: rawTags } ) }
					>
						{ __( 'Purge', 'cache-tags-for-cloudflare' ) }
					</Button>
				</div>
			</CardBody>
		</Card>
	);
}
