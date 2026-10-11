<?php
/**
 * Interactive English help guide for non-technical users.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Admin;

use OmniLoop\Engine\Core\HelpVideo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DocsPage {

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ], 20 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'assets' ] );
	}

	public static function menu(): void {
		add_submenu_page(
			'omniloop',
			__( 'OmniLoop Help', 'omniloop' ),
			__( 'Help Guide', 'omniloop' ),
			'manage_options',
			'omniloop-docs',
			[ self::class, 'render' ]
		);
	}

	public static function assets( string $hook ): void {
		if ( 'omniloop_page_omniloop-docs' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'omniloop-docs',
			OMNILOOP_URL . 'assets/css/omniloop-docs.css',
			[],
			OMNILOOP_VERSION
		);

		wp_enqueue_script(
			'omniloop-docs',
			OMNILOOP_URL . 'assets/js/omniloop-docs.js',
			[],
			OMNILOOP_VERSION,
			true
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings_url = admin_url( 'admin.php?page=omniloop' );
		$pages_url    = admin_url( 'edit.php?post_type=page' );
		$intro_video  = HelpVideo::markup( __( 'OmniLoop intro', 'omniloop' ) );
		?>
		<div class="wrap omniloop-docs" id="omniloop-docs-app">
			<header class="omniloop-docs-hero<?php echo $intro_video ? ' omniloop-docs-hero--video' : ''; ?>">
				<div class="omniloop-docs-hero__copy">
					<p class="omniloop-docs-hero__eyebrow"><?php esc_html_e( 'Help Guide', 'omniloop' ); ?></p>
					<h1><?php esc_html_e( 'Welcome to OmniLoop', 'omniloop' ); ?></h1>
					<p>
						<?php esc_html_e( 'This short guide explains what OmniLoop does and how to use it — in plain English. No coding needed.', 'omniloop' ); ?>
					</p>
				</div>
				<?php
				if ( $intro_video ) {
					echo $intro_video; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup() returns escaped embed HTML
				}
				?>
			</header>

			<div class="omniloop-docs-shell">
				<aside class="omniloop-docs-nav" aria-label="<?php esc_attr_e( 'Guide sections', 'omniloop' ); ?>">
					<div class="omniloop-docs-nav__head">
						<strong><?php esc_html_e( 'On this page', 'omniloop' ); ?></strong>
						<span><?php esc_html_e( 'Click a topic, or search below', 'omniloop' ); ?></span>
					</div>
					<div class="omniloop-docs-search">
						<label class="screen-reader-text" for="omniloop-docs-search"><?php esc_html_e( 'Search the guide', 'omniloop' ); ?></label>
						<input type="search" id="omniloop-docs-search" placeholder="<?php esc_attr_e( 'Search… (try “price” or “carousel”)', 'omniloop' ); ?>" autocomplete="off">
					</div>
					<ul class="omniloop-docs-nav__list" id="omniloop-docs-nav"></ul>
				</aside>

				<div class="omniloop-docs-main">
					<p class="omniloop-docs-empty" id="omniloop-docs-empty"><?php esc_html_e( 'No sections match your search. Try another word.', 'omniloop' ); ?></p>

					<section class="omniloop-docs-section is-active" data-section="welcome" data-title="<?php esc_attr_e( 'What is OmniLoop?', 'omniloop' ); ?>" data-keywords="intro overview video watch tutorial what is plugin product filter shop loop divi unique selling">
						<span class="omniloop-docs-kicker"><?php esc_html_e( 'Start here', 'omniloop' ); ?></span>
						<h2><?php esc_html_e( 'What is OmniLoop?', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'OmniLoop is the shop layer for Divi 5: a fast catalog with live filters, cards you design, merchandising, and pretty SEO URLs. Visitors search, sort, and narrow results instantly — without reloading the page.', 'omniloop' ); ?></p>
						<ul class="omniloop-docs-pills">
							<li><?php esc_html_e( 'One Visual Builder module', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Own index table', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'WooCommerce optional', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Pretty /ol/ SEO URLs', 'omniloop' ); ?></li>
						</ul>
						<p><?php esc_html_e( 'Think of it like a modern online shop catalog: pick a category, choose a color, set a price range, and the list updates right away.', 'omniloop' ); ?></p>
						<div class="omniloop-docs-callout">
							<strong><?php esc_html_e( 'One module, one job', 'omniloop' ); ?></strong>
							<p><?php esc_html_e( 'You only need one Divi module: “OmniLoop Filter”. Nested Card Item children reorder image, title, price, and buttons. Place it on a page, choose what to show, and you are done.', 'omniloop' ); ?></p>
						</div>
						<p><?php esc_html_e( 'WooCommerce is optional. Without it, OmniLoop still works for blog posts and other content types. With WooCommerce, you unlock product price, stock, sale, ratings, and attribute filters.', 'omniloop' ); ?></p>
					</section>

					<section class="omniloop-docs-section" data-section="why" data-title="<?php esc_attr_e( 'Why OmniLoop', 'omniloop' ); ?>" data-keywords="why unique selling points different vs divi post filter facetwp ajax index merchandising seo loop builder woo optional">
						<span class="omniloop-docs-kicker"><?php esc_html_e( 'Selling points', 'omniloop' ); ?></span>
						<h2><?php esc_html_e( 'Why shops pick OmniLoop', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'Divi 5 already ships a free Post Filter. OmniLoop is not that module with extra checkboxes. It is the catalog layer on top: speed, merchandising, SEO, and cards you design — still inside the Visual Builder.', 'omniloop' ); ?></p>
						<ul class="omniloop-docs-sell">
							<li>
								<strong><?php esc_html_e( 'One module, the whole shop', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Filters, grid, chips, sort, and pagination live in OmniLoop Filter. Loop Item Builder lets you nest and reorder card pieces in the layers panel. No separate Filter + Loop pair.', 'omniloop' ); ?></span>
							</li>
							<li>
								<strong><?php esc_html_e( 'Own index, not meta joins', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Facet queries hit OmniLoop’s table. Saving a product, term, or ACF field updates that row. Rebuild once when you change post types so large catalogs stay snappy.', 'omniloop' ); ?></span>
							</li>
							<li>
								<strong><?php esc_html_e( 'WooCommerce optional', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Posts and any public post type work on Divi + WordPress alone. Woo unlocks swatches, price slider, stock, sale, rating, cart, and quick view. ACF is a separate opt-in.', 'omniloop' ); ?></span>
							</li>
							<li>
								<strong><?php esc_html_e( 'Pretty /ol/ SEO URLs', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Filtered pages can look like /ol/color/navy/category/shoes/. Title, description, and H1 templates, noindex for page 2+, optional sitemap, and shareable ?ol_preset= links.', 'omniloop' ); ?></span>
							</li>
							<li>
								<strong><?php esc_html_e( 'Merchandising in the same index', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Pin products, push out-of-stock last, promo badges, and “Only X left.” Filter Sync keeps two modules on one page. This is shop control, not only a checkbox list.', 'omniloop' ); ?></span>
							</li>
							<li>
								<strong><?php esc_html_e( 'Divi’s Loop, OmniLoop’s speed', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Already using Loop Builder? Native Loop attach hands matching IDs to Divi’s loop (main query only unless you opt in), so other loops on the page stay clean.', 'omniloop' ); ?></span>
							</li>
						</ul>
						<div class="omniloop-docs-callout">
							<strong><?php esc_html_e( 'What it is not', 'omniloop' ); ?></strong>
							<p><?php esc_html_e( 'Not a Gutenberg block. Not a Woo-only plugin. Not Divi’s free Post Filter with a new skin. It is a Divi 5 Visual Builder extension with its own index.', 'omniloop' ); ?></p>
						</div>
					</section>

					<section class="omniloop-docs-section" data-section="setup" data-title="<?php esc_attr_e( 'First-time setup', 'omniloop' ); ?>" data-keywords="setup install activate rebuild index settings start quick begin">
						<span class="omniloop-docs-kicker"><?php esc_html_e( '5 minutes', 'omniloop' ); ?></span>
						<h2><?php esc_html_e( 'First-time setup', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'Follow these steps once after you install OmniLoop. After that, you mostly work inside the Divi Visual Builder.', 'omniloop' ); ?></p>
						<ol class="omniloop-docs-steps">
							<li>
								<strong><?php esc_html_e( 'Activate the plugin', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'In WordPress, open Plugins and make sure OmniLoop for Divi is active. Divi 5 must also be active.', 'omniloop' ); ?></span>
							</li>
							<li>
								<strong><?php esc_html_e( 'Open OmniLoop Settings', 'omniloop' ); ?></strong>
								<span>
									<?php
									echo wp_kses(
										sprintf(
											/* translators: %s: settings URL */
											__( 'Go to <a href="%s">OmniLoop → Settings</a>.', 'omniloop' ),
											esc_url( $settings_url )
										),
										[ 'a' => [ 'href' => true ] ]
									);
									?>
								</span>
							</li>
							<li>
								<strong><?php esc_html_e( 'Choose what to index', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Check the content types you want to filter — for example Products, Posts, or Pages. If you use Advanced Custom Fields, you can also tick those fields here.', 'omniloop' ); ?></span>
							</li>
							<li>
								<strong><?php esc_html_e( 'Rebuild the index', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Click Rebuild index and wait until it says complete. This builds a fast lookup table so filters stay snappy.', 'omniloop' ); ?></span>
							</li>
							<li>
								<strong><?php esc_html_e( 'Add the module to a page', 'omniloop' ); ?></strong>
								<span>
									<?php
									echo wp_kses(
										sprintf(
											/* translators: %s: pages URL */
											__( 'Edit a page in Divi, insert OmniLoop Filter, save, then view the page. Start from <a href="%s">Pages</a> if you need a new shop page.', 'omniloop' ),
											esc_url( $pages_url )
										),
										[ 'a' => [ 'href' => true ] ]
									);
									?>
								</span>
							</li>
						</ol>
						<div class="omniloop-docs-callout omniloop-docs-callout--warn">
							<strong><?php esc_html_e( 'Remember', 'omniloop' ); ?></strong>
							<p><?php esc_html_e( 'Whenever you change indexed post types, attributes, or ACF fields, rebuild the index again.', 'omniloop' ); ?></p>
						</div>
						<ul class="omniloop-docs-checklist" data-checklist="setup">
							<li>
								<input type="checkbox" id="od-check-1">
								<label for="od-check-1">
									<strong><?php esc_html_e( 'Plugin activated', 'omniloop' ); ?></strong>
									<span><?php esc_html_e( 'OmniLoop for Divi is on', 'omniloop' ); ?></span>
								</label>
							</li>
							<li>
								<input type="checkbox" id="od-check-2">
								<label for="od-check-2">
									<strong><?php esc_html_e( 'Index rebuilt', 'omniloop' ); ?></strong>
									<span><?php esc_html_e( 'Settings shows indexed rows above zero', 'omniloop' ); ?></span>
								</label>
							</li>
							<li>
								<input type="checkbox" id="od-check-3">
								<label for="od-check-3">
									<strong><?php esc_html_e( 'Module on a page', 'omniloop' ); ?></strong>
									<span><?php esc_html_e( 'OmniLoop Filter is inserted and saved', 'omniloop' ); ?></span>
								</label>
							</li>
						</ul>
					</section>

					<section class="omniloop-docs-section" data-section="add-module" data-title="<?php esc_attr_e( 'Add the module', 'omniloop' ); ?>" data-keywords="divi visual builder insert module add page omnilooop filter">
						<h2><?php esc_html_e( 'Add the OmniLoop Filter module', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'You build everything from one Divi module. Here is the everyday workflow:', 'omniloop' ); ?></p>
						<ol>
							<li><?php esc_html_e( 'Open a page with the Divi Visual Builder.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Click to add a module and search for “OmniLoop”.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Choose OmniLoop Filter.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'In Content, pick Post Type (for shops, choose Products).', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Save the page, then open the live page (not only the builder) to test filters.', 'omniloop' ); ?></li>
						</ol>
						<div class="omniloop-docs-callout">
							<strong><?php esc_html_e( 'Tip', 'omniloop' ); ?></strong>
							<p><?php esc_html_e( 'After plugin updates, hard-refresh the front page once (Ctrl+F5 / Cmd+Shift+R) so new styles and scripts load.', 'omniloop' ); ?></p>
						</div>
					</section>

					<section class="omniloop-docs-section" data-section="content" data-title="<?php esc_attr_e( 'Content settings', 'omniloop' ); ?>" data-keywords="content settings post type per page show filters visible add to cart quick view pagination empty">
						<h2><?php esc_html_e( 'Content settings (plain English)', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'These live in the module’s Content tab. You do not need to understand code — just pick what visitors should see.', 'omniloop' ); ?></p>
						<ul>
							<li><strong><?php esc_html_e( 'Post Type', 'omniloop' ); ?></strong> — <?php esc_html_e( 'What kind of items appear in the grid (Products, Posts, Pages, and other public types you indexed).', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Per Page', 'omniloop' ); ?></strong> — <?php esc_html_e( 'How many items show before “next page”.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Show Filters', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Turn the filter panel on or off. Off is useful if you only want a styled grid.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Visible Filters', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Choose which filters appear (price, categories, color, and so on). Leave empty to show all available filters.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Add to Cart', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Shows a cart button on product cards (WooCommerce). Simple products add instantly; variable products open the product page.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Quick View', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Lets shoppers peek at an item in a popup without leaving the list. Quick View Text changes the button label (for example Peek or Quick look). Leave it blank for Quick view.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Show Pagination', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Shows page numbers under the loop. Turn off to display only the first page of results.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Within-facet match', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Advanced. Smart mode usually means “any category” but “all selected attributes”. Most shops can leave this on Smart.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Merchandising', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Uses rules from OmniLoop → Settings: search to pin products, push out-of-stock last, promo badges, and “Only X left”. Extra Pin IDs apply only to this module.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Card builder', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Inside OmniLoop Filter, add OmniLoop Card Item children. Each item is Image, Title, Excerpt, Price, Rating, Add to Cart, or Quick View. Drag to reorder. If you add none, Card Style presets are used.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Empty state', 'omniloop' ); ?></strong> — <?php esc_html_e( 'The friendly message when no items match the current filters.', 'omniloop' ); ?></li>
						</ul>
					</section>

					<section class="omniloop-docs-section" data-section="filter-styles" data-title="<?php esc_attr_e( 'Filter layouts', 'omniloop' ); ?>" data-keywords="filter style sidebar accordion toolbar mega bar pills chip only layout">
						<h2><?php esc_html_e( 'Filter layouts', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'Filter Style changes where filters sit on the page. Click a style below to see a simple explanation.', 'omniloop' ); ?></p>
						<div class="omniloop-docs-grid" data-pick-group="filters">
							<button type="button" class="omniloop-docs-pick is-active" data-pick="sidebar" data-title="<?php esc_attr_e( 'Sidebar', 'omniloop' ); ?>" data-body="<?php esc_attr_e( 'Filters sit in a left column. Great for classic shop pages with lots of options.', 'omniloop' ); ?>">
								<strong><?php esc_html_e( 'Sidebar', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Filters on the left', 'omniloop' ); ?></span>
							</button>
							<button type="button" class="omniloop-docs-pick" data-pick="accordion" data-title="<?php esc_attr_e( 'Accordion', 'omniloop' ); ?>" data-body="<?php esc_attr_e( 'Each filter group collapses to save space. Nice when you have many facets.', 'omniloop' ); ?>">
								<strong><?php esc_html_e( 'Accordion', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Collapsible groups', 'omniloop' ); ?></span>
							</button>
							<button type="button" class="omniloop-docs-pick" data-pick="toolbar" data-title="<?php esc_attr_e( 'Toolbar', 'omniloop' ); ?>" data-body="<?php esc_attr_e( 'Filters sit in a compact bar above the results — clean and modern.', 'omniloop' ); ?>">
								<strong><?php esc_html_e( 'Toolbar', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Bar above results', 'omniloop' ); ?></span>
							</button>
							<button type="button" class="omniloop-docs-pick" data-pick="megabar" data-title="<?php esc_attr_e( 'Mega Bar', 'omniloop' ); ?>" data-body="<?php esc_attr_e( 'A wide multi-column filter panel above the grid. Strong for large catalogs.', 'omniloop' ); ?>">
								<strong><?php esc_html_e( 'Mega Bar', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Wide filter panel', 'omniloop' ); ?></span>
							</button>
							<button type="button" class="omniloop-docs-pick" data-pick="pills" data-title="<?php esc_attr_e( 'Pills', 'omniloop' ); ?>" data-body="<?php esc_attr_e( 'Filters look like rounded chips. Friendly and visual — good for fashion or brand filters.', 'omniloop' ); ?>">
								<strong><?php esc_html_e( 'Pills', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Chip-style choices', 'omniloop' ); ?></span>
							</button>
							<button type="button" class="omniloop-docs-pick" data-pick="chipsonly" data-title="<?php esc_attr_e( 'Chip Only', 'omniloop' ); ?>" data-body="<?php esc_attr_e( 'Keeps the page open. A Filters button opens a drawer; active choices show as chips on the page.', 'omniloop' ); ?>">
								<strong><?php esc_html_e( 'Chip Only', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Drawer + active chips', 'omniloop' ); ?></span>
							</button>
						</div>
						<div class="omniloop-docs-preview" data-preview="filters">
							<p class="omniloop-docs-preview__title"><?php esc_html_e( 'Sidebar', 'omniloop' ); ?></p>
							<p class="omniloop-docs-preview__body"><?php esc_html_e( 'Filters sit in a left column. Great for classic shop pages with lots of options.', 'omniloop' ); ?></p>
						</div>
					</section>

					<section class="omniloop-docs-section" data-section="card-styles" data-title="<?php esc_attr_e( 'Card layouts', 'omniloop' ); ?>" data-keywords="card style classic overlay list compact carousel masonry grid">
						<h2><?php esc_html_e( 'Card layouts', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'Card Style changes how each item looks in the grid. Click one to learn when to use it.', 'omniloop' ); ?></p>
						<div class="omniloop-docs-grid" data-pick-group="cards">
							<button type="button" class="omniloop-docs-pick is-active" data-pick="classic" data-title="<?php esc_attr_e( 'Classic', 'omniloop' ); ?>" data-body="<?php esc_attr_e( 'Image on top, title and price below. The safest default for most shops.', 'omniloop' ); ?>">
								<strong><?php esc_html_e( 'Classic', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Standard product card', 'omniloop' ); ?></span>
							</button>
							<button type="button" class="omniloop-docs-pick" data-pick="overlay" data-title="<?php esc_attr_e( 'Overlay', 'omniloop' ); ?>" data-body="<?php esc_attr_e( 'Text sits over the image. More editorial and visually bold.', 'omniloop' ); ?>">
								<strong><?php esc_html_e( 'Overlay', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Text on the image', 'omniloop' ); ?></span>
							</button>
							<button type="button" class="omniloop-docs-pick" data-pick="list" data-title="<?php esc_attr_e( 'List', 'omniloop' ); ?>" data-body="<?php esc_attr_e( 'Wide rows with image beside the details. Easy to scan many items. The Columns setting hides because list is always one item per row.', 'omniloop' ); ?>">
								<strong><?php esc_html_e( 'List', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Horizontal rows', 'omniloop' ); ?></span>
							</button>
							<button type="button" class="omniloop-docs-pick" data-pick="compact" data-title="<?php esc_attr_e( 'Compact', 'omniloop' ); ?>" data-body="<?php esc_attr_e( 'Smaller cards so more products fit on screen. Good for large catalogs.', 'omniloop' ); ?>">
								<strong><?php esc_html_e( 'Compact', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Dense grid', 'omniloop' ); ?></span>
							</button>
							<button type="button" class="omniloop-docs-pick" data-pick="carousel" data-title="<?php esc_attr_e( 'Carousel', 'omniloop' ); ?>" data-body="<?php esc_attr_e( 'A sliding row with arrows and dots. Pick Card Style → Carousel and the Design → Carousel group appears (Autoplay, Speed, Pause on Hover, Loop, arrows, dots).', 'omniloop' ); ?>">
								<strong><?php esc_html_e( 'Carousel', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Swipeable row + autoplay options', 'omniloop' ); ?></span>
							</button>
							<button type="button" class="omniloop-docs-pick" data-pick="masonry" data-title="<?php esc_attr_e( 'Masonry', 'omniloop' ); ?>" data-body="<?php esc_attr_e( 'A Pinterest-like staggered collage. Lovely for image-heavy catalogs.', 'omniloop' ); ?>">
								<strong><?php esc_html_e( 'Masonry', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Staggered collage', 'omniloop' ); ?></span>
							</button>
						</div>
						<div class="omniloop-docs-preview" data-preview="cards">
							<p class="omniloop-docs-preview__title"><?php esc_html_e( 'Classic', 'omniloop' ); ?></p>
							<p class="omniloop-docs-preview__body"><?php esc_html_e( 'Image on top, title and price below. The safest default for most shops.', 'omniloop' ); ?></p>
						</div>
					</section>

					<section class="omniloop-docs-section" data-section="shopper" data-title="<?php esc_attr_e( 'What shoppers can do', 'omniloop' ); ?>" data-keywords="shopper filter search sort price slider chips sticky rating popularity cart quick view typo keyboard">
						<h2><?php esc_html_e( 'What shoppers can do', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'On the published page, visitors can:', 'omniloop' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'Type a name to search. A small spelling mistake can still find the item.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Tick categories, colors, sizes, stock, sale, and similar filters.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Drag the price bar, or type a from and to amount.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Type inside a long list (like brands) to jump to a name. Arrow keys move, Enter ticks it.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Sort by newest, title, price, popularity, or rating.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'See their choices as little chips. Click a chip to remove it, or clear all.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Add to cart or peek with Quick View, if you turned those on.', 'omniloop' ); ?></li>
						</ul>
						<div class="omniloop-docs-callout">
							<strong><?php esc_html_e( 'Nothing matches?', 'omniloop' ); ?></strong>
							<p><?php esc_html_e( 'Empty choices hide themselves, so shoppers are not staring at “0” next to every option.', 'omniloop' ); ?></p>
						</div>
					</section>

					<section class="omniloop-docs-section" data-section="design" data-title="<?php esc_attr_e( 'Design tab', 'omniloop' ); ?>" data-keywords="design columns gap badge title hover zoom decoration divi">
						<h2><?php esc_html_e( 'Design tab basics', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'Use the Design tab when you want the loop to match your brand colors and spacing.', 'omniloop' ); ?></p>
						<ul>
							<li><strong><?php esc_html_e( 'Columns & image ratio', 'omniloop' ); ?></strong> — <?php esc_html_e( 'How many cards per row, and whether images are square, wide, or tall.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Gaps', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Space between cards, and space between filters and results.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Badge position & title lines', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Where the Sale badge sits, and how many lines titles can use.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Image hover zoom', 'omniloop' ); ?></strong> — <?php esc_html_e( 'A soft zoom when someone hovers a card.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Decoration groups', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Native Divi controls for filters, cards, titles, prices, and sale badges (colors, fonts, borders, shadows).', 'omniloop' ); ?></li>
						</ul>
					</section>

					<section class="omniloop-docs-section" data-section="settings" data-title="<?php esc_attr_e( 'Admin settings', 'omniloop' ); ?>" data-keywords="admin settings rebuild index acf post types dashboard seo analytics export import tabs video intro help">
						<h2><?php esc_html_e( 'Admin settings', 'omniloop' ); ?></h2>
						<p>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: settings URL */
									__( 'Open <a href="%s">OmniLoop → Settings</a>. The tabs at the top split the work:', 'omniloop' ),
									esc_url( $settings_url )
								),
								[ 'a' => [ 'href' => true ] ]
							);
							?>
						</p>
						<ul>
							<li><strong><?php esc_html_e( 'Index', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Choose what to filter (Products, Posts, and so on). Rebuild after catalog changes. “Ready” means filters can run fast. Same tab: saved presets (pick filters, no JSON) and pin products by searching the title.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'SEO', 'omniloop' ); ?></strong> — <?php esc_html_e( 'How filter pages look in Google (title, description, heading).', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Facets', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Hide empty choices, hide a whole filter until another is picked, and allow small typos in Search.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Analytics', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Which filters people click, and which mixes find nothing. Last 30 days only.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Tools', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Add a Help Guide intro video, download your setup, or upload it on another site. Then rebuild the index there.', 'omniloop' ); ?></li>
						</ul>
						<div class="omniloop-docs-callout">
							<strong><?php esc_html_e( 'ACF', 'omniloop' ); ?></strong>
							<p><?php esc_html_e( 'If you use Advanced Custom Fields, tick those fields on the Index tab, then rebuild.', 'omniloop' ); ?></p>
						</div>
					</section>

					<section class="omniloop-docs-section" data-section="seo" data-title="<?php esc_attr_e( 'Google and filter pages', 'omniloop' ); ?>" data-keywords="seo title description h1 robots noindex sitemap pretty url google heading">
						<h2><?php esc_html_e( 'Google and filter pages', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'When someone picks Red and Shoes, the page URL can look clean, like …/ol/color/red/category/shoes/. The SEO tab controls the words Google sees on that page.', 'omniloop' ); ?></p>
						<ul>
							<li><strong><?php esc_html_e( 'Title, description, heading', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Write a pattern. OmniLoop fills in the shop name, the page name, and the filters they picked (for example “Red, Shoes”).', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Hide page 2 and later from Google', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Leave this on. Extra result pages are usually the same list again.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Hide every filter page from Google', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Use only if you do not want those links in search results at all.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Sitemap', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Optional. Tells Google about simple filter pages, like each category. Pick the shop page first.', 'omniloop' ); ?></li>
						</ul>
						<div class="omniloop-docs-callout">
							<strong><?php esc_html_e( 'You do not need code', 'omniloop' ); ?></strong>
							<p><?php esc_html_e( 'Keep the default patterns if you are unsure. They already add the chosen filters to the title.', 'omniloop' ); ?></p>
						</div>
					</section>

					<section class="omniloop-docs-section" data-section="smart-filters" data-title="<?php esc_attr_e( 'Smarter filters', 'omniloop' ); ?>" data-keywords="hide empty zero count conditional facet show hide color size typo search">
						<h2><?php esc_html_e( 'Smarter filters', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'Open Settings → Facets. These three switches keep the filter list tidy.', 'omniloop' ); ?></p>
						<ul>
							<li><strong><?php esc_html_e( 'Hide empty choices', 'omniloop' ); ?></strong> — <?php esc_html_e( 'If nobody has Size XL in Red, XL disappears instead of showing 0.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Hide a whole group', 'omniloop' ); ?></strong> — <?php esc_html_e( 'If a filter has no choices left, the whole group goes away.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Search with typos', 'omniloop' ); ?></strong> — <?php esc_html_e( '“Shose” can still find “Shoes”. Turn it off if you only want exact words.', 'omniloop' ); ?></li>
						</ul>
						<p><?php esc_html_e( 'You can also write a simple rule, like: show Size only after someone picks a Color. Or hide Stock when On sale is ticked.', 'omniloop' ); ?></p>
						<div class="omniloop-docs-callout">
							<strong><?php esc_html_e( 'Leave a rule blank to skip it', 'omniloop' ); ?></strong>
							<p><?php esc_html_e( 'If When facet and Target facet are empty, OmniLoop ignores that row.', 'omniloop' ); ?></p>
						</div>
					</section>

					<section class="omniloop-docs-section" data-section="workflows" data-title="<?php esc_attr_e( 'Two ways to build a shop', 'omniloop' ); ?>" data-keywords="divi 5 native loop builder workflow module shop grid list">
						<h2><?php esc_html_e( 'Two ways to build a shop', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'Pick one. Most shops only need the first.', 'omniloop' ); ?></p>
						<ol>
							<li>
								<strong><?php esc_html_e( 'The easy way — OmniLoop Filter', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Add one module. You get filters, the product grid, page numbers, and clean URLs together. Start here.', 'omniloop' ); ?></span>
							</li>
							<li>
								<strong><?php esc_html_e( 'Already using Divi’s Loop Builder?', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'You can keep Divi’s cards. When a shopper opens a filter link (or you ask OmniLoop to attach), that list shows only matching items. Same fast index — no extra setup in the product editor.', 'omniloop' ); ?></span>
							</li>
						</ol>
						<div class="omniloop-docs-callout">
							<strong><?php esc_html_e( 'Not sure?', 'omniloop' ); ?></strong>
							<p><?php esc_html_e( 'Use OmniLoop Filter. Loop Builder is only needed if you already designed cards there and want to keep them.', 'omniloop' ); ?></p>
						</div>
					</section>

					<section class="omniloop-docs-section" data-section="i18n" data-title="<?php esc_attr_e( 'Other languages and money', 'omniloop' ); ?>" data-keywords="wpml polylang language translation multi currency woomulti price money english bangla">
						<h2><?php esc_html_e( 'Other languages and money', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'If the site uses WPML or Polylang, OmniLoop shows filter names in the current language and keeps other-language products out of the list. That is hook-level support — not a full multilingual editor.', 'omniloop' ); ?></p>
						<p><?php esc_html_e( 'If a multi-currency plugin is active (WooCommerce Multi-Currency or WPML WooCommerce), the price bar follows the shopper currency when those plugins expose conversion functions. Otherwise the store’s base currency is used.', 'omniloop' ); ?></p>
					</section>

					<section class="omniloop-docs-section" data-section="copy-setup" data-title="<?php esc_attr_e( 'Copy setup to another site', 'omniloop' ); ?>" data-keywords="export import json agency tools copy settings client">
						<h2><?php esc_html_e( 'Copy setup to another site', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'Built the filters once and need the same rules on a client site? Use Settings → Tools.', 'omniloop' ); ?></p>
						<ol>
							<li><?php esc_html_e( 'On the finished site, click Download settings JSON.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'On the new site, upload that file and import.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Click Rebuild index. Products themselves are not in the file — only your OmniLoop choices.', 'omniloop' ); ?></li>
						</ol>
					</section>

					<section class="omniloop-docs-section" data-section="fix" data-title="<?php esc_attr_e( 'Troubleshooting', 'omniloop' ); ?>" data-keywords="fix help fix 404 empty filter slow rebuild permalinks">
						<h2><?php esc_html_e( 'Troubleshooting', 'omniloop' ); ?></h2>
						<ul>
							<li><strong><?php esc_html_e( 'Filters look empty or outdated', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Rebuild the index in OmniLoop → Settings.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Filter pages show “not found”', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Go to Settings → Permalinks and click Save. You do not need to change anything else.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'New options missing in Divi', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Delete the old OmniLoop module, insert a new one, then hard-refresh the page (Ctrl+F5).', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'No products appear', 'omniloop' ); ?></strong> — <?php esc_html_e( 'In the module, Post Type should be Products. Those products must be published, and Products must be ticked in Settings.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'A filter never shows', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Check Settings → Facets. A “show” rule can hide a group until another filter is picked.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Builder vs live page look different', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Always test on the published page. Shoppers never see the builder.', 'omniloop' ); ?></li>
						</ul>
						<div class="omniloop-docs-callout">
							<strong><?php esc_html_e( 'Still stuck?', 'omniloop' ); ?></strong>
							<p><?php esc_html_e( 'Send your WordPress, Divi, PHP, and OmniLoop versions, plus a screenshot of OmniLoop → Settings and the page URL.', 'omniloop' ); ?></p>
						</div>
					</section>

					<div class="omniloop-docs-footer-nav">
						<button type="button" id="omniloop-docs-prev" disabled><?php esc_html_e( '← Previous', 'omniloop' ); ?></button>
						<button type="button" id="omniloop-docs-next"><?php esc_html_e( 'Next →', 'omniloop' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
