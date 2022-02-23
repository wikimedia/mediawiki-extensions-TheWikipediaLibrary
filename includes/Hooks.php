<?php

namespace MediaWiki\Extension\TheWikipediaLibrary;

use DeferredUpdates;
use EchoAttributeManager;
use ExtensionRegistry;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\UserIdentity;
use Title;
use User;
use WikiPage;

/**
 * TheWikipediaLibrary extension hooks
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */
class Hooks {

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
			'category' => 'system-noemail',
			'group' => 'positive',
			'section' => 'message',
			'presentation-model' => TwlEligiblePresentationModel::class,
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
		// Need CentralAuth extension.
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) ) {
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
		// Wrap in a POSTSEND deferred update to avoid blocking the HTTP response
		DeferredUpdates::addCallableUpdate( function () use ( $userIdentity, $title ) {
			$services = MediaWikiServices::getInstance();
			$user = $services->getUserFactory()->newFromUserIdentity( $userIdentity );
			// Only proceed if we're dealing with a non-system account
			if ( $user->isSystemUser() ) {
				return;
			}
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
			// Only proceed if we haven't already notified this user
			$twlNotifiedPref = PreferenceHelper::getGlobalPreference( $user, 'twl-notified' );
			if ( $twlNotifiedPref === 'yes' ) {
				return;
			}
			// Only proceed if we're dealing with an eligible account
			if ( !self::isTwlEligible( $centralAuthUser ) ) {
				return;
			}
			// Set the twl-notified preference to 'no' if we haven't notified this user
			// We've added this extra step to ensure that global preferences may be modified
			// to avoid multiple notifications in case the preference isn't saved before the next edit
			if ( $twlNotifiedPref === null ) {
				PreferenceHelper::setGlobalPreference( $user, 'twl-notified', 'no' );
				return;
			}
			// Notify the user if:
			// - they haven't been notified yet
			// - we can successfully set the preference
			if (
				$twlNotifiedPref === 'no'
				&& PreferenceHelper::setGlobalPreference( $user, 'twl-notified', 'yes' )
			) {
				EchoHelper::send( $user, $title );
			}
		} );
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

		$twlRegistrationDays = $config->get( 'TwlRegistrationDays' );
		$minimumAge = $twlRegistrationDays * 24 * 3600;
		$accountAge = (int)wfTimestamp( TS_UNIX ) -
			(int)wfTimestamp( TS_UNIX, $centralAuthUser->getRegistration() );
		if ( $accountAge < $minimumAge ) {
			return false;
		}

		$twlEditCount = $config->get( 'TwlEditCount' );
		$globalEditCount = $centralAuthUser->getGlobalEditCount();

		return $globalEditCount >= $twlEditCount;
	}
}
