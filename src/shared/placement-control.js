import { __ } from '@wordpress/i18n';
import { Button, SelectControl, Spinner, TextControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const TERMS_QUERY = {
	per_page: 100,
	orderby: 'name',
	order: 'asc',
	context: 'view',
};
const PROTECTED = [ 'everywhere', 'all' ];

function mergeTerms( terms, extras ) {
	const bySlug = new Map();
	( terms || [] ).forEach( ( term ) => {
		if ( term?.slug ) {
			bySlug.set( term.slug, term );
		}
	} );
	extras.forEach( ( term ) => {
		if ( ! term?.slug ) {
			return;
		}
		const current = bySlug.get( term.slug );
		bySlug.set( term.slug, {
			...( current || {} ),
			...term,
			placement_active:
				typeof current?.placement_active === 'boolean'
					? current.placement_active
					: term.placement_active,
		} );
	} );
	return Array.from( bySlug.values() );
}

function isInactive( term ) {
	return term?.placement_active === false;
}

export default function PlacementControl( { value, onChange } ) {
	const { terms, isResolving } = useSelect(
		( select ) => {
			const { getEntityRecords, isResolving: isResolvingRecords } =
				select( coreStore );

			return {
				terms: getEntityRecords( 'taxonomy', 'sponsor_placement', TERMS_QUERY ),
				isResolving: isResolvingRecords(
					'taxonomy',
					'sponsor_placement',
					TERMS_QUERY
				),
			};
		},
		[]
	);
	const { invalidateResolution } = useDispatch( coreStore );
	const [ extras, setExtras ] = useState( [] );
	const [ newName, setNewName ] = useState( '' );
	const [ rename, setRename ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( '' );

	const allTerms = mergeTerms( terms, extras );
	const selected = allTerms.find( ( term ) => term.slug === value );
	const isProtected = PROTECTED.includes( value );

	useEffect( () => {
		setRename( selected?.name || '' );
	}, [ selected?.name, value ] );

	const refreshTerms = () =>
		invalidateResolution( 'getEntityRecords', [
			'taxonomy',
			'sponsor_placement',
			TERMS_QUERY,
		] );

	const remember = ( term ) => {
		setExtras( ( current ) => [
			...current.filter( ( item ) => item.slug !== term.slug ),
			term,
		] );
	};

	const createPlacement = async () => {
		const name = newName.trim();
		if ( ! name || busy ) {
			return;
		}

		setBusy( true );
		setNotice( '' );
		try {
			const term = await apiFetch( {
				path: '/sponsor-manager/v1/placements',
				method: 'POST',
				data: { name },
			} );
			remember( term );
			await refreshTerms();
			onChange( term.slug );
			setNewName( '' );
			if ( term.existed ) {
				setNotice(
					__(
						'A placement with that name already exists. This block will use it.',
						'sponsor-manager'
					)
				);
			}
		} catch ( error ) {
			setNotice(
				error?.message ||
					__( 'The placement could not be created.', 'sponsor-manager' )
			);
		} finally {
			setBusy( false );
		}
	};

	const renamePlacement = async () => {
		const name = rename.trim();
		if ( ! value || ! name || busy || isProtected || name === selected?.name ) {
			return;
		}

		setBusy( true );
		setNotice( '' );
		try {
			const term = await apiFetch( {
				path: `/sponsor-manager/v1/placements/${ value }`,
				method: 'PUT',
				data: { name },
			} );
			remember( term );
			await refreshTerms();
			setNotice( __( 'Placement name updated.', 'sponsor-manager' ) );
		} catch ( error ) {
			setNotice(
				error?.message ||
					__( 'The placement could not be renamed.', 'sponsor-manager' )
			);
		} finally {
			setBusy( false );
		}
	};

	if ( isResolving && ! allTerms.length ) {
		return <Spinner />;
	}

	const active = [];
	const inactive = [];
	allTerms.forEach( ( term ) => {
		const option = { label: term.name, value: term.slug };
		if ( isInactive( term ) ) {
			inactive.push( {
				...option,
				label: `${ term.name } (${ __( 'inactive', 'sponsor-manager' ) })`,
			} );
		} else {
			active.push( option );
		}
	} );

	const options = [
		{ label: __( 'Select a placement…', 'sponsor-manager' ), value: '' },
		...active,
	];
	if ( inactive.length ) {
		options.push( {
			label: __( 'Inactive', 'sponsor-manager' ),
			value: '__inactive__',
			disabled: true,
		} );
		options.push( ...inactive );
	}

	let help = __(
		'Create a name for this slot, or choose one that already exists. Two slots on a page need two names. Ads assigned to Everywhere also show here.',
		'sponsor-manager'
	);
	if ( PROTECTED.includes( value ) ) {
		help = __( 'Shows ads assigned to Everywhere.', 'sponsor-manager' );
	} else if ( value && isInactive( selected ) ) {
		help = __(
			'Inactive until you update this page and the block still uses it. Assigned ads stay assigned.',
			'sponsor-manager'
		);
	} else if ( value ) {
		help = __(
			'Shows ads assigned to this placement plus ads assigned to Everywhere.',
			'sponsor-manager'
		);
	}

	return (
		<>
			<SelectControl
				label={ __( 'Placement', 'sponsor-manager' ) }
				value={ value }
				options={ options }
				onChange={ ( next ) => {
					if ( '__inactive__' === next ) {
						return;
					}
					setNotice( '' );
					onChange( next );
				} }
				help={ help }
				disabled={ busy }
			/>
			{ value && ! isProtected && (
				<TextControl
					label={ __( 'Placement name', 'sponsor-manager' ) }
					value={ rename }
					onChange={ setRename }
					disabled={ busy }
					help={ __(
						'Renaming keeps the same slot, so ads assigned to it stay assigned.',
						'sponsor-manager'
					) }
				/>
			) }
			{ value && ! isProtected && (
				<Button
					variant="secondary"
					onClick={ renamePlacement }
					disabled={ busy || ! rename.trim() || rename.trim() === selected?.name }
				>
					{ __( 'Update name', 'sponsor-manager' ) }
				</Button>
			) }
			<TextControl
				label={ __( 'New placement', 'sponsor-manager' ) }
				value={ newName }
				onChange={ setNewName }
				disabled={ busy }
				placeholder={ __( 'Home — above stories', 'sponsor-manager' ) }
				onKeyDown={ ( event ) => {
					if ( 'Enter' === event.key ) {
						event.preventDefault();
						createPlacement();
					}
				} }
				help={ __(
					'Pretty name for a new slot. The block switches to it after you create it.',
					'sponsor-manager'
				) }
			/>
			<Button
				variant="secondary"
				onClick={ createPlacement }
				disabled={ busy || ! newName.trim() }
			>
				{ __( 'Create placement', 'sponsor-manager' ) }
			</Button>
			{ notice && <p className="description">{ notice }</p> }
		</>
	);
}
