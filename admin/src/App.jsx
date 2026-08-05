/**
 * Cache Tags for Cloudflare — admin app.
 */
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	TabPanel,
	Card,
	CardHeader,
	CardBody,
	PanelBody,
	ToggleControl,
	TextControl,
	SelectControl,
	Button,
	Notice,
	Modal,
	ExternalLink,
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

const TOKEN_URL = 'https://dash.cloudflare.com/profile/api-tokens';
const SUCCESS_TIMEOUT = 8000;
const MAX_NOTICES = 3;

let noticeId = 0;

export default function App() {
	const [ loading, setLoading ] = useState( true );
	const [ settings, setSettings ] = useState( null );
	const [ groups, setGroups ] = useState( {
		post_types: [],
		taxonomies: [],
		site_id: '1',
	} );
	const [ notices, setNotices ] = useState( [] );
	const timers = useRef( [] );

	const removeNotice = useCallback( ( id ) => {
		setNotices( ( current ) => current.filter( ( n ) => n.id !== id ) );
	}, [] );

	const addNotice = useCallback(
		( status, message ) => {
			const id = ++noticeId;

			setNotices( ( current ) => [
				...current.slice( 1 - MAX_NOTICES ),
				{ id, status, message },
			] );

			if ( 'success' === status ) {
				timers.current.push(
					setTimeout( () => removeNotice( id ), SUCCESS_TIMEOUT )
				);
			}
		},
		[ removeNotice ]
	);

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

	useEffect(
		() => () => timers.current.forEach( ( timer ) => clearTimeout( timer ) ),
		[]
	);

	if ( loading || ! settings ) {
		return (
			<div className="ctcf-app">
				<Spinner />
			</div>
		);
	}

	const tabs = [
		{ name: 'purge', title: __( 'Purge', 'cache-tags-for-cloudflare' ) },
		{ name: 'settings', title: __( 'Settings', 'cache-tags-for-cloudflare' ) },
	];

	return (
		<div className="ctcf-app">
			<h1>{ __( 'Cache Tags for Cloudflare', 'cache-tags-for-cloudflare' ) }</h1>

			<TabPanel
				className="ctcf-tabs"
				tabs={ tabs }
				initialTabName={ settings.is_configured ? 'purge' : 'settings' }
			>
				{ ( tab ) => {
					if ( 'settings' === tab.name ) {
						return (
							<SettingsPanel
								settings={ settings }
								onSettings={ setSettings }
								addNotice={ addNotice }
							/>
						);
					}

					return (
						<PurgePanel
							groups={ groups }
							locked={ ! settings.is_configured }
							addNotice={ addNotice }
						/>
					);
				} }
			</TabPanel>

			<div className="ctcf-notices" aria-live="polite">
				{ notices.map( ( notice ) => (
					<Notice
						key={ notice.id }
						status={ notice.status }
						onRemove={ () => removeNotice( notice.id ) }
					>
						{ notice.message }
					</Notice>
				) ) }
			</div>
		</div>
	);
}

/**
 * Persistent summary of the Cloudflare connection state.
 */
function ConnectionStatus( { settings, error } ) {
	if ( ! settings.is_configured ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ __(
					'Not configured yet. Add an API token and Zone ID below, then save.',
					'cache-tags-for-cloudflare'
				) }
			</Notice>
		);
	}

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}

	if ( ! settings.verified ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ __(
					'Credentials saved but not tested yet. Run “Test connection” to confirm they work.',
					'cache-tags-for-cloudflare'
				) }
			</Notice>
		);
	}

	return (
		<Notice status="success" isDismissible={ false }>
			{ settings.verified_at
				? sprintf(
						/* translators: %s: date and time of the last successful connection test. */
						__(
							'Connected to Cloudflare. Last checked %s.',
							'cache-tags-for-cloudflare'
						),
						settings.verified_at
				  )
				: __( 'Connected to Cloudflare.', 'cache-tags-for-cloudflare' ) }
		</Notice>
	);
}

