<?php
namespace App\Models\Group\Orm;

use App\Models\MModel;

class KktProdcutGroupStartNocie extends MModel{
	protected $table = 'kkt_product_group_start_notice';
	protected $fillable = [
		'tour_id','plan_id','path','group','name',
		'is_delete'	
	];
}	