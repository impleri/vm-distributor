<?php
defined('_JEXEC') or die('Restricted access.');

jimport('joomla.plugin.plugin');

/**
* VMDisti Ingram Micro Helper
*
* @package VMDisti
* @subpackage ingram
* @author Christopher Roussel
*/
class plgVmDistributorIngram extends JPlugin {
	private $_myType = 'ingram';
	private $_priceAdjust = 1.05;
	private $_keepNames = false;
	private $_separator = '|';

	/**
	 * Constructor
	 *
	 * @param object $subject The object to observe
	 * @param array  $config  An array that holds the plugin configuration
	 */
	public function __construct (&$subject, $config=array()) {
		parent::__construct($subject, $config);

		$distiPlugin = JPluginHelper::getPlugin('vmextended', 'distributor');
		$distiParams = new JParameter($distiPlugin->params);

		if (VmConfig::isJ15()) {
			// fake $this->params for 1.5
			$plugin = JPluginHelper::getPlugin('vmdistributor', 'ingram');
			$this->params = new JParameter($plugin->params);
			JPlugin::loadLanguage('plg_vmdistributor_ingram');
		} else {
			$this->loadLanguage('plg_vmdistributor_ingram');
		}

		$this->_priceAdjust = floatval($this->params->get('priceAdjust', 1.05));
		$this->_keepNames = $distiParams->get('keep_names', false);
		$this->_separator = $distiParams->get('separator', '|');


		JTable::addIncludePath(JPATH_PLUGINS.DS.'vmdistributor'.DS.'ingram'.DS.'tables');
	}

	/**
	 * Prepare a category for action
	 *
	 * @param array One data row from import process
	 * @param string Distributor helper type
	 * @return mixed stdClass object on success, null otherwise
	 */
	public function OnVmDistributorPrepareCategory ($row, $type) {
		static $parent = '';

		if ($type != $this->_myType) {
			return; // important to return null here for dispatcher
		}

		$table = JTable::getInstance('IngramCategories', 'Table');

		$new_parent = !intval($row[2]);
		$ingram_code = $row[1] . (($new_parent) ? '' : $row[2]);

		// lookup parent name if it is not set in static here (e.g. a page refresh during processing)
		if (empty($parent) && !$new_parent) {
			$row = $table->getCategory($row[2]);
			$parent = $row->category_name;
			unset($row);
		}

		// attempt to load existing category that matches the ingram code
		$ret = $table->getCategory($ingram_code);
		$ret->ingram_category_id = $ingram_code;

		// set category name
		$ret->category_name = ($this->_keepNames && $ret->category_name) ? $ret->category_name : $this->_name($row[0]);

		// set parentage for category
		$ret->category_path = ($new_parent) ? '' : $parent . $this->_separator;
		$ret->category_path .= $ret->category_path;

		// publish category? (assume yes)
		$ret->category_publish = ($ret->category_publish) ? $ret->category_publish : 'Y';

		$parent = ($new_parent) ? $ret->category_name : $parent;
		return $ret;
	}

	/**
	 * Save Ingram category xref
	 *
	 * @param array One data row from save action @see plgVmExtendedDistributor::saveCategory for details
	 * @param string Distributor helper type
	 */
	public function AfterVmDistributorSaveCategory ($row, $type) {
		if ($type != $this->_myType) {
			return;
		}

		$table = JTable::getInstance('IngramCategories', 'Table');
		$table->save($row);
	}

