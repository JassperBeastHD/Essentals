<?PHP

/*
__PocketMine Plugin__
name=Essentials
version=0.0.1
description=Essentials
author=KsyMC
class=Essentials
apiversion=10
*/

class Essentials implements Plugin{
	private $api;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	
	public function init(){
		
	}
	
	public function __destruct(){}
}