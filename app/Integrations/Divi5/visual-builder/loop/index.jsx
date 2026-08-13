import React, { useEffect, useState } from 'react';
import metadata from './module.json';

const { addAction, addFilter } = window?.vendor?.wp?.hooks;
const { ModuleContainer, StyleContainer, elementClassnames } = window?.divi?.module;
const { registerModule } = window?.divi?.moduleLibrary;

const fallbackTypeLabels = {
	post: 'Posts',
	product: 'Products',
	page: 'Pages',
};

const readVb = () => {
	const from = (w) => (w && (w.omniloopVb || w.OmniloopDiviVbLoopData)) || null;
	return from(window) || from(window.parent) || {};
};

const postTypeLabels = () => {
	const types = readVb().postTypes;
	if (types && typeof types === 'object' && Object.keys(types).length) {
		return types;
	}
	return fallbackTypeLabels;
};

const applyDynamicPostTypes = (meta) => {
	const types = postTypeLabels();
	const options = {};
	Object.keys(types).forEach((slug) => {
		options[slug] = { label: String(types[slug]) };
	});
	const item = meta?.attributes?.module?.settings?.advanced?.postType?.item;
	if (item?.component?.props && Object.keys(options).length) {
		item.component.props.options = options;
	}
	return meta;
};

const ModuleStyles = ({ attrs, elements, settings, mode, state, noStyleTag }) => (
	<StyleContainer mode={mode} state={state} noStyleTag={noStyleTag}>
		{elements.style({
			attrName: 'module',
			styleProps: {
				disabledOn: {
					disabledModuleVisibility: settings?.disabledModuleVisibility,
				},
			},
		})}
	</StyleContainer>
);

const ModuleScriptData = ({ elements }) => (
	<>{elements.scriptData({ attrName: 'module' })}</>
);

const moduleClassnames = ({ classnamesInstance, attrs }) => {
	classnamesInstance.add('omniloop-divi');
	classnamesInstance.add(
		elementClassnames({ attrs: attrs?.module?.decoration ?? {} })
	);
};

const attrValue = (attrs, key, fallback) =>
	attrs?.module?.advanced?.[key]?.desktop?.value ||
	attrs?.[key]?.innerContent?.desktop?.value ||
	fallback;

const CardMedia = ({ thumbnail, title }) => {
	if (thumbnail) {
		return (
			<div className="omniloop-card__media">
				<img src={thumbnail} alt={title || ''} width="600" height="450" />
			</div>
		);
	}
	const letter = (title || '•').charAt(0).toUpperCase();
	return (
		<div className="omniloop-card__media omniloop-card__media--empty">
			<span className="omniloop-card__fallback">{letter}</span>
		</div>
	);
};

const LiveGrid = ({ postType, perPage }) => {
	const previewCount = Math.min(8, Math.max(1, parseInt(perPage, 10) || 12));
	const [posts, setPosts] = useState([]);
	const labels = postTypeLabels();

	useEffect(() => {
		const cfg = readVb();
		const endpoint = cfg.restUrl || `${window.location.origin}/wp-json/omniloop/v1/query`;
		let url;
		try {
			url = new URL(endpoint, window.location.origin);
		} catch (e) {
			return undefined;
		}
		url.searchParams.set('post_type', postType);
		url.searchParams.set('per_page', String(previewCount));
		url.searchParams.set('page', '1');

		const headers = { Accept: 'application/json' };
		if (cfg.nonce) {
			headers['X-WP-Nonce'] = cfg.nonce;
		}

		let cancelled = false;
		fetch(url.toString(), { credentials: 'same-origin', headers })
			.then((res) => (res.ok ? res.json() : null))
			.then((data) => {
				if (cancelled || !data || data.status !== 'success') {
					return;
				}
				setPosts(Array.isArray(data.posts) ? data.posts : []);
			})
			.catch(() => {});

		return () => {
			cancelled = true;
		};
	}, [postType, previewCount]);

	if (!posts.length) {
		const items = [];
		for (let i = 0; i < previewCount; i += 1) {
			items.push(
				<article className="omniloop-card omniloop-card--vb" key={`ol-ph-${i}`}>
					<div className="omniloop-card__media omniloop-card__media--ph" />
					<h3 className="omniloop-card__title">{labels[postType] || postType} {i + 1}</h3>
				</article>
			);
		}
		return items;
	}

	return posts.map((item) => (
		<article className="omniloop-card" key={item.id}>
			<CardMedia thumbnail={item.thumbnail} title={item.title} />
			<h3 className="omniloop-card__title">{item.title}</h3>
			{item.price ? <p className="omniloop-card__price">{item.price}</p> : null}
		</article>
	));
};

const loopModule = {
	metadata,
	renderers: {
		edit: ({ attrs, id, name, elements }) => {
			const postType = attrValue(attrs, 'postType', 'post');
			const perPage = attrValue(attrs, 'perPage', '12');
			const columns = attrValue(attrs, 'columns', '3');
			const imageRatio = attrValue(attrs, 'imageRatio', '4-3');
			const showFilters = attrValue(attrs, 'showFilters', 'on');
			const filtersOn = showFilters !== 'off';
			const labels = postTypeLabels();
			const appClass = [
				'omniloop-app',
				'omniloop-app--vb',
				`omniloop-app--cols-${columns}`,
				`omniloop-app--ratio-${imageRatio}`,
				filtersOn ? '' : 'omniloop-app--no-filters',
			]
				.filter(Boolean)
				.join(' ');

			return (
				<ModuleContainer
					attrs={attrs}
					elements={elements}
					id={id}
					moduleClassName="ol_loop"
					name={name}
					scriptDataComponent={ModuleScriptData}
					stylesComponent={ModuleStyles}
					classnamesFunction={moduleClassnames}
				>
					{elements.styleComponents({ attrName: 'module' })}
					<div className="et_pb_module_inner">
						<div className={appClass}>
							{filtersOn ? (
								<aside className="omniloop-filters omniloop-filters--vb" aria-hidden="true">
									<p className="omniloop-filters__heading">Filters</p>
									<div className="omniloop-facet">
										<span className="omniloop-vb-chip">Search</span>
										<span className="omniloop-vb-chip">Category</span>
										<span className="omniloop-vb-chip">Sort</span>
									</div>
								</aside>
							) : null}
							<div className="omniloop-results">
								<p className="omniloop-count">
									{labels[postType] || postType} · {perPage} per page
								</p>
								<div className="omniloop-grid">
									<LiveGrid postType={postType} perPage={perPage} />
								</div>
							</div>
						</div>
					</div>
				</ModuleContainer>
			);
		},
	},
	placeholderContent: {
		module: {
			advanced: {
				postType: {
					desktop: { value: 'post' },
				},
				perPage: {
					desktop: { value: '12' },
				},
				showFilters: {
					desktop: { value: 'on' },
				},
				columns: {
					desktop: { value: '3' },
				},
				imageRatio: {
					desktop: { value: '4-3' },
				},
			},
		},
	},
};

addAction('divi.moduleLibrary.registerModuleLibraryStore.after', 'omniloop.loop', () => {
	registerModule(applyDynamicPostTypes(loopModule.metadata), loopModule);
});

addFilter('divi.iconLibrary.icon.map', 'omniloop.loop', (icons) => ({
	...icons,
	'omniloop/module-loop': {
		name: 'omniloop/module-loop',
		viewBox: '0 0 24 24',
		component: () => (
			<path d="M4 6h16v2H4V6zm0 5h10v2H4v-2zm0 5h16v2H4v-2z" />
		),
	},
}));
