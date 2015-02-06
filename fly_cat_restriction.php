<?php
$plugin['name'] = 'fly_cat_restriction';
$plugin['version'] = '1.1';
$plugin['author'] = 'Superfly';
$plugin['author_uri'] = 'http://www.flyweb.at';
$plugin['description'] = 'Restrict Article Categories for Sections in Write Panel';
$plugin['order'] = '5';
$plugin['type'] = '4';

if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001);
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002);

$plugin['flags'] = '3';

# --- BEGIN PLUGIN CODE ---
/**
 *
 * Textpattern CMS Plugin <www.textpattern.com>
 *
 * fly_cat_restriction.php
 * Flyweb Categories Restriction Plugin
 *
 * This Plugin is for the CMS Write Panel. It can deactivate Category
 * Names relative to Sections Names.
 *
 * @author flyweb productions <www.flyweb.at>
 * @copyright 2015 flyweb productions
 * @license http://opensource.org/licenses/MIT - MIT License (MIT)
 * @version 1.1 <https://github.com/brachycera/fw_cat_restriction>
 *
 */

if (txpinterface === 'admin') {

	add_privs('fly_cat_restriction','1');
	add_privs('plugin_prefs.fly_cat_restriction', '1');

	register_tab("extensions", "fly_cat_restriction", 'Category Restrictions');

	register_callback('fly_cat_restriction', 'plugin_lifecycle.fly_cat_restriction', 'installed');
	register_callback('fly_cat_restriction', 'plugin_lifecycle.fly_cat_restriction', 'deleted');
	register_callback('fly_cat_restriction', 'plugin_prefs.fly_cat_restriction');
	register_callback('fly_cat_restriction', 'article_ui','sort_display');
	register_callback('fly_cat_restriction', 'fly_cat_restriction');

}

/**
 *
 * fly_cat_restriction - Plugin Factory Function
 *
 * @param  string $event - Textpattern Event Callback Parameter
 * @param  string $step - Textpattern Step Callback Parameter
 *
 */
function fly_cat_restriction($event, $step){

	$fly_cat_restriction =  new fly_cat_restrictionClass();

	switch ($step) {

		case 'installed':
			$fly_cat_restriction->install();
			break;

		case 'deleted':
			$fly_cat_restriction->uninstall();
			break;

		case 'save':
			$fly_cat_restriction->adminpage();
			break;

		case 'savePref':
			$fly_cat_restriction->savePref();
			break;

		case 'sort_display':
			$fly_cat_restriction->javascript();
			break;

		default:
			$fly_cat_restriction->adminpage();
			break;

	}

	return;

}


/**
 *
 * fly_cat_restrictionClass
 *
 * This Plugin adds the ability to restrict Categories from specific Sections in the Write Tab
 *
 *
 * @version 1.0
 * @author Superfly <www.flyweb.at>
 * @project Textpattern Admin Plugin
 *
 */
class fly_cat_restrictionClass {


	/**
	 *
	 * __construct
	 * Adds Theme Class
	 *
	 */
	function __construct(){

		global $theme;
		$this->theme = $theme;

	}


	/**
	 *
	 * Install Database Table txp_fly_cat_restriction
	 *
	 */
	function install() {

		if (!getThings("Show tables like '" . PFX . "txp_fly_cat_restriction'")) {

			//figure out what MySQL version we are using (from _update.php)
			$mysqlversion = mysql_get_server_info();

			$tabletype = ( intval($mysqlversion[0]) >= 5 || preg_match('#^4\.(0\.[2-9]|(1[89]))|(1\.[2-9])#', $mysqlversion))
			? " ENGINE=MyISAM "
					: " TYPE=MyISAM ";

			if ( isset($txpcfg['dbcharset']) && (intval($mysqlversion[0]) >= 5 || preg_match('#^4\.[1-9]#', $mysqlversion)))
				$tabletype .= " CHARACTER SET = ". $txpcfg['dbcharset'] ." ";

			// Create the txp_fly_cat_restrictions table
			$fly_cat_restrictionsTable = safe_query("CREATE TABLE `" . PFX . "txp_fly_cat_restriction` (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`section` VARCHAR(128) NOT NULL,
					`category` VARCHAR(64) NOT NULL,
					PRIMARY KEY (`id`),
  					UNIQUE KEY `id` (`id`)
			) $tabletype");

		}

	}


	/**
	 *
	 * uninstall - Uninstall Database Table txp_fly_cat_restriction
	 *
	 */
	function uninstall() {

		if ( getThings("Show tables like '" . PFX . "txp_fly_cat_restriction'") ) {

			$sql = "DROP TABLE IF EXISTS " . PFX . "txp_fly_cat_restriction;";

			$dropTable = safe_query($sql);

		}

		$sqlPref = "DELETE FROM  " . PFX . "txp_prefs WHERE name='fly_restrict_cat_pref';";
		$deletePref = safe_query($sqlPref);


	}