	/**
	 * Prepare a product for action
	 *
	 * @param array One data row from import process
	 * @param string Distributor helper type
	 * @return mixed stdClass object on success, null otherwise
	 */
	public function OnVmDistributorPrepareProduct ($row, $type) {
		if ($type != $this->_myType) {
			return;
		}

		$catTable = JTable::getInstance('IngramCategories', 'Table');

		// start by looking up an existing product with the same ingram stock code
		$ret = $this->getProdBySku($row[3]);

		$desc = trim($row[4]) . ' ' . trim($row[5]);

		$ret->_remove = ($row[0] == 'D'); // command (Add, Change, Delete)
		$mfg = $this->_createMfgName($row[1]);
		$ret->mf_name = $mfg; // Manufacturer Name
		$ret->product_name = ($this->_keepNames && $ret->product_name) ? $ret->product_name : $this->_createName($desc, $mfg); // get first three words of desc for name?
		// $row[2] = IM Manufacturer #
		$ret->product_sku = $row[3]; //IM SKU
		$ret->product_s_desc = ($this->_keepNames && $ret->product_s_desc) ? $ret->product_s_desc : $desc; //descriptions lines
		$ret->product_cpu_code = $row[6]; // CPU Code
		$ret->product_part_no = $row[7]; // MFG Part #
		$ret->product_price = (float)$row[8] * $this->_priceAdjust/100; // Price
		$ret->product_publish = (intval($row[8]) == 0) ? 'N' : 'Y';
		$ret->product_rrp = $row[9]; // RRP
		// $row[10] = IM Received
		$ret->product_new_sku = $row[11]; // New Sku
		if ($row[12] == 'Y') { // Discontinued
			$ret->product_in_stock = 0; // discontinued objects lose all stock so we don't sell what we don't have
			$ret->_discontinue = true;
		}
		$ret->product_upc = $row[13]; // UPC Code
		$ret->product_ingram_cat = $row[14]; // IM Cat
		$ret->product_currency = ($row[15] == 'UK') ? 'GBP' : $row[15]; // Currency
		$ret->product_weight = $row[16]; // Weight in kg
		$ret->product_weight_uom = 'kg';
		// $row[17] = SKU class
		// $row[18] = Case Qty
		// $row[19] = Pallet Qty
		// $row[20] = SKU Country
		// $row[21] = Promo

		return $ret;
	}

	/**
	 * Prepare a product availability for action
	 *
	 * @param array One data row from import process
	 * @param string Distributor helper type
	 * @return mixed stdClass object on success, null otherwise
	 */
	public function OnVmDistributorPrepareAvailability ($row, $table) {
		if ($type != $this->_myType) {
			return;
		}

		// start by looking up an existing product with the same ingram stock code
		$ret = $table->getIdBySku($row[3]);

		$ret->product_sku = $row[0]; //IM SKU
		$ret->product_part_no = $row[1];
		$ret->product_in_stock = $row[2];
		//'product_available_date' = date('Y-m-d', strtotime(str_replace('/', '-', $row[4])))
		//'product_availability' = ($row[3] > 0) ? 'On Order' : '',

		return $ret;
	}

	// helper methods

	function _name($name) {
		$name = strtolower($name);
		$parts = explode(' ', $name);
		if (!is_array($parts)) {
			$parts = array($parts);
		}
		array_walk($parts, array($this, '_cleanName'));
		return implode(' ', $parts);
	}

	function _createMfgName($name) {
		// first remove the excess info
		$pos = strpos($name, ' - ');
		if ($pos) {
			$name = trim(substr($name, 0, $pos));
		}
		$name = strtolower($name);
		// zebra names
		if (strpos($name, 'zc') !== false || strpos($name, 'zebra') !== false) {
			$name = 'zebra';
		}

		// replace words
		$replace = array('fts' => 'fujitsu siemens', 'asustek' => 'asus', 'kingston technology' => 'kingston', 'wd' => 'western digital');
		$name = str_replace(array_keys($replace), array_values($replace), $name);
		//remove words
		$remove = array('acco/', 'special', 'computer works', 'computer', 'data systems', 'value ram', 'for dell only', 'manufactured hp products', 'display solutions', '-symbol', '3a consignment', 'spares', '(ex caere)', 'outsourcing', 'dacom', 'consumer electronics', 'optiarc europe', 'accessories');
		$name = str_replace($remove, '', $name);

		return $this->_name($name);
	}

	function _cleanName(&$name) {
		$ucwords = array('tdk', 'rim', 'hp', 'i.c.p.', 'gn', 'ibm', 'amd', 'dvd', 'dvd-rom', 'lcd', 'crt', 'pc', 'svga', 'sata', 'usb', 'vga', 'ups', 'uhf', 'tv', 'gps', 'nas', 'cat', 'lan', 'ip', 'apc', '3com', 'ca', 'lg', 'nec');
		$lcwords = array('');
		$chr = array('+-' => chr(241));

		$name = strtolower(trim($name));
		if (in_array($name, $ucwords)) {
			$name = strtoupper($name);
		}
		if (!in_array($name, $lcwords)) {
			$name = ucfirst($name);
		}
		$name = trim($name);
		return str_replace(array_keys($chr), array_values($chr), $name);
	}

	function _createName ($name, $mfg_name='', $length=3) {
		$test = explode(' ', $name);
		if ($this->getMfgName() && !empty($mfg_name)) {
			array_unshift($test, $mfg_name);
		}
		array_splice($test, $length);
		array_walk($test, array($this, '_cleanName'));
		return implode(' ', $test);
	}
}
