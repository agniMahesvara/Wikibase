<?php

namespace Wikibase\DataModel\Services\Tests\Lookup;

use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityIdLookupException;

/**
 * @covers Wikibase\DataModel\Services\Lookup\EntityIdLookupException
 *
 * @licence GNU GPL v2+
 * @author Adam Shorland
 */
class EntityIdLookupExceptionTest extends \PHPUnit_Framework_TestCase {

	public function testConstructorWithJustAnId() {
		$propertyId = new PropertyId( 'P42' );
		$exception = new EntityIdLookupException( $propertyId );

		$this->assertEquals( $propertyId, $exception->getEntityId() );
	}

}
