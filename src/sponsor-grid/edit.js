import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	Disabled,
	PanelBody,
	Placeholder,
	RadioControl,
	RangeControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import PlacementControl from '../shared/placement-control';
import StyleControls from '../shared/style-controls';
import metadata from './block.json';

function resolvedMaxItems( attributes ) {
	if ( attributes.maxItems > 0 ) {
		return attributes.maxItems;
	}
	const columns = attributes.columns ?? 4;
	const rows = attributes.rows ?? 1;
	return Math.max( 1, columns * rows );
}

function resolvedOrientation( attributes ) {
	if ( attributes.orientation === 'vertical' || attributes.orientation === 'horizontal' ) {
		return attributes.orientation;
	}
	return attributes.layout === 'vertical' ? 'vertical' : 'horizontal';
}

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const { placement } = attributes;
	const orientation = resolvedOrientation( attributes );
	const maxItems = resolvedMaxItems( attributes );

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Placement', 'sponsor-manager' ) }>
					<PlacementControl
						value={ placement }
						onChange={ ( value ) => setAttributes( { placement: value } ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Layout', 'sponsor-manager' ) }>
					<RadioControl
						label={ __( 'Direction', 'sponsor-manager' ) }
						selected={ orientation }
						options={ [
							{
								label: __( 'Horizontal', 'sponsor-manager' ),
								value: 'horizontal',
							},
							{
								label: __( 'Vertical', 'sponsor-manager' ),
								value: 'vertical',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( {
								orientation:
									value === 'vertical' ? 'vertical' : 'horizontal',
								maxItems,
							} )
						}
					/>
					<RangeControl
						label={ __( 'Maximum ads', 'sponsor-manager' ) }
						help={
							orientation === 'vertical'
								? __(
										'Ads stack in a column. Extra ads rotate in every two minutes.',
										'sponsor-manager'
								  )
								: __(
										'Ads sit in a row. Extra ads rotate in every two minutes.',
										'sponsor-manager'
								  )
						}
						value={ maxItems }
						onChange={ ( value ) =>
							setAttributes( { maxItems: value ?? 4 } )
						}
						min={ 1 }
						max={ 12 }
					/>
				</PanelBody>
				<StyleControls
					attributes={ attributes }
					setAttributes={ setAttributes }
				/>
			</InspectorControls>

			{ ! placement ? (
				<Placeholder
					icon="megaphone"
					label={ metadata.title }
					instructions={ __(
						'Choose a sponsor placement, then set horizontal or vertical and how many ads to show.',
						'sponsor-manager'
					) }
				>
					<PlacementControl
						value={ placement }
						onChange={ ( value ) => setAttributes( { placement: value } ) }
					/>
				</Placeholder>
			) : (
				<Disabled>
					<ServerSideRender
						block={ metadata.name }
						attributes={ attributes }
						httpMethod="POST"
						skipBlockSupportAttributes
					/>
				</Disabled>
			) }
		</div>
	);
}
