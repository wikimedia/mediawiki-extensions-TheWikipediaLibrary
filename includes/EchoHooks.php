<?php

namespace MediaWiki\Extension\TheWikipediaLibrary;

use MediaWiki\Extension\Notifications\AttributeManager;
use MediaWiki\Extension\Notifications\Hooks\BeforeCreateEchoEventHook;
use MediaWiki\Extension\Notifications\UserLocator;

/**
 * TheWikipediaLibrary extension hooks
 * All hooks from the Echo extension which is optional to use with this extension.
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */
class EchoHooks implements
	BeforeCreateEchoEventHook
{
	/**
	 * Add The Wikipedia Library - eligibility events to Echo
	 *
	 * @param array &$notifications array of Echo notifications
	 * @param array &$notificationCategories array of Echo notification categories
	 * @param array &$icons array of icon details
	 */
	public function onBeforeCreateEchoEvent(
		array &$notifications, array &$notificationCategories, array &$icons
	): void {
		$notifications['twl-eligible'] = [
			AttributeManager::ATTR_LOCATORS => [
				[ [ UserLocator::class, 'locateEventAgent' ] ],
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
}
