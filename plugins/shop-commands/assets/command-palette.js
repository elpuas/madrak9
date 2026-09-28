/* eslint-disable import/no-unresolved -- WordPress resolves these public script modules at runtime. */
/* eslint-disable prettier/prettier -- Project JavaScript style omits semicolons. */

import { executeAbility, getAbility } from '@wordpress/abilities'
import { ready as abilitiesReady } from '@wordpress/core-abilities'

const { Button, Modal, Notice } = window.wp.components
const { createElement, createRoot, Fragment, useEffect, useState } = window.wp.element
const { __ } = window.wp.i18n
const { useCommand } = window.wp.commands

const PALETTE_ABILITIES = [ 'shop-commands/orders-needing-attention' ]

const humanizeKey = ( key ) => {
	const words = key.replaceAll( '_', ' ' )

	return words.charAt( 0 ).toUpperCase() + words.slice( 1 )
}

const formatValue = ( value ) => {
	if ( value === null ) {
		return __( 'None', 'shop-commands' )
	}

	if ( typeof value === 'boolean' ) {
		return value
			? __( 'Yes', 'shop-commands' )
			: __( 'No', 'shop-commands' )
	}

	if ( typeof value === 'object' ) {
		return JSON.stringify( value )
	}

	return String( value )
}

const ResultRows = ( { ability, result } ) => {
	const properties = ability.output_schema?.properties ?? {}

	return createElement(
		'dl',
		{ className: 'shop-commands-result-modal__list' },
		Object.entries( result ).map( ( [ key, value ] ) => {
			const propertySchema = properties[ key ] ?? {}
			const label = propertySchema.title ?? humanizeKey( key )

			return createElement(
				'div',
				{
					className: 'shop-commands-result-modal__row',
					key,
				},
				createElement(
					'dt',
					null,
					label,
					propertySchema.description
						? createElement(
							'span',
							{ className: 'screen-reader-text' },
							` — ${ propertySchema.description }`
						)
						: null
				),
				createElement( 'dd', null, formatValue( value ) )
			)
		} )
	)
}

const ResultModal = ( { state, onClose } ) => {
	if ( ! state ) {
		return null
	}

	const { ability, error, result } = state
	const title = ability.output_schema?.title ?? ability.label

	return createElement(
		Modal,
		{
			className: 'shop-commands-result-modal',
			onRequestClose: onClose,
			size: 'small',
			title,
		},
		error
			? createElement(
				Notice,
				{
					isDismissible: false,
					status: 'error',
				},
				error.message || __( 'The Ability could not be executed.', 'shop-commands' )
			)
			: createElement( ResultRows, { ability, result } ),
		createElement(
			'div',
			{ className: 'shop-commands-result-modal__footer' },
			createElement(
				Button,
				{
					onClick: onClose,
					variant: 'primary',
				},
				__( 'Done', 'shop-commands' )
			)
		)
	)
}

const AbilityCommand = ( { abilityName, onResult } ) => {
	const [ ability, setAbility ] = useState( null )

	useEffect( () => {
		let isMounted = true

		abilitiesReady.then( () => {
			if ( isMounted ) {
				setAbility( getAbility( abilityName ) ?? null )
			}
		} )

		return () => {
			isMounted = false
		}
	}, [ abilityName ] )

	useCommand( {
		name: abilityName,
		label: ability?.label ?? abilityName,
		category: 'action',
		disabled: ! ability,
		callback: async ( { close } ) => {
			try {
				const result = await executeAbility( ability.name )

				close()
				onResult( { ability, result } )
			} catch ( error ) {
				close()
				onResult( { ability, error } )
			}
		},
	} )

	return null
}

const CommandPaletteApp = () => {
	const [ modalState, setModalState ] = useState( null )
	const closeModal = () => {
		setModalState( null )
		window.requestAnimationFrame( () => {
			document
				.querySelector( '#wp-admin-bar-command-palette > .ab-item' )
				?.focus()
		} )
	}

	return createElement(
		Fragment,
		null,
		...PALETTE_ABILITIES.map( ( abilityName ) =>
			createElement( AbilityCommand, {
				abilityName,
				key: abilityName,
				onResult: setModalState,
			} )
		),
		createElement( ResultModal, {
			onClose: closeModal,
			state: modalState,
		} )
	)
}

const root = document.createElement( 'div' )
root.id = 'shop-commands-command-palette'
document.body.appendChild( root )

createRoot( root ).render( createElement( CommandPaletteApp ) )
