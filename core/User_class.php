<?php

	/*
	*	User system class for Contra. By photofroggy.
	*
	*	Released under a Creative Commons Attribution-Noncommercial-Share Alike 3.0 License, which allows you to copy, distribute, transmit, alter, transform,
	*	and build upon this work but not use it for commercial purposes, providing that you attribute me, photofroggy (froggywillneverdie@msn.com) for its creation.
	*
	*	This file contains the class used to control the access
	*	levels and the users of the bot. Created for Contra by
	*	photofroggy. Do not edit this file unless you know what
	*	you are doing.
	*
	*	Also; this class is an ugly fucker.
	*/

class User_System {
	protected $list = array();
	protected $owner;
	protected $Bot;

	public function __get($varName) { return (substr($varName, 0, 1) != '_') ? $this->$varName : Null; }

	public function __construct($owner, $bot) {
		$this->Bot = $bot;
		$this->owner = $owner;
		$user_list = 0;
		if(file_exists('./storage/users.cf')) $user_list = include './storage/users.cf';
		if(!is_array($user_list)) {
			$user_list = array(
				100 => array(),
				99 => array(),
				75 => array(),
				50 => array(),
				25 => array(),
				1 => array(),
				'override' => array('user' => array(), 'command' => array(), 'default' => 25),
				'pc' => array(
					100 => 'Owner',
					99 => 'Operators',
					75 => 'Moderators',
					50 => 'Members',
					25 => 'Guests',
					1 => 'Banned',
				),
			);
		}
		$this->list = $user_list;
		if(array_key_exists('override', $this->list) && !array_key_exists('user', $this->list['override'])) {
			$user_override = $this->list['override'];
			$this->list['override'] = array('user' => $user_override, 'command' => array());
		}
		$this->list[100][0] = $owner;
		if(!array_key_exists(25, $this->list)) $this->list[25] = array();
		if(!array_key_exists( 1, $this->list)) $this->list[ 1] = array();
		if(!array_key_exists('pc', $this->list) || !array_key_exists('override', $this->list)) {
			echo '>> ERROR: Access levels are missing vital parts! Make sure the users.cf file is correct!';
			echo chr(10),'>> Bot Closed.',chr(10);
			exit();
		}
		foreach($this->list['pc'] as $number => $name) {
			if(!is_numeric($number)) {
				echo '>> ERROR: User list contains a string for an access level! Make sure the users.cf file is correct!',chr(10);
				echo '>> Bot Closed.',chr(10);
				unset(System::$bots[$bot]);
			}
			if(!array_key_exists($number, $this->list)) $this->list[$number] = array();
			if(!isset($name)) {
				switch($number) {
					case 100: $this->list['pc'][100] = 'Owner';
						break;
					case 25: $this->list['pc'][25] = 'Guests';
						break;
					case 1: $this->list['pc'][1] = 'Banned';
						break;
					default:
						echo '>> ERROR: User list contains an unnamed access level! Make sure the users.cf file is correct!',chr(10);
						echo '>> Bot Closed.',chr(10);
						exit();
						break;
				}
			}
		}
		foreach($this->list as $id => $content) {
			if((!array_key_exists($id, $this->list['pc']) && is_numeric($id)) || !is_array($content)) {
				echo '>> ERROR: User list contains an unnamed access level! Make sure the users.cf file is correct!',chr(10);
				echo '>> Bot Closed',chr(10);
				exit();
			}
		}
		$this->UpdateList();

	}

	public function UpdateList() {
		$file = '<?php

	/*
	*		USERS FILE
	*
	*		Hello and welcome to the file containing the
	*		users and access levels for Contra! Do not
	*		mess with this file unless you know what you\'re
	*		doing.
	*
	*		You can add and remove to the user_list variable
	*		if you wish, but access levels 100, 25 and 1
	*		MUST be in there, it\'s best to leave those ones
	*		in particular untouched. You can change their
	*		names in the pc array though.
	*/

return '.var_export($this->list, true).';'.chr(10).'?>';
		file_put_contents('./storage/users.cf', $file);
		$this->list = include('./storage/users.cf');
	}

	/*
	*	USER METHODS
	*
	*	These methods allow
	*	you to manage your
	*	users. Cool I guess.
	*
	*/

