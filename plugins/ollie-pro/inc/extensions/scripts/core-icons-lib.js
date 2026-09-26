/**
 * Transforms for exporting the Ollie icon set to the WP 7.1 core icon
 * registry (wp_register_icon).
 *
 * Core sanitizes every registered icon through wp_kses with a strict
 * allowlist — only svg, path, and polygon elements survive, and `fill`
 * is allowed on path/polygon but not on the svg root. These helpers
 * produce markup that passes through that sanitizer unchanged, and
 * detect icons that cannot (gradients, rects, groups), so the generator
 * can leave those on the Icon Block integration only.
 */

// Elements core's icon kses allowlist accepts.
const ALLOWED_ELEMENTS = new Set( [ 'svg', 'path', 'polygon' ] );

// wp_register_icon()'s slug pattern for the icon name.
const CORE_NAME_PATTERN = /^[a-z0-9](?:[a-z0-9_-]*[a-z0-9])?$/;

// Icon Block library prefixes → core collection routing. Categories not
// listed land in the main collection.
const CATEGORY_COLLECTIONS = {
	'ecommerce-payments': 'ollie-payments',
	'ecommerce-badges': 'ollie-badges',
};
const DEFAULT_COLLECTION = 'ollie';

// Trim float noise from computed path coordinates.
const fmt = ( n ) => String( parseFloat( n.toFixed( 4 ) ) );

/**
 * Blend two #RRGGBB/#RGB colors 50/50.
 *
 * @param {string} a First color.
 * @param {string} b Second color.
 * @return {?string} Blended lowercase hex, or null on unparsable input.
 */
function blendHex( a, b ) {
	const expand = ( hex ) => {
		const m = hex.match( /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/ );
		if ( ! m ) {
			return null;
		}
		const raw =
			m[ 1 ].length === 3
				? m[ 1 ].replace( /./g, '$&$&' )
				: m[ 1 ];
		return [ 0, 2, 4 ].map( ( i ) => parseInt( raw.slice( i, i + 2 ), 16 ) );
	};
	const ca = expand( a );
	const cb = expand( b );
	if ( ! ca || ! cb ) {
		return null;
	}
	return (
		'#' +
		ca
			.map( ( v, i ) =>
				Math.round( ( v + cb[ i ] ) / 2 )
					.toString( 16 )
					.padStart( 2, '0' )
			)
			.join( '' )
	);
}

/**
 * Flatten gradient paints so the markup survives core's allowlist.
 *
 * Opaque gradients become the 50/50 blend of their end stops; gradients
 * with a transparent stop are shading overlays with no flat equivalent,
 * so the elements they paint are removed. Unresolvable url() fills are
 * left in place — the safety check then excludes the icon.
 *
 * @param {string} svg SVG markup.
 * @return {string} Markup with gradient defs resolved and removed.
 */
function flattenGradients( svg ) {
	const resolutions = {};
	const gradientPattern =
		/<(linearGradient|radialGradient)[^>]*\sid="([^"]+)"[^>]*>([\s\S]*?)<\/\1>/g;
	for ( const match of svg.matchAll( gradientPattern ) ) {
		const [ , , id, body ] = match;
		const stops = Array.from( body.matchAll( /<stop\b[^>]*>/g ) ).map(
			( stop ) => ( {
				color: ( stop[ 0 ].match( /stop-color="([^"]+)"/ ) || [] )[ 1 ],
				opacity: parseFloat(
					( stop[ 0 ].match( /stop-opacity="([^"]+)"/ ) || [] )[ 1 ] ??
						'1'
				),
			} )
		);
		if ( ! stops.length ) {
			continue;
		}
		if ( stops.some( ( stop ) => stop.opacity < 1 ) ) {
			resolutions[ id ] = { drop: true };
			continue;
		}
		const color = blendHex(
			stops[ 0 ].color || '',
			stops[ stops.length - 1 ].color || stops[ 0 ].color || ''
		);
		if ( color ) {
			resolutions[ id ] = { color };
		}
	}

	for ( const [ id, resolution ] of Object.entries( resolutions ) ) {
		if ( resolution.drop ) {
			svg = svg.replace(
				new RegExp(
					'<[a-zA-Z]+\\b[^>]*fill="url\\(#' + id + '\\)"[^>]*/>\\s*',
					'g'
				),
				''
			);
		} else {
			svg = svg.replaceAll(
				'fill="url(#' + id + ')"',
				'fill="' + resolution.color + '"'
			);
		}
	}

	return svg
		.replace( /<defs>\s*<\/defs>\s*/g, '' )
		.replace( /<defs>[\s\S]*?<\/defs>\s*/g, ( defs ) =>
			// Only drop defs whose gradients were all resolved.
			defs.replace( gradientPattern, ( gradient, _tag, id ) =>
				resolutions[ id ] ? '' : gradient
			)
		)
		.replace( /<defs>\s*<\/defs>\s*/g, '' );
}

