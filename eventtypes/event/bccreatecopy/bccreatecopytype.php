<?php

/*
Copyright (C) 2006 SCK-CEN
Written by Kristof Coomans ( kristof[dot]coomans[at]telenet[dot]be )

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

include_once( 'kernel/classes/ezworkflowtype.php' );

define( 'EZ_WORKFLOW_TYPE_BCCREATECOPY', 'bccreatecopy' );

class BCCreateCopyType extends eZWorkflowEventType
{
    function BCCreateCopyType()
    {
        $this->eZWorkflowEventType( EZ_WORKFLOW_TYPE_BCCREATECOPY, ezi18n( 'kernel/workflow/event', 'BC Create Copy' ) );
        // limit workflows which use this event to be used only on the post-publish trigger
        $this->setTriggerTypes( array( 'content' => array( 'publish' => array( 'after' ) ) ) );
    }

    function &attributeDecoder( &$event, $attr )
    {
        $retValue = null;
        switch( $attr )
        {
            case 'selected_nodes':
            {
                $implodedNodeList = $event->attribute( 'data_text1' );

                $nodeList = array();
                if ( $implodedNodeList != '' )
                {
                    $nodeList = explode( ';', $implodedNodeList );
                }
                return $nodeList;
            }

            default:
            {
                eZDebug::writeNotice( 'unknown attribute:' . $attr, 'BccreatecopyType' );
            }
        }
        return $retValue;
    }

    function typeFunctionalAttributes()
    {
        return array( 'selected_nodes' );
    }

    function fetchHTTPInput( &$http, $base, &$event )
    {
    }

        /*
     \reimp
    */
    function customWorkflowEventHTTPAction( &$http, $action, &$workflowEvent )
    {
        $eventID = $workflowEvent->attribute( 'id' );
        $module =& $GLOBALS['eZRequestedModule'];

        switch ( $action )
        {
            case 'BrowseNodes':
            {
                include_once( 'kernel/classes/ezcontentbrowse.php' );
                eZContentBrowse::browse( array( 'action_name' => 'SelectNodes',
                                                'browse_custom_action' => array( 'name' => 'CustomActionButton[' . $eventID . '_AddNodes]',
                                                                                 'value' => $eventID ),
                                                'from_page' => '/workflow/edit/' . $workflowEvent->attribute( 'workflow_id' ),
                                                'ignore_nodes_select' => $this->attributeDecoder( $workflowEvent, 'selected_nodes' )
                                               ),
                                         $module );
            } break;

            case 'AddNodes':
            {
                include_once( 'kernel/classes/ezcontentbrowse.php' );
                $nodeList = eZContentBrowse::result( 'SelectNodes' );
                $workflowEvent->setAttribute( 'data_text1', implode( ';', array_unique( array_merge( $this->attributeDecoder( $workflowEvent, 'selected_nodes' ), $nodeList ) ) ) );
            } break;

            case 'RemoveNodes':
            {
                if ( $http->hasPostVariable( 'DeleteNodeIDArray_' . $eventID ) )
                {
                    $deleteList = $http->postVariable( 'DeleteNodeIDArray_' . $eventID );
                    $currentList = $this->attributeDecoder( $workflowEvent, 'selected_nodes' );
                    
                    if ( is_array( $deleteList ) )
                    {
                        $dif = array_diff( $currentList, $deleteList );
                        $workflowEvent->setAttribute( 'data_text1', implode( ';', $dif ) );
                    }
                }
            } break;

            default:
            {
                eZDebug::writeNotice( 'unknown custom action: ' . $action, 'BCCreateCopyType' );
            }
        }
    }

    function execute( &$process, &$event )
    {
        // global variable to prevent endless recursive workflows with this event
        $recursionProtect = 'BCCreateCopyType_recursionprotect_' . $event->attribute( 'id' );
        if ( array_key_exists( $recursionProtect, $GLOBALS ) )
        {
            unset( $GLOBALS[$recursionProtect] );
            return EZ_WORKFLOW_TYPE_STATUS_ACCEPTED;
        }

        $parameters = $process->attribute( 'parameter_list' );
        eZDebug::writeDebug( $parameters, 'BCCreateCopyType::execute process parameter_list' );

        include_once( 'kernel/classes/ezcontentobject.php' );
        $object =& eZContentObject::fetch( $parameters['object_id'] );

        // check if the object is available and if it's getting published for the first time
        if ( $object && ( $object->attribute( 'modified' ) == $object->attribute( 'published' ) ) )
        {
            $mainNode = $object->attribute( 'main_node' );

            eZDebug::writeDebug( $mainNode->attribute( 'node_id' ), 'BCCreateCopyType::execute object main node' );

            $copyNodeIDList = $event->attribute( 'selected_nodes' );
            eZDebug::writeDebug( $copyNodeIDList, 'BCCreateCopyType::execute nodes to copy' );

            if ( count( $copyNodeIDList ) > 0 )
            {
                if ( !array_key_exists( $recursionProtect, $GLOBALS ) )
                {
                    $GLOBALS[$recursionProtect] = true;
                }

                include_once( 'kernel/classes/ezcontentobjecttreenode.php' );

                foreach ( $copyNodeIDList as $copyNodeID )
                {
                    $copyNode = eZContentObjectTreeNode::fetch( $copyNodeID );

                    if ( $copyNode )
                    {
                        $copyObject =& $copyNode->attribute( 'object' );
                        $result = $this->copyObject( $copyObject, $mainNode );
                    }
                    else
                    {
                        eZDebug::writeWarning( 'node to copy doesn\'t exist anymore: ' . $copyNodeID, 'BCCreateCopyType::execute' );
                    }
                }
            }
        }

        return EZ_WORKFLOW_TYPE_STATUS_ACCEPTED;
    }

    function copyObject( &$object, &$newParentNode )
    {
        $newParentNodeID = $newParentNode->attribute( 'node_id' );
        $classID = $object->attribute('contentclass_id');

        include_once( 'lib/ezdb/classes/ezdb.php' );
        $db =& eZDB::instance();
        $db->begin();
        $newObject =& $object->copy( false );

        $curVersion        =& $newObject->attribute( 'current_version' );
        $curVersionObject  =& $newObject->attribute( 'current' );
        $newObjAssignments =& $curVersionObject->attribute( 'node_assignments' );
        unset( $curVersionObject );

        // remove old node assignments
        foreach( $newObjAssignments as $assignment )
        {
            $assignment->remove();
        }

        // and create a new one
        $nodeAssignment = eZNodeAssignment::create( array(
                                                         'contentobject_id' => $newObject->attribute( 'id' ),
                                                         'contentobject_version' => $curVersion,
                                                         'parent_node' => $newParentNodeID,
                                                         'is_main' => 1
                                                         ) );
        $nodeAssignment->store();

        eZDebug::writeDebug( $newObject->attribute( 'id' ), 'BCCreateCopyType::copyObject new object id' );

        // publish the newly created object
        include_once( 'lib/ezutils/classes/ezoperationhandler.php' );
        $result = eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $newObject->attribute( 'id' ),
                                                                  'version'   => $curVersion ) );

        eZDebug::writeDebug( $result, 'BCCreateCopyType::copyObject publish operation result' );

        // Update "is_invisible" attribute for the newly created node.
        $newNode =& $newObject->attribute( 'main_node' );
        eZContentObjectTreeNode::updateNodeVisibility( $newNode, $newParentNode );

        $db->commit();
        return true;
    }
}

eZWorkflowEventType::registerType( EZ_WORKFLOW_TYPE_BCCREATECOPY, 'bccreatecopytype' );

?>
