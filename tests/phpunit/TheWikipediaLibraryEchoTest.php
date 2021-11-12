<?php

use MediaWiki\Extension\TheWikipediaLibrary\EchoHelper;

/**
 * @group TheWikipediaLibrary
 * @group Database
 */
class TheWikipediaLibraryEchoTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'echo_event';
		$this->tablesUsed[] = 'echo_notification';
	}

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
		$notificationMapper = new EchoNotificationMapper();
		$notifications = $notificationMapper->fetchByUser( $user, 10, null, [ 'twl-eligible' ] );
		$this->assertCount( 1, $notifications );
	}

	private function deleteEchoData() {
		$db = MWEchoDbFactory::newFromDefault()->getEchoDb( DB_PRIMARY );
		$db->delete( 'echo_event', '*', __METHOD__ );
		$db->delete( 'echo_notification', '*', __METHOD__ );
	}

}
