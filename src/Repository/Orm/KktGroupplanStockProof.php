<?php
namespace App\Models\Group\Orm;

use App\Models\MModel;
use DB;
class KktGroupplanStockProof extends MModel
{
    public $timestamps = true;
    protected $table = 'kkt_groupplan_stock_proof';
    protected $fillable = [
        'stock', 'type', 'plan_id', 'order_uuid',
        'created_at', 'updated_at','shelf_id'
    ];

    /**
     * 获取库存凭证汇总数据列表
     * @param array $wh 查询条件 参照 getModelList
     * array(
     *      'type'=>团期库存类型 1 预留库存 2 锁位库存 3 确认库存
     *      'planId'=>团期计划id
     *      'shelfId'=>货架id
     * )
     * @param array $pages 分页，为空数组则返回全部数据
     *  array('page'=>'当前页','limit'=>'每页限制')
     *  @param String $fieldStr = " round(sum(total_money),2) as totalMoney "; 查询数据
     */
    public function getSumList($fieldStr, $wh=array(), $pages=array(), $groupBy=""){

        $result = array('page'=>1, 'limit'=>0, 'count'=>0, 'data'=>array());
        $model = $this;
        //获取查询model
        if(isset($wh['type']) && $wh['type']){
            $model = $model->where('type', $wh['type']);
        }
        if(isset($wh['planId']) && $wh['planId']){
            $model = $model->where('plan_id', $wh['planId']);
        }
        if(isset($wh['shelfId']) && $wh['shelfId']){
            $model = $model->where('shelf_id', $wh['shelfId']);
        }
        //汇总数据
        $model = $model->select(DB::raw("*,{$fieldStr} "));
        if ($groupBy) $model = $model->groupBy(DB::raw($groupBy));

        //分页及汇总
        if(!empty($pages)){
            $countModel = clone $model;
            $result['count'] = count($countModel->select(DB::raw("count(1) as count"))->get());
            $result['limit'] = $pages['limit'];
            $result['page'] = $pages['page'];
            $model = $model->skip(($pages['page']-1)*$pages['limit'])->take($pages['limit']);
        }
        //获取对象列表
        $result['data'] = $model->orderBy('id', 'asc')->get();
        //获取全部数据汇总
        if($result['count'] == 0){
            $result['count'] = count($result['data']);
        }
        return $result;
    }
}