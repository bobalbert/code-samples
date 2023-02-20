/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import {
	useBlockProps,
	RichText,
	BlockControls,
	AlignmentToolbar,
	InspectorControls,
	PanelColorSettings,
	ContrastChecker
} from '@wordpress/block-editor';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';
import {appearance} from "../../../../wp-includes/js/codemirror/csslint";

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
export default function Edit( { attributes, setAttributes }) {
	const { text, alignment, backgroundColor, textColor } = attributes;

	const onChangeAlignment = ( newAlignment ) => {
		setAttributes( {alignment: newAlignment} );
	}

	const onChangeText = ( newText ) => {
		setAttributes({ text: newText } );
	}

	const onChangeBackgroundColor = ( newBgColor ) => {
		setAttributes( { backgroundColor: newBgColor } );
	}

	const onChangeTextColor = ( newTextColor ) => {
		setAttributes( { textColor: newTextColor } );
	}

	return (
		<>
			<InspectorControls>
				<PanelColorSettings
					title={__("Color settings", "ba" ) }
					icon="admin-appearance"
					initialOpen
					colorSettings={[
						{
							value:backgroundColor,
							onChange: onChangeBackgroundColor,
							label: __( 'Background Color', 'text-box' )
						},
						{
							value: textColor,
							onChange: onChangeTextColor,
							label: __( 'Text Color', 'text-box')
						}
					]}
				>
					<ContrastChecker
						textColor={textColor}
						backgroundColor={backgroundColor}
					/>
				</PanelColorSettings>
			</InspectorControls>
			<BlockControls>
				<AlignmentToolbar
					value={ alignment }
					onChange={ onChangeAlignment }
				/>
			</BlockControls>
			<RichText
				{ ...useBlockProps({
					className: `text-box-align-${alignment}`,
					style: {
						backgroundColor,
						color: textColor
					}
				}) }
				onChange={ onChangeText }
				placeholder={ __("Your Text", "ba") }
				tagName="h4"
				allowedFormats={ [] }
				value={ text }
			/>
		</>
	);
}
