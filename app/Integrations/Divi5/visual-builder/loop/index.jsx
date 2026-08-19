import React, { Component, useEffect, useRef, useState } from 'react';
import metadata from './module.json';
import '../item/index.jsx';

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
	if (types && typeof types === 'object' && !Array.isArray(types) && Object.keys(types).length) {
		return types;
	}
	return fallbackTypeLabels;
};

const filterPresetLabels = () => {
	const presets = readVb().filterPresets;
	if (presets && typeof presets === 'object' && !Array.isArray(presets) && Object.keys(presets).length) {
		return presets;
	}
	return { '': 'None' };
};

const applyDynamicPostTypes = (meta) => {
	let next = meta;
	try {
		next = JSON.parse(JSON.stringify(meta));
	} catch (e) {
		return applyConditionalVisibility(meta);
	}

	const types = postTypeLabels();
	const item = next?.attributes?.module?.settings?.advanced?.postType?.item;
	const existing = item?.component?.props?.options && typeof item.component.props.options === 'object'
		? item.component.props.options
		: {};
	const options = { ...existing };

	Object.keys(types).forEach((slug) => {
		const label = types[slug];
		if (typeof label === 'string' && slug) {
			options[slug] = { label };
		}
	});

	if (!options.product) {
		options.product = { label: 'Product' };
	}

	const ordered = {};
	if (options.product) {
		ordered.product = options.product;
	}
	Object.keys(options).forEach((slug) => {
		if (!ordered[slug]) {
			ordered[slug] = options[slug];
		}
	});

	if (item?.component?.props && Object.keys(ordered).length) {
		item.component.props.options = ordered;
	}

	if (ordered.product && next?.attributes?.module?.default?.advanced?.postType?.desktop) {
		next.attributes.module.default.advanced.postType.desktop.value = 'product';
	}

	try {
		const facets = readVb().facetOptions;
		const facetItem = next?.attributes?.module?.settings?.advanced?.visibleFacets?.item;
		if (facetItem?.component?.props && facets && typeof facets === 'object' && !Array.isArray(facets)) {
			const facetOpts = [];
			Object.keys(facets).forEach((key) => {
				const label = facets[key];
				if (key && typeof label === 'string') {
					facetOpts.push({ value: String(key), label: String(label) });
				}
			});
			if (facetOpts.length) {
				facetItem.component.props.options = facetOpts;
			}
		}
	} catch (e) {
		// Keep static checkbox options if live facet list fails.
	}

	try {
		const presets = filterPresetLabels();
		const presetItem = next?.attributes?.module?.settings?.advanced?.filterPreset?.item;
		if (presetItem?.component?.props && presets && typeof presets === 'object' && !Array.isArray(presets)) {
			const presetOpts = {};
			Object.keys(presets).forEach((id) => {
				const label = presets[id];
				if (typeof label === 'string') {
					presetOpts[id] = { label };
				}
			});
			if (Object.keys(presetOpts).length) {
				presetItem.component.props.options = presetOpts;
			}
		}
	} catch (e) {
		// Keep static preset options if bridge data fails.
	}

	return applyConditionalVisibility(next);
};

class PreviewBoundary extends Component {
	constructor(props) {
		super(props);
		this.state = { error: null };
	}

	static getDerivedStateFromError(error) {
		return { error };
	}

	render() {
		if (this.state.error) {
			return (
				<div className="omniloop-app omniloop-app--vb">
					<p className="omniloop-count">
						{String(this.state.error.message || this.state.error)}
					</p>
				</div>
			);
		}
		return this.props.children;
	}
}

const ModuleStyles = ({ attrs, elements, settings, mode, state, noStyleTag }) => {
	const { StyleContainer } = window?.divi?.module || {};
	if (!StyleContainer || !elements?.style) {
		return null;
	}

	return (
		<StyleContainer mode={mode} state={state} noStyleTag={noStyleTag}>
			{elements.style({
				attrName: 'module',
				styleProps: {
					disabledOn: {
						disabledModuleVisibility: settings?.disabledModuleVisibility,
					},
				},
			})}
			{elements.style({ attrName: 'card' })}
			{elements.style({ attrName: 'cardTitle' })}
			{elements.style({ attrName: 'cardPrice' })}
			{elements.style({ attrName: 'cardPriceRegular' })}
			{elements.style({ attrName: 'cardBadge' })}
			{elements.style({ attrName: 'filters' })}
		</StyleContainer>
	);
};

const ModuleScriptData = ({ elements }) => {
	if (!elements?.scriptData) {
		return null;
	}
	return <>{elements.scriptData({ attrName: 'module' })}</>;
};

const moduleClassnames = ({ classnamesInstance, attrs }) => {
	const { elementClassnames } = window?.divi?.module || {};
	classnamesInstance.add('omniloop-divi');
	if (typeof elementClassnames !== 'function') {
		return;
	}
	try {
		classnamesInstance.add(
			elementClassnames({ attrs: attrs?.module?.decoration ?? {} })
		);
	} catch (e) {
		// Ignore decoration class errors so the canvas still paints.
	}
};

const attrValue = (attrs, key, fallback) => {
	const advanced = attrs?.module?.advanced?.[key]?.desktop?.value;
	if (advanced !== undefined && advanced !== null && advanced !== '') {
		return advanced;
	}
	const legacy = attrs?.[key]?.innerContent?.desktop?.value;
	if (legacy !== undefined && legacy !== null && legacy !== '') {
		return legacy;
	}
	return fallback;
};

const fieldAttrValue = (params, key, fallback) => {
	const fromAttrs = attrValue(params?.attrs, key, undefined);
	if (fromAttrs !== undefined && fromAttrs !== null && fromAttrs !== '') {
		return fromAttrs;
	}
	return attrValue(params?.defaultSettingsAttrs, key, fallback);
};

const isCarouselStyle = (params) => {
	if (params?.isActivePreset) {
		return true;
	}
	return fieldAttrValue(params, 'cardStyle', 'classic') === 'carousel';
};

const isCarouselAutoplayOn = (params) => {
	if (!isCarouselStyle(params)) {
		return false;
	}
	return fieldAttrValue(params, 'carouselAutoplay', 'off') === 'on';
};

const isNotListStyle = (params) => {
	if (params?.isActivePreset) {
		return true;
	}
	return fieldAttrValue(params, 'cardStyle', 'classic') !== 'list';
};

const isPaginationOn = (params) => {
	if (params?.isActivePreset) {
		return true;
	}
	return fieldAttrValue(params, 'showPagination', 'on') === 'on';
};

const isPaginationStyleVisible = (params) => {
	if (!isPaginationOn(params)) {
		return false;
	}
	return !isCarouselStyle(params);
};

