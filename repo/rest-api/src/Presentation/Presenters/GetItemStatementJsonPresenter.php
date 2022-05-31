<?php declare( strict_types=1 );

namespace Wikibase\Repo\RestApi\Presentation\Presenters;

use Wikibase\DataModel\Serializers\StatementSerializer;
use Wikibase\Repo\RestApi\UseCases\GetItemStatement\GetItemStatementSuccessResponse;

/**
 * @license GPL-2.0-or-later
 */
class GetItemStatementJsonPresenter {

	private $serializer;

	/**
	 * @param StatementSerializer $serializer Should have $useObjectsForMaps (e.g. for qualifiers) set to true.
	 */
	public function __construct( StatementSerializer $serializer ) {
		$this->serializer = $serializer;
	}

	public function getJson( GetItemStatementSuccessResponse $response ): string {
		return json_encode(
			$this->serializer->serialize( $response->getStatement() )
		);
	}
}