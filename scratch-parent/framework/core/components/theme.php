<?php if (!defined('FW')) die('Forbidden');

/**
 * Theme
 * Works with folder .../framework-customizations/theme
 */
final class _FW_Component_Theme extends FW_Component
{
	private static $cache_key = 'fw_theme';

	/**
	 * @var FW_Theme_Manifest
	 */
	public $manifest;

	public function __construct()
	{
		{
			$manifest = array();

			@include FW_PT_THEME_DIR .'/manifest.php';

			$this->manifest = new FW_Theme_Manifest($manifest);
		}
	}

	/**
	 * Include file from child and parent theme
	 * @param string $rel_path
	 */
	private static function include_file_all($rel_path)
	{
		if (FW_CT) {
			fw_include_file_isolated(FW_CT_CUSTOM_DIR .'/theme'. $rel_path);
		}

		fw_include_file_isolated(FW_PT_CUSTOM_DIR .'/theme'. $rel_path);
	}

	/**
	 * Include all files from directory, from parent and child theme
	 * @param string $rel_path
	 */
	private static function include_directory_all($rel_path)
	{
		$paths = array();

		if (FW_CT) {
			$paths[] = FW_CT_CUSTOM_DIR .'/theme'. $rel_path;
		}

		$paths[] = FW_PT_CUSTOM_DIR .'/theme'. $rel_path;

		foreach ($paths as $path) {
			if ($files = glob($path .'/*.php')) {
				foreach ($files as $dir_file_path) {
					fw_include_file_isolated($dir_file_path);
				}
			}
		}
	}

	/**
	 * @internal
	 */
	protected function _init()
	{
		self::include_file_all('/hooks.php');

		add_action('fw_init',               array($this, '_action_fw_init'));
		add_action('init',                  array($this, '_action_init'));
		add_action('wp_enqueue_scripts',    array($this, '_action_enqueue_scripts'));
		add_action('admin_enqueue_scripts', array($this, '_action_enqueue_scripts'));
		add_action('widgets_init',          array($this, '_action_widgets_init'));
	}

	/**
	 * @internal
	 */
	public function _action_fw_init()
	{
		self::include_file_all('/helpers.php');
		self::include_directory_all('/includes');
	}

	/**
	 * @internal
	 */
	public function _action_init()
	{
		self::include_file_all('/posts.php');
		self::include_file_all('/menus.php');
	}

	/**
	 * @internal
	 */
	public function _action_enqueue_scripts()
	{
		self::include_file_all('/static.php');
	}

	/**
	 * @internal
	 */
	public function _action_widgets_init()
	{
		$paths = array();

		if (FW_CT) {
			$paths[] = FW_CT_CUSTOM_DIR .'/theme/widgets';
		}

		$paths[] = FW_PT_CUSTOM_DIR .'/theme/widgets';

		$included_widgets = array();

		foreach ($paths as $path) {
			$dirs = glob($path .'/*', GLOB_ONLYDIR);

			if (!$dirs) {
				continue;
			}

			foreach ($dirs as $dir_path) {
				$dirname = basename($dir_path);

				if (isset($included_widgets[$dirname])) {
					// this happens when a widget in child theme wants to overwrite the widget from parent theme
					continue;
				} else {
					$included_widgets[$dirname] = true;
				}

				fw_include_file_isolated($dir_path .'/class-fw-widget-'. $dirname .'.php');

				register_widget('FW_Widget_'. fw_dirname_to_classname($dirname));
			}
		}
	}

	/**
	 * Search relative path in: child theme -> parent "theme" directory and return full path
	 * @param string $rel_path
	 * @return false|string
	 */
	public function locate_path($rel_path)
	{
		if (FW_CT && file_exists(FW_CT_THEME_DIR . $rel_path)) {
			return FW_CT_THEME_DIR . $rel_path;
		}

		if (file_exists(FW_PT_THEME_DIR . $rel_path)) {
			return FW_PT_THEME_DIR . $rel_path;
		}

		return false;
	}

	/**
	 * Return array with options from specified name/path
	 * @param string $name
	 * @return array
	 */
	public function get_options($name)
	{
		$path = $this->locate_path('/options/'. $name .'.php');

		if (!$path) {
			return array();
		}

		$variables = fw_get_variables_from_file($path, array('options' => array()));

		return $variables['options'];
	}

	public function get_settings_options()
	{
		$cache_key = self::$cache_key .'/options/settings';

		try {
			return FW_Cache::get($cache_key);
		} catch (FW_Cache_Not_Found_Exception $e) {
			$options = apply_filters('fw_settings_options', $this->get_options('settings'));

			FW_Cache::set($cache_key, $options);

			return $options;
		}
	}

	public function get_post_options($post_type)
	{
		$cache_key = self::$cache_key .'/options/posts/'. $post_type;

		try {
			return FW_Cache::get($cache_key);
		} catch (FW_Cache_Not_Found_Exception $e) {
			$options = apply_filters('fw_post_options', $this->get_options('posts/'. $post_type), $post_type);

			FW_Cache::set($cache_key, $options);

			return $options;
		}
	}

	public function get_taxonomy_options($taxonomy)
	{
		$cache_key = self::$cache_key .'/options/taxonomies/'. $taxonomy;

		try {
			return FW_Cache::get($cache_key);
		} catch (FW_Cache_Not_Found_Exception $e) {
			$options = apply_filters('fw_taxonomy_options', $this->get_options('taxonomies/'. $taxonomy),
				$taxonomy,
				null
			);

			FW_Cache::set($cache_key, $options);

			return $options;
		}
	}

	/**
	 * Return config key value, or entire config array
	 * Config array is merged from child configs
	 * @param string|null $key Multi key format accepted: 'a/b/c'
	 * @return mixed|null
	 */
	final public function get_config($key = null)
	{
		$cache_key = self::$cache_key .'/config';

		try {
			$config = FW_Cache::get($cache_key);
		} catch (FW_Cache_Not_Found_Exception $e) {
			$config = array();

			if (file_exists(FW_PT_CUSTOM_DIR .'/theme/config.php')) {
				$variables = fw_get_variables_from_file(FW_PT_CUSTOM_DIR .'/theme/config.php', array('cfg' => null));

				if (!empty($variables['cfg'])) {
					$config = array_merge($config, $variables['cfg']);
					unset($variables);
				}
			}

			if (FW_CT && file_exists(FW_CT_CUSTOM_DIR .'/theme/config.php')) {
				$variables = fw_get_variables_from_file(FW_CT_CUSTOM_DIR .'/theme/config.php', array('cfg' => null));

				if (!empty($variables['cfg'])) {
					$config = array_merge($config, $variables['cfg']);
					unset($variables);
				}
			}

			unset($path);

			FW_Cache::set($cache_key, $config);
		}

		return $key === null ? $config : fw_akg($key, $config);
	}
}