const isFilterSyncOn = (params) => {
	if (params?.isActivePreset) {
		return true;
	}
	return fieldAttrValue(params, 'filterSync', 'on') === 'on';
};

const isMerchandisingOn = (params) => {
	if (params?.isActivePreset) {
		return true;
	}
	return fieldAttrValue(params, 'merchandising', 'on') === 'on';
};

const setFieldVisible = (item, visible) => {
	if (item && typeof item === 'object') {
		item.visible = visible;
	}
};

const setGroupVisible = (group, visible) => {
	if (!group || typeof group !== 'object') {
		return;
	}
	// Divi ModuleGroups ignores group.visible; it only honors component.props.visible as a function.
	group.visible = visible;
	if (!group.component) {
		group.component = { name: 'divi/composite', props: {} };
	}
	if (!group.component.props) {
		group.component.props = {};
	}
	group.component.props.visible = visible;
};

const applyConditionalVisibility = (meta) => {
	if (!meta || typeof meta !== 'object') {
		return meta;
	}

	const advanced = meta?.attributes?.module?.settings?.advanced;
	if (advanced) {
		[
			'carouselAutoplay',
			'carouselSpeed',
			'carouselPauseHover',
			'carouselLoop',
			'carouselArrows',
			'carouselDots',
		].forEach((key) => {
			setFieldVisible(advanced[key]?.item, isCarouselStyle);
		});
		setFieldVisible(advanced.carouselSpeed?.item, isCarouselAutoplayOn);
		setFieldVisible(advanced.carouselPauseHover?.item, isCarouselAutoplayOn);
		setFieldVisible(advanced.cardColumns?.item, isNotListStyle);
		setFieldVisible(advanced.paginationStyle?.item, isPaginationStyleVisible);
		setFieldVisible(advanced.syncGroup?.item, isFilterSyncOn);
		setFieldVisible(advanced.extraPinIds?.item, isMerchandisingOn);
		setFieldVisible(advanced.stockUrgency?.item, isMerchandisingOn);
		setFieldVisible(advanced.promotedBadge?.item, isMerchandisingOn);
	}

	setGroupVisible(meta?.settings?.groups?.carouselOptions, isCarouselStyle);

	return meta;
};

const applyGroupFieldVisibility = (groups) => {
	if (!groups || typeof groups !== 'object') {
		return groups;
	}
	setGroupVisible(groups.carouselOptions, isCarouselStyle);

	const carouselFields = groups.carouselOptions?.component?.props?.fields;
	if (carouselFields && typeof carouselFields === 'object') {
		Object.keys(carouselFields).forEach((key) => {
			const field = carouselFields[key];
			if (!field || typeof field !== 'object') {
				return;
			}
			const name = String(key).toLowerCase();
			if (name.includes('speed') || name.includes('pausehover') || name.includes('pause')) {
				field.visible = isCarouselAutoplayOn;
			} else {
				field.visible = isCarouselStyle;
			}
		});
	}

	const layoutFields = groups.loopLayout?.component?.props?.fields;
	if (layoutFields && typeof layoutFields === 'object') {
		Object.keys(layoutFields).forEach((key) => {
			const field = layoutFields[key];
			if (!field || typeof field !== 'object') {
				return;
			}
			if (String(key).toLowerCase().includes('cardcolumns') || String(key).toLowerCase().includes('columns')) {
				field.visible = isNotListStyle;
			}
		});
	}

	const queryFields = groups.loopQuery?.component?.props?.fields;
	if (queryFields && typeof queryFields === 'object') {
		Object.keys(queryFields).forEach((key) => {
			const field = queryFields[key];
			if (!field || typeof field !== 'object') {
				return;
			}
			if (String(key).toLowerCase().includes('paginationstyle')) {
				field.visible = isPaginationStyleVisible;
			}
			if (String(key).toLowerCase().includes('syncgroup')) {
				field.visible = isFilterSyncOn;
			}
			if (
				String(key).toLowerCase().includes('extrapinids') ||
				String(key).toLowerCase().includes('stockurgency') ||
				String(key).toLowerCase().includes('promotedbadge')
			) {
				field.visible = isMerchandisingOn;
			}
		});
	}

	return groups;
};

const CardMedia = ({ thumbnail, title, onSale, salePercent, promotedBadge, stockUrgency }) => {
	const safeTitle = typeof title === 'string' ? title : '';
	const pct = Number(salePercent) || 0;
	const badges = [];
	if (promotedBadge) {
		badges.push(
			<span key="promo" className="omniloop-card__badge omniloop-card__badge--promo">{String(promotedBadge)}</span>
		);
	}
	if (onSale) {
		badges.push(
			<span key="sale" className="omniloop-card__badge">{pct > 0 ? `-${pct}%` : 'Sale'}</span>
		);
	}
	if (stockUrgency) {
		badges.push(
			<span key="urgency" className="omniloop-card__badge omniloop-card__badge--urgency">{String(stockUrgency)}</span>
		);
	}
	const badge = badges.length ? <span className="omniloop-card__badges">{badges}</span> : null;
	if (thumbnail) {
		return (
			<div className="omniloop-card__media">
				{badge}
				<img src={thumbnail} alt={safeTitle} width="600" height="450" />
			</div>
		);
	}
	const letter = (safeTitle || '•').charAt(0).toUpperCase();
	return (
		<div className="omniloop-card__media omniloop-card__media--empty">
			{badge}
			<span className="omniloop-card__fallback">{letter}</span>
		</div>
	);
};

const FACET_FALLBACK = {
	product_cat: 'Category',
	product_tag: 'Tag',
	product_brand: 'Brand',
	_stock_status: 'Stock',
	on_sale: 'On sale',
	featured: 'Featured',
	rating: 'Rating',
};

const VALUE_FALLBACK = {
	instock: 'In stock',
	outofstock: 'Out of stock',
	onbackorder: 'On backorder',
};

const facetLabel = (key, labels) => {
	if (labels && typeof labels[key] === 'string' && labels[key]) {
		return labels[key];
	}
	if (FACET_FALLBACK[key]) {
		return FACET_FALLBACK[key];
	}
	if (key.indexOf('pa_') === 0) {
		return key.slice(3).replace(/-/g, ' ');
	}
	return key.replace(/_/g, ' ');
};

const valueLabel = (key, value) => {
	if (value === 'yes' && (key === 'on_sale' || key === 'featured')) {
		return FACET_FALLBACK[key] || value;
	}
	if (key === 'rating') {
		const stars = parseInt(value, 10);
		if (stars === 1) {
			return '1 star';
		}
		if (stars >= 2 && stars <= 5) {
			return `${stars} stars`;
		}
	}
	if (VALUE_FALLBACK[value]) {
		return VALUE_FALLBACK[value];
	}
	return String(value).replace(/-/g, ' ');
};

