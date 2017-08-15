<?php
namespace App\Models\Group\Orm;

use App\Models\MModel;

class KktGroupplanStockLog extends MModel
{
    public $timestamps = true;
    protected $table = 'kkt_groupplan_stock_log';
    protected $fillable = [
        'stock', 'type', 'plan_id', 'order_uuid',
        'created_at', 'updated_at','shelf_id'
    ];
}