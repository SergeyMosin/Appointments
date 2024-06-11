const path = require('path')
const webpack = require('webpack')
const {VueLoaderPlugin} = require('vue-loader')

const scssDir = path.join(__dirname, 'scss')

module.exports = {
	entry: {
		script: path.join(__dirname, 'src', 'main.js'),
		form: path.join(__dirname, 'src', 'form.js'),
		cncf: path.join(__dirname, 'src', 'cncf.js'),
		form_css: path.join(scssDir, 'form.scss'),
		style_css: path.join(scssDir, 'style.scss'),
		hide_app_css: path.join(scssDir, 'hide-app.scss'),
	},
	output: {
		path: path.resolve(__dirname, './js'),
		publicPath: '/js/',
		filename: '[name].js',
		chunkFilename: 'chunks/appt.[name].[contenthash].js',
		clean: true,
	},
	module: {
		rules: [
			{
				test: /\.css$/,
				use: ['vue-style-loader', 'css-loader']
			},
			{
				// test: /\.scss$/,
				// match only vue's scss
				test: /^((?!.*scss\/)).*\.scss$/,
				use: ['vue-style-loader', 'css-loader', 'sass-loader']
			},
			{
				test: /scss\/.*\.scss$/,
				use: [
					{
						loader: 'file-loader',
						options: {
							outputPath: '../css/',
							name: '[name].css'
						}
					},
					{
						loader: 'sass-loader',
						options: this.mode === 'production' ? {
							sassOptions: {
								outputStyle: 'compressed',
							}
						} : {}
					}
				]
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
							'@babel/plugin-proposal-object-rest-spread'
						],
						presets: ['@babel/preset-env']
					}
				},
				exclude: /node_modules/
			},
			{
				test: /\.m?js/,
				resolve: {
					fullySpecified: false
				}
			}
		]
	},
	plugins: [
		new VueLoaderPlugin(),
		new webpack.DefinePlugin({
			appVersion: JSON.stringify(require('./package.json').version)
		}),
		new webpack.ProvidePlugin({
			process: 'process/browser',
		}),
	],
	resolve: {
		alias: {
			Components: path.resolve(__dirname, 'src/components/'),
			'vue$': 'vue/dist/vue.esm.js'
		},
		extensions: ['*', '.js', '.vue', '.json'],
		fallback: {
			"buffer": require.resolve("buffer/"),
			"path": false
		}
	}
}

