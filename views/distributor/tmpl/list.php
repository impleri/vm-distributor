<?php
/**
 * distributors list view
 *
 * @package VMDisti
 * @author Christopher Roussel
 */

defined('_JEXEC') or die('Restricted access');
AdminUIHelper::startAdminArea();
JHTML::_('behavior.tooltip');
$option = JRequest::getWord('option');
?>
<form action="index.php" method="post" name="adminForm" id="adminForm">
<div style="text-align: left;">
	<table class="admin-table" cellspacing="0" cellpadding="0">
	<thead>
	<tr>
		<th><input type="checkbox" name="toggle" value="" onclick="checkAll('<?php echo count($this->distributors); ?>')" /></th>
		<th><?php echo JHTML::_('grid.sort', 'VMDISTI_DISTRIBUTOR_NAME', 'distributor_name', $this->lists['filter_order_Dir'], $this->lists['filter_order'] ); ?></th>
		<th><?php echo JHTML::_('grid.sort', 'VMDISTI_DISTRIBUTOR_TYPE', 'distributor_type', $this->lists['filter_order_Dir'], $this->lists['filter_order'] ); ?></th>
		<th><?php echo JText::_('VMDISTI_DISTRIBUTOR_PATH'); ?></th>
		<th><?php echo JText::_('VMDISTI_DISTRIBUTOR_LAST_RUN'); ?></th>
		<th width="40px" ><?php echo JHTML::_('grid.sort', 'COM_VIRTUEMART_PUBLISHED', 'published', $this->lists['filter_order_Dir'], $this->lists['filter_order'] ); ?></th>
		<th><?php echo 'id'; ?></th>
	</tr>
	</thead>
	<tbody>
	<?php
	if ($total = count($this->distributors) ) {
		$i = 0;
		$k = 0;
		foreach ($this->distributors as $key => $distributor) {
			?>
			<tr class="<?php echo "row$k"; ?>">
				<td><?php echo JHTML::_('grid.id', $i , $distributor->distributor_id,null,'distributor_id'); ?></td>
				<td><?php
					echo JHTML::_('link', JRoute::_('index.php?option='.$option.'&view=distributor&task=edit&cid='.$distributor->distributor_id), $distributor->distributor_name, array('title' => JText::_('COM_VIRTUEMART_EDIT').' '.$distributor->distributor_name));
				?></td>
				<td><?php echo JText::_('VMDISTI_TYPE_' . strtoupper($distributor->distributor_type)); ?></td>
				<td><?php echo $distributor->path; ?></td>
				<td><?php echo $distributor->last_run; ?></td>
				<td><?php echo JHTML::_('grid.published', $distributor, $i ); ?></td>
				<td><?php echo $distributor->distributor_id; ?></td>
			</tr>
		<?php
			$k = 1 - $k;
			$i++;
		}
	}
	?>
	</tbody>
	<tfoot>
		<tr>
		<td colspan="7">
			<?php echo $this->pagination->getListFooter(); ?>
		</td>
		</tr>
	</tfoot>
	</table>
</div>
<input type="hidden" name="option" value="<?php echo $option; ?>" />
<input type="hidden" name="view" value="distributor" />
<input type="hidden" name="task" value="" />
<input type="hidden" name="boxchecked" value="0" />
<input type="hidden" name="filter_order" value="<?php echo $this->lists['filter_order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['filter_order_Dir']; ?>" />
<?php echo JHTML::_( 'form.token' ); ?>
</form>

<?php AdminUIHelper::endAdminArea(); ?>
