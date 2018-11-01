<?php

use MediaWiki\MediaWikiServices;

/**
 * TheWikipediaLibrary extension hooks
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */
class TheWikipediaLibraryHooks {

	/**
	 * Add The Wikipedia Library - eligibility events to Echo
	 *
	 * @param array &$notifications array of Echo notifications
	 * @param array &$notificationCategories array of Echo notification categories
	 * @param array &$icons array of icon details
	 * @return bool
	 */
	public static function onBeforeCreateEchoEvent(
		&$notifications, &$notificationCategories, &$icons
	) {
		$notificationCategories['system'] = [
			'priority' => 9
		];

		$notifications['twl-eligible'] = [
			EchoAttributeManager::ATTR_LOCATORS => [
				'EchoUserLocator::locateEventAgent'
			],
			'category' => 'system',
			'group' => 'positive',
			'section' => 'message',
			'presentation-model' => 'TwlEligiblePresentationModel'
		];

		$icons['twl-eligible'] = [
			'path' => 'TheWikipediaLibrary/modules/icons/twl-eligible.svg'
		];

		return true;
	}

	/**
	 * Add API preference tracking whether the user has been notified already.
	 * @param User $user
	 * @param array &$preferences
	 */
	public static function onGetPreferences( User $user, array &$preferences ) {
		$preferences['twl-notified'] = [
			'type' => 'api'
		];
	}

	/**
	 * Send a Wikipedia Library notification if the user has reached 6 months and 500 edits.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageContentSaveComplete
	 *
	 * @param WikiPage &$wikiPage
	 * @param User &$user
	 * @param Content $content
	 * @param string $summary
	 * @param bool $isMinor
	 * @param bool $watch
	 * @param string $section
	 * @param int &$flags
	 * @param Revision $revision
	 * @param Status &$status
	 * @param int $baseRevId
	 * @param int $undidRevId
	 */
	public static function onPageContentSaveComplete(
			WikiPage &$wikiPage, User &$user, Content $content, $summary, $isMinor, $watch, $section,
			&$flags, Revision $revision, Status &$status, $baseRevId, $undidRevId
	) {
		global $wgTwlSendNotifications;
		$title = $wikiPage->getTitle();

		if ( $wgTwlSendNotifications ) {
			// Send a notification if the user has at least $wgTwlEditCount edits and their account
			// is at least $wgTwlRegistrationDays days old
			DeferredUpdates::addCallableUpdate( function () use ( $user, $title ) {
				global $wgTwlEditCount, $wgTwlRegistrationDays;

				// If we've already notified this user, don't notify them again
				if ( $user->getOption( 'twl-notified' ) ) {
					return;
				}

				$globalUser = CentralAuthUser::getInstance( $user );
				if ( !$globalUser->isAttached() ) {
					return;
				}
				$accountAge = wfTimestampNow( TS_UNIX ) -
					wfTimestamp( TS_UNIX, $globalUser->getRegistration() );
				$minimumAge = $wgTwlRegistrationDays * 24 * 3600;
				if ( $globalUser->getGlobalEditCount() >= $wgTwlEditCount && $accountAge >= $minimumAge ) {
					EchoEvent::create( [
						'type' => 'twl-eligible',
						'agent' => $user,
						'extra' => [
							'notifyAgent' => true,
						]
					] );

					// Set the twl-notified preference globally, so we'll know not to notify this user again
					$prefsFactory = MediaWikiServices::getInstance()->getPreferencesFactory();
					$prefsFactory->setUser( $user );
					$prefs = $prefsFactory->getGlobalPreferencesValues( true );
					$prefs['twl-notified'] = 1;
					$prefsFactory->setGlobalPreferences( $prefs, RequestContext::getMain() );
				}
			} );
		}
	}
}
