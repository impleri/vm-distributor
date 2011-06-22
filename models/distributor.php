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
// define('VMDISTI_CAT_PARENT', 1);
// define('VMDISTI_CAT_CHILD', 2);
// define('VMDISTI_CAT_PARENT_CHILD', 3);

	// distributor table
	private $_dist = null;
	public $result = '';

	// total lines to process
	public $total = 0;

	// current line
	public $line = 0;

	private $_cron = false;

	// separator for category paths
	private $_separator = '|';

	// separator for category paths
	private $_keepDeleted = false;

	// distributor types
	private $_types = array(
		'c' => 'category',
		'p' => 'product',
		'a' => 'availability',
	);

	function __construct($config = array()) {
		parent::__construct($config);
		$this->setMainTable('distributors');

		// set parameters
		$plugin = JPluginHelper::getPlugin('vmextended', 'distributor');
		$params = new JParameter($plugin->params);
		$this->_separator = $params->def('separator', '|');
		$this->_keepDeleted = $params->def('keep_deleted', false);

		// load plugin group
		JPluginHelper::importPlugin('vmdistributor');
	}

	// clone distributor
	// @param int ID of distributor to clone
	// $return int|bool ID of clone if successful, false if not
	function duplicate ($id) {
		$disti = $this->getTable($this->_maintablename);
		$disti->load($id);
		$disti->virtuemart_distributor_id = 0;
		$disti->distributor_name .= '(copy of '.$id.')';

		$this->store($disti);
		return $this->_id;
	}

	// is the import run complete
	function isComplete() {
		return ($this->line >= $this->_total);
	}

	// maps the type listed in the db (int[1]) to the proper table
	function getModelByType ($type) {
		if(!class_exists('VirtueMartModelCategory')) require(JPATH_VM_ADMINISTRATOR.DS.'models'.DS.'category.php');
		if(!class_exists('VirtueMartModelProduct')) require(JPATH_VM_ADMINISTRATOR.DS.'models'.DS.'product.php');

		switch ($type) {
			case 'c':
				$ret = 'VirtueMartModelCategory';
				break;
			default:
				$ret = 'VirtueMartModelProduct';
				break;
		}
		return new $ret();
	}

	// run through an import cycle
	function process ($distId) {
		$this->_disti = $this->getTable($this->_maintablename);
		if(!$this->_disti->load($distId)) {
			$this->setError($this->_disti->getError());
			return false;
		}

		$dispatcher = JDispatcher::getInstance();
		$data = $dispatcher->trigger('OnVmDistributorImport', $this->_disti);
		if (count($data) < 1) {
			JError::raiseWarning(404, JText::_('VMDISTI_NO_DATA'));
			return false;
		}

		$this->line = $this->_disti->distributor_line;
		$type = (isset($this->_types[$type])) ? $this->_types[$type] : 'product';
		$table = $this->getModelByType($type);
		$type = ucfirst($type);

		foreach ($data as $datum) {
			$trigger = 'OnVmDistributorPrepare'.$type;
			$input = $dispatcher->trigger($trigger, array($datum, $table));
			$input->vendor_id = $this->getVendorId();

			$action = ('remove' == $input->action && $this->_keepDeleted) ? 'discontinue' : $input->action;
			$action .= $type;
			$this->$action($input);
			$this->line++;
		}

		return true;
	}

	// remove category
	// @todo use VirtueMartModelCategory::remove
	function removeCategory ($row) {
		$model = new VirtueMartModelCategory();

		// testing token check
		return $model->remove(array($row->virtuemart_category_id));

		$table = $this->getTable('categories');
		$cid = intval($row->virtuemart_category_id);

		if( $model->clearProducts($cid) ) {
			if (!$table->delete($cid)) {
				$this->setError($table->getError());
				return false;
			}

			//deleting relations
			$query = "DELETE FROM `#__virtuemart_product_categories` WHERE `category_child_id` = ". $this->_db->Quote($cid);
			$this->_db->setQuery($query);

			if(!$this->_db->query()){
				$this->setError( $this->_db->getErrorMsg() );
			}

			//updating parent relations
			$query = "UPDATE `#__virtuemart_product_categories` SET `category_parent_id` = 0 WHERE `category_parent_id` = ". $this->_db->Quote($cid);
			$this->_db->setQuery($query);

			if(!$this->_db->query()){
				$this->setError( $this->_db->getErrorMsg() );
			}
		}
		else {
			$this->setError('VMDISTI_UNABLE_TO_CLEAR_CATEGORY_PRODUCTS');
			return false;
		}

		return true;
	}

	// remove product
	function removeProduct ($row) {
		$model = new VirtueMartModelProduct();
		return $model->remove(array($row->virtuemart_product_id));
	}

	// remove availability
	function removeAvailability ($row) {
		if (!$row->virtuemart_product_id) {
			return true;
		}
		$model = new VirtueMartModelProduct();
		$model->decreaseStock($row->virtuemart_product_id, $row->product_in_stock);
		return true;
	}

	// discontinue category (move category and children to the discontinued category)
	function discontinueCategory ($row) {
		$model = new VirtueMartModelCategory();
		$row->category_parent_id = $this->_discontinued;
		return $model->store($row);
	}

	// discontinue product (move product to the discontinued category)
	function discontinueProduct ($row) {
		$model = new VirtueMartModelProduct();
		$row->categories = array($this->_discontinued);
		return $model->store($row);
	}

	// discontinue availability
	function discontinueAvailability ($row) {
		return $this->removeAvailability($row);
	}

	// insert/update category
	function saveCategory ($row) {
		$model = new VirtueMartModelCategory();

		$path = (empty($row->category_path)) ? array($row->category_name) : explode($this->_separator, $row->category_path);
		$parent = 0;
		for($x = 0; $x < count($path); $x++) {
			$current = $this->_getCatByName($path[$x]);
			$current ->category_parent_id = $parent;
			$parent = $model->store($current);
		}
		$row->category_parent_id = $parent;
		return $model->store($row);
	}

	// insert/update product
	function saveProduct ($row) {
		$model = new VirtueMartModelProduct();
		return $model->store($row);
	}

	// update product availability
	function saveAvailability ($row) {
		if (!$row->virtuemart_product_id) {
			return true;
		}
		$model = new VirtueMartModelProduct();
		return $model->store($row);
	}

	// does the cron run
	function cron() {
		$this->cron = true;
		$jobs = $this->getList(true);

		if (!empty($jobs)) {
			foreach ($jobs as $id => $job) {
				$this->load($job['dist'], $job['type']);
				if ($this->process()) {
					$this->result .= $this->line . ' rows imported for ' . $job['name'] . ' ' . $this->getType($job['type']) . '<br />';
				}
				else {
					return false;
				}
			}
		}
		return true;
	}
}