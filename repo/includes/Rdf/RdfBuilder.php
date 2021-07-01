<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Rdf;

use SplQueue;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Repo\Content\EntityContentFactory;
use Wikimedia\Purtle\RdfWriter;

/**
 * RDF mapping for wikibase data model.
 *
 * @license GPL-2.0-or-later
 */
class RdfBuilder implements EntityRdfBuilder, EntityStubRdfBuilder, EntityMentionListener {

	/**
	 * A list of entities mentioned/touched to or by this builder.
	 * The prefixed entity IDs are used as keys in the array, the value 'true'
	 * is used to indicate that the entity has been resolved. If the value
	 * is an EntityId, this indicates that the entity has not yet been resolved
	 * (defined).
	 *
	 * @var (bool|EntityId)[]
	 */
	private $entitiesResolved = [];

	/**
	 * A queue of entities to output by this builder.
	 *
	 * @var SplQueue<EntityDocument>
	 */
	private $entitiesToOutput;

	/**
	 * What the serializer would produce?
	 *
	 * @var int
	 */
	private $produceWhat;

	/**
	 * @var RdfWriter
	 */
	private $writer;

	/**
	 * @var RdfVocabulary
	 */
	private $vocabulary;

	/** @var EntityContentFactory */
	private $entityContentFactory;

	/**
	 * Entity-specific RDF builders to apply when building RDF for an entity.
	 * @var EntityRdfBuilder[]
	 */
	private $entityRdfBuilders;

	/**
	 * Entity-specific stub RDF builders factory
	 * @var EntityStubRdfBuilderFactory|null
	 */
	private $entityStubRdfBuilderFactory;

	/**
	 * Entity-specific stub RDF builders to apply when building RDF for an entity.
	 * @var EntityStubRdfBuilder[]
	 */
	private $entityStubRdfBuilders;
	/**
	 * @var EntityRevisionLookup
	 */
	private $entityRevisionLookup;

	public function __construct(
		RdfVocabulary $vocabulary,
		EntityRdfBuilderFactory $entityRdfBuilderFactory,
		int $flavor,
		RdfWriter $writer,
		DedupeBag $dedupeBag,
		EntityContentFactory $entityContentFactory,
		EntityStubRdfBuilderFactory $entityStubRdfBuilderFactory,
		EntityRevisionLookup $entityRevisionLookup
	) {
		$this->entitiesToOutput = new SplQueue();
		$this->vocabulary = $vocabulary;
		$this->writer = $writer;
		$this->produceWhat = $flavor;
		$this->entityContentFactory = $entityContentFactory;
		$this->entityStubRdfBuilderFactory = $entityStubRdfBuilderFactory;
		$this->entityRevisionLookup = $entityRevisionLookup;

		$this->entityRdfBuilders = $entityRdfBuilderFactory->getEntityRdfBuilders(
			$flavor,
			$vocabulary,
			$writer,
			$this,
			$dedupeBag
		);

		$this->entityStubRdfBuilders = $this->entityStubRdfBuilderFactory->getEntityStubRdfBuilders(
			$vocabulary,
			$writer
		);
	}

	/**
	 * Start writing RDF document
	 * Note that this builder does not have to finish it, it may be finished later.
	 */
	public function startDocument(): void {
		foreach ( $this->getNamespaces() as $gname => $uri ) {
			$this->writer->prefix( $gname, $uri );
		}

		$this->writer->start();
	}

	/**
	 * Finish writing the document
	 * After that, nothing should ever be written into the document.
	 */
	public function finishDocument(): void {
		$this->writer->finish();
	}

	/**
	 * Returns the RDF generated by the builder
	 */
	public function getRDF(): string {
		return $this->writer->drain();
	}

	/**
	 * Returns a map of namespace names to URIs
	 */
	public function getNamespaces(): array {
		return $this->vocabulary->getNamespaces();
	}

	/**
	 * Get map of page properties used by this builder
	 *
	 * @return string[][]
	 */
	public function getPagePropertyDefs(): array {
		return $this->vocabulary->getPagePropertyDefs();
	}

	/**
	 * Should we produce this aspect?
	 */
	private function shouldProduce( int $what ): bool {
		return ( $this->produceWhat & $what ) !== 0;
	}

	/**
	 * @see EntityMentionListener::entityReferenceMentioned
	 */
	public function entityReferenceMentioned( EntityId $id ): void {
		if ( $this->shouldProduce( RdfProducer::PRODUCE_RESOLVED_ENTITIES ) ) {
			$this->entityToResolve( $id );
		}
	}

	/**
	 * @see EntityMentionListener::propertyMentioned
	 */
	public function propertyMentioned( PropertyId $id ): void {
		if ( $this->shouldProduce( RdfProducer::PRODUCE_PROPERTIES ) ) {
			$this->entityToResolve( $id );
		}
	}

	/**
	 * @see EntityMentionListener::subEntityMentioned
	 */
	public function subEntityMentioned( EntityDocument $entity ): void {
		$this->entitiesToOutput->enqueue( $entity );
	}

