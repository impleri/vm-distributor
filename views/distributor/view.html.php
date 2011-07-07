<?php
/**
 * distributor html views
 *
 * @package VMDisti
 * @author Christopher Roussel
 */

defined( '_JEXEC' ) or die( 'Direct Access to this location is not allowed.' );

jimport( 'joomla.application.component.view' );

class VirtuemartViewDistributor extends JView {
	/**
	 * @var array distributor ids
	 */
	public $cids = array();

	/**
	 * @var string notification message
	 */
	public $message = '';

	/**
	 * general display method
	 *
	 */
	public function display($tpl=null) {
		$this->loadHelper('shopFunctions');
		$this->loadHelper('adminui');

		$model = $this->getModel();

		switch ($this->_layout) {
			case 'run':
			case 'cron':
				$this->cron();
			case 'edit':
				$this->assign('distributor', $model->getDistributor($this->cids));
				break;
			case 'distributor':
			case 'list':
			default:
				$this->assignRef('pagination',	$model->getPagination());
				$this->assign('distributors', $model->getDistributorList());
				$this->assignRef('lists', ShopFunctions::addStandardDefaultViewLists($model));
				break;
		}

		$this->assign('cids', $this->cids);
		ShopFunctions::SetViewTitle('vm_distributor_48', '', $this->message);
		parent::display($tpl);
	}

	/**
	 * run imports for selected distributor(s)
	 */
	private function cron() {
		$model = $this->getModel();

		if (!$model->run($this->cids)) {
			$this->setError($model->getError());
		}

		$this->assign('line', $model->get('line'));
		$this->assign('total', $model->get('total'));

		if (!$model->isComplete()) {
			$document = JFactory::getDocument();
			$document->setMetaData ('refresh', '1', true);
		}
		else {
			$this->message = JText::_('VMDISTI_RUN_COMPLETE');
		}
	}

}
