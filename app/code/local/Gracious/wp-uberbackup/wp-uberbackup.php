<?php
/**
 * Plugin Name: WPUberBackup
 * Plugin URI: https://woocommerce.com/
 * Description:  just a tyoe
 * Version: 2.6.11
 * Author: Sourcerer
 * Author URI: https://sourcerer.mnl
 * Requires at least: 4.4
 * Tested up to: 4.7
 *
 * Text Domain: sourcerer
 *
 * @package Sourcerer
 * @category Core
 * @author Sourcerer
 */
add_action('init', 'wpUberBackupInit');
function wpUberBackupInit()
{
	add_action('admin_menu', 'wp_ub_addmenu');
	add_action('admin_init', 'register_mysettings');
}

function wp_ub_addmenu()
{
	add_menu_page('UberBackup', 'Uberbackup', 'administrator', 'ub-settings', 'wp_ub_settings_page', plugins_url('/images/icon.png', __FILE__));

	//call register settings function
	add_action('admin_init', 'register_wp_ub_settings');
}

function register_wp_ub_settings()
{
	//register our settings
	register_setting('wp-ub-settings-group', 'new_option_name');
	register_setting('wp-ub-settings-group', 'some_other_option');
	register_setting('wp-ub-settings-group', 'option_etc');
}

function wp_bu_recursive_scan()
{
	$basepath = realpath(__DIR__ . '/../../..');

	$extensions = ['php', 'html', 'css', 'js', 'po', 'mo', 'txt', 'jpg', 'png', 'pdf', 'doc', 'svg', 'eot', 'ttf', 'woff', 'docx', 'xlsx', 'sql'];
	$dirs = wp_bu_scandir($basepath, $extensions);
	$dir = wp_upload_dir();
	require_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');
	$filename = 'backup-'.time().'.zip';
	$file = $dir['basedir'] . '/'.$filename;
	ini_set('display_errors', true);
	$zip = new PclZip($file);
	$zip->add($dirs['files'], PCLZIP_OPT_REMOVE_PATH, $basepath);
	return $filename;
}

function wp_bu_scandir($path, $extensions, &$root = [])
{
	$files = scandir($path);
	if (!isset($root['files'])) {
		$root['files'] = [];
	}
	foreach ($files as $file) {
		if (substr($file, 0, 1) == '.') {
			continue;
		}
		$filepath = $path . '/' . $file;
		if (is_dir($filepath)) {
			wp_bu_scandir($filepath, $extensions, $root);
		} else {
			$prts = explode('.', $file);
			$ext = array_pop($prts);

			if (!in_array($ext, $extensions)) {
				continue;
			}
			$root['files'][] = $filepath;
		}
	}
	return $root;
}

function wp_ub_settings_page_makebackup()
{
	global $wpdb;
	$rows = $wpdb->get_results('SHOW TABLES');
	$tables = [];
	$dir = wp_upload_dir();
	$file = $dir['basedir'] . '/backup.sql';


	$fp = fopen($file, 'w');
	fwrite($fp, "SET foreign_key_checks = 0;\n");
	fwrite($fp, "\n");
	fwrite($fp, "\n");
	foreach ($rows as $row) {
		$name = implode('', array_values((array)$row));
		$tables[$name] = [];
		$fieldrows = $wpdb->get_results('SHOW COLUMNS FROM ' . $name);
		$tables[$name]['fields'] = $fieldrows;
		fwrite($fp, wp_ub_create_schema_sql($name, $fieldrows) . ";\n\n");
	}
	fwrite($fp, "\n");

	foreach ($tables as $name => $table) {
		fwrite($fp, "\n");
		$offset = 0;
		$num = 100;
		$fields = $table['fields'];
		$columns = [];
		foreach ($fields as $field) {
			$columns[] = $field->Field;
		}
		while (($datarows = wp_ub_dump_data($name, $offset, $num))) {
			foreach ($datarows as $data) {

				$sql = 'INSERT INTO `' . $name . '` (' . implode(', ', $columns) . ') VALUES ';
				$rowdata = [];
				foreach ((array)$data as $key => $val) {
					$val = str_replace("\n", '\\n', addslashes($val));
					$rowdata[] = '\'' . ($val) . '\'';
				}
				$sql .= ' (' . implode(', ', $rowdata) . ') ;';
				fwrite($fp, $sql . "\n");
			}
			$offset += $num;
		}
	}

	fwrite($fp, "SET foreign_key_checks = 1;\n");
	fclose($fp);
	return wp_bu_recursive_scan();

}