	/**
	 *
	 * Saves Categories from HTML Form to Database
	 *
	 * @param string $_POST['section']
	 * @param array $_POST['categories']
	 * @uses lib/txplib_theme.php - announce()
	 * @return string - Admin Theme Notification message
	 *
	 */
	function save() {

		$section = gps('section');
		$categories = array_filter( array_map('array_filter', gps('categories')) );

		$sqlCat = '';
		$sqlDelCat = '';
		if ( !empty($categories) ){

			foreach ($categories as $category) {

				$sqlDelCat .= ( !empty($category['id']) && !empty($category['name']) ? "id != '" . $category['id'] . "' AND " : '');

				$sqlCat .= ( empty($category['id']) && !empty($category['name']) ? "('', '" . $section . "', '" . $category['name'] . "'), " : '');

			}


			if ( !empty($sqlDelCat) )
				$sqlDelCat = 'AND (' . trim($sqlDelCat, 'AND ') . ')';

			$sqlDel = "DELETE FROM " . PFX . "txp_fly_cat_restriction WHERE section='" . $section . "'" . $sqlDelCat;
			$delete = safe_query($sqlDel);


			$sqlCat = trim($sqlCat, ', ');
			if ( !empty($sqlCat) ){

				$sql = "INSERT INTO " . PFX . "txp_fly_cat_restriction VALUES " . $sqlCat;
				$insert = safe_query($sql);

			}

			return $this->theme->announce( 'Saved restricted Categroies' );

		}


	}


	/**
	 *
	 * savePref - Save setting to show/hide restricted Categories in Write Panel
	 *
	 * @param string $_POST['fly_restrict_cat_pref']
	 * @uses lib/txplib_theme.php - announce_async()
	 * @return string - Admin Theme Javascript Notification message
	 *
	 */
	function savePref(){

		if ( gps('fly_restrict_cat_pref') == 'true' ){

			set_pref('fly_restrict_cat_pref', true, 'fly_cat_restriction');
			echo $this->theme->announce_async( 'Restricted Categories are hidden' );

		} else {

			set_pref('fly_restrict_cat_pref', false, 'fly_cat_restriction');
			echo $this->theme->announce_async( 'Restricted Categories are shown');

		}

	}


	/**
	 *
	 * Loads all restrictet Catgories from the database and converts the result to JSON
	 *
	 * @return string $restriction - JSON Encoded Result: Key is Sectionname - Values are Category Name and ID
	 *
	 */
	function restrictions_JSON() {

		$sql = "SELECT * FROM " . PFX . "txp_fly_cat_restriction ORDER BY section DESC";

		$result = getRows($sql);

		if ( $result == true ){

			$i=1; // NTMS: we cant use the array key. json_encode() would make an JS-array from array key '0', and not the desired JS-object
			foreach ($result as $item) {

				$filterItem[ $item['section'] ][$i]['name'] = $item['category'];
				$filterItem[ $item['section'] ][$i]['id'] = $item['id'];
				$i++;

			}

			$restriction = json_encode( $filterItem );

		} else
			$restriction = "[{}]";

		return $restriction;


	}


	/**
	 *
	 * adminpage - Admin Page to configure and save restrictet categories
	 *
	 * @return string HTML Code
	 *
	 */
	function adminpage() {


		if ( !empty( $_POST['step'] ) == 'save' )
			$saveMsg = $this->save();


		pagetop( 'Article Category Restrictions' );

		echo '<div class="text-column">' . "\n";

			echo '<h1>Category Restrictions</h1>' . "\n";
			echo '<p>This plugin only works with Article-Categories. Only parent categories can be disabled. Sub-categories get automatically disabled if Parent-Category is disabled.</p>' . "\n";

			echo '<div>' . form(

				'<label for="save_pref">' .
				checkbox('fly_restrict_cat_pref', '" onchange="savePref(this); ', 0, null, 'save_pref') .
				'Hide restricted Categories in Dropdown Menu on Write Panel</label>'

			) . "\n";

			echo '</div><br/>' . "\n";

			echo '<style type="text/css">label {display:block; cursor:pointer;}</style>' . "\n";

			echo form(

					startTable('', 'left', 'txp-list', '5', '30%') .

						'<thead>'.  tr ( hCell('Choose a section and disable the desired categories', null) ) . '</thead>'.

						tr( tda($this->sections_dropdown(), ' class="align-center"') )

						. $this->categories_checkboxes() .

						tr(
							tda(
								'<p>' . fInput( 'submit', 'save', 'Save Categories', 'publish'  ) . '</p>' .
								sInput( 'save' ) .
								eInput( 'fly_cat_restriction' ),
								' style="text-align:center"'
							)
						) .

					endTable(),
					null,
					null,
					'post',
					null,
					null,
					'theForm'

			); // form() END

		echo '</div><br clear="all"/>';

		echo $this->javascript('checkbox');

		echo '<br/><p align="center"><small>This Plugin was made by ' . href('flyweb Productions', 'http://www.flyweb.at') . '.</small></p>' . "\n";

		if (!empty($saveMsg))
			echo $saveMsg;

	}


