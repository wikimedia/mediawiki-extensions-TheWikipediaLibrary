<?php

use MediaWiki\Extension\Notifications\DbFactory;
use MediaWiki\Extension\Notifications\Mapper\NotificationMapper;
use MediaWiki\Extension\TheWikipediaLibrary\EchoHelper;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\Platform\ISQLPlatform;

/**
 * @group TheWikipediaLibrary
 * @group Database
 */
class TheWikipediaLibraryEchoTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers MediaWiki\Extension\TheWikipediaLibrary\EchoHelper::send
	 */
	public function testNoDupes() {
		// setup
		$this->deleteEchoData();
		$user = $this->getMutableTestUser()->getUser();
		$title = Title::newFromText( 'Help:MWEchoThankYouEditTest_testFirstEdit' );

		// action
		for ( $i = 0; $i < 12; $i++ ) {
			EchoHelper::send( $user, $title );
			// Reload to reflect deferred update
			$user->clearInstanceCache();
		}

		// assertions
		$notificationMapper = new NotificationMapper();
		$notifications = $notificationMapper->fetchByUser( $user, 10, null, [ 'twl-eligible' ] );
		$this->assertCount( 1, $notifications );
	}

	private function deleteEchoData() {
		$db = DbFactory::newFromDefault()->getEchoDb( DB_PRIMARY );
		$db->newDeleteQueryBuilder()
			->deleteFrom( 'echo_event' )
			->where( ISQLPlatform::ALL_ROWS )
			->caller( __METHOD__ )
			->execute();
		$db->newDeleteQueryBuilder()
			->deleteFrom( 'echo_notification' )
			->where( ISQLPlatform::ALL_ROWS )
			->caller( __METHOD__ )
			->execute();
	}

}
