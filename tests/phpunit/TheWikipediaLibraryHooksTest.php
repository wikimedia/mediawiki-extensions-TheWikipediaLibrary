<?php

use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\TheWikipediaLibrary\Hooks as TheWikipediaLibraryHooks;
use MediaWiki\Extension\TheWikipediaLibrary\PreferenceHelper;
use MediaWiki\Page\WikiPage;
use MediaWiki\User\User;

/**
 * @group TheWikipediaLibrary
 */
class TheWikipediaLibraryHooksTest extends MediaWikiIntegrationTestCase {

	private CentralAuthUser $centralAuthUser1;
	private CentralAuthUser $centralAuthUser2;
	private User $user1;
	private User $user2;
	private WikiPage $mockEntityPage1;
	private WikiPage $mockEntityPage2;

	private function newHooks() {
		$services = $this->getServiceContainer();
		return new TheWikipediaLibraryHooks(
			$services->getConfigFactory(),
			$services->getPermissionManager(),
			$services->getUserFactory()
		);
	}

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValue( 'TwlEditCount', 2 );

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
			->onlyMethods( $methods )
			->getMock();

		$this->centralAuthUser1->expects( $this->never() )->method( $this->anythingBut( '__destruct', ...$methods ) );

		$this->centralAuthUser1->method( 'getName' )->willReturn( $user1Name );
		$this->centralAuthUser1->method( 'getGlobalEditCount' )->willReturn( 2 );
		$this->centralAuthUser1->method( 'getRegistration' )->willReturn( 365 );
		$this->centralAuthUser1->method( 'isAttached' )->willReturn( true );

		$this->user1 = $this->getMockBuilder( User::class )
			->disableOriginalConstructor()
			->disableOriginalClone()
			->disableArgumentCloning()
			->disallowMockingUnknownTypes()
			->onlyMethods( [ 'getName' ] )
			->getMock();
		$this->user1->method( 'getName' )->willReturn( $user1Name );

		$this->mockEntityPage1 = $this->createMock( WikiPage::class );

		// Creating second global user that will not be eligible for the Wikipedia Library
		$user2Name = 'User2';
		$this->centralAuthUser2 = $this->getMockBuilder( CentralAuthUser::class )
			->disableOriginalConstructor()
			->onlyMethods( $methods )
			->getMock();

		$this->centralAuthUser2->expects( $this->never() )->method( $this->anythingBut( '__destruct', ...$methods ) );

		$this->centralAuthUser2->method( 'getName' )->willReturn( $user2Name );
		$this->centralAuthUser2->method( 'getGlobalEditCount' )->willReturn( 1 );
		$this->centralAuthUser2->method( 'getRegistration' )->willReturn( 180 );
		$this->centralAuthUser2->method( 'isAttached' )->willReturn( true );

		$this->user2 = $this->getMockBuilder( User::class )
			->disableOriginalConstructor()
			->disableOriginalClone()
			->disableArgumentCloning()
			->disallowMockingUnknownTypes()
			->onlyMethods( [ 'getName' ] )
			->getMock();
		$this->user2->method( 'getName' )->willReturn( $user2Name );

		$this->mockEntityPage2 = $this->createMock( WikiPage::class );
	}

	/**
	 * @covers \MediaWiki\Extension\TheWikipediaLibrary\Hooks::isTwlEligible()
	 */
	public function testIsTwlEligibleUserNotified() {
		$prefsFactory = $this->getMockBuilder( GlobalPreferencesFactory::class )
			->disableOriginalConstructor()
			->disableOriginalClone()
			->disableArgumentCloning()
			->disallowMockingUnknownTypes()
			->onlyMethods( [ 'getGlobalPreferencesValues' ] )
			->setMockClassName( 'GlobalPreferencesFactory' )
			->getMock();

		$theWikipediaLibraryHooks = $this->newHooks();
		if ( $theWikipediaLibraryHooks->isTwlEligible( $this->centralAuthUser1 ) ) {
			$prefsFactory->method( 'getGlobalPreferencesValues' )
				->willReturn( [
					'twl-notified' => 'yes',
				] );
		} else {
			$prefsFactory->method( 'getGlobalPreferencesValues' )
				->willReturn( [
					'twl-notified' => 'no',
				] );
		}

		$this->setService( 'PreferencesFactory', $prefsFactory );

		$twlNotified = PreferenceHelper::getGlobalPreference( $this->user1, 'twl-notified' );

		$this->assertSame( 'yes', $twlNotified );
	}

	/**
	 * @covers \MediaWiki\Extension\TheWikipediaLibrary\Hooks::isTwlEligible()
	 */
	public function testIsTwlEligibleUserNotNotified() {
		$prefsFactory = $this->getMockBuilder( GlobalPreferencesFactory::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getGlobalPreferencesValues' ] )
			->getMock();
		$theWikipediaLibraryHooks = $this->newHooks();
		if ( $theWikipediaLibraryHooks->isTwlEligible( $this->centralAuthUser2 ) ) {
			$prefsFactory->method( 'getGlobalPreferencesValues' )
				->willReturn( [
					'twl-notified' => 'yes',
				] );
		} else {
			$prefsFactory->method( 'getGlobalPreferencesValues' )
				->willReturn( [
					'twl-notified' => 'no',
				] );
		}

		$this->setService( 'PreferencesFactory', $prefsFactory );

		$twlNotified = PreferenceHelper::getGlobalPreference( $this->user2, 'twl-notified' );

		$this->assertSame( 'no', $twlNotified );
	}

}
