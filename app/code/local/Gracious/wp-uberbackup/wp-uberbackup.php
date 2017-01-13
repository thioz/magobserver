<?php
/**
 * Plugin Name: WPBuberBackup
 * Plugin URI: https://woocommerce.com/
 * Description:  just a tyoe
 * Version: 2.6.11
 * Author: Sourcerer
 * Author URI: https://woocommerce.com
 * Requires at least: 4.4
 * Tested up to: 4.7
 *
 * Text Domain: woocommerce
 * Domain Path: /i18n/languages/
 *
 * @package Sourcerer
 * @category Core
 * @author WooThemes
 */
add_action('init', 'wpUberBackupInit');
function wpUberBackupInit() {
	add_action('admin_menu', 'wp_ub_addmenu');
	add_action('admin_init', 'register_mysettings');
}

function wp_ub_addmenu() {
	add_menu_page('UberBackup', 'Cool Settings', 'administrator', 'ub-settings', 'wp_ub_settings_page', plugins_url('/images/icon.png', __FILE__));
	add_submenu_page('ub-settings', 'make backup', 'Make backup', 'administrator', 'wp_ub_settings_page_makeb', 'wp_ub_settings_page_makeb', plugins_url('/images/icon.png', __FILE__));

	//call register settings function
	add_action('admin_init', 'register_wp_ub_settings');
}

function register_wp_ub_settings() {
	//register our settings
	register_setting('wp-ub-settings-group', 'new_option_name');
	register_setting('wp-ub-settings-group', 'some_other_option');
	register_setting('wp-ub-settings-group', 'option_etc');
}

function wp_bu_recursive_scan() {
	$basepath = realpath(__DIR__ . '/../../..');

	$extensions = ['php', 'html', 'css', 'js', 'po', 'mo', 'txt', 'jpg', 'png', 'pdf', 'doc', 'svg', 'eot', 'ttf', 'woff', 'docx', 'xlsx','sql'];
	$dirs = wp_bu_scandir($basepath, $extensions);
	$dir = wp_upload_dir();
	require_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');
	$file = $dir['basedir'] . '/backup.zip';
	ini_set('display_errors', true);
	$zip = new PclZip($file);
	$zip->add($dirs['files'], PCLZIP_OPT_REMOVE_PATH, $basepath);

	echo '<pre>';
	print_r($dirs);
	echo '</pre>';
}

