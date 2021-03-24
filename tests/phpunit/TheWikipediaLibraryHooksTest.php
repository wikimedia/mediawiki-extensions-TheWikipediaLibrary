<?php
use CentralAuth\CentralAuthUser;
use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;

/**
 * @group TheWikipediaLibrary
 */
class TheWikipediaLibraryHooksTest extends MediaWikiIntegrationTestCase {

	protected function setUp() : void {
		parent::setUp();

		// Methods that need to be set on both global users
		$methods = [
			'getName',
			'getGlobalEditCount',
			'getRegistration',
			'isAttached',
		];

		// Creating first global user that will be eligible for the Wikipedia Library
		$user1Name = 'User1';
		$this->centralAuthUser1 = $this->getMockBuilder( CentralAuthUser::class )
			->disableOriginalConstructor()
			->setMethods( $methods )
			->getMock();

		$this->centralAuthUser1->expects( $this->never() )->method( $this->anythingBut( '__destruct', ...$methods ) );

		$this->centralAuthUser1->method( 'getName' )->willReturn( $user1Name );
		$this->centralAuthUser1->method( 'getGlobalEditCount' )->willReturn( 650 );
		$this->centralAuthUser1->method( 'getRegistration' )->willReturn( 365 );
		$this->centralAuthUser1->method( 'isAttached' )->willReturn( true );

		$this->user1 = $this->getMockBuilder( User::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getOption', 'getName' ] )
			->getMock();
		$this->user1->method( 'getOption' )
			->will( self::returnValueMap( [
				[ 'twl-notified', null, false ],
			] ) );
		$this->user1->method( 'getName' )->willReturn( $user1Name );

		$this->mockEntityPage1 = $this->createMock( WikiPage::class );

		// Creating second global user that will not be eligible for the Wikipedia Library
		$user2Name = 'User2';
		$this->centralAuthUser2 = $this->getMockBuilder( CentralAuthUser::class )
			->disableOriginalConstructor()
			->setMethods( $methods )
			->getMock();

		$this->centralAuthUser2->expects( $this->never() )->method( $this->anythingBut( '__destruct', ...$methods ) );

		$this->centralAuthUser2->method( 'getName' )->willReturn( $user2Name );
		$this->centralAuthUser2->method( 'getGlobalEditCount' )->willReturn( 50 );
		$this->centralAuthUser2->method( 'getRegistration' )->willReturn( 180 );
		$this->centralAuthUser2->method( 'isAttached' )->willReturn( true );

		$this->user2 = $this->getMockBuilder( User::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getOption', 'getName' ] )
			->getMock();
		$this->user2->method( 'getOption' )
			->will( self::returnValueMap( [
				[ 'twl-notified', null, false ],
			] ) );
		$this->user2->method( 'getName' )->willReturn( $user2Name );

		$this->mockEntityPage2 = $this->createMock( WikiPage::class );
	}

	/**
	 * @covers \TheWikipediaLibraryHooks::onPageSaveComplete()
	 */
	public function testOnPageSaveCompleteUserNotified() {
		$flags = EDIT_NEW;
		$prefsFactory = $this->getMockBuilder( GlobalPreferencesFactory::class )
				->disableOriginalConstructor()
				->setMethods( [ 'getGlobalPreferencesValues' ] )
				->getMock();
		$prefsFactory->method( 'getGlobalPreferencesValues' )
			->willReturn( [
				'twl-notified' => 0,
			] );

		$this->setService( 'PreferencesFactory', $prefsFactory );

		$summary = 'Test summary';

		TheWikipediaLibraryHooks::onPageSaveComplete(
			$this->mockEntityPage1,
			$this->user1,
			$summary,
			$flags,
			$this->createMock( RevisionRecord::class ),
			$this->createMock( EditResult::class )
		);

		$prefs = $prefsFactory->getGlobalPreferencesValues( $this->user1, true );

		// TODO: uncomment this assertion when working on T256297
		// $this->assertSame( $prefs['twl-notified'], 1 );
	}

	/**
	 * @covers \TheWikipediaLibraryHooks::onPageSaveComplete()
	 */
	public function testOnPageSaveCompleteUserNotNotified() {
		$flags = EDIT_NEW;
		$prefsFactory = $this->getMockBuilder( GlobalPreferencesFactory::class )
				->disableOriginalConstructor()
				->setMethods( [ 'getGlobalPreferencesValues' ] )
				->getMock();
		$prefsFactory->method( 'getGlobalPreferencesValues' )
			->willReturn( [
				'twl-notified' => 0,
			] );

		$this->setService( 'PreferencesFactory', $prefsFactory );

		$summary = 'Test summary 2';

		TheWikipediaLibraryHooks::onPageSaveComplete(
			$this->mockEntityPage2,
			$this->user2,
			$summary,
			$flags,
			$this->createMock( RevisionRecord::class ),
			$this->createMock( EditResult::class )
		);

		$prefs = $prefsFactory->getGlobalPreferencesValues( $this->user2, true );

		// TODO: uncomment this assertion when working on T256297
		// $this->assertSame( $prefs['twl-notified'], 0 );
	}

}
