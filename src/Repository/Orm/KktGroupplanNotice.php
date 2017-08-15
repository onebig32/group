<?php 
namespace Group\Repository\Orm;
use App\Models\MModel;

class KktGroupplanNotice extends MModel{
	protected $table = 'kkt_groupplan_notice';
	protected $fillable = [
		'plan_id', 'leader_name', 'leader_phone', 'gather_time',
		'gather_address', 'donw_name', 'path_name', 'path_group',
		'is_delete'
	];
	
	/**
	 * 定义与团期计划的外键
	 */
	public function getPlan(){
		return $this->belongsTo('App\Models\Group\Orm\KktProductGroupPlan', 'plan_id');
	}
}