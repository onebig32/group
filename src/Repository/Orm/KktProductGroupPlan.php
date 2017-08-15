<?php
namespace App\Models\Group\Orm;

use App\Models\MModel;
use App\Models\User\Interfaces\SystemUser;
use App\Models\Order\Interfaces\BaseOrderInfo;
use App\Models\Tourist\Interfaces\BaseTouristInfo;
use App\Models\Resources\Interfaces\CityBaseInfo;
use App\Models\Product\Interfaces\ProductTourInfo;
use AppSession;

class KktProductGroupPlan extends MModel
{
    public $timestamps = true;
    protected $table = 'kkt_product_group_plan';
    protected $fillable = [
        'group_code', 'created_user_id', 'product_id',
        'start_date', 'ticket_end_date', 'end_date',
        'type', 'status', 'leader_num', 'group_code_pre',
        'all_stock', 'sure_stock', 'aside_stock','locked_stock',
        'is_delete', 'start_city_id', 'back_city_id',
        'start_time', 'back_time', 'draw_bill', 'wm_flight_id', 
    	'is_close', 'close_content', 'updatetime',
    	'created_organization_id','name','self_built','version_f','uuid','created_at','updated_at'
    ];

    /**
     * 定义一对多关系
     */
    public function getGroup()
    {
        return $this->hasMany('App\Models\Group\Orm\KktProductGroup', 'plan_id');
    }

    /**
     * 定义外键
     */
    public function getProduct()
    {
        return $this->belongsTo('App\Models\Product\Orm\KktProduct', 'product_id');
    }

    /**
     * 定义与口岸一对一关系
     */
    public function getStartCity()
    {
        return $this->hasOne('App\Models\Resources\Orm\KktCity', 'id', 'start_city_id');
    }

    /**
     * 定义指派用户一对多关系
     */
    public function getDesignateUser()
    {
        return $this->hasMany('App\Models\Group\Orm\KktProductGroupPlanDesignate', 'plan_id');
    }

    /**
     * 定义团期日志一对多关系
     */
    public function getLog()
    {
        return $this->hasMany('App\Models\Group\Orm\KktProductGroupPlanLog', 'plan_id');
    }

    /**
     * 定义指派用户一对多关系
     */
    public function exist()
    {
        if ($this->exists && $this->is_delete == 1) {
            return true;
        } else {
            return false;
        }
    }

    public function existInUser($userId)
    {
        $sysUserObj = $this->getSysUserObj();
        $firstUserId = $sysUserObj->getFirstUserPid($userId);
        $dataFirstUserId = $sysUserObj->getFirstUserPid($this->created_user_id);
        if ($firstUserId == $dataFirstUserId) {
            return true;
        } else {
            return false;
        }
    }

