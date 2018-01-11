<?php
/**
 * Created by PhpStorm.
 * User: lex
 * Date: 2018/1/9
 * Time: 下午12:13
 */
namespace Lex\Mongotree;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Kalnoy\Nestedset\QueryBuilder as Builder;
use LogicException;

class QueryBuilder extends Builder {

	public function makeGap($cut, $height)
	{
		$params = compact('cut', 'height');

		return $this->updateNodes($params);

	}

	public function moveNode($key, $position)
	{
		list($lft, $rgt) = $this->model->newNestedSetQuery()
			->getPlainNodeData($key, true);

		if ($lft < $position && $position <= $rgt) {
			throw new LogicException('Cannot move node into itself.');
		}

		// Get boundaries of nodes that should be moved to new position
		$from = min($lft, $position);
		$to = max($rgt, $position - 1);

		// The height of node that is being moved
		$height = $rgt - $lft + 1;

		// The distance that our node will travel to reach it's destination
		$distance = $to - $from + 1 - $height;

		// If no distance to travel, just return
		if ($distance === 0) {
			return 0;
		}

		if ($position > $lft) {
			$height *= -1;
		} else {
			$distance *= -1;
		}

		$params = compact('lft', 'rgt', 'from', 'to', 'height', 'distance');

		return $this->updateNodes($params);
	}


	protected function updateNodes($params) {
		$updates = [];

		foreach ([ $this->model->getLftName(), $this->model->getRgtName() ] as $col) {
			$updates[$col] = $this->columnPatch($col, $params);
		}

		$updated = false;
		foreach ($updates as $col=>$ops) {
			foreach ($ops as $upd) {
				$query = $this->model->newNestedSetQuery();
				$change = $upd['op'];
				$op = $change > 0 ? 'increment' : 'decrement';
				if (isset($upd['ids'])) {
					$updated = $query->whereIn($this->model->getKeyName(), $upd['ids'])->$op($col, abs($change));
				}
				if (isset($upd['cut'])) {
					$updated = $query->where($col, '>=', $upd['cut'])->$op($col, abs($change));
				}
			}
		}

		return $updated;
	}

	protected function columnPatch($col, array $params)
	{
		extract($params);

		/** @var int $height */
		if ($height > 0) $height = '+'.$height;

		if (isset($cut)) {
			return [
				[
					'cut'=>$cut,
					'op'=>$height,
				]
			];
		}

		/** @var int $distance */
		/** @var int $lft */
		/** @var int $rgt */
		/** @var int $from */
		/** @var int $to */
		if ($distance > 0) $distance = '+'.$distance;

		if ($from == $lft) {
			$otherRangeFrom = $rgt + 1;
			$otherRangeTo = $to;
		}
		else if ($to == $rgt) {
			$otherRangeFrom = $from;
			$otherRangeTo = $lft - 1;
		} else {
			throw new LogicException('Failed for logic error.');
		}

		$keyName = $this->model->getKeyName();

		$query = $this->model->newNestedSetQuery();
		$idsOfSelf = $query->whereBetween($col, [$lft, $rgt])->get([$keyName]);
		$query2 = $this->model->newNestedSetQuery();
		$idsOfOther = $query2->whereBetween($col, [$otherRangeFrom, $otherRangeTo])->get([$keyName]);

		$idsOfSelf = $idsOfSelf->pluck($keyName)->all();
		$idsOfOther = $idsOfOther->pluck($keyName)->all();

		return [
			[
				'op'=>$distance,
				'ids'=>$idsOfSelf,
			],
			[
				'op'=>$height,
				'ids'=>$idsOfOther,
			]


		];
	}

	public function getNodeData($id, $required = false)
	{
		$query = $this->toBase();

		$query->where($this->model->getKeyName(), '=', $id);

		$data = $query->first([ $this->model->getLftName(),
			$this->model->getRgtName() ]);

		if ( ! $data && $required) {
			throw new ModelNotFoundException;
		}

		unset($data[$this->model->getKeyName()]);

		return (array)$data;
	}

}