<?php
namespace Group\Repository\Orm;

use App\Models\MModel;
use App\Models\User\Interfaces\SystemUser;

class KktProductGroupPlanLog extends MModel{
	public $timestamps = false;
	protected $table = 'kkt_product_group_plan_log';
	protected $fillable = [
	    'created_user_id', 'plan_id', 'content', 'created','type'
	];
	protected $connection = "mysql_platform";
	/**
	 * 定义外键
	 */
	public function planData(){
		return $this->belongsTo('App\Models\Group\Orm\Group', 'plan_id');
	}
	
	/**
	 * 团期列表数据
	 * @param int $page 页码
	 * @param int $limit 每页显示条数
	 * @param int $planId 团期计划id
	 */
	public function logList($page, $limit, $planId){
		$reuslt = array('page'=>$page, 'limit'=>$limit, 'count'=>0, 'data'=>array());
		$model = $this->where('plan_id', $planId);
		$countModel = clone $model;
		$reuslt['count'] = $countModel->count();
		if($reuslt['count'] == 0){
			return $reuslt;
		}
		$dataModel = $model->skip(($page-1)*$limit)->take($limit)
					 ->orderBy('id', 'asc')
					 ->get();
		$number = ($page-1)*$limit+1;
		foreach($dataModel as $k=>$obj){
			$result['data'][$k] = array_only($obj->toArray(), ['id','content']);
			$userInfo = $this->getSystemUserObj()->getUserInfo($obj->created_user_id);
			$result['data'][$k]['op_user_id'] = $userInfo['realname'];
			$result['data'][$k]['operate_time'] = date('Y-m-d H:i:s', $obj->created);
			$result['data'][$k]['number'] = $number++;
		}
		return $result;
	}
	
	private function getSystemUserObj(){
		return new SystemUser();
	}
}
