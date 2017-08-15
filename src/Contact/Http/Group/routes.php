<?php
/**********************************************************
 * 产品路线团期计划路由
 * 路由参数说明
 * 'dir.control.method:type:priv'
 * dir.control.method 对应模块、控制器、方法
 * type 'GET', 'PUT'、'DELETE'、'POST'
 * priv 权限级别 1:无权限设置 ,2菜单级别权限, 3数据级别权限
 **********************************************************/
Route::post('/group/planinfo/getdetailforgroupajax', 'Group\\PlanInfo@getDetailForGroupAjax');
Route::post('/group/planinfo/getgroupsfortourajax', 'Group\\PlanInfo@getGroupsForTourAjax');
Route::post('/group/planinfo/getgroupsforuserajax', 'Group\\PlanInfo@getGroupsForUserAjax');
Route::post('/group/planinfo/getgroupforproductajax', 'Group\\PlanInfo@getGroupForProductAjax');
Route::post('/group/planinfo/getgroupsforrolewxajax', 'Group\\PlanInfo@getGroupsForRoleWxAjax');
return [
    'Group.PlanInfo.saleList:GET:3', 'Group.PlanInfo.pListForUserAjax:POST:4',
    'Group.PlanInfo.opList:GET:3', 'Group.PlanInfo.gListForRoleAjax:POST',
    'Group.PlanInfo.pList:GET:3', 'Group.PlanInfo.saveAjax:PATCH',
    'Group.PlanInfo.deletePlanAjax:DELETE:3', 'Group.PlanInfo.save:GET:3',
    'Group.PlanInfo.detail:GET:3', 'Group.PlanInfo.releasePlanAjax:POST',
    'Group.PlanInfo.getGroupsForRoleAjax:POST', 'Group.PlanInfo.copyPriv:POST:3',
    'Group.PlanInfo.closePlanPriv:POST:3', 'Group.PlanInfo.getSimpleAjax:GET:2',
    'Group.PlanInfo.detailAjax:POST', 'Group.PlanInfo.lockPlanAjax:PUT:3',
    'Group.PlanInfo.setCreatedUserAjax:POST:3', 'Group.PlanInfo.designateAjax:PUT:3',
    //团期日志
    'Group.PlanLogInfo.logAjax:POST',

];