<?php
defined('_JEXEC') or die('Restricted access.');

jimport('joomla.application.component.controller');
if(!class_exists('VmController'))require(JPATH_VM_ADMINISTRATOR.DS.'helpers'.DS.'vmcontroller.php');

/**
 * Distributor controller
 *
 * add/edit, save/apply, remove, cancel, un/publish handled in VmController
 *
 * @package VMDisti
 * @author Christopher Roussel
 */
class VirtuemartControllerDistributor extends VmController {
	/**
	 * constructor
	 */
	function __construct() {
		parent::__construct();
		$this->addModelPath(JPATH_PLUGINS.DS.'vmextended'.DS.'distributor'.DS.'models');
		$this->addViewPath(JPATH_PLUGINS.DS.'vmextended'.DS.'distributor'.DS.'views');
		$this->registerTask('distributor', 'list');
	}

	/**
	 * list all distributors
	 */
	function listAll() {}

	/**
	 * run one distribution item
	 */
	function runOne() {
		$this->display();
	}

	/**
	 * run all distribution items
	 */
	function run() {
		$this->display();
	}

	/**
	 * run all distribution items (raw)
	 */
	function cron() {
		$model = $this->getModel('distributor');
		if (!$model->cron()) {
			$this->setError(implode('<br />', $model->getErrors()));
		}
		return $model->result;
	}

	/**
	 * clone a distribution
	 */
	public function duplicate() {
		$mainframe = Jfactory::getApplication();

		$view = $this->getView();
		$model = $this->getModel();
		$cids = JRequest::getInt('virtuemart_distributor_id',0);
		$newId = $model->duplicate($cids[0]);
		if ($newId) {
			$redirect = 'task=edit&cid='.$newId;
			$msg = JText::_('VMDISTI_CLONE_SUCCESSFUL');
			$msgtype = '';
		}
		else {
			$redirect = 'task=list';
			$msg = JText::_('VMDISTI_CLONE_FAILED');
			$msgtype = 'error';
		}
		$mainframe->redirect('index.php?option=com_virtuemart&view=distributor&'.$redirect, $msg, $msgtype);
	}
}