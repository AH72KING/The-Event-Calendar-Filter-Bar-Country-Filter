<?php
/**
 * This file is edited version of views.php in folder src/Tribe/
 * Basically just one line no 345
 * Controls the filter views.
 */

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) { die( '-1' ); }

if ( ! class_exists( 'Tribe__Events__Filterbar__View' ) ) {
	class Tribe__Events__Filterbar__View {

		/**
		 * @static
		 * @var The instance of the class.
		 */
		protected static $instance;

		/**
		 * @var string The absolute path to the main plugin file
		 */
		protected static $plugin_file = '';

		/**
		 * @var The directory of the plugin.
		 */
		protected $pluginDir;

		/**
		 * @var the plugin path.
		 */
		protected $pluginPath;

		/**
		 * @var Whether filters sidebar is being displayed or not.
		 */
		protected $sidebarDisplayed;

		/**
		 * @var The default filters for a MU site.
		 */
		protected static $defaultMuFilters;

		const REQUIRED_TEC_VERSION = '4.0.2';

		const VERSION = '4.0.2';
		/**
		 * Initialize the addon to make sure the versions line up.
		 *
		 * @author PaulHughes01
		 * @since 0.1
		 * @param array $plugins The array of registered plugins.
		 * @return array The array of registered plugins.
		 */
		public static function initAddon( $plugins ) {
			$plugins['TribeFilterView'] = array(
				'plugin_name' => 'The Events Calendar: Filter Bar',
				'required_version' => self::REQUIRED_TEC_VERSION,
				'current_version' => self::VERSION,
				'plugin_dir_file' => basename( dirname( dirname( __FILE__ ) ) ) . '/the-events-calendar-filter-view.php',
			);
			return $plugins;
		}

		/**
		 * Create the plugin instance and include the other class.
		 *
		 * @param string $plugin_file_path
		 * @since 3.4
		 * @return void
		 */
		public static function init( $plugin_file_path ) {
			self::$plugin_file = $plugin_file_path;
//			spl_autoload_register( array( __CLASS__, 'autoloader') );
			self::$instance = self::instance();
		}

		// @todo: make sure this is not needed anymore - 3202
//		public static function autoloader( $class ) {
//			if ( strpos( $class, 'Tribe__Events__Filterbar__Filter' ) !== 0 ) {
//				return;
//			}
//			$dir = self::plugin_path('lib');
//			if ( strpos( $class, 'TribeEventsFilter_') === 0 ) {
//				$dir .= DIRECTORY_SEPARATOR.'filters';
//			}
//
//			if ( file_exists( $dir.DIRECTORY_SEPARATOR.$class.'.php' ) ) {
//				include_once( $dir.DIRECTORY_SEPARATOR.$class.'.php' );
//				return;
//			}
//		}

		/**
		 * The singleton function.
		 *
		 * @since 3.4
		 * @return Tribe__Events__Filterbar__View The instance.
		 */
		public static function instance() {
			if ( ! self::$instance instanceof self ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * The class constructor.
		 *
		 * @author PaulHughes01
		 * @since 3.4
		 * @return void
		 */
		public function __construct() {
			$this->pluginPath = trailingslashit( dirname( dirname( dirname( __FILE__ ) ) ) );
			$this->pluginDir  = trailingslashit( basename( $this->pluginPath ) );
			$this->pluginUrl = trailingslashit( plugins_url() . '/' . $this->pluginDir );
			$this->sidebarDisplayed = false;

			add_action( 'wp', array( $this, 'setSidebarDisplayed' ) );
			add_action( 'parse_query', array( $this, 'maybe_initialize_filters_for_query' ), 10, 1 );
			add_action( 'current_screen', array( $this, 'maybe_initialize_filters_for_screen' ), 10, 0 );
			add_filter( 'body_class', array( $this, 'addBodyClass' ) );

			add_filter( 'tribe_events_template_paths', array( $this, 'template_paths' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueueStylesAndScripts' ), 11 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAdminScripts' ) );

			$settings_page = new Tribe__Events__Filterbar__Settings();
			$settings_page->set_hooks();

			add_action( 'init', array( $this, 'loadTextDomain' ) );

			// Load multisite defaults
			if ( is_multisite() ) {
				$tribe_events_filters_default_mu_filters = array();
				if ( file_exists( WP_CONTENT_DIR . '/tribe-events-mu-defaults.php' ) )
					include( WP_CONTENT_DIR . '/tribe-events-mu-defaults.php' );
				self::$defaultMuFilters = apply_filters( 'tribe_events_mu_filters_default_filters', $tribe_events_filters_default_mu_filters );
			}
		}

		/**
		 * Enqueue the plugin stylesheet(s).
		 *
		 * @author PaulHughes01
		 * @since 3.4
		 * @return void
		 */
		public function enqueueStylesAndScripts() {
			if ( tribe_is_event_query() ||  tribe_is_event_organizer() || tribe_is_event_venue() ) {

				$show_filter = apply_filters( 'tribe_events_filters_should_show', in_array( get_post_type(), array( Tribe__Events__Main::VENUE_POST_TYPE, Tribe__Events__Main::ORGANIZER_POST_TYPE ) ) ? false : true );

				if ( $show_filter ) {
					//Only display filters before template if the layout is horizontal
					if ( tribe_get_option( 'events_filters_layout', 'vertical' ) == 'vertical' ) {
						add_action( 'tribe_events_bar_after_template', array( $this, 'displaySidebar' ), 25 );
					} else {
						if ( tribe_get_option( 'tribeDisableTribeBar', false ) == true ) {
							add_action( 'tribe_events_before_template', array( $this, 'displaySidebar' ), 25 );
						} else {
							add_action( 'tribe_events_bar_after_template', array( $this, 'displaySidebar' ), 25 );
						}
					}
				}

				// enqueue chosen for tag multi-select
				Tribe__Events__Template_Factory::asset_package( 'chosen' );
				Tribe__Events__Template_Factory::asset_package( 'calendar-script', array( 'jquery-ui-slider' ) );

				wp_enqueue_style( 'custom-jquery-styles' );
				wp_enqueue_style(
					'Tribe__Events__Filterbar__View-css',
					Tribe__Events__Template_Factory::getMinFile( $this->pluginUrl . 'src/resources/css/filter-view.css', true ),
					array(),
					apply_filters( 'tribe_events_filters_css_version', self::VERSION )
				);

				wp_enqueue_script( 'jquery-ui-slider' );
				wp_enqueue_script(
					'Tribe__Events__Filterbar__View-scripts',
					Tribe__Events__Template_Factory::getMinFile( $this->pluginUrl . 'src/resources/js/filter-scripts.js', true ),
					array(),
					apply_filters( 'tribe_events_filters_js_version', self::VERSION )
				);

				//Check for override stylesheet
				$user_stylesheet_url = Tribe__Events__Templates::locate_stylesheet( 'tribe-events/filterbar/filter-view.css' );
				$user_stylesheet_url = apply_filters( 'tribe_events_filterbar_stylesheet_url', $user_stylesheet_url );

				//If override stylesheet exists, then enqueue it
				if ( $user_stylesheet_url ) {
					wp_enqueue_style( 'tribe-events-filterbar-override-style', $user_stylesheet_url );
				}
			}
		}

		/**
		 * Enqueue the admin scripts.
		 *
		 * @author PaulHughes01
		 * @since 3.4
		 * @return void
		 */
		public function enqueueAdminScripts() {
			global $current_screen;
			if ( $current_screen->id == 'tribe_events_page_' . Tribe__Settings::$parent_slug && isset( $_GET['tab'] ) && $_GET['tab'] == 'filter-view' ) {
				wp_enqueue_script( 'jquery-ui-sortable' );
			}

		}

		public function setSidebarDisplayed( $unused_query ) {
			if ( tribe_is_event_query() && ( ! is_single() || tribe_is_showing_all() ) && ! is_admin() ) {
				$active_filters = $this->get_active_filters();
				if ( ! empty( $active_filters ) ) {
					$this->sidebarDisplayed = true;
				}
			}
		}

		/**
		 * Add the filters body class.
		 *
		 * @author PaulHughes01
		 * @since 3.4
		 * @return array The new set of body classes.
		 */
		public function addBodyClass( $classes ) {
			if ( $this->sidebarDisplayed ) {
				$classes[] = 'tribe-events-filter-view';
				$classes[] = 'tribe-filters-' . tribe_get_option( 'events_filters_default_state', 'closed' );
			}
			return $classes;
		}

		/**
		 * Add premium plugin paths for each file in the templates array
		 *
		 * @param $template_paths array
		 * @return array
		 * @author Jessica Yazbek
		 * @since 3.4
		 */
		public function template_paths( $template_paths = array() ) {
			$template_paths['filter-bar'] = $this->pluginPath;
			return $template_paths;
		}

		/**
		 * Display the filters sidebar.
		 *
		 * @author PaulHughes01
		 * @since 3.4
		 * @return void
		 */
		public function displaySidebar( $html ) {
			if ( $this->sidebarDisplayed ) {
				if ( ! is_single() || tribe_is_showing_all() ) {
					ob_start();
					tribe_get_template_part( 'filter-bar/filter-view-' . tribe_get_option( 'events_filters_layout', 'vertical' ) );
					$html = ob_get_clean() . $html;
				}
				echo $html;
			}
		}

		/**
		 * Returns whether or not there are tribe specific query vars in the given query object
		 *
		 * @param WP_Query $query Query object
		 *
		 * @return boolean
		 */
		public function is_tribe_query( $query ) {
			// if the post type is the event post type, we're in a tribe query
			if ( $query->get( 'post_type' ) === Tribe__Events__Main::POSTTYPE ) {
				return true;
			}

			foreach ( array_keys( $query->query ) as $key ) {
				if ( 0 === strpos( $key, 'tribe_' ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Create the default filters and execute the action for other filters to hook into.
		 * NOTE: The slug must be one word, no underscores or hyphens.
		 *
		 * @param WP_Query $query
		 *
		 * @return void
		 */
		public function maybe_initialize_filters_for_query( $query = null ) {

			if ( $this->is_tribe_query( $query ) ) {
				if (
					$query->is_main_query()
					|| ( defined( 'DOING_AJAX' ) && DOING_AJAX )
					|| (
						! empty( $query->query['tribe_render_context'] )
						&& 'default' === $query->query['tribe_render_context']
					)
				) {
					$this->initialize_filters();
				}
			}
		}

		public function maybe_initialize_filters_for_screen() {
			if ( $this->on_settings_page() ) {
				$this->initialize_filters();
			}
		}

		private function on_settings_page() {
			global $current_screen;
			if (
				   isset( $current_screen )
				&& $current_screen->id == 'tribe_events_page_tribe-common'
				&& isset( $_GET['tab'] )
				&& $_GET['tab'] == 'filter-view'
			) {
				return true;
			}
			return false;
		}

		public function initialize_filters() {
			static $initialized = false;
			if ( $initialized ) {
				return; // only run once
			}
			$initialized = true;
			new Tribe__Events__Filterbar__Filters__Category( sprintf( __( '%s Category', 'tribe-events-filter-view' ), tribe_get_event_label_singular() ), 'eventcategory' );
			new Tribe__Events__Filterbar__Filters__Cost( sprintf( __( 'Cost (%s)', 'tribe-events-filter-view' ), tribe_get_option( 'defaultCurrencySymbol', '$' ) ), 'cost' );
			new Tribe__Events__Filterbar__Filters__Tag( __( 'Tags', 'tribe-events-filter-view' ), 'tags' );
			new Tribe__Events__Filterbar__Filters__Venue( tribe_get_venue_label_plural(), 'venues' );
			new Tribe__Events__Filterbar__Filters__Country( 'Countries', 'Country' ); // added country name
			new Tribe__Events__Filterbar__Filters__Organizer( tribe_get_organizer_label_plural(), 'organizers' );
			new Tribe__Events__Filterbar__Filters__Day_Of_Week( __( 'Day', 'tribe-events-filter-view' ), 'dayofweek' );
			new Tribe__Events__Filterbar__Filters__Time_Of_Day( __( 'Time', 'tribe-events-filter-view' ), 'timeofday' );
			Tribe__Events__Filterbar__Additional_Fields__Manager::init();

			do_action( 'tribe_events_filters_create_filters' );
		}

		/**
		 * @return array|bool
		 */
		public function get_filter_settings() {
			$settings = get_option( Tribe__Events__Filterbar__Settings::OPTION_ACTIVE_FILTERS, false );
			if ( false === $settings ) {
				$settings = $this->get_multisite_default_settings();
			}
			return $settings;
		}

		protected function get_multisite_default_settings() {
			if ( ! is_multisite() ) {
				return false;
			}

			if ( empty( self::$defaultMuFilters ) ) {
				return false;
			}
			return self::$defaultMuFilters;
		}

		public function get_active_filters() {
			$current_filters = $this->get_filter_settings();
			if ( ! is_array( $current_filters ) ) { // everything is active
				$current_filters = $this->get_registered_filters();
			}
			return apply_filters( 'tribe_events_active_filters', array_keys( $current_filters ) );
		}

		public function get_registered_filters() {
			$filters = apply_filters( 'tribe_events_all_filters_array', array() );
			return $filters;
		}

		/**
		 * Load the plugin's textdomain.
		 *
		 * @return void
		 * @author Paul Hughes
		 * @since 3.4
		 */
		public function loadTextDomain() {
			load_plugin_textdomain( 'tribe-events-filter-view', false, $this->pluginDir . 'lang/' );
		}


		/**
		 * Get the absolute system path to the plugin directory, or a file therein
		 * @static
		 * @param string $path
		 * @return string
		 */
		public static function plugin_path( $path ) {
			$base = dirname( self::$plugin_file );
			if ( $path ) {
				return trailingslashit( $base ) . $path;
			} else {
				return untrailingslashit( $base );
			}
		}

		/**
		 * Get the absolute URL to the plugin directory, or a file therein
		 * @static
		 * @param string $path
		 * @return string
		 */
		public static function plugin_url( $path ) {
			return plugins_url( $path, self::$plugin_file );
		}
	}
}
