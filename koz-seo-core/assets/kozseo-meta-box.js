( function () {
	'use strict';

	const i18n = window.kozseoMetaBox || {};

	function init( box ) {
		const title = box.querySelector( '#kozseo_title' );
		const description = box.querySelector( '#kozseo_description' );
		const previewTitle = box.querySelector( '.kozseo-preview-title' );
		const previewDescription = box.querySelector( '.kozseo-preview-description' );

		function counter( field ) {
			const output = box.querySelector( '.kozseo-count[data-for="' + field.id + '"]' );
			if ( ! output ) {
				return;
			}
			const limit = parseInt( field.getAttribute( 'data-kozseo-limit' ), 10 ) || 0;
			const length = ( field.value || field.placeholder || '' ).length;
			output.textContent = length + ' ' + ( i18n.characters || 'characters' ) + ( limit && length > limit ? ' – ' + ( i18n.tooLong || '' ) : '' );
			output.style.color = limit && length > limit ? '#b32d2e' : '';
		}

		function refresh() {
			if ( previewTitle && title ) {
				previewTitle.textContent = title.value || title.placeholder || box.dataset.autoTitle || '';
			}
			if ( previewDescription && description ) {
				previewDescription.textContent = description.value || description.placeholder || box.dataset.autoDescription || '';
			}
			[ title, description ].forEach( function ( field ) {
				if ( field ) {
					counter( field );
				}
			} );
		}

		[ title, description ].forEach( function ( field ) {
			if ( field ) {
				field.addEventListener( 'input', refresh );
			}
		} );
		refresh();

		const idField = box.querySelector( '#kozseo_social_image_id' );
		const selectButton = box.querySelector( '.kozseo-image-select' );
		const removeButton = box.querySelector( '.kozseo-image-remove' );
		const imagePreview = box.querySelector( '.kozseo-image-preview' );
		let frame = null;

		if ( selectButton && idField && window.wp && window.wp.media ) {
			selectButton.addEventListener( 'click', function () {
				if ( ! frame ) {
					frame = window.wp.media( {
						title: i18n.chooseImage || 'Choose image',
						button: { text: i18n.useImage || 'Use this image' },
						library: { type: 'image' },
						multiple: false,
					} );
					frame.on( 'select', function () {
						const attachment = frame.state().get( 'selection' ).first().toJSON();
						idField.value = String( attachment.id );
						const url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
						imagePreview.innerHTML = '';
						const img = document.createElement( 'img' );
						img.src = url;
						img.alt = '';
						img.style.cssText = 'max-width:240px;height:auto;display:block;margin:6px 0';
						imagePreview.appendChild( img );
						if ( removeButton ) {
							removeButton.hidden = false;
						}
					} );
				}
				frame.open();
			} );
		}

		if ( removeButton && idField ) {
			removeButton.addEventListener( 'click', function () {
				idField.value = '';
				imagePreview.innerHTML = '';
				removeButton.hidden = true;
			} );
		}
	}

	function boot() {
		document.querySelectorAll( '.kozseo-meta' ).forEach( init );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