function wp_ub_get_row($fp, $p)
{
	fseek($fp, $p);
	return json_decode(trim(fgets($fp)));
}

function wp_ub_compare_meta($meta, $file)
{
	$prev = json_decode(file_get_contents($file), true);
	$changed = [];
	foreach ($meta as $table => $tablemeta) {
		$changed[$table] = [];
		foreach ($tablemeta as $id => $hash) {

			if (!isset($prev[$table][$id]) || $prev[$table][$id]['hash'] != $hash['hash']) {
				$changed[$table][$id] = $hash;
			}
		}
	}
	return $changed;
}

function wp_ub_get_primary($rows)
{
	foreach ($rows as $field) {
		if ($field->Key == 'PRI') {
			return $field->Field;
		}
	}
}

function wp_ub_insert_row($table, $row)
{
	global $wpdb;
	$data = (array)$row;

	$wpdb->replace($table, $data);
}

function wp_ub_read_chunked($fp, $num)
{
	$lines = [];
	for ($i = 0; $i < $num; $i++) {
		$line = fgets($fp);
		$lines[] = json_decode(trim($line));
	}
	return $lines;
}

function wp_ub_dump_data($name, $offset, $num)
{
	global $wpdb;
	$rows = $wpdb->get_results('select * FROM ' . $name . ' LIMIT ' . $offset . ',' . $num);
	if (count($rows) == 0) {
		return false;
	}
	return $rows;
}

function wp_ub_create_schema_sql($name, $fields)
{

	$pri = [];
	$unique = [];
	$parts = ['CREATE TABLE ' . $name];
	$parts[] = '(';
	$fieldparts = [];
	foreach ($fields as $field) {
		$pf = [$field->Field];
		$pf[] = $field->Type;
		$pf[] = $field->Null == 'NO' ? 'NOT NULL' : 'NULL';
		if ($field->Default != '') {
			$pf[] = 'DEFAULT ' . ($field->Null == 'NO' ? '\'' . $field->Default . '\'' : 'NULL');
		}
		if ($field->Key == 'PRI') {
			$pri[] = $field->Field;
		}
		if ($field->Key == 'UNI') {
			$unique[] = $field->Field;
		}
		if ($field->Extra == 'auto_increment') {
			$pf[] = 'AUTO_INCREMENT';
		}
		$fieldparts[] = implode(' ', $pf);
	}
	if ($pri) {
		$fieldparts[] = 'PRIMARY KEY (`' . implode('`,`', $pri) . '`)';
	}
	foreach ($unique as $ufield) {
		$fieldparts[] = 'UNIQUE KEY (`' . $ufield . '`)';
	}

	$parts[] = implode(', ', $fieldparts);
	$parts[] = ')';
	return implode(' ', $parts);
}

function wp_ub_settings_page()
{

	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		ini_set('max_execution_time', 600);
		$filename = wp_ub_settings_page_makebackup();

		?>
		<div class="wrap">
			<p>Backup created with filename :<?php $filename?></p>
		</div>

		<?php



	} else {
		?>
		<div class="wrap">
			<h1>WP Uber backup</h1>

			<form method="post" action="">
				<table class="form-table">

					<tr valign="top">
						<th scope="row">Create new backup</th>
					</tr>
				</table>

				<?php submit_button(); ?>

			</form>
		</div>
		<?php
	}
	?>
<?php } ?>