<div class="element">
{"Nodes to copy"|i18n("design/standard/workflow/eventtype/view")}: 
{def $selectedNodeIDList=$event.selected_nodes}
{foreach $selectedNodeIDList as $selectedNodeID}
{delimiter}, {/delimiter}
{def $selectedNode=fetch('content','node',hash('node_id', $selectedNodeID ))}
<a href={$selectedNode.url_alias|ezurl}>{$selectedNode.name|wash}</a>
{undef $selectedNode}
{/foreach}
{undef $selectedNodeIDList}
</div>