const normalizeVisible = (value) => {
	if (Array.isArray(value)) {
		return value
			.map((item) => {
				if (item && typeof item === 'object' && item.value != null) {
					return String(item.value).trim();
				}
				return String(item || '').trim();
			})
			.filter(Boolean);
	}
	if (typeof value !== 'string') {
		return [];
	}
	const raw = value.trim();
	if (!raw || raw.toLowerCase() === 'all') {
		return [];
	}
	return raw.split(/[\s,|]+/).map((item) => item.trim()).filter(Boolean);
};

const isFacetVisible = (key, visible) => !visible.length || visible.indexOf(key) !== -1;

const swatchMode = (key, ui) => {
	if (ui && ui[key] && (ui[key].mode === 'color' || ui[key].mode === 'size' || ui[key].mode === 'rating' || ui[key].mode === 'image')) {
		return ui[key].mode;
	}
	if (key === 'rating') {
		return 'rating';
	}
	if (key === 'pa_color' || key === 'pa_colour') {
		return 'color';
	}
	if (key === 'pa_size') {
		return 'size';
	}
	return '';
};

const ratingEntries = (map) => {
	if (!map || typeof map !== 'object') {
		return [];
	}
	return ['5', '4', '3', '2', '1']
		.filter((value) => map[value] != null)
		.map((value) => [value, map[value]]);
};

const Stars = ({ count }) => {
	const n = Math.max(1, Math.min(5, parseInt(count, 10) || 0));
	const marks = [];
	for (let i = 1; i <= 5; i += 1) {
		marks.push(
			<span className={i <= n ? 'omniloop-stars__on' : 'omniloop-stars__off'} key={`star-${i}`}>★</span>
		);
	}
	return <span className="omniloop-stars" aria-hidden="true">{marks}</span>;
};

const topFacetEntries = (map, limit = 8) => {
	if (!map || typeof map !== 'object') {
		return [];
	}
	return Object.keys(map)
		.map((value) => [value, map[value]])
		.sort((a, b) => Number(b[1]) - Number(a[1]))
		.slice(0, limit);
};

const useLoopQuery = (postType, perPage, queryParts) => {
	const previewCount = Math.min(8, Math.max(1, parseInt(perPage, 10) || 12));
	const [data, setData] = useState(null);
	const facets = queryParts && queryParts.facets && typeof queryParts.facets === 'object' ? queryParts.facets : {};
	const search = queryParts && typeof queryParts.search === 'string' ? queryParts.search : '';
	const facetKey = JSON.stringify(facets);

	useEffect(() => {
		const cfg = readVb();
		const endpoint = cfg.restUrl || `${window.location.origin}/wp-json/omniloop/v1/query`;
		const headers = {
			Accept: 'application/json',
			'Content-Type': 'application/json',
		};
		if (cfg.nonce) {
			headers['X-WP-Nonce'] = cfg.nonce;
		}

		let cancelled = false;
		const ac = typeof AbortController === 'function' ? new AbortController() : null;
		fetch(endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers,
			body: JSON.stringify({
				post_type: postType || 'post',
				per_page: previewCount,
				page: 1,
				search,
				sort_by: 'date',
				facets,
			}),
			signal: ac ? ac.signal : undefined,
		})
			.then((res) => (res.ok ? res.json() : null))
			.then((payload) => {
				if (cancelled || !payload || payload.status !== 'success') {
					return;
				}
				setData(payload);
			})
			.catch(() => {});

		return () => {
			cancelled = true;
			if (ac) {
				ac.abort();
			}
		};
	}, [postType, previewCount, search, facetKey]);

	return { data, previewCount };
};

const stopBuilder = (event) => {
	if (event && event.stopPropagation) {
		event.stopPropagation();
	}
};

