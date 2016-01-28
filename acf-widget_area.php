<?php

/*
  Plugin Name: Advanced Custom Fields: Widget Area
  Plugin URI: PLUGIN_URL
  Description: SHORT_DESCRIPTION
  Version: 1.0.0
  Author: AUTHOR_NAME
  Author URI: AUTHOR_URL
  License: GPLv2 or later
  License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */




// 1. set text domain
// Reference: https://codex.wordpress.org/Function_Reference/load_plugin_textdomain
load_plugin_textdomain( 'acf-widget_area', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );

// 2. Include field type for ACF5
// $version = 5 and can be ignored until ACF6 exists
function include_field_types_widget_area( $version ) {

	include_once('acf-widget_area-v5.php');
}

add_action( 'acf/include_field_types', 'include_field_types_widget_area' );

// 3. Include field type for ACF4
function register_fields_widget_area() {

	include_once('acf-widget_area-v4.php');
}

add_action( 'acf/register_fields', 'register_fields_widget_area' );

class ACF_Panels_Sidebars_Emulator {

	private $all_posts_widgets;

	function __construct() {
		$this->all_posts_widgets = array();
		add_action( 'widgets_init', array($this, 'register_widgets'), 99 );
		add_filter( 'sidebars_widgets', array($this, 'add_widgets_to_sidebars') );
	}

	/**
	 * Get the single instance.
	 *
	 * @return SiteOrigin_Panels_Widgets
	 */
	static function single() {
		static $single = false;
		if ( empty( $single ) )
			$single = new ACF_Panels_Sidebars_Emulator();

		return $single;
	}

	/**
	 * @param string $name The name of the function
	 * @param array $args
	 *
	 * @return mixed
	 */
	function __call( $name, $args ) {

		// Check if this is a filter option call
		preg_match( '/filter_option_widget_(.+)/', $name, $opt_matches );
		if ( !empty( $opt_matches ) && count( $opt_matches ) > 1 ) {
			$opt_name = $opt_matches[1];
			global $wp_widget_factory;
			foreach ( $wp_widget_factory->widgets as $widget ) {
				if ( $widget->id_base != $opt_name )
					continue;

				$widget_class = get_class( $widget );
				foreach ( $this->all_posts_widgets as $post_widgets ) {
					foreach ( $post_widgets as $widget_instance ) {
						if ( empty( $widget_instance['panels_info']['class'] ) )
							continue;

						$instance_class = $widget_instance['panels_info']['class'];
						if ( $instance_class == $widget_class ) {
							//The option value uses only the widget id number as keys
							preg_match( '/-([0-9]+$)/', $widget_instance['id'], $num_match );
							$args[0][$num_match[1]] = $widget_instance;
						}
					}
				}
			}

			return $args[0];
		}
	}

	/**
	 * Register all the current widgets so we can filter the get_option('widget_...') values to add instances
	 */
	function register_widgets() {
		global $wp_widget_factory;

		// Get the ID of the current post
		$post_id = url_to_postid( add_query_arg( false, false ) );
		if ( empty( $post_id ) ) {
			// Maybe this is the home page
			$current_url_path = parse_url( add_query_arg( false, false ), PHP_URL_PATH );
			$home_url_path = parse_url( trailingslashit( home_url() ), PHP_URL_PATH );

			if ( $current_url_path === $home_url_path && get_option( 'page_on_front' ) != 0 ) {
				$post_id = absint( get_option( 'page_on_front' ) );
			}
		}
		if ( empty( $post_id ) ) {
			return;
		}


		$widgets = array();
		$this->all_posts_widgets[$post_id] = array();
		foreach ( $widgets as $widget_instance ) {
			if ( empty( $widget_instance['panels_info']['class'] ) ) {
				continue;
			}

			$id_val = $post_id . strval( 1000 + intval( $widget_instance['panels_info']['id'] ) );
			$widget_class = $widget_instance['panels_info']['class'];
			if ( !empty( $wp_widget_factory->widgets[$widget_class] ) ) {
				$widget = $wp_widget_factory->widgets[$widget_class];
				$widget_instance['id'] = $widget->id_base . '-' . $id_val;
				$widget_option_names[] = $widget->option_name;
			}
			$this->all_posts_widgets[$post_id][] = $widget_instance;
		}

		$widget_option_names = array_unique( $widget_option_names );
		foreach ( $widget_option_names as $widget_option_name ) {
			add_filter( 'option_' . $widget_option_name, array($this, 'filter_option_' . $widget_option_name) );
		}
	}

	/**
	 * Add a sidebar for SiteOrigin Panels widgets so they are correctly detected by is_active_widget
	 *
	 * @param $sidebars_widgets
	 * @return array
	 */
	function add_widgets_to_sidebars( $sidebars_widgets ) {
		if ( empty( $this->all_posts_widgets ) )
			return $sidebars_widgets;

		foreach ( array_keys( $this->all_posts_widgets ) as $post_id ) {
			$post_widgets = $this->all_posts_widgets[$post_id];
			foreach ( $post_widgets as $widget_instance ) {
				if ( empty( $widget_instance['id'] ) )
					continue;
				//Sidebars widgets and the global $wp_registered widgets use full widget ids as keys
				$siteorigin_panels_widget_ids[] = $widget_instance['id'];
			}
			if ( !empty( $siteorigin_panels_widget_ids ) )
				$sidebars_widgets['sidebar-siteorigin_panels-post-' . $post_id] = $siteorigin_panels_widget_ids;
		}

		return $sidebars_widgets;
	}

}

