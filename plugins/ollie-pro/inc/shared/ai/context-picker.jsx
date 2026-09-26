/**
 * Context Picker component
 *
 * Lets users add context pages by searching the site, attach files via a
 * hidden file input, and remove items by clicking the chip's remove button.
 *
 * @package OlliePro
 */

import { useState, useRef, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	ComboboxControl,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	SVG,
	Path,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { readAndCacheFile } from './get-context-content';

// -------------------------------------------------------------------------
// Icons
// -------------------------------------------------------------------------

const uploadIcon = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width={ 20 } height={ 20 } fill="currentColor">
		<Path d="M224,144v64a8,8,0,0,1-8,8H40a8,8,0,0,1-8-8V144a8,8,0,0,1,16,0v56H208V144a8,8,0,0,1,16,0ZM93.66,77.66,120,51.31V144a8,8,0,0,0,16,0V51.31l26.34,26.35a8,8,0,0,0,11.32-11.32l-40-40a8,8,0,0,0-11.32,0l-40,40A8,8,0,0,0,93.66,77.66Z" />
	</SVG>
);

const pageIcon = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width={ 20 } height={ 20 } fill="currentColor">
		<Path d="M213.66,82.34l-56-56A8,8,0,0,0,152,24H56A16,16,0,0,0,40,40V216a16,16,0,0,0,16,16H200a16,16,0,0,0,16-16V88A8,8,0,0,0,213.66,82.34ZM160,51.31,188.69,80H160ZM200,216H56V40h88V88a8,8,0,0,0,8,8h48V216Zm-40-64a8,8,0,0,1-8,8H136v16a8,8,0,0,1-16,0V160H104a8,8,0,0,1,0-16h16V128a8,8,0,0,1,16,0v16h16A8,8,0,0,1,160,152Z" />
	</SVG>
);

const clipIcon = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width={ 18 } height={ 18 } fill="currentColor">
		<Path d="M209.66,122.34a8,8,0,0,1,0,11.32l-82.05,82a56,56,0,0,1-79.2-79.21L147.67,35.73a40,40,0,1,1,56.61,56.55L105,193A24,24,0,1,1,71,159L154.3,74.38A8,8,0,1,1,165.7,85.6L82.39,170.31a8,8,0,1,0,11.27,11.36L192.93,81A24,24,0,1,0,159,47L59.76,147.68a40,40,0,1,0,56.53,56.62l82.06-82A8,8,0,0,1,209.66,122.34Z" />
	</SVG>
);

const closeIcon = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width={ 18 } height={ 18 } fill="currentColor">
		<Path d="M208.49,191.51a12,12,0,0,1-17,17L128,145,64.49,208.49a12,12,0,0,1-17-17L111,128,47.51,64.49a12,12,0,0,1,17-17L128,111l63.51-63.52a12,12,0,0,1,17,17L145,128Z" />
	</SVG>
);

const chipTxtIcon = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width={ 15 } height={ 15 } fill="currentColor">
		<Path d="M48,120a8,8,0,0,0,8-8V40h88V88a8,8,0,0,0,8,8h48v16a8,8,0,0,0,16,0V88a8,8,0,0,0-2.34-5.66l-56-56A8,8,0,0,0,152,24H56A16,16,0,0,0,40,40v72A8,8,0,0,0,48,120ZM160,51.31,188.69,80H160Zm-5.49,105.34L137.83,180l16.68,23.35a8,8,0,0,1-13,9.3L128,193.76l-13.49,18.89a8,8,0,1,1-13-9.3L118.17,180l-16.68-23.35a8,8,0,1,1,13-9.3L128,166.24l13.49-18.89a8,8,0,0,1,13,9.3ZM92,152a8,8,0,0,1-8,8H72v48a8,8,0,0,1-16,0V160H44a8,8,0,0,1,0-16H84A8,8,0,0,1,92,152Zm128,0a8,8,0,0,1-8,8H200v48a8,8,0,0,1-16,0V160H172a8,8,0,0,1,0-16h40A8,8,0,0,1,220,152Z" />
	</SVG>
);

const chipMdIcon = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width={ 15 } height={ 15 } fill="currentColor">
		<Path d="M213.66,82.34l-56-56A8,8,0,0,0,152,24H56A16,16,0,0,0,40,40v72a8,8,0,0,0,16,0V40h88V88a8,8,0,0,0,8,8h48V224a8,8,0,0,0,16,0V88A8,8,0,0,0,213.66,82.34ZM160,51.31,188.69,80H160ZM144,144H128a8,8,0,0,0-8,8v56a8,8,0,0,0,8,8h16a36,36,0,0,0,0-72Zm0,56h-8V160h8a20,20,0,0,1,0,40Zm-40-48v56a8,8,0,0,1-16,0V177.38L74.55,196.59a8,8,0,0,1-13.1,0L48,177.38V208a8,8,0,0,1-16,0V152a8,8,0,0,1,14.55-4.59L68,178.05l21.45-30.64A8,8,0,0,1,104,152Z" />
	</SVG>
);

const chipPageIcon = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width={ 15 } height={ 15 } fill="currentColor">
		<Path d="M213.66,82.34l-56-56A8,8,0,0,0,152,24H56A16,16,0,0,0,40,40V216a16,16,0,0,0,16,16H200a16,16,0,0,0,16-16V88A8,8,0,0,0,213.66,82.34ZM160,51.31,188.69,80H160ZM200,216H56V40h88V88a8,8,0,0,0,8,8h48V216Zm-32-80a8,8,0,0,1-8,8H96a8,8,0,0,1,0-16h64A8,8,0,0,1,168,136Zm0,32a8,8,0,0,1-8,8H96a8,8,0,0,1,0-16h64A8,8,0,0,1,168,168Z" />
	</SVG>
);

