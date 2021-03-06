<?php

        /*
        *       dAmn.Lib version 3 by photofroggy
        *
        *       Released under a Creative Commons Attribution-Noncommercial-Share Alike 3.0 License, which allows you to copy, distribute, transmit, alter, transform,
        *       and build upon this work but not use it for commercial purposes, providing that you attribute me, photofroggy (froggywillneverdie@msn.com) for its creation.
        *
        *       This class manages the incoming data for dAmn sockets
        *       in Contra.
        */

class dAmn_lib extends extension {
	public $name = 'dAmn.Lib';
	public $status = true;
	public $author = 'photofroggy';
	public $type = EXT_LIBRARY;

	public $ticker = 0;

	function init() {
		// We need to actually hook some events so we can make the bot work properly.
		// Hooking certain events also allows us to keep our data up to date.
		$this->hook('e_startup', 'startup');
		$this->hook('e_cookie', 'cookie');
		$this->hook('e_damntoken', 'damntoken');
		$this->hook('e_loop', 'loop');
		$this->hook('process', 'packet');
		$this->hook('e_disconnect', 'disconnect');
		$this->hook('e_connected', 'connected');
		$this->hook('e_ping', 'ping');
		$this->hook('e_check_msg', 'recv_msg');
		$this->hook('e_join', 'join');
		$this->hook('e_join2', 'login');
		$this->hook('e_recv_join', 'recv_join');
		$this->hook('e_recv_part', 'recv_part');
		$this->hook('e_recv_privchg', 'recv_privchg');
		$this->hook('e_recv_kicked', 'recv_kicked');
	}

	function e_startup() {
		$this->hook('e_part', 'part');
		$this->hook('e_property', 'property');
		$this->unhook('e_startup', 'startup');
	}

	function e_cookie($e) {
		$this->unhook('e_cookie', 'cookie');
		if($e['status'] == 1) {
			$this->Bot->cookie = $e['cookie'];
			$this->dAmn->cookie = $e['cookie'];
			$this->Bot->save_config();
			if(!$this->Bot->usingStored) {
				$this->Console->Notice('Got a valid cookie!');
				$this->log('~Server', ' Got a valid cookie!', time());
			}
			$this->dAmn->trigger = $this->Bot->trigger;
			$this->dAmn->owner = $this->Bot->owner;
			$this->ticker = 0;
			if(DEBUG) {
				$this->Console->Write('Data received:'.chr(10));
				$this->Console->Write($this->dAmn->cookie);
			}
			if($this->dAmn->connect()) {
				if(DEBUG) {
					$this->Console->Notice('Opened a connection with '.$this->dAmn->server['chat']['host'].':'.$this->dAmn->server['chat']['port'].'!');
					$this->Console->Notice('Waiting for handshake...');
				}
				$this->Bot->running = true;
			} else {
				if(DEBUG) $this->Console->Warning('Failed to open a connection with '
						.$this->dAmn->server['chat']['host'].':'.$this->dAmn->server['chat']['port'].'!');
				$this->Bot->running = false;
			}
		} else {
			$this->Console->Warning('Failed to get a cookie!');
			$this->Console->Warning($e['error'].'.');
			if($e['status'] >= 4 && $e['status'] != 6)
				$this->Console->Warning('Make sure your login details are correct!');
			$this->Bot->running = false;
		}
	}

	function e_damntoken() {
		$this->unhook('e_damntoken', 'damntoken');
		$this->Bot->damntoken = !$this->Bot->usingStored ? $this->dAmn->damntoken->damntoken : $this->Bot->damntoken;
		$this->Bot->save_config();
		if(!$this->Bot->usingStored) {
			$this->Console->Notice('Got a valid damntoken!');
			$this->log('~Server', ' Got a valid damntoken!', time());
		}
		$this->dAmn->trigger = $this->Bot->trigger;
		$this->dAmn->owner = $this->Bot->owner;
		$this->ticker = 0;
		if(DEBUG) {
			$this->Console->Write('Data received:'.chr(10));
			$this->Console->Write(!$this->Bot->usingStored ? $this->dAmn->damntoken->damntoken : $this->Bot->damntoken);
		}
		if($this->dAmn->connect()) {
			if(DEBUG) {
				$this->Console->Notice('Opened a connection with '.$this->dAmn->server['chat']['host'].':'.$this->dAmn->server['chat']['port'].'!');
				$this->Console->Notice('Waiting for handshake...');
			}
		}
		$this->Bot->running = true;
	}