	/**
	 * Registers an entity as mentioned.
	 * Will be recorded as unresolved
	 * if it wasn't already marked as resolved.
	 */
	private function entityToResolve( EntityId $entityId ): void {
		$prefixedId = $entityId->getSerialization();

		if ( !isset( $this->entitiesResolved[$prefixedId] ) ) {
			$this->entitiesResolved[$prefixedId] = $entityId;
		}
	}

	/**
	 * Registers an entity as resolved.
	 */
	private function entityResolved( EntityId $entityId ): void {
		$prefixedId = $entityId->getSerialization();
		$this->entitiesResolved[$prefixedId] = true;
	}

	/**
	 * Adds revision information about an entity's revision to the RDF graph.
	 *
	 * @todo extract into MetaDataRdfBuilder
	 *
	 * @param EntityId $entityId
	 * @param int $revision
	 * @param string $timestamp in TS_MW format
	 */
	public function addEntityRevisionInfo( EntityId $entityId, int $revision, string $timestamp ): void {
		$timestamp = wfTimestamp( TS_ISO_8601, $timestamp );
		$entityLName = $this->vocabulary->getEntityLName( $entityId );
		$entityRepositoryName = $this->vocabulary->getEntityRepositoryName( $entityId );

		$this->writer->about( $this->vocabulary->dataNamespaceNames[$entityRepositoryName], $entityLName )
			->a( RdfVocabulary::NS_SCHEMA_ORG, "Dataset" )
			->say( RdfVocabulary::NS_SCHEMA_ORG, 'about' )
			->is( $this->vocabulary->entityNamespaceNames[$entityRepositoryName], $entityLName );

		if ( $this->shouldProduce( RdfProducer::PRODUCE_VERSION_INFO ) ) {
			// Dumps don't need version/license info for each entity, since it is included in the dump header
			$this->writer
				->say( RdfVocabulary::NS_CC, 'license' )->is( $this->vocabulary->getLicenseUrl() )
				->say( RdfVocabulary::NS_SCHEMA_ORG, 'softwareVersion' )->value( RdfVocabulary::FORMAT_VERSION );
		}

		$this->writer->say( RdfVocabulary::NS_SCHEMA_ORG, 'version' )->value( $revision, 'xsd', 'integer' )
			->say( RdfVocabulary::NS_SCHEMA_ORG, 'dateModified' )->value( $timestamp, 'xsd', 'dateTime' );
	}

	/**
	 * Add page props information.
	 * To ensure consistent data, this recalculates the page props from the entity content;
	 * it does not actually query the page_props table.
	 */
	public function addEntityPageProps( EntityDocument $entity ): void {
		if ( !$this->shouldProduce( RdfProducer::PRODUCE_PAGE_PROPS ) ) {
			return;
		}
		$pagePropertyDefs = $this->getPagePropertyDefs();
		if ( !$pagePropertyDefs ) {
			return;
		}
		$content = $this->entityContentFactory->newFromEntity( $entity );
		$entityPageProperties = $content->getEntityPageProperties();
		if ( !$entityPageProperties ) {
			return;
		}

		$entityId = $entity->getId();
		$entityRepositoryName = $this->vocabulary->getEntityRepositoryName( $entityId );
		$entityLName = $this->vocabulary->getEntityLName( $entityId );

		foreach ( $entityPageProperties as $name => $value ) {
			if ( !isset( $pagePropertyDefs[$name]['name'] ) ) {
				continue;
			}

			if ( isset( $pagePropertyDefs[$name]['type'] ) ) {
				settype( $value, $pagePropertyDefs[$name]['type'] );
			}

			$this->writer->about( $this->vocabulary->dataNamespaceNames[$entityRepositoryName], $entityLName )
				->say( RdfVocabulary::NS_ONTOLOGY, $pagePropertyDefs[$name]['name'] )
				->value( $value );
		}
	}

	/**
	 * Adds meta-information about an entity id (such as the ID and type) to the RDF graph.
	 *
	 * @todo extract into MetaDataRdfBuilder
	 */
	private function addEntityMetaData( EntityId $entityId ): void {
		$entityLName = $this->vocabulary->getEntityLName( $entityId );
		$entityRepoName = $this->vocabulary->getEntityRepositoryName( $entityId );

		$this->writer->about(
			$this->vocabulary->entityNamespaceNames[$entityRepoName],
			$entityLName
		)
			->a( RdfVocabulary::NS_ONTOLOGY, $this->vocabulary->getEntityTypeName( $entityId->getEntityType() ) );
	}

	/**
	 * Add an entity to the RDF graph, including all supported structural components
	 * of the entity and its sub entities.
	 *
	 * @param EntityDocument $entity the entity to output.
	 */
	public function addEntity( EntityDocument $entity ): void {
		$this->addSingleEntity( $entity );
		$this->addQueuedEntities();
	}

