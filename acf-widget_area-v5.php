<?php
if ( !class_exists( 'acf_field_widget_area' ) ) :

	class acf_field_widget_area extends acf_field {
		/*
		 *  __construct
		 *
		 *  This function will setup the field type data
		 *
		 *  @type	function
		 *  @date	5/03/2014
		 *  @since	5.0.0
		 *
		 *  @param	n/a
		 *  @return	n/a
		 */

		function __construct() {

			// vars
			$this->name = 'widget_area';
			$this->label = __( "Widget Area", 'acf' );
			$this->category = 'layout';
			$this->defaults = array(
			    'layouts' => array(),
			    'min' => '',
			    'max' => '',
			    'button_label' => __( "Add Widget", 'acf' ),
			);
			$this->l10n = array(
			    'layout' => __( "layout", 'acf' ),
			    'layouts' => __( "layouts", 'acf' ),
			    'remove' => __( "remove {layout}?", 'acf' ),
			    'min' => __( "This field requires at least {min} {identifier}", 'acf' ),
			    'max' => __( "This field has a limit of {max} {identifier}", 'acf' ),
			    'min_layout' => __( "This field requires at least {min} {label} {identifier}", 'acf' ),
			    'max_layout' => __( "Maximum {label} limit reached ({max} {identifier})", 'acf' ),
			    'available' => __( "{available} {label} {identifier} available (max {max})", 'acf' ),
			    'required' => __( "{required} {label} {identifier} required (min {min})", 'acf' ),
			);


			// do not delete!
			parent::__construct();
		}

		public function input_admin_enqueue_scripts() {

			$dir = plugin_dir_url( __FILE__ );


			// register & include JS
			wp_register_script( 'acf-input-widget-area', "{$dir}js/input.js" );
			wp_enqueue_script( 'acf-input-widget-area' );

			// register & include CSS
			wp_register_style( 'acf-input-widget-area', "{$dir}css/input.css" );
			wp_enqueue_style( 'acf-input-widget-area' );
		}

		/*
		 *  get_valid_layout
		 *
		 *  This function will fill in the missing keys to create a valid layout
		 *
		 *  @type	function
		 *  @date	3/10/13
		 *  @since	1.1.0
		 *
		 *  @param	$layout (array)
		 *  @return	$layout (array)
		 */

		function get_valid_layout( $layout = array() ) {

			// parse
			$layout = wp_parse_args( $layout, array(
			    'key' => uniqid(),
			    'name' => '',
			    'label' => '',
			    'display' => 'block',
			    'sub_fields' => array(),
			    'min' => '',
			    'max' => '',
				) );


			// return
			return $layout;
		}

		/*
		 *  load_field()
		 *
		 *  This filter is appied to the $field after it is loaded from the database
		 *
		 *  @type	filter
		 *  @since	3.6
		 *  @date	23/01/13
		 *
		 *  @param	$field - the field array holding all the field options
		 *
		 *  @return	$field - the field array holding all the field options
		 */

		function load_field( $field ) {
			global $wp_widget_factory;

			$post = get_post();
			if ( $post->post_type == 'acf-field-group' ) {
				return $field;
			}

			$widgets = array();
			$subfields = array();

			if ( empty( $field['registered_widgets'] ) ) {
				return $field;
			}




			foreach ( $wp_widget_factory->widgets as $class => $widget_obj ) {
				if ( !in_array( $class, $field['registered_widgets'] ) ) {
					continue;
				}

				$widgets[$class] = $widget_obj->name;

				$field['layouts'][] = array(
				    'key' => md5( $class ),
				    'name' => 'layout_' . $widget_obj->id_base,
				    'label' => $widget_obj->name,
				    'display' => 'block',
				);

				$widget_field = array(
				    'key' => 'field_widget_' . md5( $class ),
				    'label' => '',
				    'name' => 'the_widget',
				    'prefix' => '',
				    'type' => 'a_widget',
				    'value' => NULL,
				    'menu_order' => 0,
				    'instructions' => '',
				    'required' => 0,
				    'id' => '',
				    'class' => '',
				    'conditional_logic' => 0,
				    'parent' => 0,
				    'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				    ),
				    '_name' => 'the_widget',
				    '_input' => '',
				    '_valid' => 1,
				    'widget' => $class,
				    'sub_fields' => array(),
				    'parent_layout' => md5( $class ),
				);
				
				if ( $widget_field ) {
					$widget_field = apply_filters( "acf/load_field", $widget_field );
					$widget_field = apply_filters( "acf/load_field/type={$widget_field['type']}", $widget_field );
					$widget_field = apply_filters( "acf/load_field/name={$widget_field['name']}", $widget_field );
					$widget_field = apply_filters( "acf/load_field/key={$widget_field['key']}", $widget_field );
				}
				
				$subfields[] = $widget_field;
			}

			// bail early if no field layouts
			if ( empty( $field['layouts'] ) ) {

				return $field;
			}


			// vars
			//$sub_fields = acf_get_fields( $field );
			$sub_fields = $subfields;

			// loop through layouts, sub fields and swap out the field key with the real field
			foreach ( array_keys( $field['layouts'] ) as $i ) {

				// extract layout
				$layout = acf_extract_var( $field['layouts'], $i );


				// validate layout
				$layout = $this->get_valid_layout( $layout );


				// append sub fields
				if ( !empty( $sub_fields ) ) {

					foreach ( array_keys( $sub_fields ) as $k ) {

						// check if 'parent_layout' is empty
						if ( empty( $sub_fields[$k]['parent_layout'] ) ) {

							// parent_layout did not save for this field, default it to first layout
							$sub_fields[$k]['parent_layout'] = $layout['key'];
						}


						// append sub field to layout, 
						if ( $sub_fields[$k]['parent_layout'] == $layout['key'] ) {

							$layout['sub_fields'][] = acf_extract_var( $sub_fields, $k );
						}
					}
				}


				// append back to layouts
				$field['layouts'][$i] = $layout;
			}


			// return
			return $field;
		}

		/*
		 *  render_field()
		 *
		 *  Create the HTML interface for your field
		 *
		 *  @param	$field - an array holding all the field's data
		 *
		 *  @type	action
		 *  @since	3.6
		 *  @date	23/01/13
		 */

		function render_field( $field ) {
			$sidebar_name = 'Sidebar';
			foreach ( $GLOBALS['wp_registered_sidebars'] as $sidebar ) {
				if ( $sidebar['id'] == $field['registered_widget_area'] ) {
					$sidebar_name = $sidebar['name'];
				}
			}


			// defaults
			if ( empty( $field['button_label'] ) ) {

				$field['button_label'] = $this->defaults['button_label'];
			}

			// sort layouts into names
			$layouts = array();

			foreach ( $field['layouts'] as $k => $layout ) {

				$layouts[$layout['name']] = acf_extract_var( $field['layouts'], $k );
			}


			// hidden input
			acf_hidden_input( array(
			    'type' => 'hidden',
			    'name' => $field['name'],
			) );


			// no value message
			$no_value_message = __( 'Click the "%s" button below to start creating your layout', 'acf' );
			$no_value_message = apply_filters( 'acf/fields/widget_area/no_value_message', $no_value_message, $field );
			?>


			<div class="acf-field acf-field-select">
				<div class="acf-label">
					<label><?php printf( __( 'Customize Sidebar: %s', 'acf' ), $sidebar_name ); ?></label>
				</div>
				<div class="acf-input">
					<select id="" class="" name="<?php echo "{$field['name']}[acf_widget_area_is_customized]"; ?>"
						data-ui="0" 
						data-ajax="0" 
						data-multiple="0" 
						data-placeholder="" 
						data-allow_null="0">
						<option value="no" <?php selected( 'no', $field['value']['internal_values']['customized'] ); ?>>No</option>
						<option value="yes" <?php selected( 'yes', $field['value']['internal_values']['customized'] ); ?>>Yes</option>
						<option value="inherit" <?php selected( 'inherit', $field['value']['internal_values']['customized'] ); ?>>Inherit</option>
					</select>
				</div>
			</div>

			<?php unset( $field['value']['internal_values'] ); ?>

			<div <?php acf_esc_attr_e( array('class' => 'acf-widget-area', 'data-min' => $field['min'], 'data-max' => $field['max']) ); ?>>

				<div class="no-value-message" <?php
				if ( $field['value']['rows'] ) {
					echo 'style="display:none;"';
				}
				?>>
					     <?php printf( $no_value_message, $field['button_label'] ); ?>
				</div>

				<div class="clones">
					<?php foreach ( $layouts as $layout ): ?>
						<?php $this->render_layout( $field, $layout, 'acfcloneindex', array() ); ?>
					<?php endforeach; ?>
				</div>
				<div class="values">
					<?php if ( !empty( $field['value']['rows'] ) ): ?>
						<?php foreach ( $field['value']['rows'] as $i => $value ): ?>
							<?php
							// validate
							if ( empty( $layouts[$value['acf_fc_layout']] ) ) {

								continue;
							}

							$this->render_layout( $field, $layouts[$value['acf_fc_layout']], $i, $value );
							?>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<ul class="acf-hl">
					<li class="acf-fr">
						<a href="#" class="acf-button blue button button-primary" data-event="add-widget"><?php echo $field['button_label']; ?></a>
					</li>
				</ul>

				<script type="text-html" class="tmpl-popup"><?php ?><div class="acf-wa-popup">
					<ul>
					<?php
					foreach ( $layouts as $layout ):

						$atts = array(
						    'data-layout' => $layout['name'],
						    'data-min' => $layout['min'],
						    'data-max' => $layout['max'],
						);
						?>
						<li>
						<a href="#" <?php acf_esc_attr_e( $atts ); ?>><?php echo $layout['label']; ?><span class="status"></span></a>
						</li>
					<?php endforeach; ?>
					</ul>
					<a href="#" class="focus"></a>
					</div>
				</script>

			</div>
			<?php
		}

		/*
		 *  render_layout
		 *
		 *  description
		 *
		 *  @type	function
		 *  @date	19/11/2013
		 *  @since	5.0.0
		 *
		 *  @param	$post_id (int)
		 *  @return	$post_id (int)
		 */

		function render_layout( $field, $layout, $i, $value ) {

			// vars
			$order = 0;
			$el = 'div';
			$div = array(
			    'class' => 'layout',
			    'data-id' => $i,
			    'data-layout' => $layout['name']
			);


			//Collapse by default. 
			$div['class'] .= ' -collapsed';
			


			// clone
			if ( is_numeric( $i ) ) {

				$order = $i + 1;
			} else {

				$div['class'] .= ' acf-clone';
			}
			?>

			<div <?php acf_esc_attr_e( $div ); ?>>

				<div class="acf-hidden">
					<?php acf_hidden_input( array('name' => "{$field['name']}[{$i}][acf_fc_layout]", 'value' => $layout['name']) ); ?>
				</div>

				<div class="acf-wa-layout-handle">
					<span class="fc-layout-order"><?php echo $order; ?></span> <?php echo $layout['label']; ?>
				</div>

				<ul class="acf-wa-layout-controlls acf-hl">
					<li class="acf-wa-show-on-hover">
						<a class="acf-icon -plus small" href="#" data-event="add-widget" title="<?php _e( 'Add layout', 'acf' ); ?>"></a>
					</li>
					<li class="acf-wa-show-on-hover">
						<a class="acf-icon -minus small" href="#" data-event="remove-widget" title="<?php _e( 'Remove layout', 'acf' ); ?>"></a>
					</li>
					<li>
						<a class="acf-icon -collapse small" href="#" data-event="collapse-widget" title="<?php _e( 'Click to toggle', 'acf' ); ?>"></a>
					</li>
				</ul>

				<?php if ( !empty( $layout['sub_fields'] ) ): ?>

					<?php
					if ( $layout['display'] == 'table' ):

						// update vars
						$el = 'td';
						?>
						<table class="acf-table">

							<thead>
								<tr>
									<?php
									foreach ( $layout['sub_fields'] as $sub_field ):

										$atts = array(
										    'class' => "acf-th acf-th-{$sub_field['name']}",
										    'data-key' => $sub_field['key'],
										);


										// Add custom width
										if ( $sub_field['wrapper']['width'] ) {

											$atts['data-width'] = $sub_field['wrapper']['width'];
										}
										?>
										<th <?php acf_esc_attr_e( $atts ); ?>>
											<?php acf_the_field_label( $sub_field ); ?>
											<?php if ( $sub_field['instructions'] ): ?>
									<p class="description"><?php echo $sub_field['instructions']; ?></p>
								<?php endif; ?>
								</th>

							<?php endforeach; ?> 
							</tr>
							</thead>

							<tbody>
							<?php else: ?>
							<div class="acf-fields <?php if ( $layout['display'] == 'row' ): ?>-left<?php endif; ?>">
							<?php endif; ?>

							<?php
							// loop though sub fields
							foreach ( $layout['sub_fields'] as $sub_field ) {

								// prevent repeater field from creating multiple conditional logic items for each row
								if ( $i !== 'acfcloneindex' ) {

									$sub_field['conditional_logic'] = 0;
								}


								// add value
								if ( isset( $value[$sub_field['key']] ) ) {

									// this is a normal value
									$sub_field['value'] = $value[$sub_field['key']];
								} elseif ( isset( $sub_field['default_value'] ) ) {

									// no value, but this sub field has a default value
									$sub_field['value'] = $sub_field['default_value'];
								}


								// update prefix to allow for nested values
								$sub_field['prefix'] = "{$field['name']}[{$i}]";


								// render input
								acf_render_field_wrap( $sub_field, $el );
							}
							?>

							<?php if ( $layout['display'] == 'table' ): ?>
								</tbody>
						</table>
					<?php else: ?>
					</div>
				<?php endif; ?>

			<?php endif; ?>
			</div>
			<?php
		}

		/*
		 *  render_field_settings()
		 *
		 *  Create extra options for your field. This is rendered when editing a field.
		 *  The value of $field['name'] can be used (like bellow) to save extra data to the $field
		 *
		 *  @type	action
		 *  @since	3.6
		 *  @date	23/01/13
		 *
		 *  @param	$field	- an array holding all the field's data
		 */

		function render_field_settings( $field ) {
			global $wp_widget_factory;
			// min
			acf_render_field_setting( $field, array(
			    'label' => __( 'Button Label', 'acf' ),
			    'instructions' => '',
			    'type' => 'text',
			    'name' => 'button_label',
			) );


			// min
			acf_render_field_setting( $field, array(
			    'label' => __( 'Minimum Layouts', 'acf' ),
			    'instructions' => '',
			    'type' => 'number',
			    'name' => 'min',
			) );


			// max
			acf_render_field_setting( $field, array(
			    'label' => __( 'Maximum Layouts', 'acf' ),
			    'instructions' => '',
			    'type' => 'number',
			    'name' => 'max',
			) );

			$sidebars = array();
			foreach ( $GLOBALS['wp_registered_sidebars'] as $sidebar ) {
				$sidebars[$sidebar['id']] = $sidebar['name'];
			}

			acf_render_field_setting( $field, array(
			    'label' => __( 'Widget Area', 'acf' ),
			    'instructions' => __( 'Select the widget area to override', 'acf' ),
			    'type' => 'select',
			    'name' => 'registered_widget_area',
			    'choices' => $sidebars
			) );


			$widgets = array();
			foreach ( $wp_widget_factory->widgets as $class => $widget_obj ) {
				$widgets[$class] = $widget_obj->name;
			}

			acf_render_field_setting( $field, array(
			    'label' => __( 'Allowed Widgets', 'acf' ),
			    'instructions' => __( 'Select the widgets allowed in this area', 'acf' ),
			    'type' => 'checkbox',
			    'name' => 'registered_widgets',
			    'choices' => $widgets
			) );
		}

		/*
		 *  load_value()
		 *
		 *  This filter is applied to the $value after it is loaded from the db
		 *
		 *  @type	filter
		 *  @since	3.6
		 *  @date	23/01/13
		 *
		 *  @param	$value (mixed) the value found in the database
		 *  @param	$post_id (mixed) the $post_id from which the value was loaded
		 *  @param	$field (array) the field array holding all the field options
		 *  @return	$value
		 */

		function load_value( $value, $post_id, $field ) {

			// bail early if no value
			if ( empty( $field['layouts'] ) ) {
				return $value;
			}


			// value must be an array
			$value = acf_get_array( $value );


			// vars
			$rows = array();


			// populate $layouts
			$layouts = array();

			foreach ( array_keys( $field['layouts'] ) as $i ) {

				// get layout
				$layout = $field['layouts'][$i];


				// append to $layouts
				$layouts[$layout['name']] = $layout['sub_fields'];
			}


			// loop through rows
			foreach ( $value as $i => $l ) {

				// append to $values
				$rows[$i] = array();
				$rows[$i]['acf_fc_layout'] = $l;


				// bail early if layout deosnt contain sub fields
				if ( empty( $layouts[$l] ) ) {

					continue;
				}


				// get layout
				$layout = $layouts[$l];


				// loop through sub fields
				foreach ( array_keys( $layout ) as $j ) {

					// get sub field
					$sub_field = $layout[$j];


					// update full name
					$sub_field['name'] = "{$field['name']}_{$i}_{$sub_field['name']}";


					// get value
					$sub_value = acf_get_value( $post_id, $sub_field );


					// add value
					$rows[$i][$sub_field['key']] = $sub_value;
				}
				// foreach
			}
			// foreach

			$value['rows'] = $rows;
			$customized_value = get_post_meta( $post_id, 'acf_widget_area_is_customized', true );
			if ( !empty( $customized_value ) && isset( $customized_value[$field['registered_widget_area']] ) ) {
				$value['internal_values']['customized'] = isset( $customized_value[$field['registered_widget_area']][$field['key']] ) ? $customized_value[$field['registered_widget_area']][$field['key']] : 'no';
			} else {
				$value['internal_values']['customized'] = 'no';
			}

			return $value;
		}

		/*
		 *  format_value()
		 *
		 *  This filter is appied to the $value after it is loaded from the db and before it is returned to the template
		 *
		 *  @type	filter
		 *  @since	3.6
		 *  @date	23/01/13
		 *
		 *  @param	$value (mixed) the value which was loaded from the database
		 *  @param	$post_id (mixed) the $post_id from which the value was loaded
		 *  @param	$field (array) the field array holding all the field options
		 *
		 *  @return	$value (mixed) the modified value
		 */

		function format_value( $value, $post_id, $field ) {

			// bail early if no value
			if ( empty( $value ) || empty( $field['layouts'] ) ) {

				return false;
			}


			// populate $layouts
			$layouts = array();

			foreach ( array_keys( $field['layouts'] ) as $i ) {

				// get layout
				$layout = $field['layouts'][$i];


				// append to $layouts
				$layouts[$layout['name']] = $layout['sub_fields'];
			}


			// loop over rows
			foreach ( array_keys( $value['rows'] ) as $i ) {

				// get layout name
				$l = $value['rows'][$i]['acf_fc_layout'];


				// bail early if layout deosnt exist
				if ( empty( $layouts[$l] ) ) {

					continue;
				}


				// get layout
				$layout = $layouts[$l];


				// loop through sub fields
				foreach ( array_keys( $layout ) as $j ) {

					// get sub field
					$sub_field = $layout[$j];


					// extract value
					$sub_value = acf_extract_var( $value['rows'][$i], $sub_field['key'] );


					// format value
					$sub_value = acf_format_value( $sub_value, $post_id, $sub_field );


					// append to $row
					$value['rows'][$i][$sub_field['name']] = $sub_value;
				}
			}


			// return
			return $value;
		}

		/*
		 *  validate_value
		 *
		 *  description
		 *
		 *  @type	function
		 *  @date	11/02/2014
		 *  @since	5.0.0
		 *
		 *  @param	$post_id (int)
		 *  @return	$post_id (int)
		 */

		function validate_value( $valid, $value, $field, $input ) {

			// remove acfcloneindex
			if ( isset( $value['acfcloneindex'] ) ) {

				unset( $value['acfcloneindex'] );
			}

			if ( isset( $value['acf_widget_area_is_customized'] ) ) {
				unset( $value['acf_widget_area_is_customized'] );
			}

			// valid
			if ( $field['required'] && empty( $value ) ) {

				$valid = false;
			}


			// populate $layouts
			$layouts = array();

			foreach ( array_keys( $field['layouts'] ) as $i ) {

				$layout = acf_extract_var( $field['layouts'], $i );

				// append to $layouts
				$layouts[$layout['name']] = $layout['sub_fields'];
			}


			// check sub fields
			if ( !empty( $value ) ) {

				// loop through rows
				foreach ( $value as $i => $row ) {

					// get layout
					$l = $row['acf_fc_layout'];


					// loop through sub fields
					if ( !empty( $layouts[$l] ) ) {

						foreach ( $layouts[$l] as $sub_field ) {

							// get sub field key
							$k = $sub_field['key'];


							// exists?
							if ( !isset( $value[$i][$k] ) ) {

								continue;
							}


							// validate
							acf_validate_value( $value[$i][$k], $sub_field, "{$input}[{$i}][{$k}]" );
						}
						// foreach
					}
					// if
				}
				// foreach
			}
			// if
			// return
			return $valid;
		}

		/*
		 *  update_value()
		 *
		 *  This filter is appied to the $value before it is updated in the db
		 *
		 *  @type	filter
		 *  @since	3.6
		 *  @date	23/01/13
		 *
		 *  @param	$value - the value which will be saved in the database
		 *  @param	$field - the field array holding all the field options
		 *  @param	$post_id - the $post_id of which the value will be saved
		 *
		 *  @return	$value - the modified value
		 */

		function update_value( $value, $post_id, $field ) {

			// remove acfcloneindex
			if ( isset( $value['acfcloneindex'] ) ) {

				unset( $value['acfcloneindex'] );
			}

			$customized_value = get_post_meta( $post_id, 'acf_widget_area_is_customized', true );
			if ( empty( $customized_value ) ) {
				$customized_value = array(
				    $field['registered_widget_area'] => array(
					$field['key'] => $value['acf_widget_area_is_customized']
				    )
				);
			} elseif ( !isset( $customized_value[$field['registered_widget_area']] ) ) {
				$customized_value[$field['registered_widget_area']] = array(
				    $field['key'] => $value['acf_widget_area_is_customized']
				);
			} else {
				$customized_value[$field['registered_widget_area']][$field['key']] = $value['acf_widget_area_is_customized'];
			}

			update_post_meta( $post_id, 'acf_widget_area_is_customized', $customized_value );
			unset( $value['acf_widget_area_is_customized'] );


			// vars
			$order = array();
			$layouts = array();


			// populate $layouts
			foreach ( $field['layouts'] as $layout ) {

				$layouts[$layout['name']] = $layout['sub_fields'];
			}


			// update sub fields
			if ( !empty( $value ) ) {

				// $i
				$i = -1;


				// loop through rows
				foreach ( $value as $row ) {

					// $i
					$i++;


					// get layout
					$l = $row['acf_fc_layout'];


					// append to order
					$order[] = $l;


					// loop through sub fields
					if ( !empty( $layouts[$l] ) ) {

						foreach ( $layouts[$l] as $sub_field ) {

							// value
							$v = false;


							// key (backend)
							if ( isset( $row[$sub_field['key']] ) ) {

								$v = $row[$sub_field['key']];
							} elseif ( isset( $row[$sub_field['name']] ) ) {

								$v = $row[$sub_field['name']];
							} else {

								// input is not set (hidden by conditioanl logic)
								continue;
							}


							// modify name for save
							$sub_field['name'] = "{$field['name']}_{$i}_{$sub_field['name']}";


							// update field
							acf_update_value( $v, $post_id, $sub_field );
						}
						// foreach
					}
					// if
				}
				// foreach
			}
			// if
			// remove old data
			$old_order = acf_get_metadata( $post_id, $field['name'] );
			$old_count = empty( $old_order ) ? 0 : count( $old_order );
			$new_count = empty( $order ) ? 0 : count( $order );


			if ( $old_count > $new_count ) {

				for ( $i = $new_count; $i < $old_count; $i++ ) {

					// get layout
					$l = $old_order[$i];


					// loop through sub fields
					if ( !empty( $layouts[$l] ) ) {

						foreach ( $layouts[$l] as $sub_field ) {

							// modify name for delete
							$sub_field['name'] = "{$field['name']}_{$i}_{$sub_field['name']}";


							// delete value
							acf_delete_value( $post_id, $sub_field );
						}
					}
				}
			}


			// save false for empty value
			if ( empty( $order ) ) {

				$order = false;
			}


			// return
			return $order;
		}

		/*
		 *  update_field()
		 *
		 *  This filter is appied to the $field before it is saved to the database
		 *
		 *  @type	filter
		 *  @since	3.6
		 *  @date	23/01/13
		 *
		 *  @param	$field - the field array holding all the field options
		 *  @param	$post_id - the field group ID (post_type = acf)
		 *
		 *  @return	$field - the modified field
		 */

		function update_field( $field ) {
			unset( $field['layouts'] );
			return $field;
		}

		/*
		 *  delete_field
		 *
		 *  description
		 *
		 *  @type	function
		 *  @date	4/04/2014
		 *  @since	5.0.0
		 *
		 *  @param	$post_id (int)
		 *  @return	$post_id (int)
		 */

		function delete_field( $field ) {

			if ( !empty( $field['layouts'] ) ) {

				// loop through layouts
				foreach ( $field['layouts'] as $layout ) {

					// loop through sub fields
					if ( !empty( $layout['sub_fields'] ) ) {

						foreach ( $layout['sub_fields'] as $sub_field ) {

							acf_delete_field( $sub_field['ID'] );
						}
						// foreach
					}
					// if
				}
				// foreach
			}
			// if
		}

		/*
		 *  duplicate_field()
		 *
		 *  This filter is appied to the $field before it is duplicated and saved to the database
		 *
		 *  @type	filter
		 *  @since	3.6
		 *  @date	23/01/13
		 *
		 *  @param	$field - the field array holding all the field options
		 *
		 *  @return	$field - the modified field
		 */

		function duplicate_field( $field ) {

			// vars
			$sub_fields = array();


			if ( !empty( $field['layouts'] ) ) {

				// loop through layouts
				foreach ( $field['layouts'] as $layout ) {

					// extract sub fields
					$extra = acf_extract_var( $layout, 'sub_fields' );


					// merge
					if ( !empty( $extra ) ) {

						$sub_fields = array_merge( $sub_fields, $extra );
					}
				}
				// foreach
			}
			// if
			// save field to get ID
			$field = acf_update_field( $field );


			// duplicate sub fields
			acf_duplicate_fields( $sub_fields, $field['ID'] );


			// return		
			return $field;
		}

	}

	new acf_field_widget_area();
endif;

