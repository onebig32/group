<?php
namespace Group\Repository\Orm;
use App\Models\MModel;

class KktPlanShelvesRelation extends MModel{
	protected $table = 'kkt_plan_shelves_relation';
	protected $connection = "mysql_platform";
	public function exist(){
		if($this->exists && $this->is_delete==1){
			return true;
		}
		return false;
	}
}
