(function (blocks, element, blockEditor, components, i18n, serverSideRender) {
	"use strict";

	if (!blocks || !element || !blockEditor || !components || !i18n || !serverSideRender) {
		return;
	}

	var el = element.createElement;
	var Fragment = element.Fragment;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var ToggleControl = components.ToggleControl;
	var __ = i18n.__;
	var types = (window.omniloopBlock && window.omniloopBlock.postTypes) || [
		{ value: "post", label: "Post" }
	];
	var defaultType = (window.omniloopBlock && window.omniloopBlock.defaultPostType) || "post";

	blocks.registerBlockType("omniloop/loop-gutenberg", {
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps({ className: "omniloop-editor" });

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __("OmniLoop loop", "omniloop"), initialOpen: true },
						el(SelectControl, {
							label: __("Post type", "omniloop"),
							value: attributes.postType || defaultType,
							options: types,
							onChange: function (value) {
								setAttributes({ postType: value });
							}
						}),
						el(RangeControl, {
							label: __("Per page", "omniloop"),
							value: attributes.perPage || 12,
							min: 1,
							max: 48,
							onChange: function (value) {
								setAttributes({ perPage: value || 12 });
							}
						}),
						el(SelectControl, {
							label: __("Columns", "omniloop"),
							value: String(attributes.columns || 3),
							options: [
								{ value: "2", label: __("2 columns", "omniloop") },
								{ value: "3", label: __("3 columns", "omniloop") },
								{ value: "4", label: __("4 columns", "omniloop") }
							],
							onChange: function (value) {
								setAttributes({ columns: parseInt(value, 10) || 3 });
							}
						}),
						el(SelectControl, {
							label: __("Image ratio", "omniloop"),
							value: attributes.imageRatio || "4-3",
							options: [
								{ value: "4-3", label: __("Landscape 4:3", "omniloop") },
								{ value: "16-9", label: __("Wide 16:9", "omniloop") },
								{ value: "1-1", label: __("Square 1:1", "omniloop") },
								{ value: "3-4", label: __("Portrait 3:4", "omniloop") }
							],
							onChange: function (value) {
								setAttributes({ imageRatio: value || "4-3" });
							}
						}),
						el(ToggleControl, {
							label: __("Show filters", "omniloop"),
							checked: attributes.showFilters !== false,
							onChange: function (value) {
								setAttributes({ showFilters: !!value });
							}
						})
					)
				),
				el(
					"div",
					blockProps,
					el(serverSideRender, {
						block: "omniloop/loop-gutenberg",
						attributes: attributes
					})
				)
			);
		},
		save: function () {
			return null;
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.serverSideRender
);
