<?php

/*
  Plugin Name: Advanced Custom Fields: Widget Area
  Plugin URI: https://github.com/lucasstark/acf-field-widget-area
  Description: A field type which allows users to add widgets to a sidebar on posts and pages.  The widgets added will override the default sidebar.  
  Version: 1.0.0
  Author: Lucas Stark
  Author URI: https://github.com/lucasstark/
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
		add_action( 'sidebars_widgets', array($this, 'acf_widget_area_filter_widgets'), 99, 1 );
	}

	function acf_widget_area_filter_widgets( $widgets ) {
		global $wp_widget_factory, $wp_registered_widgets;

		if ( is_single() || is_page() ) {
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
								$field = get_field_object($field_key);
								
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
	}

}

ACF_Widget_Area_Sidebar::register();