const VbFilters = ({ postType, data, visibleFacets, selected, search, priceFrom, priceTo, onToggle, onSearch, onPrice }) => {
	const facets = data?.facets_counts && typeof data.facets_counts === 'object' ? data.facets_counts : {};
	const labels = data?.facet_labels && typeof data.facet_labels === 'object' ? data.facet_labels : {};
	const ui = data?.facet_ui && typeof data.facet_ui === 'object' ? data.facet_ui : {};
	const visible = normalizeVisible(visibleFacets);
	const keys = Object.keys(facets).filter((key) => isFacetVisible(key, visible));
	const min = Math.floor(Number(data?.price_min) || 0);
	const max = Math.ceil(Number(data?.price_max) || 0);
	const rangeSpan = max - min;
	const currency = typeof data?.currency === 'string' ? data.currency : '';
	const showPrice = postType === 'product' && max > 0 && isFacetVisible('price', visible);
	const currentFrom = priceFrom == null ? min : priceFrom;
	const currentTo = priceTo == null ? max : priceTo;
	const isOn = (key, value) => Array.isArray(selected[key]) && selected[key].indexOf(value) !== -1;

	return (
		<div className="omniloop-filters omniloop-filters--vb" onMouseDown={stopBuilder} onClick={stopBuilder} onPointerDown={stopBuilder}>
			<p className="omniloop-filters__heading">Filters</p>
			<label className="omniloop-search">
				<span className="screen-reader-text">Search</span>
				<input
					type="search"
					className="omniloop-search__input"
					placeholder="Search…"
					value={search}
					onChange={(event) => onSearch(event.target.value)}
				/>
			</label>
			{showPrice ? (
				<div className="omniloop-facet" role="group" aria-label="Price">
					<div className="omniloop-facet__title">Price</div>
					<div className="omniloop-price" data-currency={currency} data-bound-min={min} data-bound-max={max}>
						<div className="omniloop-price__inputs">
							<label className="omniloop-price__field">
								<span>From</span>
								<input
									type="number"
									className="omniloop-price__min"
									min={min}
									max={max}
									step="1"
									value={currentFrom}
									aria-label="Minimum price"
									onChange={(event) => onPrice({ from: Number(event.target.value), to: currentTo })}
								/>
							</label>
							<span className="omniloop-price__sep" aria-hidden="true">–</span>
							<label className="omniloop-price__field">
								<span>To</span>
								<input
									type="number"
									className="omniloop-price__max"
									min={min}
									max={max}
									step="1"
									value={currentTo}
									aria-label="Maximum price"
									onChange={(event) => onPrice({ from: currentFrom, to: Number(event.target.value) })}
								/>
							</label>
						</div>
						<div
							className="omniloop-price__slider"
							style={{
								'--ol-range-lo': `${rangeSpan > 0 ? (((currentFrom - min) / rangeSpan) * 100) : 0}%`,
								'--ol-range-hi': `${rangeSpan > 0 ? (((currentTo - min) / rangeSpan) * 100) : 100}%`,
							}}
						>
							<div className="omniloop-price__track" aria-hidden="true">
								<div className="omniloop-price__fill" />
							</div>
							<input
								type="range"
								className="omniloop-price__thumb omniloop-price__thumb--min"
								min={min}
								max={max}
								step="1"
								value={currentFrom}
								aria-label="Minimum price"
								onChange={(event) => {
									const next = Number(event.target.value);
									onPrice({ from: Math.min(next, currentTo), to: currentTo });
								}}
							/>
							<input
								type="range"
								className="omniloop-price__thumb omniloop-price__thumb--max"
								min={min}
								max={max}
								step="1"
								value={currentTo}
								aria-label="Maximum price"
								onChange={(event) => {
									const next = Number(event.target.value);
									onPrice({ from: currentFrom, to: Math.max(next, currentFrom) });
								}}
							/>
						</div>
					</div>
				</div>
			) : null}
			{keys.map((key) => {
				const mode = swatchMode(key, ui);
				const entries = mode === 'rating'
					? ratingEntries(facets[key])
					: topFacetEntries(facets[key], mode ? 24 : 8);
				if (!entries.length) {
					return null;
				}
				const colors = ui[key] && ui[key].colors && typeof ui[key].colors === 'object' ? ui[key].colors : {};
				const images = ui[key] && ui[key].images && typeof ui[key].images === 'object' ? ui[key].images : {};
				const facetClass = mode === 'color'
					? 'omniloop-facet omniloop-facet--swatch-color'
					: mode === 'image'
						? 'omniloop-facet omniloop-facet--swatch-image'
						: mode === 'size'
							? 'omniloop-facet omniloop-facet--swatch-size'
							: mode === 'rating'
								? 'omniloop-facet omniloop-facet--rating'
								: 'omniloop-facet';
				return (
					<div className={facetClass} role="group" aria-label={facetLabel(key, labels)} key={key}>
						<div className="omniloop-facet__title">{facetLabel(key, labels)}</div>
						<div className="omniloop-facet__list">
							{entries.map(([value, count]) => {
								const name = valueLabel(key, value);
								const hex = typeof colors[value] === 'string' ? colors[value] : '';
								const image = typeof images[value] === 'string' ? images[value] : '';
								const box = (
									<input
										type="checkbox"
										checked={isOn(key, value)}
										onChange={() => onToggle(key, String(value))}
									/>
								);
								if (mode === 'color' || mode === 'image') {
									const checkClass = mode === 'image' ? 'omniloop-check omniloop-check--image' : 'omniloop-check omniloop-check--color';
									if (image) {
										return (
											<label className={checkClass} key={`${key}-${value}`} title={`${name} (${count})`}>
												{box}
												<i className="omniloop-swatch omniloop-swatch--image" aria-hidden="true">
													<img src={image} alt="" width="40" height="40" loading="lazy" decoding="async" />
												</i>
												<span className="screen-reader-text">{name}</span>
												<em>{count}</em>
											</label>
										);
									}
									if (mode === 'color') {
										const swatchStyle = hex ? { '--ol-swatch': hex } : undefined;
										return (
											<label className="omniloop-check omniloop-check--color" key={`${key}-${value}`} title={`${name} (${count})`} style={swatchStyle}>
												{box}
												<i className="omniloop-swatch" aria-hidden="true">{hex ? '' : name.charAt(0).toUpperCase()}</i>
												<span className="screen-reader-text">{name}</span>
												<em>{count}</em>
											</label>
										);
									}
									return (
										<label className="omniloop-check omniloop-check--image" key={`${key}-${value}`} title={`${name} (${count})`}>
											{box}
											<i className="omniloop-swatch omniloop-swatch--image omniloop-swatch--empty" aria-hidden="true">{name.charAt(0).toUpperCase()}</i>
											<span className="screen-reader-text">{name}</span>
											<em>{count}</em>
										</label>
									);
								}
								if (mode === 'size') {
									return (
										<label className="omniloop-check omniloop-check--size" key={`${key}-${value}`} title={`${name} (${count})`}>
											{box}
											<span>{name}</span>
											<em>{count}</em>
										</label>
									);
								}
								if (mode === 'rating') {
									return (
										<label className="omniloop-check omniloop-check--rating" key={`${key}-${value}`} title={`${name} (${count})`}>
											{box}
											<Stars count={value} />
											<span>{name}</span>
											<em>{count}</em>
										</label>
									);
								}
								return (
									<label className="omniloop-check" key={`${key}-${value}`}>
										{box}
										<span>{name}</span>
										<em>{count}</em>
									</label>
								);
							})}
						</div>
					</div>
				);
			})}
		</div>
	);
};

const ViewToggle = ({ view, onChange }) => (
	<div className="omniloop-view" role="group" aria-label="Layout" onMouseDown={stopBuilder} onClick={stopBuilder} onPointerDown={stopBuilder}>
		<button
			type="button"
			className={`omniloop-view__btn${view === 'grid' ? ' is-active' : ''}`}
			aria-pressed={view === 'grid'}
			aria-label="Grid view"
			onClick={() => onChange('grid')}
		>
			<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><rect x="1" y="1" width="6" height="6" rx="1" fill="currentColor" /><rect x="9" y="1" width="6" height="6" rx="1" fill="currentColor" /><rect x="1" y="9" width="6" height="6" rx="1" fill="currentColor" /><rect x="9" y="9" width="6" height="6" rx="1" fill="currentColor" /></svg>
		</button>
		<button
			type="button"
			className={`omniloop-view__btn${view === 'list' ? ' is-active' : ''}`}
			aria-pressed={view === 'list'}
			aria-label="List view"
			onClick={() => onChange('list')}
		>
			<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><rect x="1" y="2" width="14" height="2.4" rx="0.6" fill="currentColor" /><rect x="1" y="6.8" width="14" height="2.4" rx="0.6" fill="currentColor" /><rect x="1" y="11.6" width="14" height="2.4" rx="0.6" fill="currentColor" /></svg>
		</button>
	</div>
);

