<?php
/**
 * Default page for VM Disti
 *
 * @package VMDisti
 * @author Christopher Roussel
 * @link http://impleri.net
 * @version $Id$
 */

defined( '_JEXEC' ) or die( 'Direct Access to this location is not allowed.' );
?>
<form action="index.php" method="post" name="adminForm">
<input type="hidden" name="option" value="com_vmdisti" />
<input type="hidden" name="task" value="" />
<input type="hidden" name="controller" value="" />
<input type="hidden" name="format" value="html" />
</form>

<div>
<p>Running Crons....</p>
<p><?php echo $this->message; ?></p>
<p>Run Complete!</p>
</div>
