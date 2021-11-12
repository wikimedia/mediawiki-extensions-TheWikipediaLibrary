<?php
/**
 * User preference helper methods adapted from ContentTranslation extension.
 *
 * @copyright See https://raw.githubusercontent.com/wikimedia/mediawiki-extensions-ContentTranslation/736585619e98883f0907e7eb208a06d456f04c77/AUTHORS.txt
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\TheWikipediaLibrary;

use GlobalPreferences\GlobalPreferencesFactory;
use GlobalPreferences\Storage;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use RequestContext;
use User;

class PreferenceHelper {

	/**
	 * Set a global preference for the user.
	 * @param User $user
	 * @param string $preference
	 * @param string $value
	 * @return bool
	 */
	public static function setGlobalPreference( User $user, string $preference, string $value ) {
		/** @var GlobalPreferencesFactory $globalPref */
		$globalPref = MediaWikiServices::getInstance()->getPreferencesFactory();
		if ( !$globalPref instanceof GlobalPreferencesFactory ) {
			// Need GlobalPreferences extension.
			LoggerFactory::getInstance( 'TheWikipediaLibrary' )->warning(
				'Not setting user preference, GlobalPreferences is not available'
			);
			return false;
		}
		'@phan-var GlobalPreferencesFactory $globalPref';
		$prefs = $globalPref->getGlobalPreferencesValues( $user, Storage::SKIP_CACHE );
		$prefs[$preference] = $value;
		$user = $user->getInstanceForUpdate();
		return $globalPref->setGlobalPreferences( $user, $prefs, RequestContext::getMain() );
	}

	/**
	 * Get a global preference for the user.
	 * @param User $user
	 * @param string $preference
	 * @return string|null Preference value
	 */
	public static function getGlobalPreference( User $user, string $preference ) {
		/** @var GlobalPreferencesFactory $globalPref */
		$globalPref = MediaWikiServices::getInstance()->getPreferencesFactory();
		if ( !$globalPref instanceof GlobalPreferencesFactory ) {
			// Need GlobalPreferences extension.
			LoggerFactory::getInstance( 'TheWikipediaLibrary' )->warning(
				'Not getting user preference, GlobalPreferences is not available'
			);
			return null;
		}
		'@phan-var GlobalPreferencesFactory $globalPref';
		$prefs = $globalPref->getGlobalPreferencesValues( $user, Storage::SKIP_CACHE );
		return $prefs[$preference] ?? null;
	}
}