const EmptyState = ({ style, title, message, resetLabel, showReset, onReset }) => {
	const iconPaths = {
		box: '<rect x="4" y="7" width="16" height="13" rx="2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M4 11h16M12 7v13M8.5 7l1-3h5l1 3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
		search: '<circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M16 16l4.5 4.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
		bag: '<path d="M6 9h12l-1 11H7L6 9z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 9V7.5a3 3 0 0 1 6 0V9" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
	};
	const safeStyle = style === 'minimal' || iconPaths[style] ? style : 'box';
	const path = iconPaths[safeStyle];

	return (
		<div className={`omniloop-empty omniloop-empty--${safeStyle}`} role="status">
			{path ? (
				<span
					className="omniloop-empty__icon"
					aria-hidden="true"
					dangerouslySetInnerHTML={{
						__html: `<svg width="40" height="40" viewBox="0 0 24 24" focusable="false">${path}</svg>`,
					}}
				/>
			) : null}
			<p className="omniloop-empty__title">{title || 'No results found'}</p>
			{message ? <p className="omniloop-empty__message">{message}</p> : null}
			{showReset ? (
				<button type="button" className="omniloop-empty__reset" onMouseDown={stopBuilder} onClick={onReset}>
					{resetLabel || 'Reset filters'}
				</button>
			) : null}
		</div>
	);
};

const LiveGrid = ({ postType, posts, previewCount, queried, emptyProps, showAddToCart, showQuickView, onQuickView }) => {
	const labels = postTypeLabels();
	const items = Array.isArray(posts) ? posts : [];

	if (!items.length) {
		if (queried) {
			return <EmptyState {...emptyProps} />;
		}
		const placeholders = [];
		for (let i = 0; i < previewCount; i += 1) {
			placeholders.push(
				<div className="omniloop-card omniloop-card--vb" key={`ol-ph-${i}`}>
					<div className="omniloop-card__media omniloop-card__media--ph" />
					<div className="omniloop-card__body">
						<h3 className="omniloop-card__title">{labels[postType] || postType} {i + 1}</h3>
					</div>
				</div>
			);
		}
		return <>{placeholders}</>;
	}

	return (
		<>
			{items.map((item) => {
				const atc = item.addToCart && typeof item.addToCart === 'object' ? item.addToCart : null;
				return (
					<div className="omniloop-card" key={item.id}>
						<a className="omniloop-card__link" href={item.permalink || '#'} onMouseDown={stopBuilder} onClick={stopBuilder}>
							<CardMedia thumbnail={item.thumbnail} title={item.title} onSale={item.onSale} salePercent={item.salePercent} promotedBadge={item.promotedBadge} stockUrgency={item.stockUrgency} />
							<div className="omniloop-card__body">
								<h3 className="omniloop-card__title">{item.title}</h3>
								{item.excerpt ? (
									<p className="omniloop-card__excerpt">{item.excerpt}</p>
								) : null}
								{item.price ? (
									<p className="omniloop-card__price">
										{item.priceRegular ? <s className="omniloop-card__price-regular">{item.priceRegular}</s> : null}
										{item.priceRegular ? ' ' : ''}
										{item.price}
									</p>
								) : null}
							</div>
						</a>
						{(showQuickView || (showAddToCart && atc && atc.url)) ? (
							<div className="omniloop-card__actions">
								{showQuickView ? (
									<button
										type="button"
										className="omniloop-card__qv"
										onMouseDown={stopBuilder}
										onClick={(event) => {
											stopBuilder(event);
											if (typeof onQuickView === 'function') onQuickView(item);
										}}
									>
										Quick view
									</button>
								) : null}
								{showAddToCart && atc && atc.url ? (
									<a
										href={atc.url}
										className={`omniloop-card__atc button product_type_${atc.type || 'simple'} add_to_cart_button${atc.ajax ? ' ajax_add_to_cart' : ''}`}
										data-quantity="1"
										data-product_id={atc.id}
										data-product_sku={atc.sku || ''}
										rel="nofollow"
										onMouseDown={stopBuilder}
										onClick={stopBuilder}
									>
										{atc.label || 'Add to cart'}
									</a>
								) : null}
							</div>
						) : null}
					</div>
				);
			})}
		</>
	);
};

const VbCarousel = ({
	columns,
	autoplay,
	speed,
	pauseHover,
	loop,
	arrows,
	dots,
	children,
}) => {
	const gridRef = useRef(null);
	const [pages, setPages] = useState(1);
	const [active, setActive] = useState(0);
	const pausedRef = useRef(false);

	const measure = () => {
		const grid = gridRef.current;
		if (!grid) return;
		const card = grid.querySelector('.omniloop-card');
		const styles = window.getComputedStyle(grid);
		const gap = parseFloat(styles.columnGap || styles.gap || '0') || 0;
		const step = card ? card.getBoundingClientRect().width + gap : Math.max(grid.clientWidth, 1);
		const maxScroll = Math.max(0, grid.scrollWidth - grid.clientWidth);
		const nextPages = step > 0 ? Math.max(1, Math.ceil((maxScroll + step) / step)) : 1;
		const nextActive = step > 0 ? Math.round(grid.scrollLeft / step) : 0;
		setPages(maxScroll <= 2 ? 1 : nextPages);
		setActive(Math.max(0, Math.min(nextPages - 1, nextActive)));
	};

	const go = (dir) => {
		const grid = gridRef.current;
		if (!grid) return;
		const card = grid.querySelector('.omniloop-card');
		const styles = window.getComputedStyle(grid);
		const gap = parseFloat(styles.columnGap || styles.gap || '0') || 0;
		const step = card ? card.getBoundingClientRect().width + gap : Math.max(grid.clientWidth, 1);
		const maxScroll = Math.max(0, grid.scrollWidth - grid.clientWidth);
		if (maxScroll <= 2) return;
		const left = grid.scrollLeft;
		let target = left + dir * step;
		if (loop) {
			if (dir > 0 && left >= maxScroll - 2) target = 0;
			else if (dir < 0 && left <= 2) target = maxScroll;
		} else {
			target = Math.max(0, Math.min(maxScroll, target));
		}
		grid.scrollTo({ left: target, behavior: 'smooth' });
	};

	const goTo = (index) => {
		const grid = gridRef.current;
		if (!grid) return;
		const card = grid.querySelector('.omniloop-card');
		const styles = window.getComputedStyle(grid);
		const gap = parseFloat(styles.columnGap || styles.gap || '0') || 0;
		const step = card ? card.getBoundingClientRect().width + gap : Math.max(grid.clientWidth, 1);
		grid.scrollTo({ left: index * step, behavior: 'smooth' });
	};

	useEffect(() => {
		const grid = gridRef.current;
		if (!grid) return undefined;
		const onScroll = () => {
			window.clearTimeout(grid._olVbScroll);
			grid._olVbScroll = window.setTimeout(measure, 50);
		};
		grid.addEventListener('scroll', onScroll);
		const ro = typeof ResizeObserver === 'function' ? new ResizeObserver(measure) : null;
		if (ro) ro.observe(grid);
		window.setTimeout(measure, 80);
		window.setTimeout(measure, 400);
		return () => {
			grid.removeEventListener('scroll', onScroll);
			if (ro) ro.disconnect();
		};
	}, [children, columns]);

	useEffect(() => {
		if (!autoplay) return undefined;
		const ms = Math.max(1000, Number(speed) || 4000);
		const timer = window.setInterval(() => {
			if (pausedRef.current || document.hidden) return;
			go(1);
		}, ms);
		return () => window.clearInterval(timer);
	}, [autoplay, speed, loop, children]);

	const showDots = dots && pages > 1;
	const dotsList = [];
	for (let i = 0; i < pages; i += 1) {
		dotsList.push(i);
	}

	return (
		<div
			className="omniloop-carousel"
			data-cols={columns}
			data-autoplay={autoplay ? '1' : '0'}
			onMouseEnter={() => { if (pauseHover) pausedRef.current = true; }}
			onMouseLeave={() => { pausedRef.current = false; }}
		>
			<button
				type="button"
				className="omniloop-carousel__btn omniloop-carousel__btn--prev"
				aria-label="Previous products"
				hidden={!arrows}
				onMouseDown={stopBuilder}
				onClick={(event) => {
					stopBuilder(event);
					go(-1);
				}}
			>
				<svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><path d="M11.5 3.5L6 9l5.5 5.5" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" /></svg>
			</button>
			<div className="omniloop-carousel__viewport">
				<div className="omniloop-grid" ref={gridRef}>
					{children}
				</div>
			</div>
			<button
				type="button"
				className="omniloop-carousel__btn omniloop-carousel__btn--next"
				aria-label="Next products"
				hidden={!arrows}
				onMouseDown={stopBuilder}
				onClick={(event) => {
					stopBuilder(event);
					go(1);
				}}
			>
				<svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><path d="M6.5 3.5L12 9l-5.5 5.5" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" /></svg>
			</button>
			<div className="omniloop-carousel__dots" hidden={!showDots}>
				{showDots ? dotsList.map((index) => (
					<button
						type="button"
						key={`dot-${index}`}
						className={`omniloop-carousel__dot${index === active ? ' is-active' : ''}`}
						aria-label={`Slide ${index + 1}`}
						onMouseDown={stopBuilder}
						onClick={(event) => {
							stopBuilder(event);
							goTo(index);
						}}
					/>
				)) : null}
			</div>
		</div>
	);
};

