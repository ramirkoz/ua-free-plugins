( function ( wp ) {
	'use strict';

	const { registerBlockType } = wp.blocks;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, TextControl, SelectControl, Notice } = wp.components;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;

	registerBlockType( 'kozcoac/copy-button', {
		edit( { attributes, setAttributes } ) {
			const { text, target, label, display, format } = attributes;
			const blockProps = useBlockProps();
			const isInline = 'inline' === display;
			const shown = label || ( isInline ? text : __( 'Copy', 'koz-copy-actions' ) );
			const missing = ! text && ! target;

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Copy settings', 'koz-copy-actions' ) },
						el( TextControl, {
							label: __( 'Value to copy', 'koz-copy-actions' ),
							value: text,
							onChange: ( value ) => setAttributes( { text: value } ),
						} ),
						el( TextControl, {
							label: __( 'Or copy from element ID', 'koz-copy-actions' ),
							help: __( 'For example #account-number. Used when the value is empty.', 'koz-copy-actions' ),
							value: target,
							onChange: ( value ) => setAttributes( { target: value } ),
						} ),
						el( TextControl, {
							label: __( 'Label', 'koz-copy-actions' ),
							value: label,
							onChange: ( value ) => setAttributes( { label: value } ),
						} ),
						el( SelectControl, {
							label: __( 'Display', 'koz-copy-actions' ),
							value: display,
							options: [
								{ label: __( 'Button', 'koz-copy-actions' ), value: 'button' },
								{ label: __( 'Inline text', 'koz-copy-actions' ), value: 'inline' },
							],
							onChange: ( value ) => setAttributes( { display: value } ),
						} ),
						el( SelectControl, {
							label: __( 'Copy format', 'koz-copy-actions' ),
							value: format,
							options: [
								{ label: __( 'Site default', 'koz-copy-actions' ), value: '' },
								{ label: __( 'Plain text', 'koz-copy-actions' ), value: 'text' },
								{ label: __( 'Formatted (HTML and text)', 'koz-copy-actions' ), value: 'html' },
							],
							onChange: ( value ) => setAttributes( { format: value } ),
						} )
					)
				),
				el(
					'div',
					blockProps,
					missing
						? el( Notice, { status: 'warning', isDismissible: false }, __( 'Set a value to copy or an element ID in the block settings.', 'koz-copy-actions' ) )
						: el(
							isInline ? 'span' : 'button',
							{ className: isInline ? 'kozcoac-copy-inline' : 'kozcoac-copy-button', type: isInline ? undefined : 'button' },
							shown || __( 'Copy', 'koz-copy-actions' )
						)
				)
			);
		},
		save() {
			return null;
		},
	} );
} )( window.wp );
