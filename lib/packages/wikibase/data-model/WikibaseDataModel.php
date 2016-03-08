<?php

/**
 * Entry point for the Wikibase DataModel component.
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */

define( 'WIKIBASE_DATAMODEL_VERSION', '5.1.0' );

if ( defined( 'MEDIAWIKI' ) && function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'WikibaseDataModel', __DIR__ . '/mediawiki-extension.json' );
}

// Aliases introduced in 3.0.0
class_alias( 'Wikibase\DataModel\Statement\Statement', 'Wikibase\DataModel\Claim\Claim' );
class_alias( 'Wikibase\DataModel\Statement\StatementGuid', 'Wikibase\DataModel\Claim\ClaimGuid' );
class_alias( 'Wikibase\DataModel\Statement\StatementListProvider', 'Wikibase\DataModel\StatementListProvider' );