function SettingsPanel( { settings, onSettings, addNotice } ) {
	const [ headerEnabled, setHeaderEnabled ] = useState( settings.header_enabled );
	const [ purgeEnabled, setPurgeEnabled ] = useState( settings.purge_enabled );
	const [ debug, setDebug ] = useState( settings.debug );
	const [ token, setToken ] = useState( '' );
	const [ zone, setZone ] = useState( settings.zone_id );
	const [ saving, setSaving ] = useState( false );
	const [ testing, setTesting ] = useState( false );
	const [ error, setError ] = useState( '' );

	const runVerify = () => {
		setTesting( true );

		return verifyToken()
			.then( ( result ) => {
				setError( result.success ? '' : result.message );
				addNotice( result.success ? 'success' : 'error', result.message );

				// Refresh so the status banner picks up the new verification stamp.
				return getSettings().then( onSettings );
			} )
			.catch( () => {
				const message = __(
					'Connection test failed.',
					'cache-tags-for-cloudflare'
				);
				setError( message );
				addNotice( 'error', message );
			} )
			.finally( () => setTesting( false ) );
	};

	const save = () => {
		setSaving( true );

		return saveSettings( {
			header_enabled: headerEnabled,
			purge_enabled: purgeEnabled,
			debug,
			zone_id: zone,
			api_token: token,
		} )
			.then( ( updated ) => {
				onSettings( updated );
				setToken( '' );
				addNotice(
					'success',
					__( 'Settings saved.', 'cache-tags-for-cloudflare' )
				);

				return updated.is_configured ? runVerify() : undefined;
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
				<ConnectionStatus settings={ settings } error={ error } />

				<div className="ctcf-group">
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
				</div>

				<div className="ctcf-group">
					<div className="ctcf-field">
						<TextControl
							__nextHasNoMarginBottom
							type="password"
							autoComplete="off"
							label={ __( 'API token', 'cache-tags-for-cloudflare' ) }
							help={
								settings.token_from_constant ? (
									__(
										'Defined in wp-config.php (CACHE_TAGS_CF_API_TOKEN).',
										'cache-tags-for-cloudflare'
									)
								) : (
									<>
										{ __(
											'Needs the Zone → Cache Purge permission for this zone. Leave blank to keep the current token.',
											'cache-tags-for-cloudflare'
										) }{ ' ' }
										<ExternalLink href={ TOKEN_URL }>
											{ __( 'Create a token', 'cache-tags-for-cloudflare' ) }
										</ExternalLink>
									</>
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
					</div>

					<div className="ctcf-field">
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Zone ID', 'cache-tags-for-cloudflare' ) }
							help={
								settings.zone_from_constant
									? __(
											'Defined in wp-config.php (CACHE_TAGS_CF_ZONE_ID).',
											'cache-tags-for-cloudflare'
									  )
									: __(
											'Cloudflare dashboard → your domain → Overview → API (right-hand column).',
											'cache-tags-for-cloudflare'
									  )
							}
							value={ zone }
							disabled={ settings.zone_from_constant }
							onChange={ setZone }
						/>
					</div>
				</div>

				<div className="ctcf-actions">
					<Button
						variant="primary"
						onClick={ save }
						isBusy={ saving }
						disabled={ saving || testing }
					>
						{ __( 'Save settings', 'cache-tags-for-cloudflare' ) }
					</Button>
					<Button
						variant="secondary"
						onClick={ runVerify }
						isBusy={ testing }
						disabled={ saving || testing || ! settings.is_configured }
					>
						{ __( 'Test connection', 'cache-tags-for-cloudflare' ) }
					</Button>
				</div>

				<PanelBody
					title={ __( 'Advanced', 'cache-tags-for-cloudflare' ) }
					initialOpen={ false }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Debug logging', 'cache-tags-for-cloudflare' ) }
						help={ __(
							'Log purge activity and errors to the PHP error log (requires WP_DEBUG_LOG).',
							'cache-tags-for-cloudflare'
						) }
						checked={ debug }
						onChange={ setDebug }
					/>
				</PanelBody>
			</CardBody>
		</Card>
	);
}

/**
 * Shows the cache tag a purge action will send, so the selection is unambiguous.
 */
function TagPreview( { tag } ) {
	if ( ! tag ) {
		return null;
	}

	return (
		<p className="ctcf-preview">
			{ sprintf(
				/* translators: %s: cache tag that will be purged. */
				__( 'Will purge tag: %s', 'cache-tags-for-cloudflare' ),
				tag
			) }
		</p>
	);
}

function PurgePanel( { groups, locked, addNotice } ) {
	const [ busy, setBusy ] = useState( '' );
	const [ postType, setPostType ] = useState( '' );
	const [ taxonomy, setTaxonomy ] = useState( '' );
	const [ terms, setTerms ] = useState( [] );
	const [ termSlug, setTermSlug ] = useState( '' );
	const [ rawTags, setRawTags ] = useState( '' );
	const [ confirming, setConfirming ] = useState( false );

	const siteId = groups.site_id || '1';

	const run = ( action, data ) => {
		setBusy( action );

		return purge( data )
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

	const selectedTaxonomy = groups.taxonomies.find( ( t ) => t.slug === taxonomy );

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

	const confirmPurgeEverything = () => {
		setConfirming( false );
		run( 'everything', { mode: 'everything' } );
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

	const selectedTerm = terms.find( ( t ) => t.slug === termSlug );

	return (
		<Card>
			<CardHeader>
				<h2>{ __( 'Purge tools', 'cache-tags-for-cloudflare' ) }</h2>
			</CardHeader>
			<CardBody>
				{ locked && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Add and save valid Cloudflare credentials on the Settings tab to unlock purging.',
							'cache-tags-for-cloudflare'
						) }
					</Notice>
				) }

				<div className="ctcf-group">
					<div className="ctcf-actions">
						<Button
							isDestructive
							variant="secondary"
							onClick={ () => setConfirming( true ) }
							isBusy={ 'everything' === busy }
							disabled={ '' !== busy || locked }
						>
							{ __( 'Purge everything', 'cache-tags-for-cloudflare' ) }
						</Button>
					</div>
					<TagPreview tag={ `b${ siteId }` } />
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
						label={ __( 'Purge post type', 'cache-tags-for-cloudflare' ) }
						showTooltip
						disabled={ '' === postType || '' !== busy || locked }
						isBusy={ 'post_type' === busy }
						onClick={ () => run( 'post_type', { mode: 'post_type', post_type: postType } ) }
					>
						{ __( 'Purge', 'cache-tags-for-cloudflare' ) }
					</Button>
				</div>
				<TagPreview tag={ postType ? `b${ siteId }-pt-${ postType }` : '' } />

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
						label={ __( 'Purge taxonomy term', 'cache-tags-for-cloudflare' ) }
						showTooltip
						disabled={ '' === taxonomy || '' === termSlug || '' !== busy || locked }
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
				{ selectedTerm ? (
					<TagPreview tag={ `b${ siteId }-t${ selectedTerm.id }` } />
				) : (
					selectedTaxonomy &&
					! selectedTaxonomy.rest_base && (
						<p className="ctcf-help">
							{ __(
								'This taxonomy is not exposed to the REST API, so terms cannot be listed. Enter the term slug manually.',
								'cache-tags-for-cloudflare'
							) }
						</p>
					)
				) }

				<PanelBody
					title={ __( 'Advanced', 'cache-tags-for-cloudflare' ) }
					initialOpen={ false }
				>
					<div className="ctcf-row">
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Purge raw tags', 'cache-tags-for-cloudflare' ) }
							help={ __(
								'Comma-separated, e.g. b1-p42, b1-t5',
								'cache-tags-for-cloudflare'
							) }
							value={ rawTags }
							onChange={ setRawTags }
						/>
						<Button
							variant="secondary"
							label={ __( 'Purge raw tags', 'cache-tags-for-cloudflare' ) }
							showTooltip
							disabled={ '' === rawTags.trim() || '' !== busy || locked }
							isBusy={ 'tags' === busy }
							onClick={ () => run( 'tags', { mode: 'tags', tags: rawTags } ) }
						>
							{ __( 'Purge', 'cache-tags-for-cloudflare' ) }
						</Button>
					</div>
				</PanelBody>

				{ confirming && (
					<Modal
						title={ __( 'Purge everything?', 'cache-tags-for-cloudflare' ) }
						onRequestClose={ () => setConfirming( false ) }
						size="small"
					>
						<p>
							{ sprintf(
								/* translators: %s: cache tag that will be purged. */
								__(
									'This purges every Cloudflare object tagged by this site (tag %s). Other sites in the zone and untagged assets are not affected.',
									'cache-tags-for-cloudflare'
								),
								`b${ siteId }`
							) }
						</p>
						<div className="ctcf-actions">
							<Button
								isDestructive
								variant="primary"
								onClick={ confirmPurgeEverything }
							>
								{ __( 'Purge everything', 'cache-tags-for-cloudflare' ) }
							</Button>
							<Button variant="tertiary" onClick={ () => setConfirming( false ) }>
								{ __( 'Cancel', 'cache-tags-for-cloudflare' ) }
							</Button>
						</div>
					</Modal>
				) }
			</CardBody>
		</Card>
	);
}
