<?php

namespace Craft;

class W8Service extends BaseApplicationComponent
{

	/**
	 * @param int $elementId
	 *
	 * @return int
	 */
	public function getWeightById ($elementId)
	{
		$attributes = [
			'elementId' => $elementId,
		];

		$record = W8Record::model()->findByAttributes($attributes);

		if (!$record)
			return 0;

		return $record->weight;
	}

	/**
	 * Saves a category relative to its siblings
	 *
	 * TODO[improve]: This means we will overwrite any custom weighting
	 *
	 * @param CategoryModel $category
	 */
	public function saveCategory (CategoryModel $category)
	{
		if ($category->getParent()) {
			$relativeCategories = $category->getParent()->getChildren()->find();
		} else {
			$criteria = craft()->elements->getCriteria(ElementType::Category);
			$criteria->groupId = $category->groupId;
			$criteria->level = 1;
			$criteria->limit = null;
			$relativeCategories = $criteria->find();
		}

		$total = count($relativeCategories);
		$i = $total;

		/** @var CategoryModel $cat */
		foreach ($relativeCategories as $cat)
		{
			$weight = (($i / $total) * 100) / $cat->level;
			$this->save($cat->id, $weight, $cat->level);
			$i--;
		}
	}

	/**
	 * @param int      $elementId
	 * @param int      $weight
	 * @param null|int $depth
	 *
	 * @return bool
	 */
	public function save($elementId, $weight, $depth = null)
	{
		$attributes = [
			'elementId' => $elementId,
		];

		$record = W8Record::model()->findByAttributes($attributes);

		if (!$record)
			$record = new W8Record;

		$attributes['weight'] = (int)$weight;

		if ($depth)
			$attributes['depth'] = $depth;

		$record->setAttributes($attributes, false);

		return $record->save();
	}

}