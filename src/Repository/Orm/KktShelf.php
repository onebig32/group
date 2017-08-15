<?php
namespace Group\Repository\Orm;

use App\Models\MModel;

class KktShelf extends MModel{
	public $timestamps = true;
	protected $table = 'kkt_shelf';
	protected $fillable = [
			'name','link_type','link_uuid','created_user_id','created_organization_id',
			'is_delete','created_at','updated_at'
	];
	protected $connection = "mysql_platform";
	/**
	 * 定义与货架明细表一对多关系
	 */
	public function getDetail(){
		return $this->hasMany('App\Models\Group\Orm\KktShelfDetail', 'shelf_id', 'id');
	}

	/**
	 * 定义与货架价格表一对多关系
	 */
	public function getPrice(){
		return $this->hasMany('App\Models\Group\Orm\KktShelfPrice', 'shelf_id', 'id');
	}

}
