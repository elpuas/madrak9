/* eslint-disable import/no-unresolved -- WordPress resolves these public script modules at runtime. */
/* eslint-disable prettier/prettier -- Project JavaScript style omits semicolons. */

import { executeAbility, getAbilities, validateValueFromSchema } from '@wordpress/abilities'
import { ready as abilitiesReady } from '@wordpress/core-abilities'

const { Button, Modal, Notice, TextControl } = window.wp.components
const { createElement, createRoot, Fragment, useEffect, useState } = window.wp.element
const { __ } = window.wp.i18n
const { useCommand } = window.wp.commands

const getInputField = ( ability ) => {
	const schema = ability.input_schema
	const allowedRootKeys = [ 'type', 'properties', 'required', 'additionalProperties', 'title', 'description' ]
	const allowedFieldKeys = [ 'type', 'title', 'description', 'minLength', 'maxLength', 'pattern' ]

	if ( ! schema || schema.type !== 'object' || schema.additionalProperties !== false ) {
		return null
	}

	const fields = Object.entries( schema.properties ?? {} )
	if (
		fields.length !== 1 ||
		schema.required?.length !== 1 ||
		schema.required[ 0 ] !== fields[ 0 ][ 0 ] ||
		fields[ 0 ][ 1 ].type !== 'string' ||
		Object.keys( schema ).some( ( key ) => ! allowedRootKeys.includes( key ) ) ||
		Object.keys( fields[ 0 ][ 1 ] ).some( ( key ) => ! allowedFieldKeys.includes( key ) )
	) {
		return null
	}

	return { name: fields[ 0 ][ 0 ], schema: fields[ 0 ][ 1 ] }
}

const isPaletteAbility = ( ability ) =>
	ability.name.startsWith( 'shop-commands/' ) &&
	ability.category === 'shop-commands' &&
	ability.meta?.annotations?.serverRegistered === true &&
	ability.meta?.annotations?.readonly === true &&
	ability.meta?.annotations?.destructive === false &&
	ability.output_schema?.type === 'object' &&
	( Object.keys( ability.input_schema ?? {} ).length === 0 || getInputField( ability ) !== null )

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

	return String( value )
}

const renderValue = ( value, schema ) => {
	if ( Array.isArray( value ) ) {
		return value.length === 0
			? __( 'None', 'shop-commands' )
			: createElement(
				'ol',
				{ className: 'shop-commands-result-modal__items' },
				value.map( ( item, index ) =>
					createElement( 'li', { key: index }, renderValue( item, schema.items ?? {} ) )
				)
			)
	}

	if ( value !== null && typeof value === 'object' ) {
		return createElement( ResultRows, { result: value, schema } )
	}

	return formatValue( value )
}

const ResultRows = ( { result, schema } ) => {
	const properties = schema?.properties ?? {}

	return createElement(
		'dl',
		{ className: 'shop-commands-result-modal__list' },
		Object.entries( result ).map( ( [ key, value ] ) => {
			const propertySchema = properties[ key ] ?? {}
			const label = propertySchema.title ?? humanizeKey( key )
			const isNested = value !== null && typeof value === 'object'

			return createElement(
				'div',
				{
					className: isNested
						? 'shop-commands-result-modal__row shop-commands-result-modal__row--nested'
						: 'shop-commands-result-modal__row',
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
				createElement( 'dd', null, renderValue( value, propertySchema ) )
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
			: createElement( ResultRows, { result, schema: ability.output_schema } ),
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

const InputModal = ( { ability, onClose, onResult } ) => {
	const field = getInputField( ability )
	const [ value, setValue ] = useState( '' )
	const [ error, setError ] = useState( '' )
	const [ isSubmitting, setIsSubmitting ] = useState( false )

	const submit = async ( event ) => {
		event.preventDefault()

		const input = { [ field.name ]: value.trim() }
		const validation = validateValueFromSchema( input, ability.input_schema, 'input' )
		if ( validation !== true ) {
			setError( validation )
			return
		}

		setIsSubmitting( true )
		try {
			const result = await executeAbility( ability.name, input )
			onResult( { ability, result } )
		} catch ( executionError ) {
			onResult( { ability, error: executionError } )
		}
	}

	return createElement(
		Modal,
		{
			className: 'shop-commands-input-modal',
			onRequestClose: onClose,
			size: 'small',
			title: ability.label,
		},
		createElement(
			'form',
			{ onSubmit: submit },
			createElement( TextControl, {
				'aria-errormessage': error ? 'shop-commands-input-error' : undefined,
				'aria-invalid': Boolean( error ),
				autoFocus: true,
				help: field.schema.description,
				label: field.schema.title ?? humanizeKey( field.name ),
				onChange: ( nextValue ) => {
					setValue( nextValue )
					setError( '' )
				},
				required: true,
				value,
			} ),
			error
				? createElement(
					'div',
					{ id: 'shop-commands-input-error', role: 'alert' },
					createElement( Notice, { isDismissible: false, status: 'error' }, error )
				)
				: null,
			createElement(
				'div',
				{ className: 'shop-commands-result-modal__footer' },
				createElement(
					Button,
					{ disabled: isSubmitting, isBusy: isSubmitting, type: 'submit', variant: 'primary' },
					ability.label
				)
			)
		)
	)
}

const AbilityCommand = ( { ability, onInput, onResult } ) => {
	useCommand( {
		name: ability.name,
		label: ability.label,
		category: 'action',
		callback: async ( { close } ) => {
			if ( getInputField( ability ) ) {
				close()
				onInput( ability )
				return
			}

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
	const [ abilities, setAbilities ] = useState( [] )
	const [ inputAbility, setInputAbility ] = useState( null )
	const [ modalState, setModalState ] = useState( null )

	useEffect( () => {
		let isMounted = true

		abilitiesReady.then( () => {
			if ( isMounted ) {
				setAbilities( getAbilities( { category: 'shop-commands' } ).filter( isPaletteAbility ) )
			}
		} )

		return () => {
			isMounted = false
		}
	}, [] )

	const closeModal = () => {
		setInputAbility( null )
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
		...abilities.map( ( ability ) =>
			createElement( AbilityCommand, {
				ability,
				key: ability.name,
				onInput: setInputAbility,
				onResult: setModalState,
			} )
		),
		inputAbility
			? createElement( InputModal, {
				ability: inputAbility,
				key: inputAbility.name,
				onClose: closeModal,
				onResult: ( result ) => {
					setInputAbility( null )
					setModalState( result )
				},
			} )
			: null,
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