	function e_loop() {
		$dAmn = $this->dAmn;
		if($dAmn->connected == false && $dAmn->close == false && $dAmn->connecting == false && $dAmn->login == false) {
			$this->Bot->network(true);
		} else {
			if($dAmn->connected||$dAmn->connecting||$dAmn->login) { $data = $this->dAmn->read(); } else {
				if($dAmn->close) { $this->Bot->running = false; }
			}
		}
		if(isset($data)) {
			if(is_array($data)) {
				foreach($data as $packet) $this->Bot->Events->trigger('packet',$packet);
				$this->ticker = 0;
			}
		}
		++$this->ticker;
		if(($this->ticker/100) > 120)
			$this->process("disconnect\ne=socket timeout\n\n");
	}

	function e_disconnect($e) {
		if($this->dAmn->connected) ++$this->dAmn->disconnects;
		@stream_socket_shutdown($this->dAmn->socket,STREAM_SHUT_RDWR);
		$this->dAmn->chat = array();
		$this->dAmn->connected=false;
		if($this->dAmn->close) return;
		$this->Console->Warning('Experienced an unexpected disconnect!');
		$this->Console->Warning('Waiting before attempting to connect again...');
		if($this->Bot->auth == 'cookie')
			$this->hook('e_cookie', 'cookie');
		elseif($this->Bot->auth == 'oauth')
			$this->hook('e_damntoken', 'damntoken');
		$this->hook('e_connected', 'connected');
		sleep(1.5);
		$this->Bot->network(true);
	}
	function e_connected($version) {
		$this->unhook('e_connected', 'connected');
		$this->dAmn->connected = true;
		$this->hook('e_login', 'login');
		if($this->Bot->auth == 'cookie')
			$this->dAmn->login($this->Bot->username, $this->dAmn->cookie);
		elseif($this->Bot->auth == 'oauth')
			$this->dAmn->login($this->Bot->username, $this->Bot->damntoken);
	}

	function e_login($e) {
		$this->unhook('e_login', 'login');
		if($e == 'ok') {
			$this->dAmn->connecting = $this->dAmn->login = false;
			foreach($this->Bot->autojoin as $id => $channel) { $this->dAmn->join($this->dAmn->format_chat($channel)); }
			return;
		} elseif($this->Bot->usingStored) {
			@stream_socket_shutdown($this->dAmn->socket,STREAM_SHUT_RDWR);
			$this->dAmn->chat = array();
			$this->dAmn->connected = false;
			if($this->Bot->auth == 'cookie')
				$this->hook('e_cookie', 'cookie');
			elseif($this->Bot->auth == 'oauth')
				$this->hook('e_damntoken', 'damntoken');
			$this->hook('e_connected', 'connected');
			$this->Bot->usingStored = false;
			if($this->Bot->auth == 'cookie')
				$this->Bot->cookie = '';
			elseif($this->Bot->auth == 'oauth')
				$this->Bot->damntoken = '';
			$this->Bot->save_config();
			if($this->Bot->auth == 'cookie')
				$this->Console->Warning('Using stored cookie failed!');
			elseif($this->Bot->auth == 'oauth')
				$this->Console->Warning('Using stored damntoken failed!');
			$this->Bot->network(true);
			return;
		}
		$this->Bot->running = false;
	}

	function e_check_msg($ns, $from, $msg) {
		if(!$this->Bot->user->has($from, 25)) return;
		$trig = $this->Bot->trigger;
		if(substr($msg, 0, strlen($trig)) == $trig) {
			$msg = substr($msg, strlen($trig));
			$this->Bot->Events->command(args($msg,0),$ns,$from,htmlspecialchars_decode($msg));
		}
	}