	/**
	 * Add a single entity to the RDF graph, including all supported structural components
	 * of the entity.
	 *
	 * @param EntityDocument $entity the entity to output.
	 */
	private function addSingleEntity( EntityDocument $entity ): void {
		$this->addEntityMetaData( $entity->getId() );

		$type = $entity->getType();
		if ( !empty( $this->entityRdfBuilders[$type] ) ) {
			$this->entityRdfBuilders[$type]->addEntity( $entity );
		}

		$this->entityResolved( $entity->getId() );
	}

	/**
	 * Add the RDF serialization of all entities in the entitiesToOutput queue
	 */
	private function addQueuedEntities(): void {
		while ( !$this->entitiesToOutput->isEmpty() ) {
			$this->addSingleEntity( $this->entitiesToOutput->dequeue() );
		}
	}

	/**
	 * Add stubs for any entities that were previously mentioned (e.g. as properties
	 * or data values).
	 */
	public function resolveMentionedEntities(): void {
		$hasRedirect = false;

		// TODO: Mark all entitiesResolved for prefetching

		foreach ( $this->entitiesResolved as $id ) {
			// $value is true if the entity has already been resolved,
			// or an EntityId to resolve.
			if ( !( $id instanceof EntityId ) ) {
				continue;
			}

			$lookupResult = $this->entityRevisionLookup->getLatestRevisionId( $id );
			$lookupStatus = $lookupResult->onNonexistentEntity(
				function () {
					return 'nonexistent';
				}
			)->onConcreteRevision(
				function () {
					return 'concrete revision';
				}
			)->onRedirect(
				function ( $revisionId, $redirectsTo ) {
					return $redirectsTo;
				}
			)->map();

			switch ( $lookupStatus ) {
				case 'nonexistent':
					continue 2;
				case 'concrete revision':
					$this->addEntityStub( $id );
					break;
				default:
					// NOTE: this may add more entries to the end of entitiesResolved;
					$this->addEntityRedirect( $id,
						$lookupStatus );
					$hasRedirect = true;
					break;
			}
		}

		// If we encountered redirects, the redirect targets may now need resolving.
		// They actually got added to $this->entitiesResolved, but may not have been
		// processed by the loop above, because they got added while the loop was in progress.
		if ( $hasRedirect ) {
			// Call resolveMentionedEntities() recursively to resolve any yet unresolved
			// redirect targets. The regress will eventually terminate even for circular
			// redirect chains, because the second time an entity ID is encountered, it
			// will be marked as already resolved.
			// @phan-suppress-next-line PhanPossiblyInfiniteRecursionSameParams
			$this->resolveMentionedEntities();
		}
	}

	/**
	 * Adds stub information for the given EntityId to the RDF graph.
	 * Stub information means meta information and labels.
	 */
	public function addEntityStub( EntityId $entityId ): void {
		$this->addEntityMetaData( $entityId );

		$type = $entityId->getEntityType();
		if ( !empty( $this->entityStubRdfBuilders[ $type ] ) ) {
			$this->entityStubRdfBuilders[ $type ]->addEntityStub( $entityId );
		}
	}

	/**
	 * Declares $from to be an alias for $to, using the owl:sameAs relationship.
	 */
	public function addEntityRedirect( EntityId $from, EntityId $to ): void {
		$fromLName = $this->vocabulary->getEntityLName( $from );
		$fromRepoName = $this->vocabulary->getEntityRepositoryName( $from );
		$toLName = $this->vocabulary->getEntityLName( $to );
		$toRepoName = $this->vocabulary->getEntityRepositoryName( $to );

		$this->writer->about( $this->vocabulary->entityNamespaceNames[$fromRepoName], $fromLName )
			->say( 'owl', 'sameAs' )
			->is( $this->vocabulary->entityNamespaceNames[$toRepoName], $toLName );

		$this->entityResolved( $from );

		if ( $this->shouldProduce( RdfProducer::PRODUCE_RESOLVED_ENTITIES ) ) {
			$this->entityToResolve( $to );
		}
	}

	/**
	 * Create header structure for the dump (this makes RdfProducer::PRODUCE_VERSION_INFO redundant)
	 *
	 * @param int $timestamp Timestamp (for testing)
	 */
	public function addDumpHeader( int $timestamp = 0 ): void {
		// TODO: this should point to "this document"
		$this->writer->about( RdfVocabulary::NS_ONTOLOGY, 'Dump' )
			->a( RdfVocabulary::NS_SCHEMA_ORG, "Dataset" )
			->a( 'owl', 'Ontology' )
			->say( RdfVocabulary::NS_CC, 'license' )->is( $this->vocabulary->getLicenseUrl() )
			->say( RdfVocabulary::NS_SCHEMA_ORG, 'softwareVersion' )->value( RdfVocabulary::FORMAT_VERSION )
			->say( RdfVocabulary::NS_SCHEMA_ORG, 'dateModified' )->value( wfTimestamp( TS_ISO_8601, $timestamp ), 'xsd', 'dateTime' )
			->say( 'owl', 'imports' )->is( RdfVocabulary::getOntologyURI() );
	}

}
