<?php

declare( strict_types = 1 );
namespace Wikibase\Repo\Tests\FederatedProperties\Api;

use DataValues\Serializers\DataValueSerializer;
use FormatJson;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\SerializerFactory;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \Wikibase\Repo\Api\SetClaim
 *
 * @group API
 * @group Database
 * @group Wikibase
 * @group WikibaseAPI
 *
 * @group medium
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch
 */
class SetClaimTest extends FederatedPropertiesApiTestCase {

	public function testAlteringFederatedPropertiesIsNotSupported() {
		$entity = new Property( $this->newFederatedPropertyIdFromPId( 'P123' ), null, 'string' );
		$entityId = $entity->getId();

		$statement = new Statement( new PropertyNoValueSnak( $this->newFederatedPropertyIdFromPId( 'P626' ) ) );
		$guidGenerator = new GuidGenerator();
		$guid = $guidGenerator->newGuid( $entityId );
		$statement->setGuid( $guid );

		$this->setExpectedApiException( wfMessage( 'wikibase-federated-properties-local-property-api-error-message' ) );
		$this->doApiRequestWithToken( [
			'action' => 'wbsetclaim',
			'claim' => FormatJson::encode( $this->getSerializedStatement( $statement ) ),
		] );
	}

	public function testGivenSourceWikiUnavailable_respondsWithAnError() {
		$this->setSourceWikiUnavailable();

		$store = WikibaseRepo::getEntityStore();

		$statement = new Statement( new PropertyNoValueSnak( $this->newFederatedPropertyIdFromPId( 'P626' ) ) );

		$entity = new Item();
		$store->saveEntity( $entity, 'setclaimtest', $this->user, EDIT_NEW );
		$entityId = $entity->getId();

		$guidGenerator = new GuidGenerator();
		$guid = $guidGenerator->newGuid( $entityId );
		$statement->setGuid( $guid );

		$this->setExpectedApiException( wfMessage( 'wikibase-federated-properties-save-api-error-message' ) );
		$this->doApiRequestWithToken( [
			'action' => 'wbsetclaim',
			'claim' => FormatJson::encode( $this->getSerializedStatement( $statement ) ),
		] );
	}

	public function testAddingStatementUsingFederatedProperty(): void {
		$fedPropRemoteId = 'P626';
		$fedPropId = $this->newFederatedPropertyIdFromPId( $fedPropRemoteId );
		$statement = new Statement( new PropertyNoValueSnak( $fedPropId ) );

		$entity = new Item();
		WikibaseRepo::getEntityStore()->saveEntity( $entity, 'setclaimtest', $this->user, EDIT_NEW );
		$entityId = $entity->getId();

		$statement->setGuid( ( new GuidGenerator() )->newGuid( $entityId ) );

		$this->mockSourceApiRequests( [ [
			[
				'action' => 'wbgetentities',
				'ids' => $fedPropRemoteId,
			],
			[
				'entities' => [
					$fedPropRemoteId => [
						'datatype' => 'string',
					],
				],
			],
		] ] );

		[ $result ] = $this->doApiRequestWithToken( [
			'action' => 'wbsetclaim',
			'claim' => FormatJson::encode( $this->getSerializedStatement( $statement ) ),
		] );

		$this->assertArrayHasKey( 'success', $result );
		$this->assertSame(
			$fedPropId->getSerialization(),
			$result['claim']['mainsnak']['property']
		);
	}

	private function getSerializedStatement( $statement ) {
		$statementSerializer = ( new SerializerFactory( new DataValueSerializer() ) )->newStatementSerializer();
		return $statementSerializer->serialize( $statement );
	}

}
