const path = require('path')
const webpack = require('webpack')
const { VueLoaderPlugin } = require('vue-loader')

module.exports = {
	entry:{
		script: path.join(__dirname, 'src', 'main.js'),
		form: path.join(__dirname, 'src', 'form.js'),
	},
	output: {
		path: path.resolve(__dirname, './js'),
		publicPath: '/js/',
		filename: '[name].js',
		chunkFilename: 'chunks/appt.[name].[contenthash].js'
	},
	module: {
		rules: [
			{
				test: /\.css$/,
				use: ['vue-style-loader', 'css-loader']
			},
			{
				test: /\.scss$/,
				use: ['vue-style-loader', 'css-loader', 'sass-loader']
			},
			{
				test: /\.vue$/,
				loader: 'vue-loader'
			},
			{
				test: /\.js$/,
				use: {
					loader: 'babel-loader',
					options: {
						plugins: [
							'@babel/plugin-syntax-dynamic-import',
						],
						presets: ['@babel/preset-env']
					}
				},
				exclude: /node_modules/
			}
		]
	},
	plugins: [
		new VueLoaderPlugin(),
		new webpack.DefinePlugin({
			appVersion: JSON.stringify(require('./package.json').version)
		})
	]
	,
	resolve: {
		alias: {
			Components: path.resolve(__dirname, 'src/components/'),
		},
		extensions: ['*', '.js', '.vue', '.json']
	}
}

