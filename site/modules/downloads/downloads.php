<?php

/**
 * Downloads module.
 *
 * This module provides easy access and listing of files located in
 * specified path.
 *
 * Author: Mladen Mijatov
 */
use Core\Module;


class downloads extends Module {
	private static $_instance;
	private $file_path;
	private $file_list = array();
	private $version_list = array();
	private $supported_platforms = array(
				'debian', 'ubuntu', 'mint', 'elementary', 'redhat', 'centos',
				'fedora', 'mageia', 'mandriva', 'opensuse', 'pclinuxos', 'gentoo',
				'arch', 'tar'
			);

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct(__FILE__);

		$this->file_path = _BASEPATH.'/pub';
		$this->loadContent();
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'show_list':
					$this->tag_DownloadList($params, $children);
					break;

				case 'platform_list':
					$this->tag_Platforms($params, $children);
					break;

				case 'show_version':
					$this->tag_Version($params, $children);
					break;

				case 'get_version':
					break;

				default:
					break;
			}
	}

	/**
	 * Parse directory structure and get downloadable files.
	 */
	private function loadContent() {
		$expression =
			'/sunflower-'.  // match only right files
			'(\d+\.\d+\w?)[\.-](\d+)'.  // version
			'(?:-(\d)+)?'.  // package build number
			'(?:[\.-](all|any|noarch|i386|amd64))?(?:\.([\w\d]+))?'.  // architecture and os
			'(\.[\w\d\.]+)/iu';  // extension

		// get files from directory
		$data = array();
		$files = scandir($this->file_path);
		foreach ($files as $file_name) {
			$matched = preg_match($expression, $file_name, $matches) == 1;

			if ($matched) {
				$build = $matches[2];

				// create storage array for build number
				if (!isset($data[$build]))
					$data[$build] = array();

				// treat different extensions differently
				switch($matches[6]) {
					case '.tgz.asc':
					case '.deb.asc':
					case '.rpm.asc':
						$key_name = substr($file_name, 0, strlen($file_name) - 4);

						// make sure storage array exists
						if (!isset($data[$build][$key_name]))
							$data[$build][$key_name] = array();

						// store signature to file name
						$data[$build][$key_name]['signature'] = file_get_contents($this->file_path.'/'.$file_name);
						break;

					case '.tgz.sha256':
					case '.deb.sha256':
					case '.rpm.sha256':
						$key_name = substr($file_name, 0, strlen($file_name) - 7);

						// make sure storage array exists
						if (!isset($data[$build][$key_name]))
							$data[$build][$key_name] = array();

						// store signature to file name
						$data[$build][$key_name]['hash'] = file_get_contents($this->file_path.'/'.$file_name);
						$data[$build][$key_name]['hash_type'] = 'sha256';
						break;

					case '.tgz.md5':
					case '.deb.md5':
					case '.rpm.md5':
						$key_name = substr($file_name, 0, strlen($file_name) - 4);

						// make sure storage array exists
						if (!isset($data[$build][$key_name]))
							$data[$build][$key_name] = array();

						// store signature to file name
						$data[$build][$key_name]['hash'] = file_get_contents($this->file_path.'/'.$file_name);
						$data[$build][$key_name]['hash_type'] = 'md5';
						break;

					default:
						// make sure storage array exists
						if (!isset($data[$build][$file_name]))
							$data[$build][$file_name] = array();

						// get storage array for easier access
						$file_data = $data[$build][$file_name];

						// populate parameters
						$file_data['url'] = url_GetFromFilePath($this->file_path.'/'.$file_name);
						$file_data['version'] = $matches[1];
						$file_data['build'] = $matches[2];
						$file_data['package_build'] = $matches[3];
						$file_data['architecture'] = $matches[4];
						$file_data['platform'] = $matches[5];
						$file_data['extension'] = $matches[6];

						// store data array back to main array
						$data[$build][$file_name] = $file_data;

						// store version to reference list
						$this->version_list[$build] = $matches[1];
						break;
				}
			}
		}

		// sort version list
		krsort($this->version_list);

		// store parsed data
		$this->file_list = $data;
	}

	/**
	 * Return file name for specified build and platform.
	 *
	 * @param integer $build
	 * @param string $platform
	 * @return string
	 */
	private function getPlatformFile($build, $platform) {
		switch ($platform) {
			case 'debian':
			case 'ubuntu':
			case 'mint':
			case 'elementary':
				foreach ($this->file_list[$build] as $file_name => $data)
					if ($data['extension'] == '.deb') {
						$result = $file_name;
						break;
					}
				break;

			case 'redhat':
			case 'centos':
			case 'fedora':
			case 'mageia':
			case 'mandriva':
				foreach ($this->file_list[$build] as $file_name => $data)
					if ($data['extension'] == '.rpm' && empty($data['platform'])) {
						$result = $file_name;
						break;
					}
				break;

			case 'pclinuxos':
			case 'opensuse':
				foreach ($this->file_list[$build] as $file_name => $data)
					if ($data['extension'] == '.rpm' && $data['platform'] == $platform) {
						$result = $file_name;
						break;
					}
				break;

			case 'arch':
				foreach ($this->file_list[$build] as $file_name => $data)
					if ($data['extension'] == '.tar.xz' && $data['platform'] == 'pkg') {
						$result = $file_name;
						break;
					}
				break;

			default:
				$version = $this->version_list[$build];
				$result = "sunflower-{$version}-{$build}.tgz";
				break;
		}

		return isset($this->file_list[$build][$result]) ? $result : null;
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
	}

	/**
	 * Tag handler for a single download. If not specified operating
	 * system version is automatically detected.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Download($tag_params, $children) {
		$builds = array_keys($this->file_list);
		$template = $this->loadTemplate($tag_params, 'download.xml');

		// check if build number was specififed
		if (isset($tag_params['build']))
			$build_number = fix_id($tag_params['build']);

		// get highest build number
		if (!in_array($build, $builds)) {
			rsort($builds);
			$build_number = $builds[0];
		}

		// draw all available files for download
		foreach ($builds[$build_number] as $file_data) {
			// prepare parameters
			$params = $file_data;

			// parse template
			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Tag handler for list of downloads. If not otherwise specified
	 * all files for latest version are shown.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_DownloadList($tag_params, $children) {
		$builds = array_keys($this->file_list);
		$template = $this->loadTemplate($tag_params, 'download_version.xml');

		// check if build number was specififed
		$current_build = null;
		if (isset($tag_params['build']))
			$current_build = fix_id($tag_params['build']);

		// get highest build number
		if (!in_array($current_build, $builds)) {
			rsort($builds);
			$current_build = $builds[0];
		}

		// parse template
		foreach ($this->version_list as $build => $version) {
			$params = array(
					'build'		=> $build,
					'version'	=> $version,
					'selected'	=> $build == $current_build,
					'files'		=> count($this->file_list[$build])
				);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Show downloads for specified build for all platforms.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Platforms($tag_params, $children) {
		$builds = array_keys($this->file_list);
		$template = $this->loadTemplate($tag_params, 'download.xml');

		// check if build number was specififed
		$build_number = null;
		if (isset($tag_params['build']))
			$build_number = fix_id($tag_params['build']);

		// get highest build number
		if (!in_array($build_number, $builds)) {
			rsort($builds);
			$build_number = $builds[0];
		}

		// parse template
		foreach ($this->supported_platforms as $platform) {
			$file_name = $this->getPlatformFile($build_number, $platform);

			// skip if there's no file for specified platform
			if (is_null($file_name))
				continue;

			// prepare parameters
			$params = $this->file_list[$build_number][$file_name];
			$params['platform'] = $platform;
			$params['platform_name'] = Language::getText('platform_'.$platform);

			// draw template
			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Tag handler for showing specified version number.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Version($tag_params, $children) {
		$builds = array_keys($this->file_list);
		$template = $this->loadTemplate($tag_params, 'version.xml');

		// check if build number was specififed
		$build_number = null;
		if (isset($tag_params['build']))
			$build_number = fix_id($tag_params['build']);

		// get highest build number
		if (!in_array($build_number, $builds)) {
			rsort($builds);
			$build_number = $builds[0];
		}


		// prepare params
		$params = array(
				'build'		=> $build_number,
				'version'	=> $this->version_list[$build_number]
			);

		// parse template
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}
}

?>