    public function existInTour()
    {
        if ($this->exist() && count($this->getGroup) > 0) {
            foreach ($this->getGroup as $obj) {
                if ($obj->exist() && $obj->getTour && $obj->getTour->exist()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 获取未过期的团期计划的所有的价格
     */
    public function getGroupPrice()
    {
        if (!$this->exist()) {
            return false;
        }
        if (strtotime($this->start_date) < strtotime(date('Y-m-d'))) {
            return false;
        }
        $groupModel = $this->getGroup;
        if (!$groupModel) {
            return false;
        }
        $data = array();
        foreach ($groupModel as $obj) {
            if (!$obj->exist()) {
                continue;
            }
            $data['adult_whole_sale'][] = $obj->adult_whole_sale;
            $data['adult_retail_sale'][] = $obj->adult_retail_sale;
            $data['child_whole_sale'][] = $obj->child_whole_sale;
            $data['child_retail_sale'][] = $obj->child_retail_sale;
        }
        return $data;
    }

    /**
     * 获取团期列表数据,进行详细数据转换
     * @param array $data
     *  array('page'=>'', 'limit'=>'', 'count'=>'' ,'data'=>array(模型对象))
     * @param boolean $baseInfo 是否只获取基础数据
     */
    public function getDataList($data, $baseInfo = false)
    {
        $result = $data;
        $result['data'] = array();
        $data = $data['data'];
        $status = array('1' => '未发布', '2' => '已发布', '3' => '仅对内');
        $today = strtotime(date('Y-m-d', time()));
        $systemUserObj = $this->getSysUserObj();
        $groupModel =$this->getKktProductGroupModel();
        foreach ($data as $k => $obj) {
            $productModel = $obj->getProduct;
            if (!$baseInfo) {
                $result['data'][$k]['product_id'] = ($productModel) ?$productModel->id:0;
                $result['data'][$k]['kkt_product_name'] = ($productModel) ? $productModel->kkt_product_name : '';
                $result['data'][$k]['dayNum'] = ($productModel) ? $productModel->day_num : '';
                $result['data'][$k]['startCityName'] = $this->getCityInfoObj()->getName($obj->start_city_id, false);
                $result['data'][$k]['cover'] = (object) array('large_url_'=>'', 'middle_url_'=>'', 'small_url_'=>'', 'url'=>'');
                if($productModel){
                    $cover = $productModel->getImgInfo();
                    if($cover) $result['data'][$k]['cover'] = $cover[0];
                }
                $groupData = $obj->getGroupData();
                $groupids = array();
                if ($groupData) {
                    foreach ($groupData as $row) {
                        $groupids[] = $row['id'];
                    }
                }
                $result['data'][$k]['groupids'] = $groupids;
                $groupPrices = $obj->getGroupPrice();
                $result['data'][$k]['adult_whole_sale'] = ($groupPrices) ? min($groupPrices['adult_whole_sale']) : 0;
            }
            $result['data'][$k]['id'] = $obj->id;
            $result['data'][$k]['peopleNum'] = $obj->all_stock . '+' . $obj->leader_num;
            //余位
            $result['data'][$k]['leave_stock'] = $obj->all_stock - $obj->sure_stock - $obj->aside_stock - $obj->locked_stock;
            $result['data'][$k]['canClearNum'] = $obj->aside_stock + $obj->locked_stock;
            //暂停发布限制
            /*$result['data'][$k]['canRelease'] = (($today > strtotime($obj->start_date))
                || ($obj->sure_stock + $obj->aside_stock + $obj->locked_stock) > 0) ? false : true;*/
            $result['data'][$k]['canRelease'] = true;
            $result['data'][$k]['status_'] = ($today > strtotime($obj->start_date)) ? '封团' :
                (($today > strtotime($obj->end_date)) ? '截止收客' :
                    (isset($status[$obj->status]) ? $status[$obj->status] : ''));
            //团期指派人列表
            $userCacheArray = [];
            $userCacheArray[$obj->created_user_id] = $systemUserObj->getUserInfo($obj->created_user_id);
            $result['data'][$k]['createdUserName'][] = $userCacheArray[$obj->created_user_id]['realname'];
            $result['data'][$k]['createdOrgName'][] = $userCacheArray[$obj->created_user_id]['org_'];
            //团期指派人(操作op)列表
            $result['data'][$k]['designUserIds'] = $obj->getDesignateUserIds();
            $result['data'][$k]['designUserName'] = [];
            $result['data'][$k]['designOrgName'] = '';
            if($result['data'][$k]['designUserIds']){
                foreach ($result['data'][$k]['designUserIds'] as $userId) {
                    if (!in_array($userId, array_keys($userCacheArray))) {
                        $userCacheArray[$userId] = $systemUserObj->getUserInfo($userId);
                    }
                    $result['data'][$k]['designUserName'][] = $userCacheArray[$userId]['contact_name'];
                    $result['data'][$k]['designOrgName'] = $userCacheArray[$userId]['org_'];
                }
            }
            $result['data'][$k]['type_'] = $obj->getTypeName($obj->type);
            $result['data'][$k]['is_close_'] = $obj->is_close == 2 ? "已关团" : "关团";
            $result['data'][$k]['back_time'] = date('Y-m-d', strtotime($obj->back_time));
            $result['data'][$k] = array_merge($result['data'][$k], array_only($obj->toArray(), [
                'group_code', 'all_stock', 'sure_stock', 'aside_stock', 'start_date','name','self_built',
                'leader_num', 'status', 'locked_stock', 'type', 'ticket_end_date', 'is_close', 'close_content','lock_status'
            ]));
            //判断团期线路完整性
            $tourCompleteStatus = true;
            $tourObj = $groupModel->select('tour_id')->where('plan_id',$obj->id)->where('is_delete',1)->get();
            foreach($tourObj as $tobj){
                $lastDay = $this->getProductTourObj()->getLastDayForTourId($tobj->tour_id);
                if(!$lastDay || $lastDay != $result['data'][$k]['dayNum']){
                    $tourCompleteStatus = false;
                    break;
                }
            }
            $result['data'][$k]['tour_complete_status'] = $tourCompleteStatus;
            //团期是否超过返程日七天 --》0 否 1是
            $result['data'][$k]['over_back_time_7_days'] = 0;
            if((time() >= (strtotime($obj->back_time)+604800))){
                $result['data'][$k]['over_back_time_7_days'] = 1;
            }
        }
        if (isset($result['statistics'])) {
            foreach ($result['statistics'] as $key => $row) {
                $result['statistics'][$key]['leave_stock'] = $row['all_stock'] - $row['sure_stock'] - $row['aside_stock'] - $row['locked_stock'];
                $result['statistics'][$key]['canClearNum'] = $row['aside_stock'] + $row['locked_stock'];
            }
        }
        return $result;
    }

    /**
     * 数据权限注入方法
     */
    public function privFunc($builder, $privFlag, $userIds, $orgIds, $onlyWhere){
        if($privFlag){
            return $builder->userAuth($userIds, $orgIds, false, $onlyWhere);
        }else{
            return $builder;
        }
    }

    /**
     * 用户,组织数据权限
     *
     * @param $query 查询构造器
     * @param $userIds 系统用户ID数组
     * @param $orgIds 系统组织ID数组
     * @param boolean $isAdmin true 超管移除权限限制
     */
    public function scopeUserAuth($query, $userIds=[], $orgIds=[], $isAdmin=true, $onlyWhere=false){
        $userInfo = AppSession::getSession();
        if($isAdmin && $userInfo['role_id']==0){
            return $query;
        }
        if($userInfo['role_id']!=0 && !$onlyWhere){
            array_push($userIds, $userInfo['id']);
            array_push($orgIds, $userInfo['org_id']);
        }
        $userIds = array_unique($userIds);
        $orgIds = array_unique($orgIds);

        if(count($userIds) == 1 && count($orgIds)==1){
            return $query->whereIn('created_user_id', $userIds)->orWhereHas('getDesignateUser',function($secQuery)use($userIds){
                $secQuery->where('is_delete',1)->whereIn('user_id',$userIds);
            });
        }else{
            return $query->whereIn('created_user_id', $userIds)
                ->orwhereIn('created_organization_id', $orgIds)
                ->orWhereHas('getDesignateUser',function($secQuery)use($userIds){
                    $secQuery->where('is_delete',1)->whereIn('user_id',$userIds);
                });;
        }

    }

    /**
     * 获取团期计划模型数据列表
     * @param array $wh 查询条件
     *  array(
     *    'code' => '团号',
     *    'startCity' => '数组：出发口岸',
     *    'planIds' => array('团期计划id'),
     *    'groupIds' => array('团期id'),
     *    'createdUserId' => '归属人员工id',
     *    'createdDeparmentId'=>'归属人部门id'
     *    'hasOrder' => '1:已有订单 2:没有订单',
     *    'history' => '1:已过期 2：未过期',
     *    'expired' => '团期计划1:已过期 2：未过期',
     *    'key' => '关键字：线路'
     *    'productId' => '数组：产品id',
     *    'status' => '1:未发布 2:已发布 3:仅对内'
     *    'date' => 'array: ['2015-06-03', '2015-07-06']'
     *    'startDate' => '出发日期'
     *    'rangeMonth' => '日期范围：团期计划最大出发日期'
     *    'day' => '天数'，
     *    'statusArr' => 状态数组
     *    'urgent' => 显示紧急团期 1为是
     *    'lockStatus' =>锁定状态 1锁定 2不锁定
     *    'notDelete' => 存在则取出所有数据（包括软删除）
     *    'notPriv' => 存在值 即不启动数据权限
     *    'designateUserId' => 操作op用户id
     *    'designateOrgId' => 操作op组织id
     *    'planUuids' => 数组：团期计划uuid
     *  )
     *  所有查询条件都非必填
     * @param array $pages 分页，未空数组则返回全部数据
     *  array('page'=>'当前页','limit'=>'每页限制')
     *
     * @param $statistics array(
     *      'sum'=>array('all_stock','sure_stock','aside_stock','locked_stock','leader_num')
     * )  统计全部数据项  all_stock 库存， sure_stock 收客数 ， aside_stock 预留， locked_stock 锁位， leader_num 领队
     */
    public function getModelList($wh = array(), $pages = array(), $statistics = array(), $orderBy = ['start_date', 'asc'])
    {
        //启用数据权限
        if (isset($wh['notPriv']) && $wh['notPriv']) {
            $model = $this;
        } else {
            //启用数据权限
            $privWh = [];
            if(isset($wh['createdUserId']) && $wh['createdUserId']){
                $privWh['only']['userIds'] = [$wh['createdUserId']];
            }
            if(isset($wh['createdDeparmentId']) && $wh['createdDeparmentId']){
                $privWh['only']['orgIds'] = [$wh['createdDeparmentId']];
            }
            self::addPrivScope($privWh);

        }
        $result = array('page' => 1, 'limit' => 0, 'count' => 0, 'data' => array());
        if(isset($wh['notDelete']) && $wh['notDelete']){
            $model = $this;
        }else{
            $model = $this->where('is_delete', 1);
        }

        if (isset($wh['code']) && $wh['code']) {
            $model = $model->where('group_code', 'like', '%' . $wh['code'] . '%');
        }
        if (isset($wh['group_code']) && $wh['group_code']) {
            $model = $model->where('group_code', 'like', '%' . $wh['group_code'] . '%');
        }
        if (isset($wh['status']) && in_array($wh['status'], array(1, 2, 3))) {
            $model = $model->where('status', $wh['status']);
        }
        if (isset($wh['statusArr']) && is_array($wh['statusArr'])) {
            $model = $model->whereIn('status', $wh['statusArr']);
        }
        if (isset($wh['startCity']) && $wh['startCity']) {
            if (is_array($wh['startCity'])) {
                $model = $model->whereIn('start_city_id', $wh['startCity']);
            } else {
                $model = $model->where('start_city_id', $wh['startCity']);
            }
        }
        if (isset($wh['planIds']) && is_array($wh['planIds']) && !empty($wh['planIds'])) {
            $model = $model->whereIn('id', $wh['planIds']);
        }
        if (isset($wh['planUuids']) && is_array($wh['planUuids']) && !empty($wh['planUuids'])) {
            $model = $model->whereIn('uuid', $wh['planUuids']);
        }
        if (isset($wh['groupIds']) && !empty($wh['groupIds']) && is_array($wh['groupIds'])) {
            $groupIds = $wh['groupIds'];
            $model = $model->whereHas('getGroup', function ($query) use ($groupIds) {
                $query->whereIn('id', $groupIds)
                    ->where('is_delete', 1);
            });
        }
        if (isset($wh['userIds']) && is_array($wh['userIds']) && !empty($wh['userIds'])) {
            $model = $model->whereIn('created_user_id', $wh['userIds']);
        }
        if (isset($wh['hasOrder']) && $wh['hasOrder']) {
            if ($wh['hasOrder'] == 1) {
                $model = $model->whereRaw('(aside_stock>0 or sure_stock>0 or locked_stock>0)');
            } else if ($wh['hasOrder'] == 2) {
                $model = $model->where('aside_stock', 0)
                    ->where('sure_stock', 0)
                    ->where('locked_stock', 0);
            }
        }
        if (isset($wh['history']) && $wh['history']) {
            if ($wh['history'] == 1) {
                $model = $model->whereRaw('start_date < "' . date('Y-m-d') . '"');
            } else if ($wh['history'] == 2) {
                $model = $model->whereRaw('start_date >= "' . date('Y-m-d') . '"');
            }
        }
        if (isset($wh['key']) && $wh['key']) {
            $key = $wh['key'];
            $model = $model->whereHas('getProduct', function ($query) use ($key) {
                $query->where('kkt_product_name', 'like', '%' . $key . '%');
            });
        }
        if (isset($wh['productId']) && is_array($wh['productId']) && !empty($wh['productId'])) {
            $productIds = $wh['productId'];
            $model = $model->whereHas('getProduct', function ($query) use ($productIds) {
                $query->whereIn('id', $productIds);
            });
        }
        if (isset($wh['date']) && is_array($wh['date']) && count($wh['date'])==2) {
            $model = $model->whereRaw('start_date >= "' . $wh['date'][0] . '" and start_date <= "' . $wh['date'][1] . '"');
        }
        if (isset($wh['startDate']) && $wh['startDate'] && isset($wh['rangeMonth']) && $wh['rangeMonth']) {
                $model = $model->whereRaw('start_date >= "' . $wh['startDate'] . '" and start_date <= "' . $wh['rangeMonth'] . '"');
            }
        if (isset($wh['day']) && $wh['day'] && is_numeric($wh['day'])) {
            $key = $wh['day'];
            $model = $model->whereHas('getProduct', function ($query) use ($key) {
                $query->where('day_num', '=', $key);
            });
        }
        if (isset($wh['back_start_date']) && $wh['back_start_date']) {
            $model = $model->where('back_time', '>=', date("Y-m-d H:i:s", $wh['back_start_date']));
        }
        if (isset($wh['back_end_date']) && $wh['back_end_date']) {
            $model = $model->where('back_time', '<=', date("Y-m-d H:i:s", $wh['back_end_date']));
        }
        if (isset($wh['start_date']) && $wh['start_date']) {
            $model = $model->where('start_date', '>=', date("Y-m-d", $wh['start_date']));
        }
        if (isset($wh['end_date']) && $wh['end_date']) {
            $model = $model->where('start_date', '<=', date("Y-m-d", $wh['end_date']));
        }

        if (isset($wh['expired']) && $wh['expired']) {
            if ($wh['expired'] == 1) {
                //过期
                $model = $model->whereRaw('end_date < "' . date('Y-m-d') . '"');
            } else if ($wh['expired'] == 2) {
                //未过期
                $model = $model->whereRaw('end_date >= "' . date('Y-m-d') . '"');
            }
        }
        if (isset($wh['id']) && $wh['id']) {
            $model = $model->where('id', $wh['id']);
        }
        if (isset($wh['lockStatus']) && $wh['lockStatus']) {
            if($wh['lockStatus']==1){
                //锁定团期
                $model = $model->where('lock_status', 1)->where('back_time','<=',date('Y-m-d H:i:s',strtotime('-7 day')));
            }else if ($wh['lockStatus']==2){
                //未锁团期
                $model = $model->whereRaw("not (back_time<='".date('Y-m-d H:i:s',strtotime('-7 day'))."' and lock_status=1)");
            }

        }
        if (isset($wh['destCityId']) && $wh['destCityId']) {
            $destCityId = $wh['destCityId'];
            $model = $model->whereHas('getProduct', function ($query) use ($destCityId) {
                $query->whereHas('getDest', function ($q) use ($destCityId) {
                    $q->where('dest_city_id', $destCityId);
                });
            });
        }
        //显示紧急团期
        if (isset($wh['urgent']) && $wh['urgent']==1) {
            $sevenTime = date('Y-m-d',time()+604800);
            $now = date('Y-m-d',time());
            $model = $model
                ->where('end_date','>=',$now)
                ->where('end_date','<=',$sevenTime)
                ->whereRaw('(all_stock - sure_stock - aside_stock - locked_stock) >2');
        }
        if (isset($wh['designateUserId']) && !empty($wh['designateUserId'])) {
            $designateUserId = $wh['designateUserId'];
            $model = $model->whereHas('getDesignateUser', function ($query) use ($designateUserId) {
                $query->where('is_delete', '1')->where('user_id', $designateUserId);
            });
        }
        if (isset($wh['designateOrgId']) && !empty($wh['designateOrgId'])) {
            $designateOrgId = $wh['designateOrgId'];
            $model = $model->whereHas('getDesignateUser', function ($query) use ($designateOrgId) {
                $query->where('is_delete', '1')->where('organization_id', $designateOrgId);
            });
        }

        if (is_array($statistics) && !empty($statistics)) {
            foreach ($statistics as $key => $row) {
                $tempModel = clone $model;
                if ($key == 'sum') {
                    foreach ($row as $v) {
                        $result['statistics'][$key][$v] = $tempModel->sum($v);
                    }
                }
            }
        }
        if (!empty($pages)) {
            $countModel = clone $model;
            $result['count'] = $countModel->count();
            $result['limit'] = $pages['limit'];
            $result['page'] = $pages['page'];
            $model = $model->skip(($pages['page'] - 1) * $pages['limit'])->take($pages['limit']);
        }
        $result['data'] = $model->orderBy($orderBy[0], $orderBy[1])->get();
        if ($result['count'] == 0) {
            $result['count'] = count($result['data']);
        }
        return $result;
    }

    /**
     * or查询数据
     * @param array $orWh or查询
     * [
     *  'productIds' => '数组：产品Id',
     *  'cityIds' => '数组：出发城市',
     *  'startDataBetween' => '('最小出发时间', '最大时间戳')'
     *  'planIds' => '数组：团期计划id',
     * ]
     * @param array $andWh and查询
     * [
     *  'planType' => '0:不区分 ,1对外团期 ,2:对内团期'
     * ]
     * @param array $pages 分页数据
     * @param array orderBy array('排序字段', '排序方式')
     */
    public function getOrModelList($orWh = [], $andWh = [], $pages = [], $orderBy = ['id', 'asc'])
    {
        $orderByKeys = ['id', 'start_date'];
        if (!in_array($orderBy[0], $orderByKeys)) {
            $orderBy[0] = ['id'];
        }

        $result = ['limit' => 0, 'account' => 0, 'page' => 0, 'data' => []];
        $model = $this->where('is_delete', 1);
        $orSql = '';
        if (isset($orWh['productIds']) && is_array($orWh['productIds']) && !empty($orWh['productIds'])) {
            $orSql .= 'product_id in (' . implode(',', $orWh['productIds']) . ') or ';
        }
        if (isset($orWh['cityIds']) && is_array($orWh['cityIds']) && !empty($orWh['cityIds'])) {
            $orSql .= 'start_city_id in (' . implode(',', $orWh['cityIds']) . ') or ';
        }
        if (isset($orWh['startDataBetween']) && count($orWh['startDataBetween']) == 2) {
            $orSql .= '(start_date between "' . date('Y-m-d H:i:s', $orWh['startDataBetween'][0]) . '" and "' . date('Y-m-d H:i:s', $orWh['startDataBetween'][1]) . '") or ';
        }
        if (isset($orWh['planIds']) && is_array($orWh['planIds']) && !empty($orWh['planIds'])) {
            $orSql .= 'id in (' . implode(',', $orWh['planIds']) . ') or ';
        }
        if ($orSql) {
            $orSql = substr($orSql, 0, strlen($orSql) - 3);
            $orSql = '(' . $orSql . ')';
            $model = $model->whereRaw($orSql);
        } else {
            return $result;
        }
        if (isset($andWh['planType'])) {
            if ($andWh['planType'] == 1) {
                $model = $model->where('status', 2);
            } else if ($andWh['planType'] == 2) {
                $model = $model->where('status', 3);
            }
        }
        if (!empty($pages)) {
            $result['page'] = $pages['page'];
            $result['limit'] = $pages['limit'];
            $result['count'] = $model->count();
            $model = $model->skip(($pages['page'] - 1) * $pages['limit'])->take($pages['limit']);
        }
        $result['data'] = $model->orderBy($orderBy[0], $orderBy[0])->get();
        if ($result['count'] == 0) {
            $result['count'] = count($result['data']);
        }
        return $result;
    }

    /**
     * 获取团期计划下的团期数据,包含行程方案名称
     */
    public function getGroupData()
    {
        $result = array();
        if ($this->exist() && ($tempModel = $this->getGroup) && count($tempModel) > 0) {
            foreach ($tempModel as $k => $obj) {
                if ($obj->exist() && ($tourModel = $obj->getTour) && $tourModel->exist()) {
                    $result[$k] = array_only($obj->toArray(), [
                        'tour_id', 'adult_whole_sale', 'adult_retail_sale',
                        'child_whole_sale', 'child_retail_sale', 'prepay_money',
                        'id', 'plan_id','uuid'
                    ]);
                    $result[$k]['tour_name'] = $tourModel->name;
                }
            }
            return $result;
        } else {
            return false;
        }
    }

    /**
     * 获取团期计划下的所有游客
     * @param array $pages 分页数据
     *  array(
     *   'limit' => '每页限制数',
     *   'page' => '页码'
     *  )
     * @param array $wh 查询条件
     *  array(
     *   'tourisId' => '数组:游客id',
     *   'orderId'  => '数组：订单id'
     *   'getLeader' => '',
     *   'isRefund' => '1:正常  2:退团'
     *  )
     * @param boolean $forOrder 是否根据订单判断
     *  为空则返回全部数据
     */
    public function getTouristForPlan($pages = array(), $wh = array(), $forOrder = true)
    {
        $groupData = $this->getGroupData();
        if (!$groupData || count($groupData) == 0) {
            return false;
        }
        $groupIds = array();
        foreach ($groupData as $row) {
            $groupIds[] = $row['id'];
        }
        if (empty($groupIds)) {
            return false;
        }
        if ($forOrder) {
            $orderData = $this->getBaseOrderInofObj()->getOrderForGroup($groupIds, [1, 2, 3, 4, 5, 20]);
            $orderIds = array();
            foreach ($orderData as $row) {
                $orderIds[] = $row['id'];
            }
            if (empty($orderIds)) {
                return false;
            }
            $where['orderId'] = $orderIds;
        }
        if (isset($wh['tourisId']) && is_array($wh['tourisId']) && !empty($wh['tourisId'])) {
            $where['ids'] = $wh['tourisId'];
        }
        $where['planId'] = array($this->id);
        if (isset($wh['orderId'])) {
            $where['orderId'] = $wh['orderId'];
        }
        if (isset($wh['getLeader'])) {
            $where['getLeader'] = $wh['getLeader'];
        }
        if (isset($wh['isRefund'])) {
            $where['isRefund'] = $wh['isRefund'];
        }
        return $this->getBaseTouristObj()->getTouristForOrders($where, $pages);
    }

    /**
     * 检查团期计划是否属于此用户
     * @param int $planId 团期计划Id
     */
    public function checkPlanForUserId($planId)
    {
        $model = $this->select('created_user_id')->where('id', $planId)->where('is_delete', 1);
        $data = $model->get()->toArray();
        if (count($data) > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 根据团期计划ID获取数据
     * @param int $planId 团期计划Id
     * @param int $userId
     */
    public function getDataForPlanID($planId, $userId = false)
    {
        
        $model = $this;
        $model = $model->where('is_delete', 1)->where('id', $planId);
        $data = $model->get()->toArray();

        if (count($data) == 0) {
            return false;
        }

        $groupModel = $this->getKktProductGroupModel()
            ->select('tour_id', 'adult_whole_sale', 'adult_retail_sale', 'child_whole_sale', 'child_retail_sale', 'prepay_money', 'id')
            ->where('plan_id', $planId)
            ->where('is_delete', 1);
        $groupData = $groupModel->get()->toArray();
        $data['group'] = $groupData;
        return $data;
    }

    /**
     * 获取团期指派用户id数组
     */
    public function getDesignateUserIds()
    {
        if (!$this || !$this->exist()) {
            return false;
        }
        $designUserList = $this->getDesignateUser;
        $rs = [];
        foreach ($designUserList as $obj) {
            if ($obj->exist()) {
                $rs[] = $obj->user_id;
            }
        }
        return $rs;
    }

    /**
     * 获取团期类型名称
     * @param int $type 团期类型
     */
    public function getTypeName($type)
    {

        $typeArr = array('1' => '散拼', '2' => '专属包团', '3' => '定制', '4'=>'包团');
        $rs = "";
        if (isset($typeArr[$type])) $rs = $typeArr[$type];
        return $rs;
    }

    /**
     * 拆分团期
     * @param int $stock 拆分库存
     */
    public function copyPlan($stock)
    {

        if (!$this->exist()) return false;

        $newPlanModel = new self();
        $oldPlan = $this;
        $newPlanId = 0;
        try {
            //更新旧团期类型
            $this->all_stock -= $stock;
            $this->save();
            //复制新团期
            $data = array(
                'group_code' => '',
                'created_user_id' => $oldPlan->created_user_id,
                'product_id' => $oldPlan->product_id,
                'back_city_id' => $oldPlan->back_city_id,
                'start_city_id' => $oldPlan->start_city_id,
                'wm_flight_id' => $oldPlan->wm_flight_id,
                'start_date' => $oldPlan->start_date,
                'back_time' => $oldPlan->back_time,
                'start_time' => $oldPlan->start_time,
                'ticket_end_date' => $oldPlan->ticket_end_date,
                'draw_bill' => $oldPlan->draw_bill,
                'end_date' => $oldPlan->end_date,
                'type' => 2,
                'status' => 3,
                'leader_num' => $oldPlan->leader_num,
                'all_stock' => $stock,
            );
            $newPlanId = $newPlanModel->saveData($data);
            $newPlanModel = $newPlanModel->find($newPlanId);
            $newPlanModel->updateCode();

            //复制团期方案数据
            $productGroupModel = $this->getProductGroupModel();
            $oldGroup = $productGroupModel->where('plan_id', $oldPlan->id)->get();
            foreach ($oldGroup as $group) {
                $data = $group->toArray();
                unset($data['id']);
                $data['plan_id'] = $newPlanId;
                $productGroupModel = $this->getProductGroupModel();
                $productGroupModel->saveData($data);
            }

        } catch (\Exception $e) {
            throw $e;
        }

        return $newPlanId;
    }

    private function getProductGroupModel()
    {
        return new KktProductGroup();
    }


    /**
     * 获取剩余库存
     */
    public function getRemainStock()
    {
        if (!$this->exist()) {
            return 0;
        }
        return $this->all_stock - $this->locked_stock - $this->sure_stock - $this->aside_stock;
    }

    /**
     * 获取可清位库存
     */
    public function getCanClearNum()
    {
        if (!$this->exist()) {
            return 0;
        }
        return $this->locked_stock + $this->aside_stock;
    }

    /**
     * 获取团期详细
     * @return $rs = array(
     *      团期本条数据
     *      'ticket'=>'数组：团期出票数据'
     * )
     */
    public function getDetail()
    {

        if (!$this->exist()) {
            return false;
        }
        //团期信息
        $rs = $this->toArray();
        //出票信息
        $rs['ticket'] = ($this->getTicket && $this->getTicket->is_delete == 1) ? $this->getTicket->toArray() : [];

        return $rs;
    }

    /**
     * 根据凭证更新团期计划库存
     */
    public function updateStock(){

        if (!$this->exist()) return false;
        //根据凭证更新库存
        $this->sure_stock = $this->getSureStock();
        $this->aside_stock = $this->getAsideStock();
        $this->locked_stock = $this->getLockStock();

        $this->save();

        return $this;
    }

    /**
     *  根据凭证获取团期预留库存
     */
    public function getAsideStock($shelfId = 0){

        if (!$this->exist()) return 0;
        $stock = 0 ;
        $wh = ['planId'=> $this->id, 'type'=>1];
        if($shelfId){
            $wh['shelfId'] = $shelfId;
        }
        $fieldStr = " sum(stock)  as totalStock ";
        $rs = $this->getProofModel()->getSumList($fieldStr, $wh);
        if ($rs['count']>0){
            $stock = $rs['data'][0]['totalStock']?$rs['data'][0]['totalStock']:0;
        }

        return $stock ;
    }

    /**
     *  根据凭证获取团期锁位库存
     */
    public function getLockStock($shelfId = 0){

        if (!$this->exist()) return 0;
        $stock = 0 ;
        $wh = ['planId'=> $this->id, 'type'=>2  ];
        if($shelfId){
            $wh['shelfId'] = $shelfId;
        }
        $fieldStr = " sum(stock)  as totalStock ";
        $rs = $this->getProofModel()->getSumList($fieldStr, $wh);
        if ($rs['count']>0){
            $stock = $rs['data'][0]['totalStock']?$rs['data'][0]['totalStock']:0;
        }

        return $stock ;
    }

    /**
     *  根据凭证获取团期确认库存
     */
    public function getSureStock($shelfId = 0){

        if (!$this->exist()) return 0;
        $stock = 0 ;
        $wh = ['planId'=> $this->id, 'type'=>3  ];
        if($shelfId){
            $wh['shelfId'] = $shelfId;
        }
        $fieldStr = " sum(stock)  as totalStock ";
        $rs = $this->getProofModel()->getSumList($fieldStr, $wh);
        if ($rs['count']>0){
            $stock = $rs['data'][0]['totalStock']?$rs['data'][0]['totalStock']:0;
        }

        return $stock ;
    }

    /**
     * @return $lockStatus 1 锁定 2 未锁定
     */
    public function getLockStatus(){
        $lockStatus = 2;//未锁定
        if($this->lock_status == 1 && (time() >= (strtotime($this->back_time)+604800))){
            $lockStatus = 1;//锁定
        }
        return $lockStatus;
    }

    /**
     * 更新团号
     */
    public function updateCode()
    {
        if ($this->exist() && !$this->group_code) {
            $banName = ($productModel = $this->getProduct) ? $productModel->brand_name : '';
            $destArray = ($productModel) ? $productModel->getDestInfo() : array();
            $dest = '';
            foreach ($destArray as $row) {
                if (count($destArray) > 1) {
                    $dest .= mb_substr($row['city_name'], 0, 1, 'utf-8');
                } else {
                    $dest .= $row['city_name'];
                }
            }
            $airlineCode = '';
            if (($wmFlightModel = $this->getWmFlight) && ($flightModel = $wmFlightModel->getAirlineFlight) && ($airlineModel = $flightModel->getAirline)) {
                $airlineCode = '-' . $airlineModel->code;
            }
            $startCity = $this->getCityInfoObj()->getName($this->start_city_id, false);
            $dayNum = ($productModel) ? $productModel->day_num : '';
            $this->group_code = $banName . '-' . str_replace('-', '', $this->start_date) . $dest . $dayNum . '天' . $airlineCode . '-' . $startCity . '-' . $this->id;
            $this->group_code_pre = $this->group_code;
            $this->save();
            return true;
        } else {
            return false;
        }
    }


    private function getProofModel()
    {
        return new KktGroupplanStockProof();
    }

    private function getSysUserObj()
    {
        return new SystemUser();
    }

    private function getKktProductGroupModel()
    {
        return new KktProductGroup();
    }

    private function getBaseOrderInofObj()
    {
        return new BaseOrderInfo();
    }

    private function getBaseTouristObj()
    {
        return new BaseTouristInfo();
    }

    private function getCityInfoObj()
    {
        return new CityBaseInfo();
    }
    private function getProductTourObj()
    {
        return new ProductTourInfo();
    }



}