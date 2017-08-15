<?php
/**********************************************
 * 产品团期子系统（团期）--货架管理。拥有分配团期库存职责。
 * 主要功能：货架库存调整
 */
namespace App\Models\Group\Interfaces;

use App\Models\BusinessService;
use App\Models\Group\Exceptions\GroupException;
use App\Models\Group\Exceptions\ShelfException;
use App\Models\Group\Orm\KktProductGroupPlan;
use App\Models\Group\Orm\KktShelf;
use App\Models\Group\Orm\KktShelfDetail;
use App\Models\Group\Orm\KktShelfPrice;

class ShelfManager extends BusinessService
{
    private $shelfOrm;
    private $planOrm;

    public function __construct($id = 0 ,$planId = 0)
    {
        $this->shelfOrm = new KktShelf();
        $this->planOrm = new KktProductGroupPlan();
        if ($id) {
            $this->shelfOrm = $this->shelfOrm->find($id);
            if (!$this->shelfOrm || !$this->shelfOrm->exist()) {
                throw new ShelfException('货架不存在', 1500011);
            }
        }
        if ($planId) {
            $this->planOrm = $this->planOrm->find($planId);
            if (!$this->planOrm || !$this->planOrm->exist()) {
                throw new GroupException('团期不存在', 150002);
            }
        }
    }

    /**
     * get Orm
     */
    public function getOrm()
    {
        return $this->shelfOrm;
    }
    /**
     * 根据uuid获取货架id
     * @param string $linkUuid 关联uuid
     * @return int  货架id（没有则返回默认货架id）
     */
    public function getShelfIdForUuid($linkUuid = '')
    {
        $res = $this->shelfOrm
            ->where('link_uuid', $linkUuid)
            ->where('is_delete', 1)
            ->first();

        return $res ? $res['id'] : 1;
    }

    /**
     * 获取商品占用库存
     * @param int $planId 团期计划id
     * @return int 占用库存
     */
    public function getTakeStock()
    {
        $res = $this->shelfOrm->getDetail()
            ->where('plan_id', $this->planOrm->id)
            ->where('is_delete', 1)
            ->first();
        if (!$res) {
            throw new ShelfException('货架商品不存在', 150004);
        }
        return $res['take_stock']?$res['take_stock']:0;
    }


    /**
     * 新增货架
     * @param array $shelfArray 货架明细数据
     * array(
     *  'name' => 货架名称
     *  'link_type'=>  关联类型 1默认货架 2 活动类货架
     *  'link_uuid'=> 关联uuid
     * )
     */
    public function addShelf($shelfArray = [])
    {
        return $this->shelfOrm->saveData($shelfArray);
    }

    /**
     * 保存货架明细
     * @param array $detailArray 货架明细数据
     * array(
     *  'take_stock'=> 占用库存
     * )
     */
    public function saveDetail($detailArray = [])
    {
        $detailObj = $this->shelfOrm->getDetail()
            ->where('plan_id', $this->planOrm->id)
            ->where('is_delete', 1)
            ->first();
        if ($detailObj) {
            $detailObj->take_stock = $detailArray['take_stock'];
            $detailObj->save();
        } else {
            $detailArray['shelf_id'] = $this->shelfOrm->id;
            (new KktShelfDetail())->create($detailArray);
        }
        return true;
    }

    /**
     * 保存货架明细
     * @param array $priceArray 货架明细数据
     * array(
     *  'group_id'=> 团期计划id
     *  'plan_id'=> 团期计划id
     *  'adult_price'=> 成人价
     *  'child_price'=> 儿童价
     *  'deposit_money'=> 定金
     * )
     */
    public function savePrice($priceArray = [])
    {
        $priceObj = $this->shelfOrm->getPrice()
            ->where('group_id', $priceArray['group_id'])
            ->where('is_delete', 1)
            ->first();
        if ($priceObj) {
            $priceObj->adult_price = $priceArray['adult_price'];
            $priceObj->child_price = $priceArray['child_price'];
            $priceObj->deposit_money = $priceArray['deposit_money'];
            $priceObj->save();
        } else {
            $priceArray['shelf_id'] = $this->shelfOrm->id;
            (new KktShelfPrice())->create($priceArray);
        }
        return true;
    }

    /**
     * 保存货架明细
     * @param array $groupId 货架明细数据
     * @return array(
     *  'adult_price'=> 成人价
     *  'child_price'=> 儿童价
     * )
     */
    public function getPriceForGroup($groupId = 0)
    {
        $result = ['adult_price'=>0,'child_price'=>0, 'prepay_money'=>0];
        $priceObj = $this->shelfOrm->getPrice()
            ->where('group_id',$groupId)
            ->where('is_delete', 1)
            ->first();
        if($priceObj){
            $result = [
                'adult_price'=>$priceObj->adult_price,
                'child_price'=>$priceObj->child_price,
                'prepay_money'=>$priceObj->deposit_money,
            ];
        }
        return $result;
    }


}