<?php
namespace Group\Repository\Orm;

use App\Models\MModel;

class KktShelfDetail extends MModel{

	public $timestamps = true;
	protected $table = 'kkt_shelf_detail';
	protected $fillable = [
			'shelf_id','plan_id','take_stock','is_delete','created_at','updated_at'
	];
	protected $connection = "mysql_platform";
	/**
	 * 获取货架明细列表
	 * @param array $wh 查询条件
	 *  array(
	 *  'shelfId' => '费用名称',
	 *  'planId' => '团期计划iD',
	 *  'noDefault' => '非默认货架',
	 *  )
	 *  所有查询条件都非必填
	 * @param  $pages 分页信息
	 */
	public function getList($wh = array(), $pages = array())
	{
		$result = array('page' => 1, 'limit' => 0, 'count' => 0, 'data' => array());
		if (empty($wh) && empty($pages)) {
			return $result;
		}
		$model = $this->where('is_delete', 1);
		if (isset($wh['shelfId']) && $wh['shelfId']) {
			$model = $model->where('shelf_id',$wh['shelfId']);
		}
		if (isset($wh['planId']) && $wh['planId']) {
			$model = $model->where('plan_id', $wh['planId']);
		}
		if (isset($wh['noDefault']) && $wh['noDefault']) {
			$model = $model->where('shelf_id', '<>',1);
		}
		if (!empty($pages)) {
			$countModel = clone $model;
			$result['count'] = $countModel->count();
			$result['limit'] = $pages['limit'];
			$result['page'] = $pages['page'];
			$model = $model->skip(($pages['page'] - 1) * $pages['limit'])->take($pages['limit']);
		}
		$result['data'] = $model->get();
		if ($result['count'] == 0) {
			$result['count'] = count($result['data']);
		}
		return $result;
	}
}
