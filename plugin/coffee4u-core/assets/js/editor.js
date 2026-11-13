( function ( blocks, element, ServerSideRender ) {
	const el = element.createElement;
	Object.keys( window.c4uBlocks || {} ).forEach( function ( slug ) {
		const name = 'coffee4u/' + slug;
		if ( blocks.getBlockType( name ) ) {
			blocks.unregisterBlockType( name );
		}
		blocks.registerBlockType( name, {
			apiVersion: 3,
			title: window.c4uBlocks[ slug ],
			category: 'widgets',
			icon: 'coffee',
			edit: function () {
				return el(
					'div',
					{ className: 'c4u-editor-preview' },
					el( ServerSideRender, {
						block: name,
						EmptyResponsePlaceholder: function () {
							return el( 'p', { className: 'c4u-editor-placeholder' }, window.c4uBlocks[ slug ] + ' (rendered on the live site)' );
						},
					} )
				);
			},
			save: function () {
				return null;
			},
		} );
	} );
} )( window.wp.blocks, window.wp.element, window.wp.serverSideRender );
