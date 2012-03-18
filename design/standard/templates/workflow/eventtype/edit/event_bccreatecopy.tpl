<div class="block">
<fieldset>
<legend>{'Nodes to copy'|i18n( 'design/admin/workflow/eventtype/edit' )}</legend>

{if $event.selected_nodes|count|gt(0)}
<table class="list" cellspacing="0">
<thead>
<tr>
<th class="tight">&nbsp;</th>
<th>{'Nodes'|i18n( 'design/admin/workflow/eventtype/edit' )}</th>
</tr>
</thead>
<tbody>
{foreach $event.selected_nodes as $nodeID}
{def $node=fetch( 'content', 'node', hash( 'node_id', $nodeID ) )}
{if $node}
<tr>
<td><input type="checkbox" name="DeleteNodeIDArray_{$event.id}[]" value="{$nodeID}" /></td>
<td><a href={$node.url_alias|ezurl}>{$node.name}</a></td>
</tr>
{/if}
{undef $node}
{/foreach}
</tbody>
</table>
<input type="submit" class="button" name="CustomActionButton[{$event.id}_RemoveNodes]" value="{'Remove selected'|i18n( 'design/admin/workflow/eventtype/edit' )}" />
{/if}
<input type="submit" class="button" name="CustomActionButton[{$event.id}_BrowseNodes]" value="{'Add nodes'|i18n( 'design/admin/workflow/eventtype/edit' )}" />
</fieldset>
</div>