const LoopEdit = ({ attrs, id, name, elements }) => {
	const { ModuleContainer } = window?.divi?.module || {};
	const postType = attrValue(attrs, 'postType', postTypeLabels().product ? 'product' : 'post');
	const perPage = attrValue(attrs, 'perPage', '12');
	const columns = attrValue(attrs, 'cardColumns', attrValue(attrs, 'columns', '3'));
	const imageRatio = attrValue(attrs, 'imageRatio', '4-3');
	const filterStyle = attrValue(attrs, 'filterStyle', 'sidebar');
	const cardStyle = attrValue(attrs, 'cardStyle', 'classic');
	const gridGap = attrValue(attrs, 'gridGap', 'md');
	const layoutGap = attrValue(attrs, 'layoutGap', 'md');
	const badgePosition = attrValue(attrs, 'badgePosition', 'top-left');
	const titleLines = attrValue(attrs, 'titleLines', '2');
	const imageHover = attrValue(attrs, 'imageHover', 'subtle');
	const emptyStyle = attrValue(attrs, 'emptyStyle', 'box');
	const emptyTitle = attrValue(attrs, 'emptyTitle', 'No results found');
	const emptyMessage = attrValue(attrs, 'emptyMessage', 'Try clearing filters or searching for something else.');
	const emptyResetLabel = attrValue(attrs, 'emptyResetLabel', 'Reset filters');
	const emptyShowReset = attrValue(attrs, 'emptyShowReset', 'on') !== 'off';
	const showFilters = attrValue(attrs, 'showFilters', 'on');
	const showAddToCart = attrValue(attrs, 'showAddToCart', 'on') !== 'off' && postType === 'product';
	const showQuickView = attrValue(attrs, 'showQuickView', 'on') !== 'off';
	const carouselAutoplay = attrValue(attrs, 'carouselAutoplay', 'off') === 'on';
	const carouselSpeed = attrValue(attrs, 'carouselSpeed', '4000');
	const carouselPauseHover = attrValue(attrs, 'carouselPauseHover', 'on') !== 'off';
	const carouselLoop = attrValue(attrs, 'carouselLoop', 'on') !== 'off';
	const carouselArrows = attrValue(attrs, 'carouselArrows', 'on') !== 'off';
	const carouselDots = attrValue(attrs, 'carouselDots', 'on') !== 'off';
	const visibleFacets = attrValue(attrs, 'visibleFacets', []);
	const filtersOn = showFilters !== 'off';
	const [shopperView, setShopperView] = useState('');
	const [selected, setSelected] = useState({});
	const [searchInput, setSearchInput] = useState('');
	const [search, setSearch] = useState('');
	const [priceFrom, setPriceFrom] = useState(null);
	const [priceTo, setPriceTo] = useState(null);
	const [qvItem, setQvItem] = useState(null);
	const boundsRef = useRef({ min: 0, max: 0 });

	useEffect(() => {
		const timer = window.setTimeout(() => setSearch(searchInput), 250);
		return () => window.clearTimeout(timer);
	}, [searchInput]);

	const facets = { ...selected };
	const boundMin = boundsRef.current.min;
	const boundMax = boundsRef.current.max;
	const from = priceFrom == null ? boundMin : priceFrom;
	const to = priceTo == null ? boundMax : priceTo;
	if (boundMax > 0 && (from > boundMin || to < boundMax)) {
		facets.price_range = [Math.min(from, to), Math.max(from, to)];
	}

	const { data, previewCount } = useLoopQuery(postType, perPage, { facets, search });
	if (data && Number(data.price_max) > 0) {
		boundsRef.current = {
			min: Math.floor(Number(data.price_min) || 0),
			max: Math.ceil(Number(data.price_max) || 0),
		};
	}
	const typeLabels = postTypeLabels();
	const total = Number(data?.total_posts);
	const defaultView = cardStyle === 'list' ? 'list' : 'grid';
	const view = shopperView || defaultView;
	const liveCardStyle = view === 'list' ? 'list' : (cardStyle === 'list' ? 'classic' : cardStyle);
	const appClass = [
		'omniloop-app',
		'omniloop-app--vb',
		`omniloop-app--cols-${columns}`,
		`omniloop-app--ratio-${imageRatio}`,
		`omniloop-app--filter-${filterStyle}`,
		`omniloop-app--card-${liveCardStyle}`,
		`omniloop-app--gap-${gridGap}`,
		`omniloop-app--layout-gap-${layoutGap}`,
		`omniloop-app--badge-${badgePosition}`,
		`omniloop-app--title-lines-${titleLines}`,
		`omniloop-app--zoom-${imageHover}`,
		filtersOn ? '' : 'omniloop-app--no-filters',
	]
		.filter(Boolean)
		.join(' ');

	if (!ModuleContainer) {
		return (
			<div className="omniloop-app omniloop-app--vb">
				<p className="omniloop-count">OmniLoop Visual Builder module is loading…</p>
			</div>
		);
	}

	const activeFilterCount = Object.keys(selected).reduce((sum, key) => {
		const vals = selected[key];
		return sum + (Array.isArray(vals) ? vals.length : 0);
	}, 0) + (searchInput.trim() ? 1 : 0) + (
		boundsRef.current.max > 0
		&& priceFrom != null
		&& priceTo != null
		&& (priceFrom > boundsRef.current.min || priceTo < boundsRef.current.max)
			? 1
			: 0
	);

	const emptyProps = {
		style: emptyStyle,
		title: emptyTitle,
		message: emptyMessage,
		resetLabel: emptyResetLabel,
		showReset: emptyShowReset && activeFilterCount > 0,
		onReset: (event) => {
			stopBuilder(event);
			setSelected({});
			setSearchInput('');
			setPriceFrom(null);
			setPriceTo(null);
		},
	};

	const filtersNode = filtersOn ? (
		<VbFilters
			postType={postType}
			data={data}
			visibleFacets={visibleFacets}
			selected={selected}
			search={searchInput}
			priceFrom={priceFrom}
			priceTo={priceTo}
			onToggle={(key, value) => {
				setSelected((prev) => {
					if (key === 'rating') {
						const current = Array.isArray(prev.rating) ? prev.rating : [];
						const next = { ...prev };
						if (current.indexOf(value) !== -1) {
							delete next.rating;
						} else {
							next.rating = [value];
						}
						return next;
					}
					const current = Array.isArray(prev[key]) ? prev[key].slice() : [];
					const index = current.indexOf(value);
					if (index === -1) {
						current.push(value);
					} else {
						current.splice(index, 1);
					}
					const next = { ...prev };
					if (current.length) {
						next[key] = current;
					} else {
						delete next[key];
					}
					return next;
				});
			}}
			onSearch={setSearchInput}
			onPrice={({ from: nextFrom, to: nextTo }) => {
				const loBound = boundsRef.current.min;
				const hiBound = boundsRef.current.max;
				const lo = Number.isFinite(nextFrom) ? nextFrom : loBound;
				const hi = Number.isFinite(nextTo) ? nextTo : hiBound;
				setPriceFrom(Math.min(lo, hi));
				setPriceTo(Math.max(lo, hi));
			}}
		/>
	) : null;

	const chipKeys = Object.keys(selected).filter((key) => Array.isArray(selected[key]) && selected[key].length);
	const showVbChips = chipKeys.length > 0 || !!searchInput.trim();

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
			{elements?.styleComponents ? elements.styleComponents({ attrName: 'module' }) : null}
			<div className="et_pb_module_inner">
				<div className={appClass}>
					<div className="omniloop-layout">
						{filtersOn && filterStyle === 'chipsonly' ? (
							<div className="omniloop-filters-wrap">
								<button
									type="button"
									className="omniloop-filters__open"
									aria-expanded="false"
									onMouseDown={stopBuilder}
									onClick={stopBuilder}
								>
									{activeFilterCount ? `Filters (${activeFilterCount})` : 'Filters'}
								</button>
								{filtersNode}
							</div>
						) : (
							filtersNode
						)}
						<div className="omniloop-results">
							<div className="omniloop-active">
								<div className="omniloop-toolbar">
									<p className="omniloop-count">
										{Number.isFinite(total) && total >= 0
											? `${total} result${total === 1 ? '' : 's'}`
											: `${typeLabels[postType] || postType} · ${perPage} per page`}
									</p>
									<ViewToggle view={view} onChange={setShopperView} />
								</div>
								<div className="omniloop-chips" hidden={!showVbChips}>
									{chipKeys.map((key) =>
										selected[key].map((value) => (
											<button
												key={`${key}:${value}`}
												type="button"
												className="omniloop-chip"
												onMouseDown={stopBuilder}
												onClick={(event) => {
													stopBuilder(event);
													setSelected((prev) => {
														const current = Array.isArray(prev[key])
															? prev[key].filter((v) => v !== value)
															: [];
														const next = { ...prev };
														if (current.length) next[key] = current;
														else delete next[key];
														return next;
													});
												}}
											>
												<span className="omniloop-chip__label">{key}: {value}</span>
												<span className="omniloop-chip__remove" aria-hidden="true">×</span>
											</button>
										))
									)}
									{searchInput.trim() ? (
										<button
											type="button"
											className="omniloop-chip"
											onMouseDown={stopBuilder}
											onClick={(event) => {
												stopBuilder(event);
												setSearchInput('');
											}}
										>
											<span className="omniloop-chip__label">Search: {searchInput.trim()}</span>
											<span className="omniloop-chip__remove" aria-hidden="true">×</span>
										</button>
									) : null}
								</div>
							</div>
							{liveCardStyle === 'carousel' ? (
								<VbCarousel
									columns={columns}
									autoplay={carouselAutoplay}
									speed={carouselSpeed}
									pauseHover={carouselPauseHover}
									loop={carouselLoop}
									arrows={carouselArrows}
									dots={carouselDots}
								>
									<LiveGrid
										postType={postType}
										posts={data?.posts}
										previewCount={previewCount}
										queried={!!data}
										emptyProps={emptyProps}
										showAddToCart={showAddToCart}
										showQuickView={showQuickView}
										onQuickView={setQvItem}
									/>
								</VbCarousel>
							) : (
								<div className="omniloop-grid">
									<LiveGrid
										postType={postType}
										posts={data?.posts}
										previewCount={previewCount}
										queried={!!data}
										emptyProps={emptyProps}
										showAddToCart={showAddToCart}
										showQuickView={showQuickView}
										onQuickView={setQvItem}
									/>
								</div>
							)}
							{qvItem ? (
								<div className="omniloop-qv" onMouseDown={stopBuilder}>
									<button type="button" className="omniloop-qv__backdrop" aria-label="Close" onClick={() => setQvItem(null)} />
									<div className="omniloop-qv__dialog" role="dialog" aria-modal="true">
										<button type="button" className="omniloop-qv__close" aria-label="Close" onClick={() => setQvItem(null)}>&times;</button>
										<div className="omniloop-qv__media">
											{qvItem.thumbnail ? (
												<img src={qvItem.thumbnail} alt={qvItem.title || ''} />
											) : (
												<div className="omniloop-qv__fallback">{(qvItem.title || '•').charAt(0).toUpperCase()}</div>
											)}
										</div>
										<div className="omniloop-qv__content">
											<h3 className="omniloop-qv__title">{qvItem.title}</h3>
											{qvItem.price ? (
												<p className="omniloop-qv__price">
													{qvItem.priceRegular ? <s className="omniloop-card__price-regular">{qvItem.priceRegular}</s> : null}
													{qvItem.priceRegular ? ' ' : ''}
													{qvItem.price}
												</p>
											) : null}
											{qvItem.excerpt ? <p className="omniloop-qv__excerpt">{qvItem.excerpt}</p> : null}
											<div className="omniloop-qv__actions">
												{showAddToCart && qvItem.addToCart && qvItem.addToCart.url ? (
													<a
														href={qvItem.addToCart.url}
														className="omniloop-card__atc"
														onMouseDown={stopBuilder}
														onClick={stopBuilder}
													>
														{qvItem.addToCart.label || 'Add to cart'}
													</a>
												) : null}
												{qvItem.permalink ? (
													<a href={qvItem.permalink} className="omniloop-qv__more" onMouseDown={stopBuilder} onClick={stopBuilder}>
														View details
													</a>
												) : null}
											</div>
										</div>
									</div>
								</div>
							) : null}
						</div>
					</div>
				</div>
			</div>
		</ModuleContainer>
	);
};

