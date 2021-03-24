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
		$notifications['twl-eligible'] = [
			EchoAttributeManager::ATTR_LOCATORS => [
				'EchoUserLocator::locateEventAgent'
			],
			'canNotifyAgent' => true,
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
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageSaveComplete
	 *
	 * @note parameters include classes not available before 1.35, so for those typehints
	 * are not used. The variable name reflects the class
	 *
	 * @param WikiPage $wikiPage
	 * @param mixed $userIdentity
	 * @param string $summary
	 * @param int $flags
	 * @param mixed $revisionRecord
	 * @param mixed $editResult
	 */
	public static function onPageSaveComplete(
		WikiPage $wikiPage,
		$userIdentity,
		string $summary,
		int $flags,
		$revisionRecord,
		$editResult
	) {
		global $wgTwlSendNotifications;
		if ( $wgTwlSendNotifications ) {
			$user = User::newFromIdentity( $userIdentity );
			self::maybeSendNotification( $user );
		}
	}

	/**
	 * Shared implementation for PageContentSaveComplete and PageSaveComplete
	 *
	 * @param User $user
	 */
	private static function maybeSendNotification( User $user ) {
		// Send a notification if the user has at least $wgTwlEditCount edits and their account
		// is at least $wgTwlRegistrationDays days old
		DeferredUpdates::addCallableUpdate( function () use ( $user ) {
			global $wgTwlEditCount, $wgTwlRegistrationDays;

			// If we've already notified this user, don't notify them again
			if ( $user->getOption( 'twl-notified' ) ) {
				return;
			}

			$globalUser = CentralAuthUser::getInstance( $user );
			if ( !$globalUser->isAttached() ) {
				return;
			}
			$accountAge = (int)wfTimestamp( TS_UNIX ) -
				(int)wfTimestamp( TS_UNIX, $globalUser->getRegistration() );
			$minimumAge = $wgTwlRegistrationDays * 24 * 3600;
			if ( $globalUser->getGlobalEditCount() >= $wgTwlEditCount && $accountAge >= $minimumAge ) {
				EchoEvent::create( [
					'type' => 'twl-eligible',
					'agent' => $user,
				] );

				// Set the twl-notified preference globally, so we'll know not to notify this user again
				$prefsFactory = MediaWikiServices::getInstance()->getPreferencesFactory();
				'@phan-var \GlobalPreferences\GlobalPreferencesFactory $prefsFactory';
				$prefs = $prefsFactory->getGlobalPreferencesValues( $user, true );
				$prefs['twl-notified'] = 1;
				$prefsFactory->setGlobalPreferences( $user, $prefs, RequestContext::getMain() );
			}
		} );
	}
}
