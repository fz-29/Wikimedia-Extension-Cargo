<?php
/**
 * Defines a class, CargoDrilldownHierarchy, counts the template calls with occurrences of
 * CargoHierarchy Node's value after applying a CargoFilter instance.
 *
 * @author Feroz Ahmad
 * @ingroup Cargo
 */

class CargoDrilldownHierarchy extends CargoHierarchy {
    // Helper class members for Drilldown
	public $mWithinTreeMatchCount = 0;
	public $mExactRootMatchCount = 0;

    static function computeNodeCountByFilter( $node, $f, $fullTextSearchTerm, $appliedFilters ) {
        $cdb = CargoUtils::getDB();
        list( $tableNames, $conds, $joinConds ) = $f->getQueryParts( $fullTextSearchTerm, $appliedFilters );
        if ( $f->fieldDescription->mIsList ) {
            $countArg = "_rowID";
            $fieldTableName = $f->tableName . '__' . $f->name;
            $tableNames[] = $fieldTableName;
            $fieldName = '_value';
            $joinConds[$fieldTableName] = CargoUtils::joinOfMainAndFieldTable( $cdb, $f->tableName, $fieldTableName );
        } else {
            $countArg = "_ID";
            $fieldName = $f->name;
            $fieldTableName = $f->tableName;
        }

        $countClause = "COUNT(DISTINCT($countArg)) AS total";

        $hierarchyTableName = $f->tableName . '__' . $f->name . '__hierarchy';
        $tableNames[] = $hierarchyTableName;

        $joinConds[$hierarchyTableName] = CargoUtils::joinOfSingleFieldAndHierarchyTable( $cdb,
            $fieldTableName, $fieldName, $hierarchyTableName );

        $withinTreeHierarchyConds = array();
        $exactRootHierarchyConds = array();
        $withinTreeHierarchyConds[] = "_left >= $node->mLeft";
        $withinTreeHierarchyConds[] = "_right <= $node->mRight";
        $exactRootHierarchyConds[] = "_left = $node->mLeft";

        // within hierarchy tree value count
        $res = $cdb->select( $tableNames, array( $countClause ), array_merge( $conds, $withinTreeHierarchyConds ),
            null, null, $joinConds );
        $count = 0;
        while ( $row = $cdb->fetchRow( $res ) ) {
            $count = $row['total'];
        }
        $node->mWithinTreeMatchCount = $count;
        $cdb->freeResult( $res );
        // exact hierarchy node value count
        $res = $cdb->select( $tableNames, array( $countClause ), array_merge( $conds, $exactRootHierarchyConds ),
            null, null, $joinConds );
        $count = 0;
        while ( $row = $cdb->fetchRow( $res ) ) {
            $count = $row['total'];
        }
        $node->mExactRootMatchCount = $count;
        $cdb->freeResult( $res );
    }

    /**
     * Fill up (set the value) the count data members of nodes of the tree represented by node used
     * for calling this function. Also return an array of distinct values of the field and their counts.
     */
    static function computeNodeCountForTreeByFilter( $node, $f, $fullTextSearchTerm, $appliedFilters ) {
        $filter_values = array();
        $stack = new SplStack();
        // preorder traversal of the tree
        $stack->push( $node );
        while ( !$stack->isEmpty() ) {
            $node = $stack->pop();
            if ( $node->mLeft !== 1 ) {
                // check if its not __pseudo_root__ node, then only add count
                CargoDrilldownHierarchy::computeNodeCountByFilter( $node, $f, $fullTextSearchTerm, $appliedFilters );
                $filter_values[$node->mTitle] = $node->mWithinTreeMatchCount;
            }
            if ( count( $node->mChildren ) > 0 ) {
                if ( $node->mLeft !== 1 ) {
                    $filter_values[$node->mTitle . " only"] = $node->mWithinTreeMatchCount;
                }
                $i = count( $node->mChildren ) - 1;
                while ( $i >= 0 ) {
                    $stack->push( $node->mChildren[$i] );
                    $i = $i - 1;
                }
            }
        }
        return $filter_values;
    }
}