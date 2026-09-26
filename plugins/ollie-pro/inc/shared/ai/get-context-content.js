import apiFetch from '@wordpress/api-fetch';

// -------------------------------------------------------------------------
// Context cache — persists in localStorage, content cached in memory
// -------------------------------------------------------------------------

const CONTEXT_STORAGE_KEY = 'ollie_rewrite_context_pages';
const CONTEXT_FILES_KEY = 'ollie_rewrite_context_files';
export const contextContentCache = {};

// Load persisted file contents into cache on init.
try {
	const storedFiles = localStorage.getItem( CONTEXT_FILES_KEY );
	if ( storedFiles ) {
		const parsed = JSON.parse( storedFiles );
		Object.keys( parsed ).forEach( ( key ) => {
			contextContentCache[ key ] = parsed[ key ];
		} );
	}
} catch ( e ) {
	// Ignore.
}

function persistFileCache() {
	try {
		const fileEntries = {};
		Object.keys( contextContentCache ).forEach( ( key ) => {
			if ( key.indexOf( 'file-' ) === 0 ) {
				fileEntries[ key ] = contextContentCache[ key ];
			}
		} );
		localStorage.setItem( CONTEXT_FILES_KEY, JSON.stringify( fileEntries ) );
	} catch ( e ) {
		// Ignore.
	}
}

export function loadPersistedContextPages() {
	try {
		const stored = localStorage.getItem( CONTEXT_STORAGE_KEY );
		return stored ? JSON.parse( stored ) : [];
	} catch ( e ) {
		return [];
	}
}

export function persistContextPages( pages ) {
	try {
		localStorage.setItem( CONTEXT_STORAGE_KEY, JSON.stringify( pages ) );
	} catch ( e ) {
		// Ignore.
	}
}

function fetchAndCachePage( pageId ) {
	if ( contextContentCache[ pageId ] ) {
		return Promise.resolve( contextContentCache[ pageId ] );
	}
	return apiFetch( { path: '/wp/v2/posts/' + pageId + '?_fields=title,content' } )
		.catch( () => apiFetch( { path: '/wp/v2/pages/' + pageId + '?_fields=title,content' } ) )
		.then( ( result ) => {
			if ( result?.title && result?.content ) {
				const title = result.title.rendered || result.title;
				const raw = result.content.rendered || result.content;
				const div = document.createElement( 'div' );
				div.innerHTML = raw;
				let plainText = div.textContent || '';
				if ( plainText.length > 2000 ) {
					plainText = plainText.substring( 0, 2000 ) + '...';
				}
				const cached = { title, plainText };
				contextContentCache[ pageId ] = cached;
				return cached;
			}
			return null;
		} )
		.catch( () => null );
}

function fetchAndCacheFile( item ) {
	const cacheKey = 'file-' + item.id;
	if ( contextContentCache[ cacheKey ] ) {
		return Promise.resolve( contextContentCache[ cacheKey ] );
	}
	return Promise.resolve( null );
}

export function readAndCacheFile( file ) {
	return new Promise( ( resolve ) => {
		const reader = new FileReader();
		reader.onload = () => {
			let text = reader.result || '';
			if ( text.length > 4000 ) {
				text = text.substring( 0, 4000 ) + '...';
			}
			const fileId = file.name + '-' + file.size;
			const cacheKey = 'file-' + fileId;
			contextContentCache[ cacheKey ] = { title: file.name, plainText: text };
			persistFileCache();
			resolve( { id: fileId, title: file.name, type: 'file' } );
		};
		reader.onerror = () => resolve( null );
		reader.readAsText( file );
	} );
}

export function fetchAndCacheContextItem( item ) {
	return item.type === 'file' ? fetchAndCacheFile( item ) : fetchAndCachePage( item.id );
}

export function getContextContent( items ) {
	if ( items.length === 0 ) {
		return Promise.resolve( '' );
	}
	return Promise.all( items.map( fetchAndCacheContextItem ) ).then( ( results ) => {
		const parts = [];
		results.forEach( ( result, idx ) => {
			if ( result ) {
				const label = items[ idx ].type === 'file' ? 'File' : 'Page';
				parts.push( label + ': ' + result.title + '\n' + result.plainText );
			}
		} );
		return parts.join( '\n\n---\n\n' );
	} );
}