	/**
	 *
	 * HTML Dropdown Menu with Section Names
	 *
	 * @param string $_POST['section] - If present Section gets preselected in Dropdown Menu
	 * @return string $dropdown - HTML Dropdown Menu
	 *
	 */
	function sections_dropdown() {

		$dropdown = '';
		$section = gps('section');

		$getSections = safe_column('name', PFX . 'txp_section', "name != 'default'");

		if ($getSections) {

			$dropdown .= '<label for="section">Section ' . "\n";
			$dropdown .= selectInput('section', $getSections, $section, false, '', 'section');
			$dropdown .= '</label>' . "\n";

		}

		return $dropdown;

	}


	/**
	 *
	 * HTML Table Rows with Categporie Checkboxes for given Section
	 *
	 * @return string $rows
	 *
	 */
	function categories_checkboxes() {

		$rows = '';

		$getCategories = getTree('root', 'article');

		if ($getCategories) {

			foreach ($getCategories as $catKey => $category) {

				if ( $category['parent'] == 'root' ){

					$cell =
						tda(
							'<label for="cat-' . $category['name'] . '">' .
							checkbox('categories[' . $catKey . '][name]', $category['name'], 0, null, 'cat-' . $category['name']) .
							$category['title'] . '</label>' .
							hInput( 'categories[' . $catKey . '][id]', '')
						);

				} else {

					$cell = tda( '&#160;&#160;&#160;&#160;&#160;&#160;' . $category['title']);

				}

				$rows .= tr( $cell );
			}

		}

		return $rows;

	}


	/**
	 *
	 * javascript - Javascript Functions for Write Panel and Admin Page.
	 *
	 * @param  string $element - Set JS-Function for "dropdown" (Write Panel) or "checkbox" (Admin Page)
	 * @uses jQuery
	 * @return string $html
	 *
	 */
	function javascript($element='dropdown') {

		$JSON = $this->restrictions_JSON();

		$class = ( get_pref('fly_restrict_cat_pref') ? 'restrictedHidden' : 'restricted' );


		$html = '<style type="text/css"> .restricted {background-color: red; color: white; } .restrictedHidden { display:none; }</style>' . "\n";

		$html .= "<script type=\"text/javascript\">

			jQuery(function() {

				var filter = JSON.parse('$JSON');


				$('#section').change( function() {

					if( filter.hasOwnProperty(this.value) == true ){

						catObj = filter[this.value];

					} else {

						catObj = [];

					}

					fw_cat_$element(catObj);


				}).change();



				$(\"input[id^='cat-']\").change( function() {

						$(this).parents('tr').toggleClass('selected');

					}

				);



				function fw_cat_checkbox(catObj) {

					$(\"input[id^='cat-']\").prop('checked', false);
					$(\"input[id^='cat-']\").parents('tr').removeClass('selected');
					$(\"input[id^='cat-']\").parent().next('input').prop('value', '');

					Object.keys(catObj).forEach(function (key) {

					    var val = catObj[key];

						$(\"#cat-\" + val.name).prop('checked', true);
						$(\"#cat-\" + val.name).parents('tr').addClass('selected');
						$(\"#cat-\" + val.name).parent().next('input').prop('value', val.id);

					});

				};


				function fw_cat_dropdown(catObj) {

					$(\"select[name='Category1'] option:gt(0), select[name='Category2'] option:gt(0)\").attr('disabled', false);
					$(\"select[name='Category1'] option:gt(0), select[name='Category2'] option:gt(0)\").removeClass('$class');

					Object.keys(catObj).forEach(function (key) {

					    var val = catObj[key];

						$(\"#categories_group option[value='\" + val.name + \"']\").attr('disabled', 'disabled');
						$(\"#categories_group option[value='\" + val.name + \"']\").attr('selected', false);
						$(\"#categories_group option[value='\" + val.name + \"']\").nextUntil( $(\" option:not(:contains('\u00a0')) \") ).attr('disabled', 'disabled');
						$(\"#categories_group option[value='\" + val.name + \"']\").addClass('$class');
						$(\"#categories_group option[value='\" + val.name + \"']\").nextUntil( $(\" option:not(:contains('\u00a0')) \") ).addClass('$class');

					});

				};

			});

			function savePref(pref){

				sendAsyncEvent(

					{
						event: textpattern.event,
						step: 'savePref',
						fly_restrict_cat_pref: pref.checked

					}, null, 'script'

				);
			};

		</script>";

		echo $html;

	}



} // class fly_cat_restrictionsClass() END

# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---
<h1>Category Restrictions</h1>
<p>With this Plugin you can restrict Article-Categories from specific sections in the Write Panels Categories Dropdowns.</p>
<h2>Installation</h2>
<p>After installing the Plugin you can find the Settings Page "Category Restrictions" under the Extensions Tab.</p>
<h2>Changelog</h2>
<p><strong>Version 1.0</strong> Initial release -  03. Feb. 2015</p>
<br><br><br><br><br><hr><p align="center"><small>This Plugin was made by <a href="http://www.flyweb.at"> flyweb Productions</a>.</small></p>
# --- END PLUGIN HELP ---
-->
<?php
}
?>