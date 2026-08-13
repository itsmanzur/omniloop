const path = require('path');

module.exports = {
	mode: 'production',
	entry: {
		loop: './loop/index.jsx',
	},
	externals: {
		react: 'React',
	},
	module: {
		rules: [
			{
				test: /\.jsx?$/,
				exclude: /node_modules/,
				use: {
					loader: 'babel-loader',
					options: {
						compact: false,
						presets: [
							['@babel/preset-env', { modules: false, targets: '> 5%' }],
							'@babel/preset-react',
						],
					},
				},
			},
		],
	},
	resolve: {
		extensions: ['.js', '.jsx'],
	},
	output: {
		filename: '[name].js',
		path: path.resolve(__dirname, 'build'),
	},
};
