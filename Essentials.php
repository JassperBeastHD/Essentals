<?php

/*
__PocketMine Plugin__
name=Essentials
description=Essentials
version=2.1
author=KsyMC
class=Essentials
apiversion=10
*/

class Essentials implements Plugin{
	private $api, $server, $lang, $data, $motd, $lastafk, $afk;
	
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->data = array();
		$this->lastafk = array();
		$this->afk = array();
		$this->server = ServerAPI::request();
		EssentialsAPI::setEssentials($this);
	}
	
	public function init(){
		$this->api->console->register("clearinventory", "[player] [item]", array($this, "defaultCommands"));
		
		
		console("[INFO] Essentials enabled!");
		}
	
	public function __destruct(){}
	
	
	
	public function defaultCommands($cmd, $params, $issuer, $alias){
		$output = "";
		switch($cmd){
			case "afk":
				if(!($issuer instanceof Player)){
					$output .= "Please run this command in-game.\n";
					break;
				}
				$this->api->chat->broadcast($this->getMessage("userIsAway", array($issuer->username, "", "", "")));
				$this->afk[$issuer->iusername] = true;
				break;
			case "home":
				if(!($issuer instanceof Player)){
					$output .= "Please run this command in-game.\n";
					break;
				}
				$homes = $this->getData($issuer, "home");
				if($homes === false){
					$output .= "You do not have a home.\n";
					break;
				}
				if($params[0] == ""){
					$output = "Homes: ";
					foreach($homes as $home => $data){
						$output .= "$home, ";
					}
					break;
				}
				if($homes[$params[0]]["world"] !== $issuer->level->getName()){
					$this->api->player->teleport($issuer->iusername, "w:".$homes[$params[0]]["world"]);
				}
				$this->api->player->tppos($issuer->iusername, $homes[$params[0]]["x"], $homes[$params[0]]["y"], $homes[$params[0]]["z"]);
				break;
			case "sethome":
				if(!($issuer instanceof Player)){
					$output .= "Please run this command in-game.\n";
					break;
				}
				if($params[0] == ""){
					$output .= "Usage: /$cmd <name>\n";
					break;
				}
				$homes = $this->getData($issuer, "home");
				$homes[$params[0]] = array(
					"world" => $issuer->level->getName(),
					"x" => $issuer->entity->x,
					"y" => $issuer->entity->y,
					"z" => $issuer->entity->z
				);
				$this->setData($issuer, "home", $homes);
				$output .= "Your home has been saved.\n";
				break;
			case "delhome":
				if(!($issuer instanceof Player)){
					$output .= "Please run this command in-game.\n";
					break;
				}
				if($params[0] == ""){
					$output .= "Usage: /$cmd <name>\n";
					break;
				}
				$homes = $this->getData($issuer, "home");
				unset($homes[$params[0]]);
				$this->setData($issuer, "home", $homes);
				$output .= "Your home has been deleted.\n";
				break;
			case "back":
				if(!($issuer instanceof Player)){
					$output .= "Please run this command in-game.\n";
					break;
				}
				$pos = $this->getData($issuer, "lastlocation");
				if($pos !== false){
					$name = $issuer->iusername;
					if($pos["world"] !== $issuer->level->getName()){
						$this->api->player->teleport($name, "w:".$pos["world"]);
					}
					$this->api->player->tppos($name, $pos["x"], $pos["y"], $pos["z"]);
					$output .= $this->getMessage("backUsageMsg");
				}
				break;
			case "tree":
				if(!($issuer instanceof Player)){
					$output .= "Please run this command in-game.\n";
					break;
				}
				switch(strtolower($params[0])){
					case "redwood":
						$meta = 1;
						break;
					case "brich":
						$meta = 2;
						break;
					case "tree":
						$meta = 0;
						break;
					default:
						$output .= "Usage: /$cmd <tree|brich|redwood>\n";
						break 2;
				}
				TreeObject::growTree($issuer->level, new Vector3 (((int)$issuer->entity->x), ((int)$issuer->entity->y), ((int)$issuer->entity->z)), new Random(), $meta);
				$output .= $this->getMessage("treeSpawned");
				break;
			case "setspawn":
				if(!($issuer instanceof Player)){
					$output .= "Please run this command in-game.\n";
					break;
				}
				$pos = new Vector3(((int)$issuer->entity->x + 0.5), ((int)$issuer->entity->y), ((int)$issuer->entity->z + 0.5));
				$output .= "Spawn location set.\n";
				$issuer->level->setSpawn($pos);
				break;
			case "mute":
				if($params[0] == ""){
					$output .= "Usage: /$cmd <player>\n";
					break;
				}
				$target = $this->api->player->get($params[0]);
				if($target === false){
					$output .= $this->getMessage("playerNotFound");
					break;
				}
				if($this->getData($target, "mute") === false){
					$output .= "Player ".$target->username." muted.\n";
					$target->sendChat($this->getMessage("playerMuted"));
					$this->setData($issuer, "mute", true);
				}else{
					$output .= "Player ".$target->username." unmuted.\n";
					$target->sendChat($this->getMessage("playerUnmuted"));
					$this->setData($issuer, "mute", false);
				}
				break;
			case "burn":
				if($params[0] == "" or $params[1] == ""){
					$output .= "Usage: /$cmd <player> <seconds>\n";
					break;
				}
				$seconds = (int)$params[1];
				$target = $this->api->player->get($params[0]);
				if($target === false){
					$output .= $this->getMessage("playerNotFound");
					break;
				}
				$target->entity->fire = $seconds * 20;
				$target->entity->updateMetadata();
				$output .= $this->getMessage("burnMsg", array($target->username, $seconds, "", ""));
				break;
			case "kickall":
				$reason = "";
				if($params[0] != ""){
					$reason = $params[0];
				}
				foreach($this->api->player->online() as $username){
					$this->api->ban->kick($username, $reason);
				}
				break;
			case "killall":
				$reason = "";
				if($params[0] != ""){
					$reason = $params[0];
				}
				foreach($this->api->player->online() as $username){
					$target = $this->api->player->get($username);
					$this->api->entity->harm($target->eid, 3000, $reason);
				}
				break;
			case "heal":
				if(!($issuer instanceof Player)){
					$output .= "Please run this command in-game.\n";
					break;
				}
				$target = $issuer;
				if($params[0] != ""){
					$target = $this->api->player->get($params[0]);
					if($target === false){
						$output .= $this->getMessage("playerNotFound");
						break;
					}
				}
				$this->api->entity->heal($target->eid, 20);
				break;
			case "clearinventory":
				if(!($issuer instanceof Player)){
					$output .= "Please run this command in-game.\n";
					break;
				}
				$target = $issuer;
				if($params[0] != ""){
					$target = $this->api->player->get($params[0]);
					if($target === false){
						$output .= "playerNotFound";
						break;
					}
				}
				if($target->gamemode === CREATIVE){
					$output .= "Player is in creative mode.\n";
					break;
				}
				if($params[1] != ""){
					$item = BlockAPI::fromString($params[1]);
				}
				foreach($issuer->inventory as $slot => $data){
					if(isset($item) and $item->getID() !== $data->getID()){
						continue;
					}
					$issuer->setSlot($slot, BlockAPI::getItem(AIR, 0, 0));
				}
				$output .= "Inventory cleared.";
				break;
		}
		return $output;
	}
	
	}
}