	public function add($user = false, $privs = false) {					// Method 'add'. INPUT == Username as $user, privileges as $privs.
		foreach($this->list as $pc => $usr) {								// FOR each part of the list CHECK
			if(is_numeric($pc)) {											// 	IF the current privclass is a number THEN
				foreach($this->list[$pc] as $mem => $k) { 					//		FOR each part of the privclass array CHECK
					if(strtolower($k)==strtolower($user)) {					//			IF the input is the current user THEN
						return 'member of '.$this->list['pc'][$pc];			//				Return the privclass name.
					}														//			END IF
				}															//		END OF LOOP
			}																// 	END IF
		}																	// END OF LOOP
		if($privs) {														//IF $privs exists THEN
			$class = false;														//	Create $class as a boolean false
			if(is_numeric($privs)) {										//	IF $privs is a number THEN
				if(isset($this->list['pc'][$privs])) {						//		IF $priv exists as an access level THEN
					$class = $privs;										//			Change $class to $privs
				}															//		END IF
			} else {														//	ELSE
				foreach($this->list['pc'] as $pn => $pnm) {					//		FOR each part of the access levels array CHECK
					if(strtolower($pnm)==strtolower($privs)) {				//			IF the current privclass is the same as the input THEN
						$class = $pn;										//				Change $class to the current privclass.
					}														//			END IF
				}															//		END OF LOOP
			}																//	END IF
		}																	//END IF
		if($class !== false) {												//IF $class is bigger than or equal to 0 THEN
			if($class==25)													//	IF $class is 25 THEN
				return "added";												//		Return the string "added"
			if($class==100) return 'can\'t make owner';
			array_push($this->list["$class"], $user);						//	Add $user on to the selected privclass.
			$this->UpdateList();
			return "added";													//	Return the string "added".
		} else {															//ELSE
			return "no such privclass";										//	Return the string "no such privclass".
		}																	//END IF
	}																		// END OF METHOD

	public function rem($user = false) {								// Method to remove users from the user list! Yeah.
		$added = false;																// Create $added as false.
		if(strtolower($user)!=strtolower($this->owner)) {			// IF the input user is not the owner of the bot THEN
			foreach($this->list as $pc => $usr) {									//	FOR EACH item in $list as $pc => $usr DO
				if(is_numeric($pc)) {												//		IF the current privclass is a number THEN
					foreach($this->list[$pc] as $mem => $k) { 						//			FOR EACH user in the current privclass DO
						if(strtolower($k)==strtolower($user)) {						//				IF the current user is the input user THEN
							unset($this->list[$pc][$mem]);							//					Delete the user from the list.
							$this->UpdateList();
							return "removed";										//					Return the string "removed".
						}															//				END IF
					}																//			END LOOP
				}																	//		END IF
			}																		//	END LOOP
			return "no such user";													//	Return string "no such user". Shouldn't get here if input user is a user of the bot.
		} else {																	// ELSE
			return "can't remove owner";											//	Return string "can't remove owner"
		}																			// END IF
	}																	// End of method lols

	public function has($user = false, $privs = false) {				// Method to check $user's privs
		$tapriv = $class = false;
		if($user) {
			foreach($this->list as $pc => $usr)
				if(is_numeric($pc))
					foreach($this->list[$pc] as $mem => $k)
						if(strtolower($k)==strtolower($user))
							$tapriv = $pc;
			if(!$tapriv) $tapriv = isset($this->list['override']['default']) ? $this->list['override']['default'] : 25;
			if($privs) {
				$class = is_numeric($privs) ? $privs : $this->priv->Number($priv);
				if($class) return ($tapriv >= $class ? true : false);
			}
			return $tapriv;
		}
		return false;
	}

	public function hasCmd($user, $cmd) {
		if(!array_key_exists(strtolower($cmd), $this->Bot->Events->events['cmd'])) return false;
		$has = false; $cmdd = $this->Bot->Events->events['cmd'][strtolower($cmd)];
		if(isset($this->list['override']['command'][$cmd]))
			$has = $this->has($user, $this->list['override']['command'][$cmd]);
		else $has = $this->has($user, $cmdd['p']);
		$over = $this->overrides($user);
		if($over!==false) {
			if(in_array(strtolower($cmd),$over['allow'])) $has = true;
			if(in_array(strtolower($cmd),$over['ban'])) $has = false;
		}
		return $has;
	}

	/*
	*	OVERRIDE METHODS
	*
	*	These ones allow you to give
	*	people access to commands that
	*	they wouldn't usually have access
	*	to. This can be useful in certain
	*	situations.
	*
	*/

