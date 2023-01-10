/**
 * As we are using hash based navigation, hack fix
 * to highlight the current selected menu
 *
 * Requires jQuery
 */
function wpMenuFix(slug) {
	let $ = window.jQuery,
		menuRoot = $('#toplevel_page_' + slug),
		currentUrl = window.location.href,
		currentPath = currentUrl.substr(currentUrl.indexOf('admin.php'));

	menuRoot.on('click', 'a', function () {
		let self = $(this);

		$('ul.wp-submenu li', menuRoot).removeClass('current');

		if (self.hasClass('wp-has-submenu')) {
			$('li.wp-first-item', menuRoot).addClass('current');
		} else {
			self.parents('li').addClass('current');
		}
	});

	$('ul.wp-submenu a', menuRoot).each(function (index, el) {
		if ($(el).attr('href') === currentPath) {
			$(el).parent().addClass('current');
		}
	});
}

export default wpMenuFix;
