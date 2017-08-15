<?php
/******************************************************
 * 会销活动管理
 * 功能 1.保存团期活动库存记录 2.更新团期活动库存
 ******************************************************/
namespace App\Models\Group\Services;

use App\Models\Group\Exceptions\GroupException;
use App\Models\BusinessService;
use App\Models\Commons\TraitValidate;
use App\Models\Group\Exceptions\PromotionException;
use App\Models\Group\Exceptions\ShelfException;
use App\Models\Group\Interfaces\ShelfManager;
use App\Models\Group\Orm\KktProductGroupPlan;
use DB;

class PromotionManager extends BusinessService
{
    use TraitValidate;

    /**
     * 监听修改活动商品事件
     * @param array $messageData
     * array(
     * 'groups'=>array(
     *      array(
     *          'plan_id' =>团期计划uuid
     *          'group_id' => 活动uuid
     *          'total' => '活动占位
     *          'adult_money'=> 活动成人价
     *          'child_money'=> 活动儿童价
     *          'deposit_money'=> 活动定金价
     *     ),
     *  'meeting'=>array(
     *      name => 会销活动名称
     *      uuid => 活动uuid
     * )
     * )
     * )
     */
    public function updatePromotion($messageData)
    {
        $this->validate($messageData, [
            'groups.*' => 'required|array',
            'groups.*.plan_id' => 'required|integer',
            'groups.*.group_id' => 'required|integer',
            'groups.*.total' => 'required|integer',
            'groups.*.adult_money' => 'required|numeric',
            'groups.*.child_money' => 'required|numeric',
            'groups.*.deposit_money' => 'required|numeric',
            'meeting.name' => 'required|string',
            'meeting.uuid' => 'required|string',
        ]);

        if ($this->getErrors()) {
            throw new PromotionException('参数有误:' . json_encode($messageData), 160011);
            return false;
        }
        DB::beginTransaction();
        try {
            $shelfManager = (new ShelfManager());
            $shelfId = $shelfManager->getShelfIdForUuid($messageData['meeting']['uuid']);
            if ($shelfId == 1) {
                //新增货架
                $shelfArray = [
                    'name' => $messageData['meeting']['name'],
                    'link_type' => 2,
                    'link_uuid' => $messageData['meeting']['uuid']
                ];
                $shelfId = $shelfManager->addShelf($shelfArray);
            }
            //货架商品明细数据
            $shelfDetailArray = [];
            $planIdArray = [];
            foreach ($messageData['groups'] as $row) {
                if (in_array($row['plan_id'], $planIdArray)) {
                    continue;
                }
                //验证活动库存是否合理
                $planModel = new KktProductGroupPlan();
                $planOrm = $planModel->find($row['plan_id']);
                if (!$planOrm || !$planOrm->exist()) {
                    DB::rollBack();
                    throw new GroupException('团期不存在', 150002);
                }
                if (($planOrm->getAsideStock($shelfId) + $planOrm->getSureStock($shelfId) + $planOrm->getLockStock($shelfId)) > $row['total']) {
                    DB::rollBack();
                    throw new ShelfException('设置活动库存不能小于已有订单总游客人数', 150007);
                }
                //默认货架库存
                $defaultShelfManager = (new ShelfManager(1, $row['plan_id']));
                $defaultTakeStock = $defaultShelfManager->getTakeStock();
                $remainStock = $defaultTakeStock - ($planOrm->getAsideStock(1) + $planOrm->getSureStock(1) + $planOrm->getLockStock(1));
                //更新默认库存
                $detailObj = (new ShelfManager($shelfId))->getOrm()->getDetail()
                    ->where('plan_id', $row['plan_id'])
                    ->where('is_delete', 1)
                    ->first();
                if ($detailObj) {
                    //更新-->更新默认货架库存
                    $changeStock = $row['total'] - $detailObj->take_stock;
                    if ($changeStock > $remainStock) {
                        throw new ShelfException('默认货架库存不足分配', 150007);
                    }
                    $finalTakeStock = $defaultTakeStock - $changeStock;
                } else {
                    //新增-->更新默认货架库存
                    if ($row['total'] > $remainStock) {
                        DB::rollBack();
                        throw new ShelfException('默认货架库存不足分配', 150007);
                    }
                    $finalTakeStock = $defaultTakeStock - $row['total'];
                }
                $defaultShelfManager->saveDetail(['take_stock' => $finalTakeStock]);
                //组装货架商品数据
                $arr = [
                    'shelf_id' => $shelfId,
                    'plan_id' => $row['plan_id'],
                    'take_stock' => $row['total']
                ];
                array_push($shelfDetailArray, $arr);
                array_push($planIdArray, $row['plan_id']);
            }
            //货架商品价格数据
            $shelfPriceArray = [];
            foreach ($messageData['groups'] as $row) {
                $arr = [
                    'shelf_id' => $shelfId,
                    'group_id' => $row['group_id'],
                    'plan_id' => $row['plan_id'],
                    'adult_price' => $row['adult_money'],
                    'child_price' => $row['child_money'],
                    'deposit_money' => $row['deposit_money'],
                ];
                array_push($shelfPriceArray, $arr);
            }
            //保存货架商品明细
            foreach ($shelfDetailArray as $row) {
                $shelfManager = (new ShelfManager($shelfId, $row['plan_id']));
                $shelfManager->saveDetail($row);
            }
            //保存货架商品价格
            foreach ($shelfPriceArray as $row) {
                $shelfManager = (new ShelfManager($shelfId, $row['plan_id']));
                $shelfManager->savePrice($row);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
        DB::commit();
        return true;
    }


}