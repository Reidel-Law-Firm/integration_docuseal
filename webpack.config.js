const path = require('path')
const NodePolyfillPlugin = require('node-polyfill-webpack-plugin')
const webpackConfig = require('@nextcloud/webpack-vue-config')

// Entry points
webpackConfig.entry = {
	adminSettings: path.join(__dirname, 'src', 'adminSettings.js'),
	filesplugin: path.join(__dirname, 'src', 'filesplugin.js'),
	sidebar: path.join(__dirname, 'src', 'sidebar.js'),
}

// Polyfill Node built-ins (Buffer, process, …) used by axios 1.x / webdav.
webpackConfig.plugins = (webpackConfig.plugins || []).concat(new NodePolyfillPlugin())

// axios 1.x and a few @nextcloud/* chunks ship as strict ESM that import
// CommonJS modules without an explicit extension. Webpack 5 requires
// `fullySpecified: false` for those files or it refuses to resolve them.
webpackConfig.module = webpackConfig.module || {}
webpackConfig.module.rules = (webpackConfig.module.rules || []).concat([
	{
		test: /\.m?js$/,
		resolve: { fullySpecified: false },
	},
])

module.exports = webpackConfig
