<?php

/*
__PocketMine Plugin__
name=EssentialsLogin
description=EssentialsLogin
version=2.0
author=KsyMC
class=EssentialsLogin
apiversion=10
*/

class EssentialsLogin implements Plugin{
	private $api, $config, $status, $lang, $forget, $data;
	
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->status = array();
		$this->forget = array();
		$this->data = array();
	}
	
	public function init(){
		$this->api->event("server.close", array($this, "close"));
		$this->api->addHandler("player.join", array($this, "newPlayer"), 50);
		$this->api->addHandler("player.spawn", array($this, "resetPlayer"), 1);
		$this->api->addHandler("player.respawn", array($this, "resetPlayer"), 50);
		$this->api->addHandler("player.chat", array($this, "permissionCheck"), 50);
		$this->api->addHandler("console.command", array($this, "permissionCheck"), 50);
		
		$this->api->console->register("logout", "", array($this, "commandHandler"));
		$this->api->console->register("changepassword", "<oldpassword> <newpassword>", array($this, "commandHandler"));
		$this->api->console->register("unregister", "<password>", array($this, "commandHandler"));
		$this->readConfig();
		
		console("[INFO] EssentialsLogin enabled!");
		$this->api->schedule(20, array($this, "checkTimer"), array(), true);
	}
	
	public function __destruct(){}
	
	public function readConfig(){
		$this->path = DATA_PATH."/plugins/Essentials/";
		$this->config = $this->api->plugin->readYAML($this->path."config.yml");
		if(file_exists($this->path."messages.yml")){
			$this->lang = new Config($this->path."messages.yml", CONFIG_YAML);
		}
		if(file_exists($this->path."Logindata.dat")){
			$this->data = unserialize(file_get_contents($this->path."Logindata.dat"));
		}
	}
	
	public function close($data, $event){
		file_put_contents("./plugins/Essentials/Logindata.dat", serialize(array("password" => $this->data["password"], "registered" => $this->data["registered"])));
	}
	
	public function checkTimer(){
		foreach($this->api->player->online() as $username){
			$player = $this->api->player->get($username);
			if(!isset($this->data[$player->iusername])) return;
			
			if($this->status[$player->iusername] == "logout"){
				if((time() - $this->data[$player->iusername]["lastconnected"]) >= $this->config["login"]["timeout"]){
					$this->api->ban->kick($username, "authentication timeout");
				}
			}
		}
	}
	
	public function newPlayer(Player $player, $event = false){
		$this->data[$player->iusername]["lastconnected"] = time();
		if(!isset($this->data["password"][$player->iusername])){
			$this->data["registered"][$player->iusername] = false;
		}
		$this->status[$player->iusername] = "logout";
		$this->forget[$player->iusername] = 0;
	}
	
	public function resetPlayer(Player $player, $event = false){
		if($event == "player.spawn"){
			$this->data[$player->iusername]["spawnpos"] = array(
				$player->entity->x,
				$player->entity->y,
				$player->entity->z
			);
			foreach($player->inventory as $slot => $item){
				$this->data[$player->iusername]["inventory"][$slot] = $player->getSlot($slot);
				$player->setSlot($slot, BlockAPI::getItem(AIR, 0, 0));
			}
			$username = $player->username;
			$spawn = $player->level->getSpawn();
			$this->api->player->tppos($username, $spawn->x, $spawn->y, $spawn->z);
		}
		if(!$this->config["login"]["allow-move"] and $this->status[$player->iusername] == "logout"){
			$player->blocked = true;
		}
	}
	
	public function permissionCheck($data, $event){
		switch($event){
			case "console.command":
				if(!($data["issuer"] instanceof Player)) break;
				if($this->status[$data["issuer"]->iusername] == "logout"){
					if($data["cmd"] == "register" and $this->config["login"]["allow-register"]){
						if($data["parameters"][0] == "" or $data["parameters"][1] == ""){
							$data["issuer"]->sendChat("Usage: /register <password> <password>");
							return true;
						}
						if($data["parameters"][0] !== $data["parameters"][1]){
							$data["issuer"]->sendChat($this->getMessage("enterPasswordAgain"));
							return true;
						}
						$password = $data["parameters"][0];
						if(strlen($password) < $this->config["login"]["pw-higher-then"] or strlen($password) > $this->config["login"]["pw-less-then"]){
							$data["issuer"]->sendChat($this->getMessage("passwordIncorrect", array($this->config["login"]["pw-higher-then"], $this->config["login"]["pw-less-then"], "", "")));
							return true;
						}
						if($this->data["registered"][$data["issuer"]->iusername] === true){
							$data["issuer"]->sendChat($this->getMessage("alreadyRegistered"));
							return true;
						}
						$this->setPlayerPassword($data["issuer"], $password);
						$data["issuer"]->sendChat($this->getMessage("register"));
						$this->api->handle("essentials.player.register", $data["issuer"]);
						return true;
					}else if($data["cmd"] == "login"){
						if($data["parameters"][0] == ""){
							$data["issuer"]->sendChat("Usage: /login <password>");
							return true;
						}
						$password = $data["parameters"][0];
						if($this->data["registered"][$data["issuer"]->iusername] === false){
							$data["issuer"]->sendChat($this->getMessage("notRegistered"));
							return true;
						}
						$realpassword = $this->data["password"][$data["issuer"]->iusername];
						if(!$this->comparePassword($password, $realpassword)){
							if($this->config["login"]["login-kick"] > 0){
								$data["issuer"]->sendChat($this->getMessage("notPasswordMatch", array($this->forget[$data["issuer"]->iusername], $this->config["login"]["login-kick"], "", "")));
								if($this->forget[$data["issuer"]->iusername] >= $this->config["login"]["login-kick"]){
									$this->api->ban->kick($data["issuer"]->username, $this->getMessage("notPasswordMatch"));
									return true;
								}
							}else{
								$data["issuer"]->sendChat($this->getMessage("notPasswordMatch"));
							}
							$this->forget[$data["issuer"]->iusername] += 1;
							return true;
						}
						$data["issuer"]->sendChat($this->getMessage("login"));
						$this->status[$data["issuer"]->iusername] = "login";
						
						$data["issuer"]->blocked = false;
						$this->returnPlayer($data["issuer"]);
						$this->api->handle("essentials.player.login", $data["issuer"]);
						return true;
					}else if(!in_array($data["cmd"], $this->config["login"]["allow-commands"])){
						return false;
					}
				}
				break;
			case "player.chat":
				if($this->status[$data["player"]->iusername] == "logout" and !$this->config["login"]["allow-chat"]){
					return false;
				}
				break;
		}
	}
	
	public function returnPlayer(Player $player){
		foreach($this->data[$player->iusername]["inventory"] as $slot => $item){
			$player->setSlot($slot, $item);
		}
		$pos = $this->data[$player->iusername]["spawnpos"];
		$username = $player->username;
		$this->api->player->tppos($username, $pos[0], $pos[1], $pos[2]);
	}
	
	public function setPlayerPassword(Player $player, $password, $remove = false){
		if($remove === false){
			$this->data["password"][$player->iusername] = hash("sha256", $password);
			$this->data["registered"][$player->iusername] = true;
		}else{
			unset($this->data["password"][$player->iusername]);
			$this->data["registered"][$player->iusername] = false;
		}
	}
	
	public function comparePassword($password, $hash){
		if(hash("sha256", $password) === $hash){
			return true;
		}
		return false;
	}
	
	public function commandHandler($cmd, $params, $issuer, $alias){
		$output = "";
		switch($cmd){
			case "logout":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				if($this->status[$issuer->iusername] == "logout"){
					$output .= $this->getMessage("notLogged");
					break;
				}
				$this->status[$issuer->iusername] = "logout";
				$output .= $this->getMessage("logout");
				
				$this->resetPlayer($issuer);
				$this->api->handle("essentials.player.logout", $issuer);
				break;
			case "changepassword":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				if($params[0] == "" or $params[1] == ""){
					$output .= "Usage: /changepassword <oldpassword> <newpassword>\n";
					break;
				}
				$oldpassword = $params[0];
				$newpassword = $params[1];
				if($this->data["registered"][$issuer->iusername] === false){
					$output .= $this->getMessage("notRegistered");
					break;
				}
				if($this->status[$issuer->iusername] == "logout"){
					$output .= $this->getMessage("notLogged");
					break;
				}
				$realpassword = $this->data["password"][$issuer->iusername];
				if(!$this->comparePassword($oldpassword, $realpassword)){
					$output .= $this->getMessage("enterPasswordAgain");
				}
				$this->setPlayerPassword($issuer, $newpassword);
				$output .= $this->getMessage("changepassword");
				break;
			case "unregister":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				if($params[0] == ""){
					$output .= "Usage: /unregister <password>\n";
					break;
				}
				$password = $params[0];
				if($this->data["registered"][$issuer->iusername] === false){
					$output .= $this->getMessage("notRegistered");
					break;
				}
				$realpassword = $this->data["password"][$issuer->iusername];
				if(!$this->comparePassword($password, $realpassword)){
					$output .= $this->getMessage("notPasswordMatch", array($this->forget[$issuer->iusername], $this->config["login"]["kick-on-wrong-password"]["count"]));
					break;
				}
				$this->setPlayerPassword($issuer, false, true);
				$output .= $this->getMessage("unregister");
				
				$this->resetPlayer($issuer);
				$this->status[$issuer->iusername] = "logout";
				break;
		}
		return $output;
	}
	
	public function getMessage($msg, $params = array("%1", "%2", "%3", "%4")){
		$msgs = array_merge($this->lang->get("Default"), $this->lang->get("Login"));
		if(!isset($msgs[$msg])){
			$msgs[$msg] = "Undefined message: $msg";
		}
		return str_replace(array("%1", "%2", "%3", "%4"), array($params[0], $params[1], $params[2], $params[3]), $msgs[$msg])."\n";
	}
}