function getChipIcon( item ) {
	if ( item.type === 'file' ) {
		const ext = item.title ? item.title.split( '.' ).pop().toLowerCase() : '';
		return ext === 'md' ? chipMdIcon : chipTxtIcon;
	}
	return chipPageIcon;
}

export function ContextPicker( { contextPages, setContextPages, disabled, currentPostId } ) {
	const [ activeView, setActiveView ] = useState( null );
	const fileInputRef = useRef( null );
	const [ , setSearch ] = useState( '' );
	const [ options, setOptions ] = useState( [] );
	const [ , setSearching ] = useState( false );
	const debounceRef = useRef( null );

	const handleFilter = useCallback( ( inputValue ) => {
		setSearch( inputValue );
		if ( debounceRef.current ) {
			clearTimeout( debounceRef.current );
		}
		if ( ! inputValue || inputValue.length < 2 ) {
			setOptions( [] );
			return;
		}
		debounceRef.current = setTimeout( () => {
			setSearching( true );
			apiFetch( {
				path: '/wp/v2/search?search=' + encodeURIComponent( inputValue ) + '&per_page=10&type=post&subtype=any',
			} )
				.then( ( results ) => {
					const selectedIds = contextPages.map( ( p ) => p.id );
					const filtered = results.filter( ( r ) => ! selectedIds.includes( r.id ) );
					setOptions( filtered.map( ( r ) => ( {
						value: String( r.id ),
						label: r.title + ' (' + r.subtype + ')',
					} ) ) );
					setSearching( false );
				} )
				.catch( () => {
					setOptions( [] );
					setSearching( false );
				} );
		}, 300 );
	}, [ contextPages ] );

	function handlePageSelect( value ) {
		if ( ! value ) {
			return;
		}
		const selected = options.find( ( o ) => o.value === value );
		if ( selected ) {
			setContextPages( [ ...contextPages, { id: parseInt( value, 10 ), title: selected.label } ] );
		}
		setSearch( '' );
		setOptions( [] );
		setActiveView( null );
	}

	function handleFileUpload( e ) {
		const file = e.target.files?.[ 0 ];
		if ( ! file ) {
			return;
		}
		const ext = file.name.split( '.' ).pop().toLowerCase();
		if ( ext !== 'md' && ext !== 'txt' ) {
			return;
		}
		readAndCacheFile( file ).then( ( item ) => {
			if ( item ) {
				setContextPages( [ ...contextPages, item ] );
			}
		} );
		e.target.value = '';
	}

	function removeItem( item ) {
		setContextPages( contextPages.filter( ( p ) =>
			! ( p.id === item.id && ( p.type || '' ) === ( item.type || '' ) )
		) );
	}

	const hiddenInput = (
		<input
			type="file"
			accept=".md,.txt"
			ref={ fileInputRef }
			onChange={ handleFileUpload }
			style={ { display: 'none' } }
		/>
	);

	if ( activeView === 'page' ) {
		return (
			<div className="ollie-rewrite-context">
				{ hiddenInput }
				<div className="ollie-rewrite-page-search-row">
					<div className="ollie-rewrite-page-search-input">
						<ComboboxControl
							value={ null }
							onChange={ handlePageSelect }
							onFilterValueChange={ handleFilter }
							options={ options }
							placeholder={ __( 'Search for a page or post...', 'ollie-pro' ) }
							disabled={ disabled }
							__next40pxDefaultSize
						/>
					</div>
					<Button
						variant="tertiary"
						onClick={ () => setActiveView( null ) }
						className="ollie-rewrite-context-cancel"
					>
						{ __( 'Cancel', 'ollie-pro' ) }
					</Button>
				</div>
			</div>
		);
	}

	return (
		<div className="ollie-rewrite-context">
			{ hiddenInput }
			<div className="ollie-rewrite-context-row">
				<DropdownMenu
					icon={ clipIcon }
					label={ __( 'Add context', 'ollie-pro' ) }
					className="ollie-rewrite-add-menu"
					toggleProps={ { className: 'ollie-rewrite-add-menu-toggle', disabled } }
				>
					{ ( { onClose } ) => (
						<MenuGroup>
							<MenuItem
								icon={ uploadIcon }
								onClick={ () => {
									onClose();
									fileInputRef.current?.click();
								} }
							>
								{ __( 'Upload .md or .txt file', 'ollie-pro' ) }
							</MenuItem>
							<MenuItem
								icon={ pageIcon }
								onClick={ () => {
									onClose();
									setActiveView( 'page' );
								} }
							>
								{ __( 'Attach page', 'ollie-pro' ) }
							</MenuItem>
						</MenuGroup>
					) }
				</DropdownMenu>
				{ contextPages.length === 0 && (
					<span className="ollie-rewrite-add-context-label">
						{ __( 'Add context', 'ollie-pro' ) }
					</span>
				) }
				{ contextPages.length > 0 && (
					<div className="ollie-rewrite-context-tags">
						{ contextPages.map( ( item ) => (
							<span
								key={ ( item.type || 'page' ) + '-' + item.id }
								className={ 'ollie-rewrite-context-tag' + ( item.type === 'file' ? ' is-file' : '' ) }
							>
								{ getChipIcon( item ) }
								<span className="ollie-rewrite-context-tag-label">
									{ item.id === currentPostId ? item.title + ' (this page)' : item.title }
								</span>
								<Button
									icon={ closeIcon }
									label={ __( 'Remove', 'ollie-pro' ) }
									onClick={ () => removeItem( item ) }
									size="small"
									className="ollie-rewrite-context-tag-remove"
									disabled={ disabled }
								/>
							</span>
						) ) }
					</div>
				) }
			</div>
		</div>
	);
}
