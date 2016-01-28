(function ($) {
	
	acf.fields.widget_area = acf.field.extend({
		type: 'widget_area',
		$el: null,
		$input: null,
		$values: null,
		$clones: null,
		actions: {
			'ready': 'initialize',
			'append': 'initialize',
			'show': 'show'
		},
		events: {
			'click [data-event="add-layout"]': '_open',
			'click [data-event="remove-layout"]': '_remove',
			'click [data-event="collapse-layout"]': '_collapse',
			'click .acf-wa-layout-handle': '_collapse',
			'click .acf-wa-popup a': '_add',
			'blur .acf-wa-popup .focus': '_close',
			'mouseenter .acf-wa-layout-handle': '_mouseenter'
		},
		focus: function () {

			// vars
			this.$el = this.$field.find('.acf-widget-area:first');
			this.$input = this.$el.siblings('input');
			this.$values = this.$el.children('.values');
			this.$clones = this.$el.children('.clones');


			// get options
			this.o = acf.get_data(this.$el);


			// min / max
			this.o.min = this.o.min || 0;
			this.o.max = this.o.max || 0;

		},
		count: function () {

			return this.$values.children('.layout').length;

		},
		initialize: function () {

			// disable clone inputs
			this.$clones.find('input, textarea, select').attr('disabled', 'disabled');


			// render
			this.render();

		},
		show: function () {

			this.$values.find('.acf-field:visible').each(function () {

				acf.do_action('show_field', $(this));

			});

		},
		render: function () {

			// update order numbers
			this.$values.children('.layout').each(function (i) {

				$(this).find('> .acf-wa-layout-handle .fc-layout-order').html(i + 1);

			});


			// empty?
			if (this.count() == 0) {

				this.$el.addClass('empty');

			} else {

				this.$el.removeClass('empty');

			}


			// row limit reached
			if (this.o.max > 0 && this.count() >= this.o.max) {

				this.$el.find('> .acf-hl .acf-button').addClass('disabled');

			} else {

				this.$el.find('> .acf-hl .acf-button').removeClass('disabled');

			}

		},
		validate_add: function (layout) {

			// vadiate max
			if (this.o.max > 0 && this.count() >= this.o.max) {

				// vars
				var identifier = (this.o.max == 1) ? 'layout' : 'layouts',
					s = acf._e('widget_area', 'max');


				// translate
				s = s.replace('{max}', this.o.max);
				s = s.replace('{identifier}', acf._e('widget_area', identifier));


				// alert
				alert(s);


				// return
				return false;
			}


			// vadiate max layout
			var $popup = $(this.$el.children('.tmpl-popup').html()),
				$a = $popup.find('[data-layout="' + layout + '"]'),
				layout_max = parseInt($a.attr('data-max')),
				layout_count = this.$values.children('.layout[data-layout="' + layout + '"]').length;


			if (layout_max > 0 && layout_count >= layout_max) {

				// vars
				var identifier = (layout_max == 1) ? 'layout' : 'layouts',
					s = acf._e('widget_area', 'max_layout');


				// translate
				s = s.replace('{max}', layout_count);
				s = s.replace('{label}', '"' + $a.text() + '"');
				s = s.replace('{identifier}', acf._e('widget_area', identifier));


				// alert
				alert(s);


				// return
				return false;
			}


			// return
			return true;

		},
		validate_remove: function (layout) {

			// vadiate min
			if (this.o.min > 0 && this.count() <= this.o.min) {

				// vars
				var identifier = (this.o.min == 1) ? 'layout' : 'layouts',
					s = acf._e('widget_area', 'min') + ', ' + acf._e('widget_area', 'remove');


				// translate
				s = s.replace('{min}', this.o.min);
				s = s.replace('{identifier}', acf._e('widget_area', identifier));
				s = s.replace('{layout}', acf._e('widget_area', 'layout'));


				// return
				return confirm(s);

			}


			// vadiate max layout
			var $popup = $(this.$el.children('.tmpl-popup').html()),
				$a = $popup.find('[data-layout="' + layout + '"]'),
				layout_min = parseInt($a.attr('data-min')),
				layout_count = this.$values.children('.layout[data-layout="' + layout + '"]').length;


			if (layout_min > 0 && layout_count <= layout_min) {

				// vars
				var identifier = (layout_min == 1) ? 'layout' : 'layouts',
					s = acf._e('widget_area', 'min_layout') + ', ' + acf._e('widget_area', 'remove');


				// translate
				s = s.replace('{min}', layout_count);
				s = s.replace('{label}', '"' + $a.text() + '"');
				s = s.replace('{identifier}', acf._e('widget_area', identifier));
				s = s.replace('{layout}', acf._e('widget_area', 'layout'));


				// return
				return confirm(s);
			}


			// return
			return true;

		},
		sync: function () {

			// vars
			var name = 'collapsed_' + this.$field.data('key'),
				collapsed = [];


			// populate collapsed value
			this.$values.children('.layout').each(function (i) {

				if ($(this).hasClass('-collapsed')) {

					collapsed.push(i);

				}

			});


			// update
			acf.update_user_setting(name, collapsed.join(','));

		},
		add: function (layout, $before) {

			// defaults
			$before = $before || false;


			// bail early if validation fails
			if (!this.validate_add(layout)) {

				return false;

			}


			// reference
			var $field = this.$field;


			// vars
			var $clone = this.$clones.children('.layout[data-layout="' + layout + '"]');
			

			// duplicate
			$el = acf.duplicate($clone);


			// enable inputs (ignore inputs disabled for life)
			$el.find('input, textarea, select').not('.acf-disabled').removeAttr('disabled');


			// hide no values message
			this.$el.children('.no-value-message').hide();


			// add row
			if ($before) {

				$before.before($el);

			} else {

				this.$values.append($el);

			}


			// focus (may have added sub flexible content)
			this.doFocus($field);


			// update order
			this.render();


			// validation
			acf.validation.remove_error(this.$field);


			// sync collapsed order
			this.sync();
		},
		/*
		 *  events
		 *
		 *  these functions are fired for this fields events
		 *
		 *  @type	function
		 *  @date	17/09/2015
		 *  @since	5.2.3
		 *
		 *  @param	e
		 *  @return	n/a
		 */

		_mouseenter: function (e) { //console.log('_mouseenter');

			// bail early if already sortable
			if (this.$values.hasClass('ui-sortable'))
				return;


			// bail early if max 1 row
			if (this.o.max == 1)
				return;


			// reference
			var self = this;


			// sortable
			this.$values.sortable({
				items: '> .layout',
				handle: '> .acf-wa-layout-handle',
				forceHelperSize: true,
				forcePlaceholderSize: true,
				scroll: true,
				start: function (event, ui) {

					acf.do_action('sortstart', ui.item, ui.placeholder);

				},
				stop: function (event, ui) {

					// render
					self.render();

					acf.do_action('sortstop', ui.item, ui.placeholder);

				},
				update: function (event, ui) {

					// trigger change
					self.$input.trigger('change');

				}
			});

		},
		_open: function (e) { //console.log('_open');

			// reference
			var $values = this.$values;


			// vars
			var $popup = $(this.$el.children('.tmpl-popup').html());


			// modify popup
			$popup.find('a').each(function () {

				// vars
				var min = parseInt($(this).attr('data-min')),
					max = parseInt($(this).attr('data-max')),
					name = $(this).attr('data-layout'),
					label = $(this).text(),
					count = $values.children('.layout[data-layout="' + name + '"]').length,
					$status = $(this).children('.status');


				if (max > 0) {

					// find diff
					var available = max - count,
						s = acf._e('widget_area', 'available'),
						identifier = (available == 1) ? 'layout' : 'layouts',
						// translate
						s = s.replace('{available}', available);
					s = s.replace('{max}', max);
					s = s.replace('{label}', '"' + label + '"');
					s = s.replace('{identifier}', acf._e('widget_area', identifier));


					// show status
					$status.show().text(available).attr('title', s);


					// limit reached?
					if (available == 0) {

						$status.addClass('warning');

					}

				}


				if (min > 0) {

					// find diff
					var required = min - count,
						s = acf._e('widget_area', 'required'),
						identifier = (required == 1) ? 'layout' : 'layouts',
						// translate
						s = s.replace('{required}', required);
					s = s.replace('{min}', min);
					s = s.replace('{label}', '"' + label + '"');
					s = s.replace('{identifier}', acf._e('widget_area', identifier));


					// limit reached?
					if (required > 0) {

						$status.addClass('warning').show().text(required).attr('title', s);

					}

				}

			});


			// add popup
			e.$el.after($popup);


			// within layout?
			if (e.$el.closest('.acf-wa-layout-controlls').exists()) {

				$popup.closest('.layout').addClass('-open');

			}


			// vars
			$popup.css({
				'margin-top': 0 - $popup.height() - e.$el.outerHeight() - 14,
				'margin-left': (e.$el.outerWidth() - $popup.width()) / 2,
			});


			// check distance to top
			var offset = $popup.offset().top;

			if (offset < 30) {

				$popup.css({
					'margin-top': 15
				});

				$popup.find('.bit').addClass('top');
			}


			// focus
			$popup.children('.focus').trigger('focus');

		},
		_close: function (e) { //console.log('_close');

			var $popup = e.$el.parent(),
				$layout = $popup.closest('.layout');


			// hide controlls?
			$layout.removeClass('-open');


			// remove popup
			setTimeout(function () {

				$popup.remove();

			}, 200);

		},
		_add: function (e) { //console.log('_add');

			// vars
			var $popup = e.$el.closest('.acf-wa-popup'),
				layout = e.$el.attr('data-layout'),
				$before = false;


			// move row
			if ($popup.closest('.acf-wa-layout-controlls').exists()) {

				$before = $popup.closest('.layout');

			}


			// add row
			this.add(layout, $before);

		},
		_remove: function (e) { //console.log('_remove');

			// reference
			var self = this;


			// vars
			var $layout = e.$el.closest('.layout');


			// bail early if validation fails
			if (!this.validate_remove($layout.attr('data-layout'))) {

				return;

			}


			// close field
			var end_height = 0,
				$message = this.$el.children('.no-value-message');

			if ($layout.siblings('.layout').length == 0) {

				end_height = $message.outerHeight();

			}


			// action for 3rd party customization
			acf.do_action('remove', $layout);


			// remove
			acf.remove_el($layout, function () {

				// update order
				self.render();


				// trigger change to allow attachment save
				self.$input.trigger('change');


				if (end_height > 0) {

					$message.show();

				}


				// sync collapsed order
				self.sync();

			}, end_height);

		},
		_collapse: function (e) { //console.log('_collapse');

			// vars
			var $layout = e.$el.closest('.layout');


			// open
			if ($layout.hasClass('-collapsed')) {

				$layout.removeClass('-collapsed');

				acf.do_action('refresh', $layout);

				// close
			} else {

				$layout.addClass('-collapsed');

			}


			// sync collapsed order
			this.sync();

		}
	});
})(jQuery);
