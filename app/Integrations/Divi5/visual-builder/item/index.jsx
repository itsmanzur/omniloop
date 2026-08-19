import React from 'react';
import metadata from './module.json';

const LABELS = {
	image: 'Image',
	title: 'Title',
	excerpt: 'Excerpt',
	price: 'Price',
	rating: 'Rating',
	addToCart: 'Add to Cart',
	quickView: 'Quick View',
};

const attrValue = (attrs, key, fallback) => {
	const advanced = attrs?.module?.advanced?.[key]?.desktop?.value;
	if (advanced !== undefined && advanced !== null && advanced !== '') {
		return advanced;
	}
	return fallback;
};

const ItemEdit = ({ attrs }) => {
	const type = String(attrValue(attrs, 'elementType', 'title'));
	const label = LABELS[type] || type;

	return (
		<div className="omniloop-item-chip" data-element={type}>
			<span className="omniloop-item-chip__type">{label}</span>
			<span className="omniloop-item-chip__hint">Card element</span>
		</div>
	);
};

const itemModule = {
	metadata,
	renderers: {
		edit: ItemEdit,
	},
	placeholderContent: {
		module: {
			advanced: {
				elementType: {
					desktop: { value: 'title' },
				},
			},
		},
	},
};

const { addAction, addFilter } = window?.vendor?.wp?.hooks || {};

if (typeof addAction === 'function') {
	addAction('divi.moduleLibrary.registerModuleLibraryStore.after', 'omniloop.loopItem', () => {
		const { registerModule } = window?.divi?.moduleLibrary || {};
		if (typeof registerModule === 'function') {
			registerModule(itemModule.metadata, itemModule);
		}
	});
}

if (typeof addFilter === 'function') {
	addFilter('divi.iconLibrary.icon.map', 'omniloop.loopItem', (icons) => ({
		...icons,
		'omniloop/module-loop-item': {
			name: 'omniloop/module-loop-item',
			viewBox: '0 0 24 24',
			component: () => (
				<path d="M4 5h16v4H4V5zm0 6h10v3H4v-3zm0 5h16v3H4v-3z" />
			),
		},
	}));
}
