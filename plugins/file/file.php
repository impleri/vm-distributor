<?php
defined('_JEXEC') or die('Restricted access.');

jimport('joomla.filesystem.file');
jimport('joomla.client.ftp');
jimport('joomla.plugin.plugin');

/**
 * File plugin
 *
 * Fetches a remote file by FTP, decompresses it (if possible), then feeds text through CSV parser
 *
 * @package VMDisti
 * @author Christopher Roussel
 */
class plgVmDistributorFile extends JPlugin {
	/**
	 * @var array Common plain-text file extensions
	 * @todo convert to params
	 */
	private $_extensions = array('txt', 'csv');

	/**
	 * @var int Maximum lines per run
	 * @todo convert to params
	 *
	 * By setting this to 0, you can effectively force the import to do all lines at once,
	 * However if it is a huge file or the PHP timeout is low, the import may not finish
	 */
	private $_limit = 2000;

	/**
	 * get the lines for import
	 *
	 * @param TableDistributors object
	 * @param bool Cron override (passed to $this::fetch())
	 * @return array CSV converted to array
	 * @see $this::_fetch for return
	 */
	public function OnVmDistributorImport ($disti, $cron=false) {
		// only fetch a new file if we're not already running an import
		if ($disti->distributor_line == 0) {
			$this->_fetch($disti);
		}

		return $this->_read($disti, $cron);
	}

	/**
	 * initiates ftp and downloads file to Joomla's temp path
	 *
	 * @param TableDistributors object
	 */
	private function _fetch ($disti) {
		$mainframe = JFactory::getApplication();
		$path = $mainframe->getCfg('tmp_path');

		$f = JFTP::getInstance($disti->distributor_host, $disti->distributor_port, null, $disti->distributor_login, $disti->distributor_pass);
		$f->chdir($disti->distributor_path);
		$f->get($path . DS . $disti->distributor_object, $disti->distributor_object);
		unset($f);

		// unzips downloaded file if needed
		$ext = JFile::getExt(strtolower($disti->distributor_object));

		if (!in_array($ext, $this->_extensions)) {
			JArchive::extract($path . DS . $disti->distributor_object, $path);
		}
	}

	/**
	 * reads a prepared file from the temp path and converts the CSV to a usable array
	 *
	 * @param TableDistributors object
	 * @param bool Cron override
	 * @return array CSV section of retrieved file
	 */
	private function _read ($disti, $cron=false) {
		$mainframe = JFactory::getApplication();
		$path = $mainframe->getCfg('tmp_path');
		$file = $path . DS . ($disti->distributor_local) ? $disti->distributor_local: $disti->distributor_object;
		$csv = array();

		if (!JFile::exists($file)) {
			JError::raiseError(404, JText::_('VMDISTI_FILE_NOT_FOUND'));
			return false;
		}

		$current = $disti->distributor_line;
		$end =  $current + $this->_limit;
		$line = 0;

		$f = fopen($file, 'r');
		while (($row = fgetcsv($f, 0)) !== false) {
			if ( ($cron) || ($line >= $current && $line <= $end) ) {
				$csv[] = $row;
				$line++;
			}
		}
		fclose($f);

		return $csv;
	}
}
