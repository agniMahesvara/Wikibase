<?php
declare( strict_types = 1 );

namespace Wikibase\Repo\Tests\Unit\ServiceWiring;

use Wikibase\Lib\EntityTypeDefinitions;
use Wikibase\Lib\SettingsArray;
use Wikibase\Repo\Tests\Unit\ServiceWiringTestCase;

/**
 * @coversNothing
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class EntityTypeDefinitionsTest extends ServiceWiringTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->mockService( 'WikibaseRepo.Settings', new SettingsArray( [ 'federatedPropertiesEnabled' => false ] ) );
	}

	public function testConstruction(): void {
		$this->assertInstanceOf(
			EntityTypeDefinitions::class,
			$this->getService( 'WikibaseRepo.EntityTypeDefinitions' )
		);
	}

	public function testRunsHook(): void {
		$this->configureHookContainer( [
			'WikibaseRepoEntityTypes' => [ function ( array &$entityTypes ) {
				$entityTypes['test'] = [];
			} ],
		] );

		/** @var EntityTypeDefinitions $entityTypeDefinitions */
		$entityTypeDefinitions = $this->getService( 'WikibaseRepo.EntityTypeDefinitions' );

		$entityTypes = $entityTypeDefinitions->getEntityTypes();
		$this->assertContains( 'test', $entityTypes );
	}

}
