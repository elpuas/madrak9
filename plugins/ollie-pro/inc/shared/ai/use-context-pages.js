import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import {
	loadPersistedContextPages,
	persistContextPages,
	fetchAndCacheContextItem,
} from './get-context-content';

/**
 * Hook providing the current post id, the selected context pages, and a
 * setter that persists changes to localStorage and warms the fetch cache.
 *
 * @return {{ contextPages: Array, setContextPages: Function, currentPost: Object }}
 */
export function useContextPages() {
	const currentPost = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return {
			id: editor?.getCurrentPostId?.() || 0,
			title: editor?.getEditedPostAttribute?.( 'title' ) || 'This page',
		};
	}, [] );

	const [ contextPagesRaw, setContextPagesRaw ] = useState( () => {
		return loadPersistedContextPages();
	} );

	const setContextPages = ( pages ) => {
		setContextPagesRaw( pages );
		persistContextPages( pages );
		pages.forEach( fetchAndCacheContextItem );
	};

	useEffect( () => {
		contextPagesRaw.forEach( fetchAndCacheContextItem );
	}, [] );

	return { contextPages: contextPagesRaw, setContextPages, currentPost };
}
