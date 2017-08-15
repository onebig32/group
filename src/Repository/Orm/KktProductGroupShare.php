<?php
namespace Group\Repository\Orm;

use App\Models\MModel;

class KktProductGroupShare extends MModel
{
    public $timestamps = false;
    protected $table = 'kkt_product_group_share';
    protected $fillable = [
        'sale_user_id', 'group_id', 'share_price', 'is_delete',
    ];
    protected $connection = "mysql_platform";
    /**
     * 获取分销商修改后分享价格
     * @param $userId      用户id
     * @param $groupId     团期id
     */
    public function getSharePrice($userId, $groupId)
    {
        $data = $this->select('share_price')
            ->where('sale_user_id', $userId)
            ->where('group_id', $groupId)
            ->where('is_delete', 1)
            ->first();
        return $data['share_price'] ? $data['share_price'] : 0;
    }

    /**
     * 分销商修改分享价格
     * @param $userId      用户id
     * @param $groupId     团期id
     * @param $sharePrice  分享价格
     */
    public function setSharePrice($userId, $groupId, $sharePrice)
    {
        $price = $this->getSharePrice($userId, $groupId);
        if ($price) {
            $result = $this->where('sale_user_id', $userId)
                ->where('group_id', $groupId)
                ->where('is_delete', 1)
                ->update(['share_price' => $sharePrice]);
        } else {
            $info = array(
                'sale_user_id' => $userId,
                'group_id' => $groupId,
                'share_price' => $sharePrice
            );
            $result = $this->saveData($info);
        }
        return $result;
    }
}