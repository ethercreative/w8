<?php

namespace Craft;

class W8Record extends BaseRecord
{

	const TABLE_NAME = 'w8';

	public function getTableName ()
	{
		return static::TABLE_NAME;
	}

	public function defineRelations ()
	{
		return [
			'element' => [
				static::BELONGS_TO,
				'ElementRecord',
				'required' => true,
				'onDelete' => static::CASCADE,
			],
		];
	}

	public function defineAttributes ()
	{
		return [
			'weight' => [
				'column'   => ColumnType::TinyInt,
				'required' => true,
				'unsigned' => true,
				'min'      => 0,
				'max'      => 100,
				'decimals' => 0,
				'default'  => 0,
			],
			'depth'  => [AttributeType::Number],
		];
	}

	public function defineIndexes ()
	{
		return [
			['columns' => ['elementId']],
		];
	}

}