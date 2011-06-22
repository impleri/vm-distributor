<?php
defined( '_JEXEC' ) or die( 'Direct Access to this location is not allowed.' );

jimport('joomla.plugin.plugin');

/**
 * Distributor plugin for VM2
 *
 * @package VMDisti
 * @author Christopher Roussel
 */
class plgVmExtendedDistributor extends VmExtendedPlugin {
	 /**
	  * Copy of the $params from JPlugin for 1.5 compatibility
	  * @var JParameter object
	  */
	public $params = null;

	/**
	 * Constructor
	 *
	 * @param object $subject The object to observe
	 * @param array  $config  An array that holds the plugin configuration
	 */
	public function __construct (&$subject, $config=array()) {
		parent::__construct($subject, $config);
		if (VmConfig::isJ15()) {
			// fake $this->params for 1.5
			$plugin = JPluginHelper::getPlugin('vmextended', 'distributor');
			$this->params = new JParameter($plugin->params);
			// load language
			JPlugin::loadLanguage('plg_vmextended_distributor');
		} else {
			// load language
			$this->loadLanguage('plg_vmextended_distributor');
		}
		JTable::addIncludePath($this->_path.DS.'tables');
	}

	/**
	 * Plugs into the backend controller logic to insert a custom controller into the VM component space
	 * This means that links can be constructed as index.php?option=com_virtuemart&view=myaddon and work
	 *
	 * @param string $controller Name of controller requested
	 * @return True if this loads a file (null otherwise)
	 */
	public function onVmAdminController ($controller) {
		if ($controller = 'distributor') {
			require_once($this->_path.DS.'controllers'.DS.'distributor.php');
			return true;
		}
	}

	/**
	 * Plugs into the updater model to remove additional VM data (useful if the plugin depends on fields in a VM table)
	 *
	 * @param object $updater VirtueMartModelUpdatesMigration object
	 */
	public function onVmSqlRemove (&$updater) {
		$filename = $this->_path.DS.'sql'.DS.'uninstall.sql';
		$updater->execSQLFile($filename);
	}

	/**
	 * Plugs into the updater model to reinstall additional VM data (useful if the plugin depends on fields in a VM table)
	 *
	 * @param object $updater VirtueMartModelUpdatesMigration object
	 */
	public function onVmSqlRestore (&$updater) {
		$filename = $this->_path.DS.'sql'.DS.'install.sql';
		$updater->execSQLFile($filename);
	}

	/**
	 * Adds warnings for discontinued products
	 *
	 * @param object $product VirtueMartTableProducts object
	 */
	public function onProductDescription (&$product) {
		// no category ids being given, so exit without processing
		if(empty($product->categories)) {
			return;
		}

		// add discontinued message to products put in the discontinued category
		if (in_array($this->params->def('discontinued', '-1'), $product->categories)) {
			$text = JText::_('VMDISTI_PRODUCT_DISCONTINUED');
			JError::raiseNotice(104, $text);
			$product->product_description = '<div class="vmProductRemoved">' . $text . '</div>' . $product->product_description;
		}
	}
}
