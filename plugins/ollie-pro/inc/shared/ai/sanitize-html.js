/**
 * Shared HTML sanitizer.
 *
 * Parses input inside an inert <template> element (so resource-loading
 * elements like <img onerror> can't fire during assignment), then walks the
 * tree applying the caller-provided allowlist and drop list. Disallowed
 * elements are unwrapped (children kept as text); drop-list elements are
 * removed entirely with their subtree. Event-handler (`on*`) and namespaced
 * (`xlink:*`, `xmlns:*`) attributes are stripped on every retained element,
 * and javascript:/data:/vbscript:/file: hrefs are scrubbed.
 *
 * @param {string} html       Raw HTML string from an external source.
 * @param {Object} allowlist  Map of UPPERCASE tag name → array of allowed
 *                            attribute names. Tags absent from the map are
 *                            unwrapped.
 * @param {Set}    dropList   Set of UPPERCASE tag names to drop entirely.
 * @return {string} Sanitized HTML.
 */
export function sanitizeHtml( html, allowlist, dropList ) {
	if ( typeof html !== 'string' || '' === html ) {
		return '';
	}
	const template = document.createElement( 'template' );
	template.innerHTML = html;

	const walk = ( node ) => {
		Array.from( node.childNodes ).forEach( ( child ) => {
			if ( child.nodeType === 3 ) { // text
				return;
			}
			if ( child.nodeType !== 1 ) { // anything not element/text
				node.removeChild( child );
				return;
			}
			const tag = ( child.tagName || '' ).toUpperCase();
			if ( dropList.has( tag ) ) {
				node.removeChild( child );
				return;
			}
			walk( child );
			const allowedAttrs = allowlist[ tag ];
			if ( ! allowedAttrs ) {
				while ( child.firstChild ) {
					node.insertBefore( child.firstChild, child );
				}
				node.removeChild( child );
				return;
			}
			Array.from( child.attributes ).forEach( ( attr ) => {
				const name = attr.name.toLowerCase();
				if ( name.startsWith( 'on' ) || name.includes( ':' ) ) {
					child.removeAttribute( attr.name );
					return;
				}
				if ( ! allowedAttrs.includes( name ) ) {
					child.removeAttribute( attr.name );
					return;
				}
				if ( 'href' === name && /^\s*(javascript|data|vbscript|file):/i.test( attr.value ) ) {
					child.removeAttribute( attr.name );
				}
			} );
		} );
	};
	walk( template.content );

	const out = document.createElement( 'div' );
	out.appendChild( template.content.cloneNode( true ) );
	return out.innerHTML;
}

/**
 * Inline-only allowlist used by AI Rewrite (formatting roundtrip).
 */
export const INLINE_ALLOWLIST = {
	STRONG: [],
	EM: [],
	B: [],
	I: [],
	MARK: [],
	U: [],
	S: [],
	CODE: [],
	BR: [],
	SUB: [],
	SUP: [],
	SMALL: [],
	A: [ 'href', 'target', 'rel', 'title' ],
};

/**
 * Default drop list — elements whose mere presence is a parsing hazard
 * regardless of attributes. Shared across all callers.
 */
export const DEFAULT_DROP_LIST = new Set( [
	'SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED',
	'NOSCRIPT', 'TEMPLATE', 'SVG', 'MATH',
	'IMG', 'VIDEO', 'AUDIO', 'SOURCE', 'TRACK', 'PICTURE',
	'CANVAS', 'FORM', 'INPUT', 'BUTTON', 'TEXTAREA', 'SELECT',
	'META', 'LINK', 'BASE', 'TITLE', 'HEAD',
] );
