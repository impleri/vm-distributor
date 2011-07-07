<?php
/**
 * distributor raw view
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
	 * general display method (only does cron/run)
	 *
	 */
	public function display($tpl=null) {
		$this->setLayout('cron');
		$model = $this->getModel();

		if (!$model->run($this->cids, true)) {
			$this->setError($model->getError());
		}

		$this->assign('total', $model->get('total'));
		parent::display();
	}

}
