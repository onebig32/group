<?php
/******************************************************
 * 产品团期库存管理
 * 功能 1.预先占用库存 2.更新库存
 ******************************************************/
namespace App\Models\Group\Services;

use App\Models\Group\Exceptions\GroupException;
use App\Models\Group\Exceptions\ShelfException;
use App\Models\Group\Interfaces\ShelfManager;
use App\Models\Group\Orm\KktGroupplanStockLog;
use App\Models\Group\Orm\KktGroupplanStockProof;
use App\Models\Group\Orm\KktProductGroupPlan;
use Cache;
use DB;

class StockManager
{
    private $planOrm;

    public function __construct(KktProductGroupPlan $planOrm)
    {
        $this->planOrm = $planOrm;
        if (!$this->planOrm || !$this->planOrm->exist()) {
            throw new GroupException('团期不存在', 150002);
        }
    }

    /**
     * 预先占用团期计划库存
     * @param int $useStockNum 预先占用的库存数
     * @param int $shelfId 货架ID，默认为1
     * @return string $token 库存使用令牌
     */
    public function preUseStock($useStockNum = 0, $shelfId = 1)
    {
        //判断货架库存是否充足
        $takeStock = (new ShelfManager($shelfId,$this->planOrm->id))->getTakeStock();
        $remainStock = $takeStock - ($this->planOrm->getSureStock() + $this->planOrm->getAsideStock() + $this->planOrm->getLockStock());
        if ($useStockNum > $remainStock) {
            throw new ShelfException('库存不足', 150006);
        }
        $cacheKey = 'kkt_product_group_plan_' . $this->planOrm->id . '_shelf_' . $shelfId;
        $result = [];
        if ($cacheValue = Cache::get($cacheKey)) {
            $result = unserialize($cacheValue);
            $preUseNum = array_sum(array_pluck($result, 'stock_num'));
            //判断货架库存是否充足
            if (($preUseNum + $useStockNum) > $remainStock) {
                throw new ShelfException('库存不足', 150006);
            }
        }
        $token = uuid();
        array_push($result, ['plan_id' => $this->planOrm->id, 'stock_num' => $useStockNum, 'token' => $token, 'shelf_id' => $shelfId]);
        Cache::put($cacheKey, serialize($result), 1);

        return $token;
    }

    /**
     * 更新团期计划库存
     * @param int $stockType 团期库存类型 1 预留库存 2 锁位库存 3 确认库存
     * @param int $changeNum 更新库存数量
     * @param int $orderUuid 订单uuid
     * @param int $finalStock 订单最终占用库存数
     * @param string $token 库存使用令牌
     * @param string $shelfId 货架id，默认为1
     */
    public function updateStock($stockType = 0, $changeNum = 0, $orderUuid = '', $finalStock = 0, $token = '', $shelfId = 1)
    {
        //记录库存更新日志
        $stockLogModel = new KktGroupplanStockLog();
        $logData = [
            'type' => $stockType,
            'stock' => $changeNum,
            'plan_id' => $this->planOrm->id,
            'order_uuid' => $orderUuid,
            'shelf_id' => $shelfId
        ];
        $stockLogModel->saveData($logData);

        //判断库存令牌是否有效
        if ($changeNum > 0) {
            $cacheKey = 'kkt_product_group_plan_' . $this->planOrm->id . '_shelf_' . $shelfId;;
            if ($cacheValue = Cache::get($cacheKey)) {
                $cacheArray = unserialize($cacheValue);
                $allowTokenArray = array_pluck($cacheArray, 'token');
                if (!in_array($token, $allowTokenArray)) {
                    throw new ShelfException('占用库存过期', 150008);
                }
            }
        }
        try {
            //开启事务
            DB::beginTransaction();
            $proofOrm = $this->getProofModel()
                ->where('plan_id', $this->planOrm->id)
                ->where('order_uuid', $orderUuid)
                ->first();
            if ($proofOrm) {
                //更新库存凭证
                $proofOrm->type = $stockType;
                $proofOrm->stock = $finalStock;
                $proofOrm->save();
            } else {
                if ($changeNum < 0 || $changeNum != $finalStock) {
                    DB::rollBack();
                    throw new GroupException('非法数据提交', 150010);
                }
                //插入库存凭证
                $proofData = [
                    'type' => $stockType,
                    'plan_id' => $this->planOrm->id,
                    'order_uuid' => $orderUuid,
                    'stock' => $finalStock,
                    'shelf_id' => $shelfId
                ];
                $this->getProofModel()->saveData($proofData);
            }
            //判断货架库存是否充足
            $takeStock = (new ShelfManager($shelfId,$this->planOrm->id))->getTakeStock();
            $proofStockSum = $this->getProofModel()->where('plan_id', $this->planOrm->id)->sum('stock');
            if ($proofStockSum > $takeStock) {
                DB::rollBack();
                throw new ShelfException('库存不足', 150006);
            }
            //更新团期库存
            $this->planOrm->updateStock();
            DB::commit();
            //清除对应token的缓存
            if ($changeNum > 0) {
                foreach ($cacheArray as $key => $arr) {
                    if ($arr['token'] == $token) {
                        unset($cacheArray[$key]);
                    }
                }
                Cache::put($cacheKey, serialize($cacheArray), 1);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * 获取货架占用团期计划库存数
     * @param int 货架id
     * @return int 预先占用库存数
     */
    public function getPreUseStockNum($shelfId = 0)
    {
        $cacheKey = 'kkt_product_group_plan_' . $this->planOrm->id.'_shelf_'.$shelfId;
        if ($cacheValue = Cache::get($cacheKey)) {
            $result = unserialize($cacheValue);
            $preUseNum = array_sum(array_pluck($result, 'stock_num'));
            return $preUseNum;
        }
        return 0;
    }



    private function getProofModel()
    {
        return new KktGroupplanStockProof();
    }

}