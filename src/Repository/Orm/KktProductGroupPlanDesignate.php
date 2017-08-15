<?php
namespace App\Models\Group\Orm;

use App\Models\MModel;
use App\Models\User\Interfaces\SystemUser;
use DB;
class KktProductGroupPlanDesignate extends MModel
{
    public $timestamps = true;
    protected $table = 'kkt_product_group_plan_designate';
    protected $fillable = [
        'plan_id', 'user_id', 'organization_id' ,'created_user_id', 'created_organization_id','created_at','updated_at','is_delete'
    ];

    /**
     * 获取团期指派列表
     * @param array $wh 查询条件
     *  array(
     *   'userIdArr'=>账号ID数组
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
        if(isset($wh['userIdArr'])  && $wh['userIdArr'] ){
            $model = $model->whereIn('user_id',$wh['userIdArr']);
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

    /**
     * 批量指派团期
     * @param int $planId    团期计划id
     * @param array $userIds 用户id数组,为空则清空团期指派
     */
    public function designatePlan($planId, $userIds = [])
    {
        $this->where('plan_id',$planId)->update(['is_delete'=>2]);
        $userObj = new SystemUser();
        foreach($userIds as $userId){
            $userInfo = $userObj->getUserInfo($userId);
            $data = [
                'plan_id'=>$planId,
                'user_id'=>$userId,
                'organization_id'=>$userInfo['org_id']
            ];
            $resObj = $this->where('plan_id',$planId)->where('user_id',$userId)->first();
            if($resObj){
                $resObj->update(['is_delete'=>1]);
            }else{
                $this->create($data);
            }
        }
        return true;
    }
}