//ACF_Panels_Sidebars_Emulator::single();




class ACF_Widget_Area_Sidebar {

	private static $instance;
	private $_page_widgets = array();
	private $_page_widget_instances = array();

	public static function register() {
		if ( self::$instance == null ) {
			self::$instance = new ACF_Widget_Area_Sidebar;
		}
	}

	private function __construct() {
		add_action( 'widgets_init', array($this, 'on_widgets_init'), 99 );
		add_action( 'sidebars_widgets', array($this, 'acf_widget_area_filter_widgets'), 99, 1 );
	}

	/**
	 * @param string $name The name of the function
	 * @param array $args
	 *
	 * @return mixed
	 */
	function __call( $name, $args ) {

		// Check if this is a filter option call
		preg_match( '/filter_option_widget_(.+)/', $name, $opt_matches );
		if ( !empty( $opt_matches ) && count( $opt_matches ) > 1 ) {
			$opt_name = $opt_matches[1];
			global $wp_widget_factory;
			foreach ( $wp_widget_factory->widgets as $widget ) {
				if ( $widget->id_base != $opt_name ) {
					continue;
				}

				$widget_class = get_class( $widget );
				break;

				foreach ( $this->all_posts_widgets as $post_widgets ) {
					foreach ( $post_widgets as $widget_instance ) {
						if ( empty( $widget_instance['panels_info']['class'] ) ) {
							continue;
						}

						$instance_class = $widget_instance['panels_info']['class'];
						if ( $instance_class == $widget_class ) {
							//The option value uses only the widget id number as keys
							preg_match( '/-([0-9]+$)/', $widget_instance['id'], $num_match );
							$args[0][$num_match[1]] = $widget_instance;
						}
					}
				}
			}

			return $args[0];
		}
	}

	function on_widgets_init() {
		//if (is_single()) {
		$object_id = get_queried_object_id();
		return;
		//}
	}

	function acf_widget_area_filter_widgets( $widgets ) {
		global $wp_widget_factory, $wp_registered_widgets;

		if ( is_single() ) {
			$object_id = get_queried_object_id();

			$this->_page_widgets[$object_id] = array();
			$this->_page_widget_instances[$object_id] = array();

			$customized_sidebars = get_post_meta( $object_id, 'acf_widget_area_is_customized', true );
			if ( !empty( $customized_sidebars ) ) {
				foreach ( array_keys( $widgets ) as $sidebar_id ) {
					if ( isset( $customized_sidebars[$sidebar_id] ) ) {
						foreach ( $customized_sidebars[$sidebar_id] as $field_key => $customized ) {
							if ( $customized == 'yes' ) {
								$widgets[$sidebar_id] = array();
								$value = get_field( $field_key );
								if ( $value && isset( $value['rows'] ) ) {
									foreach ( $value['rows'] as $layout_row ) {
										$the_widget_id = array_values( $layout_row )[1]['widget_id'];
										$the_widget_class = array_values( $layout_row )[1]['the_widget'];
										$instance = array_values( $layout_row )[1]['instance'];
										
										$this->_page_widgets[$object_id] = $layout_row;
										
										if ( !empty( $wp_widget_factory->widgets[$the_widget_class] ) ) {
											$widget = $wp_widget_factory->widgets[$the_widget_class];
											$widgets[$sidebar_id][] = $the_widget_id;
											wp_register_sidebar_widget( $the_widget_id , $widget->name, array($this, 'display_callback'), $widget->widget_options, array('instance' => $instance, 'widget' => $widget) );
										}
									}
								}
							}
						}
					}
				}
			}
		}
		return $widgets;
	}

	public function display_callback( $args, $widget_args = array() ) {
		$widget_args = wp_parse_args( $widget_args, array('widget' => null, 'instance' => null) );
		$widget = $widget_args['widget'];
		
		/**
		 * Filter the settings for a particular widget instance.
		 *
		 * Returning false will effectively short-circuit display of the widget.
		 *
		 * @since 2.8.0
		 *
		 * @param array     $instance The current widget instance's settings.
		 * @param WP_Widget $this     The current widget instance.
		 * @param array     $args     An array of default widget arguments.
		 */
		$instance = apply_filters( 'widget_display_callback', $widget_args['instance'], $widget, $args );

		if ( false === $instance ) {
			return;
		}

		$was_cache_addition_suspended = wp_suspend_cache_addition();
		if ( $widget->is_preview() && !$was_cache_addition_suspended ) {
			wp_suspend_cache_addition( true );
		}

		$widget->widget( $args, $instance );

		if ( $widget->is_preview() ) {
			wp_suspend_cache_addition( $was_cache_addition_suspended );
		}
		//}
	}

}

ACF_Widget_Area_Sidebar::register();
