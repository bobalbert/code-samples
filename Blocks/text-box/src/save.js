/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps, RichText, getColorClassName } from '@wordpress/block-editor';
import classNames from "classnames";

/**
 * The save function defines the way in which the different attributes should
 * be combined into the final markup, which is then serialized by the block
 * editor into `post_content`.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#save
 *
 * @return {WPElement} Element to render.
 */
export default function save( { attributes } ) {
	const { text, alignment, backgroundColor, textColor, customBackgroundColor, customTextColor } = attributes;

	const backgroundClass = getColorClassName(
		"background-color",
		backgroundColor
	);

	const textClass = getColorClassName(
		"color",
		textColor
	);

	const classes = classNames(`text-box-align-${alignment}`, {
		[textClass]: textClass,
		[backgroundClass]: backgroundClass
	});

	return (
		<RichText.Content
			{ ...useBlockProps.save({
				className: classes,
				style: {
					backgroundColor: backgroundClass ? undefined : customBackgroundColor,
					color: textClass ? undefined : customTextColor
				}
			}) }
			tagName="h4"
			value={text}
		/>
	);
}
