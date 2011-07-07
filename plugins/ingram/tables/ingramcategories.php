<?php
defined('_JEXEC') or die('Restricted access.');

/**
 * Ingram Category xref table
 *
 * @package VMDisti
 * @subpackage ingram
 * @author Christopher Roussel
 */
class TableIngramCategories extends JTable {
	/**
	 * @var int table key
	 */
	public $ingram_category_id;

	/**
	 * @var string distributor helper to load
	 */
	public $virtuemart_category_id;

	/**
	 * Constructor
	 *
	 * @param $db JDatabase object
	 */
	public function __construct(&$db) {
		parent::__construct('#__virtuemart_ingram_categories', 'ingram_category_id', $db);
	}

	/**
	 * Load category by ingram_category_id
	 *
	 * @param int Ingram Category ID
	 * @return mixed Category object on success, false otherwise
	 */
	public function &getCategory ($cid) {
		$sql = 'SELECT *' . '
			FROM ' . $this->_db->nameQuote($this->_tbl) . ' AS i
			LEFT JOIN ' . $this->_db->nameQuote('#__virtuemart_categories') . ' AS v ON i.' . $this->_db->nameQuote('virtuemart_category_id') . ' = v.' . $this->_db->nameQuote('virtuemart_category_id') . '
			WHERE ' . $this->_db->nameQuote($this->_tbl_key) . ' = ' . $this->_db->Quote($cid);
		$this->_db->setQuery($sql);

		$result = $this->_db->loadObject($this->_tbl_key);
		if (empty($result)) {
			$result = new stdClass();
		}

		return $result;
	}

}
