const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const CopyPlugin = require( 'copy-webpack-plugin' );

module.exports = {
	...defaultConfig,
	watchOptions: {
		...defaultConfig.watchOptions,
		ignored: /node_modules/,
		poll: 1000,
	},
	plugins: [
		...( defaultConfig.plugins || [] ),
		new CopyPlugin( {
			patterns: [
				{
					from: path.resolve( __dirname, 'src', 'carousel', 'arrow-icons.json' ),
					to: path.resolve( __dirname, 'build', 'carousel', 'arrow-icons.json' ),
				},
			],
		} ),
	],
};
