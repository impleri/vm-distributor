<?php
defined('_JEXEC') or die('Restricted access.');

if(!class_exists('VmTable'))require(JPATH_VM_ADMINISTRATOR.DS.'helpers'.DS.'vmtable.php');

/**
 * Distributors table
 *
 * @package VMDisti
 * @author Christopher Roussel
 */
class TableDistributors extends VmTable {
	/**
	 * @var int table key
	 */
	public $virtuemart_distributor_id;

	/**
	 * @var string Distributor name
	 */
	public $distributor_name;

	/**
	 * @var string distributor helper to load
	 */
	public $distributor_helper = '';

	/**
	 * @var string distributor source host (varies by helper)
	 */
	public $distributor_host;

	/**
	 * @var int port on source server (optional)
	 */
	public $distributor_port;

	/**
	 * @var string distributor source path (varies by helper)
	 */
	public $distributor_path;

	/**
	 * @var string distributor source object (e.g. file, database name, etc)
	 */
	public $distributor_object;

	/**
	 * @var string distributor local file (optional: file to open if remote object is archived)
	 */
	public $distributor_local;

	/**
	 * @var string login for source server (optional)
	 */
	public $distributor_login;

	/**
	 * @var string password for source server (optional)
	 */
	public $distributor_pass;

	/**
	 * @var str Format type (can be /P/roduct, /A/vailability, or /C/ategory)
	 */
	public $distributor_type = 'p';

	/**
	 * @var int line number from last run (for long runs)
	 */

	public $distributor_line = 0;

	/**
	 * @var int how often (in seconds) to run
	 */

	public $distributor_frequency = 86400;

	/**
	 * @var int published or unpublished
	 */
	public $published = 1;


	/**
	 * Constructor
	 *
	 * @param $db JDatabase object
	 */
	public function __construct(&$db) {
		parent::__construct('#__virtuemart_distributors', 'virtuemart_distributor_id', $db);

		$this->setPrimaryKey('virtuemart_distributor_id');
		$this->setObligatoryKeys('distributor_name');
		$this->setObligatoryKeys('distributor_helper');
		$this->setObligatoryKeys('distributor_type');
	}

	/**
	 * Overload the load method to handle names
	 *
	 * @param int|string Distributor to load
	 * @return boolean true on success, false otherwise
	 */
	public function load ($dist='') {
		$ret = true;

		if (intval($dist) === $dist) {
			$ret = parent::load($dist);
		} else {
			$ret = $this->loadByName($dist);
		}

		return $ret;
	}

	/**
	 * Load distributor by name
	 *
	 * @param string Name of distributor to load
	 * @return boolean true on success, false otherwise
	 */
	public function loadByName ($name) {
		$sql = 'SELECT *' . '
			FROM `' . $this->_tbl . '`
			WHERE `distributor_name` = ' . $this->_db->Quote($name);
		$this->_db->setQuery($sql);

		$result = $this->_db->loadAssoc();
		if (!$result) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}

		return $this->bind($result);
	}

}
