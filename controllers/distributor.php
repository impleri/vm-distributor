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
	}

	/**
	 * list all distributors
	 */
	function distributor() {
		$view = $this->getView();
		$view->setLayout('list');
		$view->setModel($this->getModel(), true);
		parent::display();
	}

	/**
	 * run one or more distribution items
	 */
	function run() {
		jimport('joomla.utilities.arrayhelper');
		$ids = JRequest::getVar('cid',array(),'', 'ARRAY');
		JArrayHelper::toInteger($ids);

		$document = JFactory::getDocument();
		$viewType = $document->getType();
		$view = $this->getView('distributor', $viewType);

		$view->setModel($this->getModel(), true);
		$view->assign('cids', $ids);
		$view->setLayout('run');
		parent::display();
	}

	/**
	 * clone a distribution
	 */
	public function duplicate() {
		$mainframe = Jfactory::getApplication();

		$view = $this->getView();
		$model = $this->getModel();
		$cid = JRequest::getInt('cid',0);
		$newId = $model->duplicate($cid);
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
		$this->setRedirect('index.php?option=com_virtuemart&view=distributor&'.$redirect, $msg, $msgtype);
	}
}