	public function addCmd($user, $cmd) {
		if(!array_key_exists(strtolower($cmd), $this->Bot->Events->events['cmd'])) return false;
		$over = $this->overrides($user);
		if(in_array(strtolower($cmd),$over['allow']))  return true;
		if(in_array(strtolower($cmd),$over['ban']))
			$this->list['override']['user'][$over['user']]['ban'] =
				array_del_key($over['ban'],array_search(strtolower($cmd),$over['ban']));
		$this->list['override']['user'][$user]['allow'][] = strtolower($cmd);
		$this->list['override'][$user] = array(
			'ban' => array(),
			'allow' => array(strtolower($cmd)),
		);
		$this->UpdateList();
		return true;
	}
	public function banCmd($user, $cmd) {
		if(!array_key_exists(strtolower($cmd), $this->Bot->Events->events['cmd'])) return false;
		$over = $this->overrides($user);
		if(in_array(strtolower($cmd),$over['ban']))  return true;
		if(in_array(strtolower($cmd),$over['allow']))
			$this->list['override']['user'][$over['user']]['allow'] =
				array_del_key($over['allow'],array_search(strtolower($cmd),$over['allow']));
		$this->list['override']['user'][$over['user']]['ban'][] = strtolower($cmd);
		$this->list['override']['user'][$user] = array(
			'ban' => array(strtolower($cmd)),
			'allow' => array(),
		);
		$this->UpdateList();
		return true;
	}
	public function remCmd($user, $cmd) {
		if(!array_key_exists(strtolower($cmd), $this->Bot->Events->events['cmd'])) return true;
		$over = $this->overrides($user);
		if(in_array(strtolower($cmd),$over['allow']))
			$this->list['override']['user'][$over['user']]['allow'] =
				array_del_key($over['allow'],array_search(strtolower($cmd),$over['allow']));
		if(in_array(strtolower($cmd),$over['ban']))
			$this->list['override']['user'][$over['user']]['ban'] =
				array_del_key($over['ban'],array_search(strtolower($cmd),$over['ban']));
		if(empty($this->list['override']['user'][$over['user']]['allow'])
		&& empty($this->list['override']['user'][$over['user']]['ban']))
			unset($this->list['override']['user'][$over['user']]);
		$this->UpdateList();
		return true;
	}
	public function overrides($user) {
		foreach($this->list['override']['user'] as $suser => $commands) {
			if(strtolower($user) == strtolower($suser)) {
				$commands['user'] = $suser;
				$this->UpdateList();
				return $commands;
			}
		}
		return false;
	}
	public function addOverride($cmd, $lvl)
	{
		if(array_key_exists(strtolower($cmd), $this->Bot->Events->events['cmd']))
		{
			$this->list['override']['command'][$cmd] = $lvl;
			$this->UpdateList();
			return true;
		}
		return false;
	}
	public function delOverride($cmd)
	{
		if(array_key_exists($cmd, $this->list['override']['command']))
		{
			unset($this->list['override']['command'][$cmd]);
			$this->UpdateList();
			return true;
		}
		return false;
	}

	/*
	*	PRIVCLASS METHODS
	*
	*	These methods are used to find and
	*	edit privclasses appropriately.
	*	This allows for more customization.
	*
	*/

	public function class_name($order=false) {
		if(!is_numeric($order)) $order = $this->class_order($order);
		if($order===false) return false;
		if(array_key_exists($order, $this->list['pc']))
			return $this->list['pc'][$order];
		return false;
	}
	public function class_order($name=false) {
		if(is_numeric($name)) $name = $this->class_name($name);
		if($name===false) return false;
		foreach($this->list['pc'] as $ord => $pc)
			if(strtolower($pc)==strtolower($name))
				return $ord;
		return false;
	}
	public function add_class($name, $order) {
		if($this->class_order($name)!==false)
			return false;
		if($this->class_name($order)!==false)
			return false;
		$this->list['pc'][$order] = $name;
		$pcs = $this->list;
		unset($pcs['override']['user']); unset($pcs['pc']);
		$pcs[$order] = array(); krsort($pcs);
		$ovrs = $this->list['override']['user'];
		$names = $this->list['pc']; krsort($names);
		$this->list = $pcs; $this->list['override']['user'] = $ovrs;
		$this->list['pc']=$names;
		$this->UpdateList();
		return true;
	}
	public function rename_class($pc, $new) {
		if(is_numeric($pc)) {
			$pc = $this->class_name($pc);
			if($pc!==false) $pc = $this->class_order($pc);
		} else { $pc = $this->class_order($pc); }
		if($pc===false) return false;
		$this->list['pc'][$pc] = $new;
		$this->UpdateList();
		return true;
	}
	public function rem_class($pc) {
		if(is_numeric($pc)) {
			$pc = $this->class_name($pc);
			if($pc!==false) $pc = $this->class_order($pc);
		} else { $pc = $this->class_order($pc); }
		if($pc===false) return false;
		if($pc==100) return false;
		unset($this->list[$pc]);
		unset($this->list['pc'][$pc]);
		$this->UpdateList();
		return true;
	}

	public function defaultClass($pc) {
		if(is_numeric($pc)) {
			$pc = $this->class_name($pc);
			if($pc !== false) $pc = $this->class_order($pc);
		}
		if($pc === false) return false;
		$this->list['override']['default'] = $pc;
		$this->UpdateList();
		return true;
	}
}

?>