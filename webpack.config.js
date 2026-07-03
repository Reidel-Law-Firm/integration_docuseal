const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

// Override entry points
webpackConfig.entry = {
	adminSettings: path.join(__dirname, 'src', 'adminSettings.js'),
	filesplugin: path.join(__dirname, 'src', 'filesplugin.js'),
	sidebar: path.join(__dirname, 'src', 'sidebar.js'),
}

// Fix Webpack 5 ESM "fully specified" errors for axios, webdav, @vue/devtools-shared, etc.
webpackConfig.module = webpackConfig.module || { rules: [] }
webpackConfig.module.rules = webpackConfig.module.rules || []
webpackConfig.module.rules.push({
	test: /\.m?js$/,
	include: /node_modules/,
	resolve: { fullySpecified: false },
})

module.exports = webpackConfig