/**
 * Convert <rect> elements to equivalent <path> elements (lossless,
 * rounded corners included) — core's allowlist has no rect.
 *
 * @param {string} svg SVG markup.
 * @return {string} Markup with rects expressed as paths.
 */
function rectsToPaths( svg ) {
	return svg.replace( /<rect\b([^>]*?)\/?>(?:<\/rect>)?/g, ( full, attrs ) => {
		const geometry = {};
		const kept = [];
		for ( const attr of attrs.matchAll(
			/([a-zA-Z-]+)="([^"]*)"/g
		) ) {
			if ( [ 'x', 'y', 'width', 'height', 'rx', 'ry' ].includes( attr[ 1 ] ) ) {
				geometry[ attr[ 1 ] ] = parseFloat( attr[ 2 ] );
			} else {
				kept.push( attr[ 0 ] );
			}
		}
		const x = geometry.x || 0;
		const y = geometry.y || 0;
		const w = geometry.width;
		const h = geometry.height;
		if ( ! ( w > 0 ) || ! ( h > 0 ) ) {
			return full;
		}
		const rx = geometry.rx ?? geometry.ry ?? 0;
		const ry = geometry.ry ?? rx;
		const d =
			rx > 0
				? `M${ fmt( x + rx ) },${ fmt( y ) }H${ fmt( x + w - rx ) }A${ fmt( rx ) },${ fmt( ry ) } 0 0 1 ${ fmt( x + w ) },${ fmt( y + ry ) }V${ fmt( y + h - ry ) }A${ fmt( rx ) },${ fmt( ry ) } 0 0 1 ${ fmt( x + w - rx ) },${ fmt( y + h ) }H${ fmt( x + rx ) }A${ fmt( rx ) },${ fmt( ry ) } 0 0 1 ${ fmt( x ) },${ fmt( y + h - ry ) }V${ fmt( y + ry ) }A${ fmt( rx ) },${ fmt( ry ) } 0 0 1 ${ fmt( x + rx ) },${ fmt( y ) }Z`
				: `M${ fmt( x ) },${ fmt( y ) }H${ fmt( x + w ) }V${ fmt( y + h ) }H${ fmt( x ) }Z`;
		const lead = kept.length ? kept.join( ' ' ) + ' ' : '';
		return `<path ${ lead }d="${ d }"/>`;
	} );
}

/**
 * Prepare one SVG string for core registration.
 *
 * @param {string} rawSvg SVG markup.
 * @return {{svg: string, safe: boolean}} Transformed markup and whether
 *         it survives core's kses allowlist intact.
 */
function prepareSvgForCore( rawSvg ) {
	let svg = rawSvg
		.replace( /<\?xml[^>]*\?>\s*/g, '' )
		.replace( /<!--[\s\S]*?-->/g, '' )
		.trim();

	svg = rectsToPaths( flattenGradients( svg ) );

	// Core strips `fill` from the svg root; push an inherited
	// currentColor down onto the paint elements that don't set their own.
	const root = svg.match( /^<svg[^>]*>/ );
	if ( root && /\sfill="currentColor"/.test( root[ 0 ] ) ) {
		svg =
			root[ 0 ].replace( /\sfill="currentColor"/, '' ) +
			svg
				.slice( root[ 0 ].length )
				.replace( /<(path|polygon)(?![^>]*\sfill=)/g, '<$1 fill="currentColor"' );
	}

	const elements = svg.match( /<([a-zA-Z][a-zA-Z0-9-]*)/g ) || [];
	const safe = elements.every( ( tag ) =>
		ALLOWED_ELEMENTS.has( tag.slice( 1 ).toLowerCase() )
	);

	return { svg, safe };
}

/**
 * Derive the core icon name from an Icon Block library icon name.
 *
 * @param {string} name Library name, e.g. "phosphor-acorn" or "ollie-zelle".
 * @return {?string} Core-safe name, or null if it can't be made valid.
 */
function coreIconName( name ) {
	const stripped = name.replace( /^(phosphor|ollie)-/, '' );
	return CORE_NAME_PATTERN.test( stripped ) ? stripped : null;
}

/**
 * Build the per-collection manifest entries from the library icon list.
 *
 * @param {Array} icons Library entries: { name, title, icon, categories }.
 * @return {{entries: Array, skipped: Array}} Registerable entries
 *         ({ collection, name, label, fileName, svg }) and the library
 *         names of icons that can't survive core's sanitizer.
 */
function buildCoreManifest( icons ) {
	const entries = [];
	const skipped = [];

	for ( const icon of icons ) {
		const name = coreIconName( icon.name );
		const { svg, safe } = prepareSvgForCore( icon.icon );
		if ( ! name || ! safe ) {
			skipped.push( icon.name );
			continue;
		}
		const category = ( icon.categories || [] )[ 0 ];
		entries.push( {
			collection: CATEGORY_COLLECTIONS[ category ] || DEFAULT_COLLECTION,
			name,
			label: icon.title,
			fileName: name + '.svg',
			svg,
		} );
	}

	return { entries, skipped };
}

module.exports = { prepareSvgForCore, coreIconName, buildCoreManifest };