const loopModule = {
	metadata,
	renderers: {
		edit: (props) => (
			<PreviewBoundary>
				<LoopEdit {...props} />
			</PreviewBoundary>
		),
	},
	callbacks: {
		isCarouselStyle,
		isCarouselAutoplayOn,
		isNotListStyle,
		isPaginationOn,
		isPaginationStyleVisible,
		design: {
			carouselOptions: {
				fields: {
					moduleAdvancedCarouselAutoplay: { visible: isCarouselStyle },
					moduleAdvancedCarouselSpeed: { visible: isCarouselAutoplayOn },
					moduleAdvancedCarouselPauseHover: { visible: isCarouselAutoplayOn },
					moduleAdvancedCarouselLoop: { visible: isCarouselStyle },
					moduleAdvancedCarouselArrows: { visible: isCarouselStyle },
					moduleAdvancedCarouselDots: { visible: isCarouselStyle },
				},
			},
			loopLayout: {
				fields: {
					moduleAdvancedCardColumns: { visible: isNotListStyle },
				},
			},
			loopQuery: {
				fields: {
					moduleAdvancedPaginationStyle: { visible: isPaginationStyleVisible },
				},
			},
		},
	},
	placeholderContent: {
		module: {
			advanced: {
				postType: {
					desktop: { value: 'product' },
				},
				perPage: {
					desktop: { value: '12' },
				},
				showFilters: {
					desktop: { value: 'on' },
				},
				showAddToCart: {
					desktop: { value: 'on' },
				},
				showQuickView: {
					desktop: { value: 'on' },
				},
				showPagination: {
					desktop: { value: 'on' },
				},
				paginationStyle: {
					desktop: { value: 'pages' },
				},
				filterSync: {
					desktop: { value: 'on' },
				},
				syncGroup: {
					desktop: { value: 'default' },
				},
				filterPreset: {
					desktop: { value: '' },
				},
				merchandising: {
					desktop: { value: 'on' },
				},
				extraPinIds: {
					desktop: { value: '' },
				},
				stockUrgency: {
					desktop: { value: 'on' },
				},
				promotedBadge: {
					desktop: { value: 'on' },
				},
				facetLogic: {
					desktop: { value: 'smart' },
				},
				sortMetaKey: {
					desktop: { value: '' },
				},
				visibleFacets: {
					desktop: { value: [] },
				},
				cardColumns: {
					desktop: { value: '3' },
				},
				imageRatio: {
					desktop: { value: '4-3' },
				},
				filterStyle: {
					desktop: { value: 'sidebar' },
				},
				cardStyle: {
					desktop: { value: 'classic' },
				},
				carouselAutoplay: {
					desktop: { value: 'off' },
				},
				carouselSpeed: {
					desktop: { value: '4000' },
				},
				carouselPauseHover: {
					desktop: { value: 'on' },
				},
				carouselLoop: {
					desktop: { value: 'on' },
				},
				carouselArrows: {
					desktop: { value: 'on' },
				},
				carouselDots: {
					desktop: { value: 'on' },
				},
				gridGap: {
					desktop: { value: 'md' },
				},
				layoutGap: {
					desktop: { value: 'md' },
				},
				badgePosition: {
					desktop: { value: 'top-left' },
				},
				titleLines: {
					desktop: { value: '2' },
				},
				imageHover: {
					desktop: { value: 'subtle' },
				},
				emptyStyle: {
					desktop: { value: 'box' },
				},
				emptyTitle: {
					desktop: { value: 'No results found' },
				},
				emptyMessage: {
					desktop: { value: 'Try clearing filters or searching for something else.' },
				},
				emptyResetLabel: {
					desktop: { value: 'Reset filters' },
				},
				emptyShowReset: {
					desktop: { value: 'on' },
				},
			},
		},
	},
};

const { addAction, addFilter } = window?.vendor?.wp?.hooks || {};

if (typeof addFilter === 'function') {
	addFilter('divi.moduleLibrary.moduleSettings.groups.omniloop.loop', 'omniloop.loop.visible', (groups) =>
		applyGroupFieldVisibility(groups)
	);
	addFilter('divi.moduleGroups.groups', 'omniloop.loop.visible.render', (groups, moduleInfo) => {
		if (moduleInfo?.moduleName !== 'omniloop/loop') {
			return groups;
		}
		return applyGroupFieldVisibility(groups);
	});
}

if (typeof addAction === 'function') {
	addAction('divi.moduleLibrary.registerModuleLibraryStore.after', 'omniloop.loop', () => {
		const { registerModule } = window?.divi?.moduleLibrary || {};
		if (typeof registerModule !== 'function') {
			return;
		}
		try {
			registerModule(applyDynamicPostTypes(loopModule.metadata), loopModule);
		} catch (e) {
			registerModule(applyConditionalVisibility(loopModule.metadata), loopModule);
		}
	});
}

if (typeof addFilter === 'function') {
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
}
