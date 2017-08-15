<?php
namespace Group;

use Illuminate\Support\ServiceProvider;

class GroupServiceProvider extends ServiceProvider{
	public function boot(){
		$this->loadMigrationsFrom(__DIR__.'/database/migrations');
	}
	
	public function register(){
		$provides =  [
			'Group\Contact\RouteServiceProvider',
		];
		foreach ($provides as $provider) {
			$this->app->register($provider);
		}


	}
}