	function e_join($ns, $e) {
		if($e!='ok') return;
		$this->dAmn->chat[$ns] = array(
			'joined' => time(),
			'title' => array(),
			'topic' => array(),
			'pc' => array(),
			'member' => array(),
		);
	}

	function e_join2($ns) {
		$ajn = array_map('strtolower',$this->Bot->autojoin);
		$i=count(array_keys($ajn, '#datashare'));
		while($i > 0) {
			unset($ajn[array_search('#datashare', $ajn)]);
			sort($ajn);
			$this->Bot->autojoin = $ajn;
			$i--;
			$this->Bot->save_config();
		}
		if(!in_array('#datashare', $ajn))
			$this->dAmn->join('chat:datashare');
	}

	function e_part($ns, $e, $r = false, $channel = false) {
		if($e != 'ok') return;
		unset($this->dAmn->chat[$ns]);
		if(empty($this->chat) && $this->dAmn->close === false) {
			$this->Console->Warning('No longer joined to any rooms! Exiting...');
			die();
		}
	}
	function e_property($ns,$prop,$data) {
		$packet = parse_dAmn_packet($data);
		$evt = array(
			'event' => 'property_'.$prop,
			'p' => array($ns,false,false,false),
		);
		switch($prop) {
			case 'title':
			case 'topic':
				$this->dAmn->chat[$ns][$prop] = array(
					'content' => $packet['body'],
					'by' => $packet['args']['by'],
					'ts' => $packet['args']['ts'],
				);
				$evt['p'][1] = $packet['body']; $evt['p'][2] = $packet['args']['by'];
				$evt['p'][3] = $packet['args']['ts'];
				break;
			case 'privclasses':
				$pcs = parse_dAmn_packet($packet['body'],':');
				$this->dAmn->chat[$ns]['pc'] = $pcs['args'];
				$evt['p'][1] = $pcs['args'];
				break;
			case 'members':
				if(!empty($this->dAmn->chat[$ns]['member'])) $this->dAmn->chat[$ns]['member'] = array();
				$member = parse_dAmn_packet($packet['body']);
				while($member['cmd'] != Null) {
					$this->register_user($ns, $member);
					$member = parse_dAmn_packet($member['body']);
				}
				break;
		} $p = $evt['p'];
		$this->Bot->Events->trigger($evt['event'], $p[0], $p[1], $p[2], $p[3]);
	}

	function e_recv_join($ns, $user, $info) {
		$member = parse_dAmn_packet($info);
		$this->register_user($ns, $member, $user);
	}

	function e_recv_part($ns, $user, $r = false) {
		if(array_key_exists($user, $this->dAmn->chat[$ns]['member'])) {
			--$this->dAmn->chat[$ns]['member'][$user]['con'];
			if($this->dAmn->chat[$ns]['member'][$user]['con']===0)
				unset($this->dAmn->chat[$ns]['member'][$user]);
			uksort($this->dAmn->chat[$ns]['member'], 'strnatcasecmp');
		}
	}

	function e_recv_privchg($ns, $user, $by, $npc) { $this->dAmn->chat[$ns]['member'][$user]['pc'] = $npc; }
	function e_recv_kicked($ns, $user, $from, $r = false) { unset($this->dAmn->chat[$ns]['member'][$user]); }
	function e_ping() { $this->dAmn->send("pong\n\0"); }
	function register_user($ns, $data, $user = false) {
		$user = ($user==false?$data['param']:$user);
		if(array_key_exists($user, $this->dAmn->chat[$ns]['member'])) {
			++$this->dAmn->chat[$ns]['member'][$user]['con'];
		} else {
			$this->dAmn->chat[$ns]['member'][$user] = array(
				'con' => 1,
				'symbol' => $data['args']['symbol'],
				'pc' => $data['args']['pc'],
			);
		}
		uksort($this->dAmn->chat[$ns]['member'], 'strnatcasecmp');
	}
	function process($packet) {
		if(strlen($packet) == 0) return;
		$data = sort_dAmn_packet($packet);
		$this->messages($data, $packet);
		$p = $data['p'];
		if($data['event'] == 'part')
			if(array_key_exists($p[0], $this->dAmn->chat))
				$p[3] = $this->dAmn->chat[$p[0]];
		$this->Bot->Events->trigger($data['event'], $p[0],$p[1],$p[2],$p[3],$p[4],$p[5]);
	}

