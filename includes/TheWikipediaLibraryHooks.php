<?php

use MediaWiki\Extension\TheWikipediaLibrary\EchoHelper;
use MediaWiki\Extension\TheWikipediaLibrary\PreferenceHelper;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\UserIdentity;

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
	 * @param UserIdentity $userIdentity
	 * @param string $summary
	 * @param int $flags
	 * @param RevisionRecord $revisionRecord
	 * @param EditResult $editResult
	 */
	public static function onPageSaveComplete(
		WikiPage $wikiPage,
		UserIdentity $userIdentity,
		string $summary,
		int $flags,
		RevisionRecord $revisionRecord,
		EditResult $editResult
	) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) ) {
			// Need CentralAuth extension.
			LoggerFactory::getInstance( 'TheWikipediaLibrary' )->warning(
				'Not checking eligibility, CentralAuth is not available'
			);
			return;
		}
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'TheWikipediaLibrary' );
		if ( $config->get( 'TwlSendNotifications' ) ) {
			$title = $wikiPage->getTitle();
			self::maybeSendNotification( $userIdentity, $title );
		}
	}

	/**
	 * Decide whether to notify the user based on central auth eligibility and global preference state
	 *
	 * @param UserIdentity $userIdentity
	 * @param Title $title
	 */
	private static function maybeSendNotification( UserIdentity $userIdentity, Title $title ) {
		$user = User::newFromIdentity( $userIdentity );
		$services = MediaWikiServices::getInstance();
		$pm = $services->getPermissionManager();
		// Only proceed if we're dealing with a non-bot account
		if ( $pm->userHasRight( $user, 'bot' ) ) {
			return;
		}
		// Only proceed if we're dealing with an SUL account
		$centralAuthUser = CentralAuthUser::getInstance( $user );
		if ( !$centralAuthUser->isAttached() ) {
			return;
		}
		// Only proceed if we're dealing with an eligible account
		if ( !self::isTwlEligible( $centralAuthUser ) ) {
			return;
		}
		// Only proceed if we haven't already notified this user
		// First check the global preference.
		$twlNotifiedPref = PreferenceHelper::getGlobalPreference( $user, 'twl-notified' );
		if ( $twlNotifiedPref === 'yes' ) {
			return;
			// Set the twl-notified preference to 'no' if we haven't notified this user
			// We've added this extra step to ensure that global preferences may be modified
			// to avoid multiple notifications in case the preference isn't saved before the next edit
		} elseif ( $twlNotifiedPref === null ) {
			PreferenceHelper::setGlobalPreference( $user, 'twl-notified', 'no' );
			return;
			// Notify the user if:
			// - they haven't been notified yet
			// - we can sucessfully set the preference
		} elseif (
			$twlNotifiedPref === 'no'
			&& PreferenceHelper::setGlobalPreference( $user, 'twl-notified', 'yes' )
		) {
			EchoHelper::send( $user, $title );
		}
	}

	/**
	 * Determine Twl Eligibility
	 *
	 * @param CentralAuthUser $centralAuthUser
	 * @return bool
	 *
	 */
	public static function isTwlEligible( CentralAuthUser $centralAuthUser ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'TheWikipediaLibrary' );
		$twlEditCount = $config->get( 'TwlEditCount' );
		$twlRegistrationDays = $config->get( 'TwlRegistrationDays' );
		$accountAge = (int)wfTimestamp( TS_UNIX ) -
			(int)wfTimestamp( TS_UNIX, $centralAuthUser->getRegistration() );
		$minimumAge = $twlRegistrationDays * 24 * 3600;
		$globalEditCount = $centralAuthUser->getGlobalEditCount();
		// Check eligibility
		if ( $globalEditCount >= $twlEditCount && $accountAge >= $minimumAge ) {
			return true;
		} else {
			return false;
		}
	}
}
