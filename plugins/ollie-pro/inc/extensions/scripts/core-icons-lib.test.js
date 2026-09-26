/**
 * Transform library for exporting the Ollie icon set to the WP 7.1 core
 * icon registry (wp_register_icon). Core sanitizes icons through a strict
 * kses allowlist (svg/path/polygon elements only), so the transforms here
 * must produce markup that survives it unchanged.
 */
const {
	prepareSvgForCore,
	coreIconName,
	buildCoreManifest,
} = require( './core-icons-lib' );

describe( 'prepareSvgForCore', () => {
	it( 'moves fill="currentColor" from the svg root onto fill-less paths', () => {
		// Core's kses strips `fill` from the <svg> element (it is only
		// allowed on path/polygon), which would leave Phosphor icons
		// rendering in default black instead of the inherited color.
		const { svg, safe } = prepareSvgForCore(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M1 1"/></svg>'
		);
		expect( safe ).toBe( true );
		expect( svg ).not.toMatch( /<svg[^>]*fill=/ );
		expect( svg ).toContain( '<path fill="currentColor" d="M1 1"/>' );
	} );

	it( 'keeps explicit path fills (brand-colored icons)', () => {
		const { svg } = prepareSvgForCore(
			'<svg viewBox="0 0 72 48" fill="currentColor"><path fill="#6D1ED4" d="M1 1"/></svg>'
		);
		expect( svg ).toContain( 'fill="#6D1ED4"' );
		expect( svg ).not.toContain( 'fill="currentColor" fill="#6D1ED4"' );
	} );

	it( 'strips XML declarations', () => {
		const { svg } = prepareSvgForCore(
			'<?xml version="1.0" encoding="UTF-8"?>\n<svg viewBox="0 0 24 24"><path d="M1 1"/></svg>'
		);
		expect( svg.startsWith( '<svg' ) ).toBe( true );
	} );

	it( 'flags icons using elements outside the core kses allowlist', () => {
		const { safe } = prepareSvgForCore(
			'<svg viewBox="0 0 76 83"><defs><linearGradient id="g"><stop offset="0"/></linearGradient></defs><rect fill="url(#g)" width="10" height="10"/></svg>'
		);
		expect( safe ).toBe( false );
	} );

	it( 'accepts polygons', () => {
		const { safe } = prepareSvgForCore(
			'<svg viewBox="0 0 24 24"><polygon points="0,0 10,0 5,10"/></svg>'
		);
		expect( safe ).toBe( true );
	} );

	it( 'converts rounded rects to equivalent paths', () => {
		// The Amex card background: core kses drops <rect>, but a rect is
		// losslessly expressible as a path.
		const { svg, safe } = prepareSvgForCore(
			'<svg width="72" height="48" viewBox="0 0 72 48" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="72" height="48" rx="6" fill="#1F72CD"/><path d="M1 1" fill="white"/></svg>'
		);
		expect( safe ).toBe( true );
		expect( svg ).not.toContain( '<rect' );
		expect( svg ).toContain(
			'<path fill="#1F72CD" d="M6,0H66A6,6 0 0 1 72,6V42A6,6 0 0 1 66,48H6A6,6 0 0 1 0,42V6A6,6 0 0 1 6,0Z"/>'
		);
	} );

	it( 'converts square-cornered rects to paths', () => {
		const { svg, safe } = prepareSvgForCore(
			'<svg viewBox="0 0 24 24"><rect x="2" y="3" width="10" height="4" fill="#000"/></svg>'
		);
		expect( safe ).toBe( true );
		expect( svg ).toContain( '<path fill="#000" d="M2,3H12V7H2Z"/>' );
	} );

	it( 'flattens opaque gradient fills to a blended solid color', () => {
		// The Discover ball base: a two-stop opaque linear gradient
		// becomes the 50/50 blend of its end stops.
		const { svg, safe } = prepareSvgForCore(
			'<svg viewBox="0 0 72 48"><path fill="url(#g1)" d="M1 1"/><defs><linearGradient id="g1"><stop stop-color="#E15315"/><stop offset="1" stop-color="#FFB320"/></linearGradient></defs></svg>'
		);
		expect( safe ).toBe( true );
		expect( svg ).not.toContain( 'defs' );
		expect( svg ).toContain( 'fill="#f0831b"' );
	} );

	it( 'drops elements painted with transparent-stop overlay gradients', () => {
		// Shading overlays (a stop at opacity 0) have no flat
		// equivalent — remove the overlay element, keep the rest.
		const { svg, safe } = prepareSvgForCore(
			'<svg viewBox="0 0 72 48"><path fill="#F0831A" d="M1 1"/><path fill="url(#g2)" d="M2 2"/><defs><radialGradient id="g2"><stop stop-color="#FFCD83" stop-opacity="0"/><stop offset="1" stop-color="#912301"/></radialGradient></defs></svg>'
		);
		expect( safe ).toBe( true );
		expect( svg ).toContain( 'fill="#F0831A"' );
		expect( svg ).not.toContain( 'M2 2' );
		expect( svg ).not.toContain( 'url(#g2)' );
	} );
} );

describe( 'coreIconName', () => {
	it( 'strips the library prefixes used by the Icon Block integration', () => {
		expect( coreIconName( 'phosphor-address-book' ) ).toBe(
			'address-book'
		);
		expect( coreIconName( 'ollie-zelle' ) ).toBe( 'zelle' );
	} );

	it( 'rejects names that violate the core slug pattern', () => {
		// wp_register_icon requires ^[a-z0-9](?:[a-z0-9_-]*[a-z0-9])?$
		expect( coreIconName( 'phosphor-Bad Name' ) ).toBeNull();
		expect( coreIconName( 'ollie--' ) ).toBeNull();
	} );
} );

describe( 'buildCoreManifest', () => {
	const icons = [
		{
			name: 'phosphor-acorn',
			title: 'Acorn',
			icon: '<svg viewBox="0 0 256 256" fill="currentColor"><path d="M1 1"/></svg>',
			categories: [ 'nature' ],
		},
		{
			name: 'ollie-zelle',
			title: 'Zelle',
			icon: '<svg viewBox="0 0 72 48"><path fill="#6D1ED4" d="M1 1"/></svg>',
			categories: [ 'ecommerce-payments' ],
		},
		{
			name: 'ollie-gradient-badge',
			title: 'Gradient Badge',
			icon: '<svg viewBox="0 0 76 83"><defs><linearGradient id="g"/></defs><rect fill="url(#g)"/></svg>',
			categories: [ 'ecommerce-badges' ],
		},
	];

	it( 'groups icons into per-collection entries and skips unsafe ones', () => {
		const { entries, skipped } = buildCoreManifest( icons );

		expect( entries ).toEqual( [
			expect.objectContaining( {
				collection: 'ollie',
				name: 'acorn',
				label: 'Acorn',
				fileName: 'acorn.svg',
			} ),
			expect.objectContaining( {
				collection: 'ollie-payments',
				name: 'zelle',
				label: 'Zelle',
				fileName: 'zelle.svg',
			} ),
		] );
		expect( skipped ).toEqual( [ 'ollie-gradient-badge' ] );
	} );

	it( 'carries the transformed svg on each entry', () => {
		const { entries } = buildCoreManifest( icons );
		expect( entries[ 0 ].svg ).toContain( 'fill="currentColor"' );
		expect( entries[ 0 ].svg ).not.toMatch( /<svg[^>]*fill=/ );
	} );
} );