	function messages($data, $raw) {
		$ts = time(); $d=$this->dAmn;
		$outputs = $usen = true; $log = false;
		$save = false; $hn = false;
		$p = $data['p'];

		switch($data['event']) {
			case 'connected':
				if(DEBUG) $this->Console->Notice('Handshake received!');
				$log = 'Connected to dAmnServer '.$p[0].'.';
				break;
			case 'login':
				if($p[0]=='ok') { $log = 'Logged in as '.$this->Bot->username.'!';
				} else { $log = 'Login failed. '.ucfirst($p[0]).'.'; }
				break;
			case 'join':
			case 'part':
				if(strtolower($d->deform_chat($p[0],$this->Bot->username)) == '#datashare')
					return;
				$log = ucfirst($data['event']);
				if($p[1]=='ok') {
					$log.=' ok';
					$save = $d->deform_chat($p[0],$this->Bot->username);
				} else { $log.= ' failed'; }
				$log .= ' for '.($save!=false?$save:$p[0]);
				if($p[1]!='ok') $log.= ' ['.$p[1].']';
				if($p[1]=='ok'&&$p[2]!=false) $log.= ' ['.$p[2].']';
				break;
			case 'property':
				if(strtolower($d->deform_chat($p[0],$this->Bot->username)) == '#datashare')
					return;
				$save = $d->deform_chat($p[0],$this->Bot->username);
				$log = 'Got '.$p[1].' for '.$save.'.';
				break;
			case 'recv_msg':
			case 'recv_action':
				if(strtolower($d->deform_chat($p[0],$this->Bot->username)) == '#datashare')
					return;
				$save = $d->deform_chat($p[0],$this->Bot->username); $usen=false; $hn = true;
				$log = ' '.(substr($data['event'],5)=='msg'?'<'.$p[1].'>':'* '.$p[1]);
				$log.= ' '.$p[2];
				break;
			case 'recv_join':
			case 'recv_part':
				if(strtolower($d->deform_chat($p[0],$this->Bot->username)) == '#datashare')
					return;
				$save = $d->deform_chat($p[0],$this->Bot->username); $usen=false; $hn = true;
				$log = ' ** '.$p[1].' has '.(substr($data['event'],5)=='join'?'joined':'left')
				.(($data['event']=='recv_part'&&$p[2]!=false)?' ['.$p[2].']':'');
				break;
			case 'recv_privchg':
			case 'recv_kicked':
				if(strtolower($d->deform_chat($p[0],$this->Bot->username)) == '#datashare')
					return;
				$save = $d->deform_chat($p[0],$this->Bot->username); $usen=false;
				$log = ' ** '.$p[1].' has been '.
				(substr($data['event'],5)=='privchg'?
					'made a member of '.$p[3].' by '.$p[2].' *':
					'kicked by '.$p[2].' *'.($p[3]!=false?' '.$p[3]:'')
				);
				break;
			case 'recv_admin_create':
			case 'recv_admin_update':
				if(strtolower($d->deform_chat($p[0],$this->Bot->username)) == '#datashare')
					return;
				$this->dAmn->get($p[0],'members');
				$save = $d->deform_chat($p[0],$this->Bot->username); $usen=false;
				$log = ' ** privilege class '.$p[3].' has been '
				.substr($data['event'],11).'d by '.$p[2].' with: '.$p[4];
				break;
			case 'recv_admin_rename':
				if(strtolower($d->deform_chat($p[0],$this->Bot->username)) == '#datashare')
					return;
				$this->dAmn->get($p[0],'members');
				$save = $d->deform_chat($p[0],$this->Bot->username); $usen=false;
				$log = ' ** privilege class '.$p[3].' has been renamed to '
				.$p[4].' by '.$p[2];
				break;
			case 'recv_admin_move':
				if(strtolower($d->deform_chat($p[0],$this->Bot->username)) == '#datashare')
					return;
				$this->dAmn->get($p[0],'members');
				$save = $d->deform_chat($p[0],$this->Bot->username); $usen=false;
				$log = ' ** all members of '.$p[3].' have been made '
				.$p[4].' by '.$p[2].' -- '.$p[5].' members were affected';
				break;
			case 'recv_admin_remove':
				if(strtolower($d->deform_chat($p[0],$this->Bot->username)) == '#datashare')
					return;
				$this->dAmn->get($p[0],'members');
				$save = $d->deform_chat($p[0],$this->Bot->username); $usen=false;
				$log = ' ** privilege class '.$p[3]
				.' has been removed by '.$p[2].' -- '.$p[4].' members were affected';
				break;
			case 'recv_admin_show':
				if(strtolower($d->deform_chat($p[0],$this->Bot->username)) == '#datashare')
					return;
				$this->dAmn->get($p[0],'members');
				$save = $d->deform_chat($p[0],$this->Bot->username); $usen=false;
				$GLOBALS['crap'] = $p[2];
				break;
			case 'recv_admin_privclass':
				if(strtolower($d->deform_chat($p[0],$this->Bot->username)) == '#datashare')
					return;
				$save = $d->deform_chat($p[0],$this->Bot->username); $usen=false;
				$log = ' ** admin '.$p[1].' failed, error: '.$p[2];
				if($p[3]!==false) $log.=' ('.$p[3].')';
				break;
			case 'kicked':
				$save = $d->deform_chat($p[0],$this->Bot->username); $usen=false;
				$log = ' ** You have been kicked by '.$p[1].' *';
				if($p[2]!==false) $log.= ' '.$p[2];
				break;
			case 'ping':
				$log = '** Ping!';
				$outputs = false;
				break;
			case 'disconnect':
				$log = 'Disconnected from dAmn ['.$p[0].']';
				break;
			case 'send':
			case 'kick':
			case 'get':
			case 'set':
				if(strtolower($d->deform_chat($p[0],$this->Bot->username)) == '#datashare')
					return;
				$save = $d->deform_chat($p[0],$this->Bot->username); $usen=false;
				$log = ' ** '.ucfirst($data['event']).' error: '.
				($p[2]!=false?$p[2].' ('.$p[1].')':$p[1]);
				break;
			case 'kill': $log = 'Kill error: '.$p[1].' ('.$p[2].')';
				break;
			case 'whois': break;
			case '': break;
			default:
				if(strtolower($d->deform_chat($p[0],$this->Bot->username)) == '#datashare')
					return;
				$log = 'Received unknown packet.';
				$log.= str_replace("\n", "\n>>", $raw);
				return;
				break;
		}

		if($log === false) return;
		$savetext = ($usen == true ? ' ' : '').$log;
		$disp = htmlspecialchars_decode(($save!=false&&$usen==false?'['.$save.']':'').$log);
		$this->log(($save == false ? '~Server' : $save), $savetext, $ts);
		if($outputs == false) return;
		if($usen == true) $this->Console->Notice($disp,$ts);
		if($usen == false) $this->Console->Message($disp,$ts);
	}

	function log($chan, $text, $time) {
		if($chan != '#DataShare') {
			$fold = date('M-Y', $time);
			$file = date('d-m-y', $time).'.txt';
			$text = $this->Console->Clock($time).$text;
			if(!is_dir('./storage')) mkdir('./storage', 0755);
			if(!is_dir('./storage/logs')) mkdir('./storage/logs', 0755);
			if(!is_dir('./storage/logs/'.$chan)) mkdir('./storage/logs/'.$chan,0755);
			if(!is_dir('./storage/logs/'.$chan.'/'.$fold)) mkdir('./storage/logs/'.$chan.'/'.$fold, 0755);
			$old = @file_get_contents('./storage/logs/'.$chan.'/'.$fold.'/'.$file);
			if($old !== false) $text = $old.chr(10).$text;
			file_put_contents('./storage/logs/'.$chan.'/'.$fold.'/'.$file, $text);
		}
	}
}

new dAmn_lib($core);

?>