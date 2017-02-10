<?php

namespace Craft;

class W8Plugin extends BasePlugin
{

	static $weightHandle;
	static $after;

	public function getName ()
	{
		return 'W8';
	}

	public function getDescription ()
	{
		return 'Sort any elements by their weight';
	}

	public function getVersion ()
	{
		return '0.0.1';
	}

	public function getSchemaVersion ()
	{
		return '0.0.1';
	}

	public function getDeveloper ()
	{
		return 'Ether Creative';
	}

	public function getDeveloperUrl ()
	{
		return 'https://ethercreative.co.uk';
	}

	public function init ()
	{
		// TODO: Inject into commerce product edit, entry popup edit & product popup edit
		craft()->templates->hook('cp.entries.edit.right-pane', function(&$context) {
			/** @var EntryModel $entry **/
			$entry = $context['entry'];

			$weight = craft()->w8->getWeightById($entry->id);

			return "<div class='pane meta'>
	<div class='field' id='element-w8-field'>
		<div class='heading'>
			<label id='element-w8-label' for='element-w8'>Weight</label>
		</div>
		<div class='input ltr'>
			<input id='element-w8' type='number' min='0' max='100' value='{$weight}' 
				   class='text fullwidth' name='w8'>
		</div>
	</div>
</div>";
		});

		craft()->on('elements.saveElement', function (Event $event) {
			/** @var BaseElementModel $element */
			$element = $event->params['element'];

			if (!in_array(
				$element->getElementType(),
				['Entry', 'Category', 'Commerce_Product']
			))
				return;

			if ($element->getElementType() == 'Category') {
				craft()->w8->saveCategory($element);
				return;
			}

			if (!array_key_exists('w8', $_POST))
				return;

			craft()->w8->save($element->id, $_POST['w8']);
		});

		craft()->on('structures.moveElement', function (Event $event) {
			/** @var BaseElementModel|CategoryModel $element */
			$element = $event->params['element'];

			if ($element->getElementType() == 'Category')
				craft()->w8->saveCategory($element);
		});

		craft()->on('elements.beforeBuildElementsQuery', function (Event $event) {
			/** @var ElementCriteriaModel $criteria */
			$criteria = $event->params['criteria'];

			$order = $criteria->getAttribute('order');

			if (strtolower(mb_substr($order, 0, 2)) !== 'w8')
				return;

			list($old, $afterHandle) = explode(' ', $order . ' ', 2);

			if (mb_substr($old, -1) == ',') {
				$old = rtrim($old, ',');
				$afterHandle = ' desc,' . $afterHandle;
			}

			static::$weightHandle = bin2hex($old);
			static::$after = $afterHandle;

			$criteria->setAttribute(
				'order',
				trim(static::$weightHandle . ' ' . $afterHandle)
			);
		});

		craft()->on('elements.buildElementsQuery', function (Event $event) {

			$tablePrefix = craft()->db->getNormalizedTablePrefix();
			$table = $tablePrefix . W8Record::TABLE_NAME;

			/** @var ElementCriteriaModel $criteria */
			$criteria = $event->params['criteria'];
			/** @var DbCommand $query */
			$query = $event->params['query'];

			$order = $criteria->getAttribute('order');

			if ($order !== trim(static::$weightHandle . ' ' . static::$after))
				return;

			$weightHandle = static::$weightHandle;

			$fields = explode('|', hex2bin(static::$weightHandle));

			if (count($fields) == 1)
				$fields = ['self'];

			$allFields = craft()->fields->getAllFields('handle');

			$fieldsByHandle = [];

			foreach ($fields as $field)
			{
				list($name, $depth) = explode(':', $field . ':');

				if (empty($name) || strtolower($name) === 'w8')
					continue;

				if (!$depth)
					$depth = '<=999';

				if ($name == 'self' || array_key_exists($name, $allFields))
					$fieldsByHandle[$name] = compact('name', 'depth');
			}

			$selects = [];
			$joins = [];

			$hasSelf = array_key_exists('self', $fieldsByHandle);

			if ($hasSelf)
				unset($fieldsByHandle['self']);

			$fieldsCount = count($fieldsByHandle);

			$fieldIds = [];
			foreach ($fieldsByHandle as $field) if (array_key_exists($field['name'], $allFields)) {
				$fieldIds[] = $allFields[$field['name']]->id;
			}
			$fieldIds = join(', ', $fieldIds);


			if ($hasSelf)
				$selects[] = "(COALESCE({$table}_a.weight, 0) * 5)";

			if ($fieldsCount) {
				$source = $hasSelf ? 'elementId' : 'id';

				$i = 0;
				foreach ($fieldsByHandle as $field) {
					$selects[] = "COALESCE(SUM({$table}_{$i}.weight), 0)";
					$joins[] = "LEFT JOIN `{$table}` `{$table}_{$i}` 
	ON {$table}_{$i}.elementId 
		IN (SELECT targetId FROM `craft_relations` WHERE fieldId IN ({$fieldIds}) AND sourceId = {$table}_a.{$source} AND sortOrder {$field['depth']})
		AND {$table}_{$i}.depth {$field['depth']}";
					// NOTE: By setting sortOrder equal to whatever depth is we
					// should, hypothetically, only get the first category of
					// the specified depth
					// TODO[improve]: find a way to give matching categories higher priority
					// e.g. If an entry has CatA & CatB, and we are sorting
					// CatB then CatA, CatA on the entry should have a lower weight
					$i++;
				}
			}

			$selects = join(' + ', $selects);
			$joins = join("\r\n", $joins);

			if ($hasSelf && $fieldsCount == 0) {
				$select = "SELECT {$selects} 
FROM `{$table}` `{$table}_a` 
WHERE {$table}_a.elementId = elements.id";
			} else if ($hasSelf && $fieldsCount > 0) {
				$select = "SELECT {$selects} 
FROM `{$table}` `{$table}_a`
{$joins}
WHERE {$table}_a.elementId = elements.id";
			} else {
				$select = "SELECT {$selects} 
FROM `{$tablePrefix}elements` `{$table}_a`
{$joins}
WHERE {$table}_a.id = elements.id";
			}

			$finalSelect = "\r\n({$select}) as {$weightHandle}";
//			static::log(print_r($finalSelect, true));
			$query->addSelect($finalSelect);
		});
	}

}