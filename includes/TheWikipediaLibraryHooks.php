<?php
use MediaWiki\Extension\TheWikipediaLibrary\PreferenceHelper;
use MediaWiki\Logger\LoggerFactory;

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
	 */
	public static function onBeforeCreateEchoEvent(
		array &$notifications, array &$notificationCategories, array &$icons
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
	 * Send a Wikipedia Library notification if the user has reached the required age and editcount.
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
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) ) {
			// Need CentralAuth extension.
			LoggerFactory::getInstance( 'TheWikipediaLibrary' )->warning(
				'Not checking eligibility, CentralAuth is not available'
			);
			return;
		}

		global $wgTwlSendNotifications;
		if ( $wgTwlSendNotifications ) {
			$user = User::newFromIdentity( $userIdentity );
			self::maybeSendNotification( $user );
		}
	}

	/**
	 * Determine Twl Eligibility
	 *
	 * @param CentralAuthUser $centralAuthUser
	 * @return bool
	 *
	 * @note CentralAuthUser class mock in tests doesn't work with typehints,
	 * so that typehint is not used. The variable name reflects the class.
	 */
	public static function isTwlEligible( CentralAuthUser $centralAuthUser ): bool {
		global $wgTwlEditCount, $wgTwlRegistrationDays;

		// Check eligibility
		$accountAge = (int)wfTimestamp( TS_UNIX ) -
			(int)wfTimestamp( TS_UNIX, $centralAuthUser->getRegistration() );
		$minimumAge = $wgTwlRegistrationDays * 24 * 3600;

		if ( $centralAuthUser->getGlobalEditCount() >= $wgTwlEditCount && $accountAge >= $minimumAge ) {
			return true;
		} else {
			return false;
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
			// Only proceed if we're dealing with an SUL account
			$globalUser = CentralAuthUser::getInstance( $user );
			if ( !$globalUser->isAttached() ) {
				return;
			}

			// Only proceed if we haven't already notified this user
			$twlNotified = PreferenceHelper::getGlobalPreference( $user, 'twl-notified' );
			if ( $twlNotified === 'yes' ) {
				return;
			// Set the twl-notified preference to 'no' if we haven't notified this user
			// We've added this extra step to ensure that global preferences may be modified
			// to avoid multiple notifications in case the preference isn't saved for some reason
			// This situation occurred in testing on beta
			} elseif ( $twlNotified === null ) {
				PreferenceHelper::setGlobalPreference( $user, 'twl-notified', 'no' );
				// Read the value back from global preferences to ensure it's available
				$twlNotified = PreferenceHelper::getGlobalPreference( $user, 'twl-notified' );
			}

			// Notify the user if they are eligible and haven't been notified yet
			if ( $twlNotified === 'no' && self::isTwlEligible( $globalUser ) ) {
				EchoEvent::create( [
					'type' => 'twl-eligible',
					'agent' => $user,
				] );

				// Set the twl-notified preference globally, so we'll know not to notify this user again
				PreferenceHelper::setGlobalPreference( $user, 'twl-notified', 'yes' );
			}
		} );
	}
}
