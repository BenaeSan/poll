<?php

namespace ColdTrick\Poll;

class MenuHandler {
	
	/**
	 * Add a menu item to the site menu
	 *
	 * @param string          $hook         the name of the hook
	 * @param string          $type         the type of the hook
	 * @param \ElggMenuItem[] $return_value current return value
	 * @param array           $params       supplied params
	 *
	 * @return void|\ElggMenuItem[]
	 */
	public static function siteMenu($hook, $type, $return_value, $params) {
		
		$return_value[] = \ElggMenuItem::factory([
			'name' => 'poll',
			'text' => elgg_echo('poll:menu:site'),
			'href' => 'poll/all',
		]);
		
		return $return_value;
	}
	
	/**
	 * Add a menu item to user owner block menu
	 *
	 * @param string          $hook         the name of the hook
	 * @param string          $type         the type of the hook
	 * @param \ElggMenuItem[] $return_value current return value
	 * @param array           $params       supplied params
	 *
	 * @return void|\ElggMenuItem[]
	 */
	public static function userOwnerBlock($hook, $type, $return_value, $params) {
		
		if (empty($params) || !is_array($params)) {
			return;
		}
		
		$entity = elgg_extract('entity', $params);
		if (!($entity instanceof \ElggUser)) {
			return;
		}
		
		$return_value[] = \ElggMenuItem::factory([
			'name' => 'poll',
			'text' => elgg_echo('poll:menu:site'),
			'href' => "poll/owner/{$entity->username}",
		]);
		
		return $return_value;
	}
	
	/**
	 * Add a menu item to group owner block menu
	 *
	 * @param string          $hook         the name of the hook
	 * @param string          $type         the type of the hook
	 * @param \ElggMenuItem[] $return_value current return value
	 * @param array           $params       supplied params
	 *
	 * @return void|\ElggMenuItem[]
	 */
	public static function groupOwnerBlock($hook, $type, $return_value, $params) {
		
		if (empty($params) || !is_array($params)) {
			return;
		}
		
		$entity = elgg_extract('entity', $params);
		if (!($entity instanceof \ElggGroup)) {
			return;
		}
		
		if (!poll_is_enabled_for_group($entity)) {
			return;
		}
		
		$return_value[] = \ElggMenuItem::factory([
			'name' => 'poll',
			'text' => elgg_echo('poll:menu:owner_block:group'),
			'href' => "poll/group/{$entity->getGUID()}/all",
		]);
		
		return $return_value;
	}
}