const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const CopyPlugin = require( 'copy-webpack-plugin' );

module.exports = {
	...defaultConfig,
	entry: {
		index: path.resolve( __dirname, 'src', 'index.js' ),
		editor: path.resolve( __dirname, 'src', 'editor.js' ),
		'advanced-group-frontend': path.resolve( __dirname, 'src', 'controls', 'advanced-group', 'frontend.js' ),
		'animation-frontend': path.resolve( __dirname, 'src', 'controls', 'animation', 'frontend.js' ),
	},
	output: {
		...defaultConfig.output,
		publicPath: 'auto',
	},
	optimization: {
		...defaultConfig.optimization,
		// Keep chunk IDs as their webpackChunkName so dynamic CSS imports
		// resolve correctly across dev/prod builds.
		chunkIds: 'named',
	},
	plugins: [
		...( defaultConfig.plugins || [] ),
		new CopyPlugin( {
			patterns: [
				{
					from: path.resolve( __dirname, 'src', 'controls', 'cover-term-image', 'preview.webp' ),
					to: path.resolve( __dirname, 'build', 'images', 'preview.webp' ),
				},
				{
					from: path.resolve( __dirname, 'loader', 'icon-block-ollie', 'ollie-icons.json' ),
					to: path.resolve( __dirname, 'build', 'ollie-icons.json' ),
				},
			],
		} ),
	],
};