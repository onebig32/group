<?php 
namespace Group\Repository\Orm;
use App\Models\MModel;

class KktProductGroup extends MModel{
	public $timestamps = false;
	protected $table = 'kkt_product_group';
	protected $fillable = [
		'plan_id', 'product_id', 'tour_id',
		'adult_whole_sale', 'adult_retail_sale', 'child_whole_sale',
		'child_retail_sale', 'prepay_money', 'created',
		'updated', 'is_delete','uuid'
	];
	protected $connection = "mysql_platform";
	/**
	 * 定义关于行程方案的外键
	 */
	public function getTour(){
		return $this->belongsTo('Group\Repository\Orm\KktProductTour', 'tour_id');
	}

    /**
     * 定义关于团期计划的外键
     */
	public function getGroupPlan(){
		return $this->belongsTo('Group\Repository\KktProductGroupPlan', 'plan_id', 'id');
	}
	
	//定义与团期表一对一关系
	public function getPlan(){
	    return $this->hasOne('Group\Repository\KktProductGroupPlan', 'id', 'plan_id');
	}
	//定义与产品表一对一关系
	public function getProduct(){
	    return $this->hasOne('App\Models\Product\Orm\KktProduct', 'id', 'product_id');
	}
	
	public function exist(){
		if($this->exists && $this->is_delete==1){
			return true;
		}else{
			return false;
		}
	}

	/**
	 * 获取团期模型数据列表
	 * @param array $wh 查询条件
	 *  array(
	 *    'idsArr' => '数组：团期id',
	 *    'notDelete' => 存在则取出所有数据（包括软删除）
	 *  )
	 *  所有查询条件都非必填
	 * @param array $pages 分页，未空数组则返回全部数据
	 *  array('page'=>'当前页','limit'=>'每页限制')
	 */
	public function getModelList($wh = [], $pages = [])
	{
		$result = ['page' => 1, 'limit' => 0, 'count' => 0, 'data' => []];
		if(isset($wh['notDelete']) && $wh['notDelete']){
			$model = $this;
		}else{
			$model = $this->where('is_delete', 1);
		}
		if (isset($wh['idsArr']) && is_array($wh['idsArr']) && !empty($wh['idsArr'])) {
			$model = $model->whereIn('id', $wh['idsArr']);
		}
		if (!empty($pages)) {
			$countModel = clone $model;
			$result['count'] = $countModel->count();
			$result['limit'] = $pages['limit'];
			$result['page'] = $pages['page'];
			$model = $model->skip(($pages['page'] - 1) * $pages['limit'])->take($pages['limit']);
		}
		$result['data'] = $model->orderBy('id', 'desc')->get();
		if ($result['count'] == 0) {
			$result['count'] = count($result['data']);
		}
		return $result;
	}

    /**
     * 根据团期计划id获取所有团期id
     * @param $planId	团期计划id
     */
    public function getGroupForPlan($planId){
        $model=$this->select('id')->where('plan_id',$planId)->where('is_delete',1);
        $data = $model->get()->toArray();
        $result = array();
        foreach($data as $row){
            $result[] = $row['id'];
        }
        return $result;
    }

}