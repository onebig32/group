<?php
namespace Group\Repository\Orm;

use App\Models\MModel;

class KktShelfPrice extends MModel{

	public $timestamps = true;
	protected $table = 'kkt_shelf_price';
	protected $fillable = [
			'shelf_id','group_id','plan_id','adult_price','child_price','deposit_money',
			'is_delete','created_at','updated_at'
	];
	protected $connection = "mysql_platform";
}