function wp_bu_scandir($path, $extensions, &$root = []) {
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
		}
		else {
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

function wp_ub_settings_page_makeb() {
	global $wpdb;
	//die();
	$rows = $wpdb->get_results('SHOW TABLES');
	$tables = [];
	$dir = wp_upload_dir();
	$file = $dir['basedir'] . '/backup.sql';
	 

	$fp = fopen($file, 'w');
	fwrite($fp, "SET foreign_key_checks = 0;\n");
	fwrite($fp, "\n");	
	//fwrite($fp, "---create tables ---- \n");	
	fwrite($fp, "\n");	
	foreach ($rows as $row) {
		$name = implode('', array_values((array) $row));
		$tables[$name] = [];
		$fieldrows = $wpdb->get_results('SHOW COLUMNS FROM ' . $name);
		$tables[$name]['fields'] = $fieldrows;
//		$tables[$name]['key'] = wp_ub_get_primary($fieldrows);
		fwrite($fp, wp_ub_create_schema_sql($name,$fieldrows). ";\n\n");	

		//$tables[$name]['sql']=  wp_ub_create_schema_sql($name,$fieldrows);
//		$fp=  fopen($file, 'w');
//		}
//		fclose($fp);
	}
	fwrite($fp, "\n");	
//	fwrite($fp, "---create data ---- \n");	
	
	foreach ($tables as $name => $table) {
		fwrite($fp, "\n");	
	//	fwrite($fp, "--- data for table ".$name."---- \n");	
		$offset = 0;
		$num = 100;
		$fields = $table['fields'];
		$columns = [];
		foreach($fields as $field){
			$columns[]=$field->Field;
		}
		while (($datarows = wp_ub_dump_data($name, $offset, $num))) {
			foreach ($datarows as $data) {
				
				$sql = 'INSERT INTO `'.$name.'` ('.implode(', ',$columns).') VALUES ';
				$rowdata = [];
				foreach((array) $data as $key=> $val){
					$val = str_replace("\n", '\\n', addslashes($val));
					$rowdata[] = '\''.($val).'\'';
				}
				$sql.=' ('.implode(', ',$rowdata).') ;';
				fwrite($fp, $sql."\n");	
			}
			$offset+=$num;
		}
	}

//	$changed = wp_ub_compare_meta($meta, $metafile);
//	foreach ($changed as $ct => $crows) {
//		foreach ($crows as $cid => $hash) {
//			$row = wp_ub_get_row($fp, $hash['p']);
//			echo '<pre>';
//			print_r($row);
//			echo '</pre>';
//		}
//	}
	fwrite($fp, "SET foreign_key_checks = 1;\n");	
		fclose($fp);
	wp_bu_recursive_scan();

	//file_put_contents($metafile, json_encode($meta));
	//chmod($file, 0777);
	//wp_ub_insert_row('wp_comments', json_decode('{"comment_ID":"1","comment_post_ID":"1","comment_author":"A WordPressddddd Commenter","comment_author_email":"wapuu@wordpress.example","comment_author_url":"https:\/\/wordpress.org\/","comment_author_IP":"","comment_date":"2017-01-01 23:56:37","comment_date_gmt":"2017-01-01 23:56:37","comment_content":"Hi, this is addd comment.\nTo get started with moderating, editing, and deleting comments, please visit the Comments screen in the dashboard.\nCommenter avatars come from <a href=\"https:\/\/gravatar.com\">Gravatar<\/a>.","comment_karma":"0","comment_approved":"1","comment_agent":"","comment_type":"","comment_parent":"0","user_id":"0"}'));
}

function wp_ub_get_row($fp, $p) {
	fseek($fp, $p);
	return json_decode(trim(fgets($fp)));
}

function wp_ub_compare_meta($meta, $file) {
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

function wp_ub_get_primary($rows) {
	foreach ($rows as $field) {
		if ($field->Key == 'PRI') {
			return $field->Field;
		}
	}
}

function wp_ub_insert_row($table, $row) {
	global $wpdb;
	$data = (array) $row;

	$wpdb->replace($table, $data);
}

function wp_ub_read_chunked($fp, $num) {
	$lines = [];
	for ($i = 0; $i < $num; $i++) {
		$line = fgets($fp);
		$lines[] = json_decode(trim($line));
	}
	return $lines;
}

function wp_ub_dump_data($name, $offset, $num) {
	global $wpdb;
	$rows = $wpdb->get_results('select * FROM ' . $name . ' LIMIT ' . $offset . ',' . $num);
	if (count($rows) == 0) {
		return false;
	}
	return $rows;
}

function wp_ub_create_schema_sql($name, $fields) {
	echo '<pre>';
	print_r($fields);
	echo '</pre>';
	$pri = [];
	$unique=[];
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
		$fieldparts[] = 'PRIMARY KEY (`' . implode('`,`',$pri). '`)';
	}
	foreach($unique as $ufield){
		$fieldparts[] = 'UNIQUE KEY (`' . $ufield . '`)';
	}
	
	$parts[] = implode(', ', $fieldparts);
	$parts[] = ')';
	return implode(' ', $parts);
}

function wp_ub_settings_page() {
	?>
	<div class="wrap">
		<h1>Your Plugin Name</h1>

		<form method="post" action="options.php">
	<?php settings_fields('wp-ub-settings-group'); ?>
	<?php do_settings_sections('wp-ub-settings-group'); ?>
	    <table class="form-table">
				<tr valign="top">
	        <th scope="row">New Option Name</th>
	        <td><input type="text" name="new_option_name" value="<?php echo esc_attr(get_option('new_option_name')); ?>" /></td>
				</tr>

				<tr valign="top">
	        <th scope="row">Some Other Option</th>
	        <td><input type="text" name="some_other_option" value="<?php echo esc_attr(get_option('some_other_option')); ?>" /></td>
				</tr>

				<tr valign="top">
	        <th scope="row">Options, Etc.</th>
	        <td><input type="text" name="option_etc" value="<?php echo esc_attr(get_option('option_etc')); ?>" /></td>
				</tr>
	    </table>

	<?php submit_button(); ?>

		</form>
	</div>
<?php } ?>