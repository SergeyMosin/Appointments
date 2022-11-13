const {merge} = require('webpack-merge')
const common = require('./webpack.common.js')
const TerserPlugin = require('terser-webpack-plugin')

module.exports = merge(common, {
	mode: 'production',
	// devtool: '#source-map',
	optimization: {
		// splitChunks: {
		// 	cacheGroups: {
		// 		commons: {
		// 			test: /[\\/]node_modules[\\/]/,
		// 			name: 'vendors',
		// 			chunks: 'all',
		// 		},
		// 	},
		// },
		minimizer: [new TerserPlugin({
			terserOptions: {
				output: {
					comments: false,
				}
			},
		})],
	}
})
