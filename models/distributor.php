<?php
defined('_JEXEC') or die('Restricted access.');

jimport('joomla.application.component.model');
if(!class_exists('VmModel')) require(JPATH_VM_ADMINISTRATOR.DS.'helpers'.DS.'vmmodel.php');

/**
* Distributor model
*
* @package VMDisti
* @author Christopher Roussel
*/
class VirtuemartModelDistributor extends VmModel {
	/**
	 * @var int total lines
	 */
	public $total = 0;

	/**
	 * @var int current line
	 */
	public $line = 0;

	/**
	 * @var TableDistrubors object
	 */
	private $_dist = null;

	/**
	 * @var boolean switch for cron run
	 */
	private $_cron = false;

	/**
	 * @var string separator for category paths
	 */
	private $_separator = '|';

	/**
	 * @var boolean switch keep original names when updating existing objects
	 */
	private $_keepNames = false;

	/**
	 * @var boolean switch keep deleted items (requires $_discontinued > 0)
	 */
	private $_keepDeleted = false;

	/**
	 * @var int category id for discontinued items
	 */
	private $_discontinued = -1;

	/**
	 * @var array distributor types
	 */
	private $_types = array(
		'c' => 'category',
		'p' => 'product',
		'a' => 'availability',
	);

	/**
	 * @var array notification messages
	 */
	private $_messages = array();

	/**
	 * Constructor
	 *
	 * @param array configuration
	 */
	public function __construct($config = array()) {
		parent::__construct($config);
		$this->setMainTable('distributors');

		// set parameters
		$plugin = JPluginHelper::getPlugin('vmextended', 'distributor');
		$params = new JParameter($plugin->params);
		$this->_separator = $params->def('separator', '|');
		$this->_keepDeleted = $params->def('keep_deleted', false);
		$this->_keepNames = $params->def('keep_names', false);
		$this->_discontinued = $params->def('discontinued', -1);

		// load plugin group
		JPluginHelper::importPlugin('vmdistributor');

		// ensure the two common models exist
		if(!class_exists('VirtueMartModelCategory')) require(JPATH_VM_ADMINISTRATOR.DS.'models'.DS.'category.php');
		if(!class_exists('VirtueMartModelProduct')) require(JPATH_VM_ADMINISTRATOR.DS.'models'.DS.'product.php');
	}

	private function &getModel ($class) {
		static $models = array();

		if (!array_key_exists($class, $models)) {
			$name = 'VirtueMartModel' . ucfirst($class);
			$models[$class] = new $name;
		}

		return $models[$class];
	}

	private function getCategoryByName ($name) {

	}

	public function getDistributorList ($cids=array()) {
		$sql = 'SELECT * FROM ' . $this->_db->nameQuote($this->_maintablename);
		if (!empty($cids)) {
			$sql .= ' WHERE ' . $this->_db->nameQuote($this->_idName) . ' IN (' . implode(',',$cids) . ')';
		}

		$this->_getList($sql);
	}

	/**
	 * Clone distributor
	 *
	 * @param int ID of distributor to clone
	 * @return int|bool ID of clone if successful, false if not
	 */
	public function duplicate ($id) {
		$disti = $this->getTable($this->_maintablename);
		$disti->load($id);
		$disti->virtuemart_distributor_id = 0;
		$disti->distributor_name .= '(copy of '.$id.')';

		$this->store($disti);
		return $this->_id;
	}

	/**
	 * Run selected import jobs
	 *
	 * @return bool true if successful, false if not
	 * @todo migrate
	 */
	public function run (&$cids=array(), $cron=false) {
		$this->_cron = $cron;
		$jobs = $this->getDistributorList((array)$cids);
		$key = $this->_idName;

		if (!empty($jobs)) {
			foreach ($jobs as $id => $job) {
				if ($this->process($job)) {
					$this->_messages[] = JText::sprintf('VMDISTI_ROWS_IMPORTED', $this->line, $job['distributor_name'], JText::_('VMDISTI_TYPE_' . strtoupper($job['distributor_type'])));
				}

				if ($this->isJobComplete($job)) {
					unset($cids[$job[$this->_idName]]);
				}
			}
		}
		return true;
	}

