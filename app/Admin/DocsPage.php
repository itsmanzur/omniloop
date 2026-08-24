<?php
/**
 * Interactive English help guide for non-technical users.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Admin;

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
		?>
		<div class="wrap omniloop-docs" id="omniloop-docs-app">
			<header class="omniloop-docs-hero">
				<p class="omniloop-docs-hero__eyebrow"><?php esc_html_e( 'Help Guide', 'omniloop' ); ?></p>
				<h1><?php esc_html_e( 'Welcome to OmniLoop', 'omniloop' ); ?></h1>
				<p>
					<?php esc_html_e( 'This short guide explains what OmniLoop does and how to use it — in plain English. No coding needed.', 'omniloop' ); ?>
				</p>
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

					<section class="omniloop-docs-section is-active" data-section="welcome" data-title="<?php esc_attr_e( 'What is OmniLoop?', 'omniloop' ); ?>" data-keywords="intro overview what is plugin product filter shop loop divi">
						<span class="omniloop-docs-kicker"><?php esc_html_e( 'Start here', 'omniloop' ); ?></span>
						<h2><?php esc_html_e( 'What is OmniLoop?', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'OmniLoop is a Divi 5 tool that builds a smart product or post grid with filters on the side (or above the grid). Visitors can search, sort, and narrow results instantly — without reloading the whole page.', 'omniloop' ); ?></p>
						<p><?php esc_html_e( 'Think of it like a modern online shop catalog: pick a category, choose a color, set a price range, and the list updates right away.', 'omniloop' ); ?></p>
						<div class="omniloop-docs-callout">
							<strong><?php esc_html_e( 'One module, one job', 'omniloop' ); ?></strong>
							<p><?php esc_html_e( 'You only need one Divi module: “OmniLoop Filter”. Place it on a page, choose what to show, and you are done.', 'omniloop' ); ?></p>
						</div>
						<p><?php esc_html_e( 'WooCommerce is optional. Without it, OmniLoop still works for blog posts and other content types. With WooCommerce, you unlock product price, stock, sale, ratings, and attribute filters.', 'omniloop' ); ?></p>
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
							<li><strong><?php esc_html_e( 'Quick View', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Lets shoppers peek at an item in a popup without leaving the list.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Show Pagination', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Shows page numbers under the loop. Turn off to display only the first page of results.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Within-facet match', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Advanced. Smart mode usually means “any category” but “all selected attributes”. Most shops can leave this on Smart.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Merchandising', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Uses rules from OmniLoop → Settings: pin products, push out-of-stock last, promo badges, and “Only X left”. Extra Pin IDs apply only to this module.', 'omniloop' ); ?></li>
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

					<section class="omniloop-docs-section" data-section="shopper" data-title="<?php esc_attr_e( 'What shoppers can do', 'omniloop' ); ?>" data-keywords="shopper filter search sort price slider chips sticky rating popularity cart quick view">
						<h2><?php esc_html_e( 'What shoppers can do', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'On the published page, visitors can:', 'omniloop' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'Type in Search to find items by title.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Use category, brand, attribute, stock, sale, featured, and rating filters (when available).', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Drag the dual price slider or type From–To amounts.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Search inside long filter lists (type to narrow categories or brands).', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Sort by newest, title, price, popularity, or average rating.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'See active choices as chips under the result count, and clear them anytime.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Add products to the cart or open Quick View (if you enabled those options).', 'omniloop' ); ?></li>
						</ul>
						<div class="omniloop-docs-callout">
							<strong><?php esc_html_e( 'Sticky active bar', 'omniloop' ); ?></strong>
							<p><?php esc_html_e( 'As shoppers scroll, the result count and active filter chips stay visible near the top so they never lose track of what is selected.', 'omniloop' ); ?></p>
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

					<section class="omniloop-docs-section" data-section="settings" data-title="<?php esc_attr_e( 'Admin settings', 'omniloop' ); ?>" data-keywords="admin settings rebuild index acf post types dashboard seo analytics export import wpml">
						<h2><?php esc_html_e( 'Admin settings', 'omniloop' ); ?></h2>
						<p>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: settings URL */
									__( 'Open <a href="%s">OmniLoop → Settings</a> any time you need to:', 'omniloop' ),
									esc_url( $settings_url )
								),
								[ 'a' => [ 'href' => true ] ]
							);
							?>
						</p>
						<ul>
							<li><?php esc_html_e( 'See how many rows are indexed (your “Ready” status).', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Turn post types on or off for indexing.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Choose ACF fields to use as filters (if ACF is installed).', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Rebuild the index after catalog or field changes.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'SEO tab: title, description, and H1 templates for filtered URLs, plus noindex and sitemap options.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Facets tab: hide zero-count values, show/hide one facet based on another, and typo-tolerant search.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Analytics tab: which filters shoppers click most, and which combinations return nothing.', 'omniloop' ); ?></li>
							<li><?php esc_html_e( 'Tools tab: export or import settings JSON when you copy a setup to another site.', 'omniloop' ); ?></li>
						</ul>
					</section>

					<section class="omniloop-docs-section" data-section="seo" data-title="<?php esc_attr_e( 'Filtered URL SEO', 'omniloop' ); ?>" data-keywords="seo title description h1 robots noindex sitemap pretty url placeholders">
						<h2><?php esc_html_e( 'Filtered URL SEO', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'Pretty URLs like /ol/category/shoes/ already work. The SEO tab lets you control what search engines see on those pages.', 'omniloop' ); ?></p>
						<ul>
							<li><strong><?php esc_html_e( 'Templates', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Use placeholders such as {facet_labels}, {category}, {page_title}, and {site_name}.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Noindex', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Turn on “page 2+” to avoid duplicate listings. Use “all filtered URLs” only if you do not want those pages indexed at all.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Sitemap', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Optional. Adds single-facet URLs (for example each category) for the page you choose.', 'omniloop' ); ?></li>
						</ul>
					</section>

					<section class="omniloop-docs-section" data-section="workflows" data-title="<?php esc_attr_e( 'Two ways to build a loop', 'omniloop' ); ?>" data-keywords="divi 5 native loop builder workflow module facets attach query condition index keys">
						<h2><?php esc_html_e( 'Two ways to build a loop', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'OmniLoop can power the whole catalog, or sit next to Divi 5’s own Loop Builder.', 'omniloop' ); ?></p>
						<ol>
							<li>
								<strong><?php esc_html_e( 'Workflow A — OmniLoop Filter module', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Insert OmniLoop Filter. It owns the filters, the grid, pagination, merchandising, and pretty URLs. This is the usual shop setup.', 'omniloop' ); ?></span>
							</li>
							<li>
								<strong><?php esc_html_e( 'Workflow B — native Loop Builder + OmniLoop facets', 'omniloop' ); ?></strong>
								<span><?php esc_html_e( 'Keep Divi’s Loop Builder for the cards. When shoppers use OmniLoop pretty URLs (or you set omniloop_attach=1 on the query), OmniLoop restricts that loop to matching IDs from its index — no extra postmeta joins.', 'omniloop' ); ?></span>
							</li>
						</ol>
						<div class="omniloop-docs-callout">
							<strong><?php esc_html_e( 'Loop Builder query conditions', 'omniloop' ); ?></strong>
							<p><?php esc_html_e( 'Facet keys from the index are available at GET /wp-json/omniloop/v1/facet-keys?post_type=product and via the omniloop_loop_builder_facet_keys filter. PHP helper: omniloop_matching_ids( \'product\', [ \'product_cat\' => [ \'shoes\' ] ] ).', 'omniloop' ); ?></p>
						</div>
					</section>

					<section class="omniloop-docs-section" data-section="i18n" data-title="<?php esc_attr_e( 'Languages and currency', 'omniloop' ); ?>" data-keywords="wpml polylang language translation multi currency woomulti price">
						<h2><?php esc_html_e( 'Languages and currency', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'If WPML or Polylang is active, facet labels and term names follow the current language, and the index query stays inside that language. Price sliders follow WOOMULTI / CURCY when that plugin converts WooCommerce prices.', 'omniloop' ); ?></p>
					</section>

					<section class="omniloop-docs-section" data-section="fix" data-title="<?php esc_attr_e( 'Troubleshooting', 'omniloop' ); ?>" data-keywords="fix help fix 404 empty filter slow rebuild permalinks">
						<h2><?php esc_html_e( 'Troubleshooting', 'omniloop' ); ?></h2>
						<ul>
							<li><strong><?php esc_html_e( 'Filters look empty or outdated', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Rebuild the index in OmniLoop → Settings.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Pretty filter URLs show 404', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Go to Settings → Permalinks and click Save (you do not need to change anything).', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'New options missing in Divi', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Remove the old OmniLoop module and insert a fresh one, then hard-refresh.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'No products appear', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Confirm Post Type is Products, those products are published, and Products is checked in Settings.', 'omniloop' ); ?></li>
							<li><strong><?php esc_html_e( 'Builder vs live page differ', 'omniloop' ); ?></strong> — <?php esc_html_e( 'Always confirm filters on the published page. The builder preview is helpful, but shoppers use the front end.', 'omniloop' ); ?></li>
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
