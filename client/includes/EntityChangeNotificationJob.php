<?php

declare( strict_types = 1 );

namespace Wikibase\Client;

use Job;
use Wikibase\Client\Changes\ChangeHandler;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\Lib\Changes\EntityChange;
use Wikibase\Lib\Changes\ItemChange;

/**
 * @license GPL-2.0-or-later
 */
class EntityChangeNotificationJob extends Job {

	/**
	 * @var EntityChange[]
	 */
	private $changes;

	/**
	 * @var ChangeHandler
	 */
	private $changeHandler;

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	public function __construct(
		ChangeHandler $changeHandler,
		EntityIdParser $entityIdParser,
		$params
	) {
		parent::__construct( 'EntityChangeNotification', $params );

		$this->changeHandler = $changeHandler;
		$this->entityIdParser = $entityIdParser;
		$this->changes = array_map( [ $this, 'reconstructChangeFromFields' ], $params['changes'] );
	}

	public static function newFromGlobalState( $unused, array $params ): self {
		return new self(
			WikibaseClient::getChangeHandler(),
			WikibaseClient::getEntityIdParser(),
			$params
		);
	}

	/**
	 * @inheritDoc
	 */
	public function run() {
		$this->changeHandler->handleChanges( $this->changes, $this->getRootJobParams() );

		return true;
	}

	private function reconstructChangeFromFields( array $changeFields ): EntityChange {
		$entityId = $this->entityIdParser->parse( $changeFields['object_id'] );
		if ( explode( '~', $changeFields['type'] )[0] === 'wikibase-item' ) {
			$entityChange = new ItemChange( $changeFields );
		} else {
			$entityChange = new EntityChange( $changeFields );
		}
		$entityChange->setEntityId( $entityId );
		return $entityChange;
	}
}