	/**
	 * Run one cycle of the importer for one distributor
	 *
	 * @param int distributor id
	 * @return bool true if successful, false if not
	 */
	private function process (&$disti) {
		// load distributor
		$this->_disti = $this->getTable($this->_maintablename);
		if(!$this->_disti->bind($disti)) {
			$this->setError($this->_disti->getError());
			return false;
		}

		// trigger plugins to import for the distributor
		$dispatcher = JDispatcher::getInstance();
		$data = $dispatcher->trigger('OnVmDistributorImport', array($this->_disti, $this->_cron));
		if (count($data) < 1) {
			JError::raiseNotice(404, JText::sprintf('VMDISTI_NO_DATA', $this->_disti->distributor_name));
			return false;
		}

		$this->total = array_shift($data);
		$this->line = $this->_disti->distributor_line;
		$type = (isset($this->_types[$type])) ? $this->_types[$type] : 'product';
		$type = ucfirst($type);
		$trigger = 'OnVmDistributorPrepare'.$type;

		// cycle through imported data, preparing it through plugins and acting accordingly
		foreach ($data as $datum) {
			$input = $dispatcher->trigger($trigger, array($datum, $this->_disti->distributor_type));
			$input->vendor_id = $this->getVendorId();

			$action = ('remove' == $input->action && $this->_keepDeleted) ? 'discontinue' : $input->action;
			$action .= $type;
			$this->$action($input);
			$dispatcher->trigger('AfterVmDistributor' . ucfirst($action), array($input, $this->_disti->distributor_type));
			$this->line++;
		}

		return true;
	}

	/**
	 * Removes a category
	 *
	 * @param object Category
	 * @return bool true if successful, false if not
	 */
	function removeCategory ($row) {
		$model = $this->getModel('category');
		return $model->remove(array($row->virtuemart_category_id));
	}

	/**
	 * Removes a category
	 *
	 * @param object Category
	 * @return bool true if successful, false if not
	 */
	function removeProduct ($row) {
		$model = $this->getModel('product');
		return $model->remove(array($row->virtuemart_product_id));
	}

	/**
	 * Removes a category
	 *
	 * @param object Category
	 * @return bool true if successful, false if not
	 */
	function removeAvailability ($row) {
		if (!$row->virtuemart_product_id) {
			return true;
		}
		$model = $this->getModel('product');
		$model->decreaseStock($row->virtuemart_product_id, $row->product_in_stock);
		return true;
	}

	/**
	 * Removes a category
	 *
	 * @param object Category
	 * @return bool true if successful, false if not
	 */
	function discontinueCategory ($row) {
		$model = $this->getModel('category');
		$row->category_parent_id = $this->_discontinued;
		return $model->store($row);
	}

	// discontinue product (move product to the discontinued category)
	function discontinueProduct ($row) {
		$model = $this->getModel('product');
		$row->categories = array($this->_discontinued);
		return $model->store($row);
	}

	// discontinue availability
	function discontinueAvailability ($row) {
		return $this->removeAvailability($row);
	}

	// insert/update category
	function saveCategory ($row) {
		$model = $this->getModel('category');

		$path = (empty($row->category_path)) ? array($row->category_name) : explode($this->_separator, $row->category_path);
		$parent = 0;
		for($x = 0; $x < count($path); $x++) {
			$current = $this->getCategoryByName($path[$x]);
			$current ->category_parent_id = $parent;
			$parent = $model->store($current);
		}
		$row->category_parent_id = $parent;
		return $model->store($row);
	}

	// insert/update product
	function saveProduct ($row) {
		$model = $this->getModel('product');
		return $model->store($row);
	}

	// update product availability
	function saveAvailability ($row) {
		if (!$row->virtuemart_product_id) {
			return true;
		}
		$model = $this->getModel('product');
		return $model->store($row);
	}
}