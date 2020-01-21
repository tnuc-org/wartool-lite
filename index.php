<?php
//error_reporting(E_ALL | E_WARNING);
error_reporting(0);

class WTlite {
private static $DBHOST = 'REMOVED';
	private static $DBUSER = 'REMOVED';
	private static $DBPASS = 'REMOVED';
	private static $DBNAME = 'REMOVED';
	private static $cookiepath = 'lite.tnuc.org';

	private static $DBLINK = null;
	private static $err = array('','');
	
	
	private static $A_OLIST = 'http://www.tibia.com/community/?subtopic=worlds&world=';
	private static $A_GUILD = 'http://www.tibia.com/community/?subtopic=guilds&page=view&GuildName=';
	private static $A_CHAR_ESC = 'http://www.tibia.com/community/?subtopic=characters&amp;name=';
	
	private static $IMG_ON = '<img src="./on.gif" alt="online" title="online" />';
	private static $IMG_OFF= '<img src="./off.gif" alt="online" title="offline" />';
	
	private static $servers = array('Aldora','Amera','Antica','Arcania','Askara','Astera','Aurea','Aurera', 'Aurora', 
		'Azura','Balera','Berylia','Calmera','Candia','Celesta','Chimera','Danera','Danubia',
		'Dolera','Elera','Elysia','Empera','Eternia','Fidera','Fortera','Furora','Galana',
		'Grimera','Guardia','Harmonia','Hiberna','Honera','Inferna','Iridia','Isara',
		'Jamera','Julera','Keltera','Kyra','Libera','Lucera','Luminera','Lunara','Magera','Malvera',
		'Menera','Morgana','Mythera','Nebula','Neptera','Nerana','Nova','Obsidia','Ocera',
		'Olympa','Pacera','Pandoria','Premia','Pythera','Refugia','Rubera','Samera','Saphira',
		'Secura','Selena','Shanera','Shivera','Silvera','Solera','Tenebra','Thoria',
		'Titania','Trimera','Unitera','Valoria','Vinera','Xantera','Xerena','Zanera');
	
	private static function dbConnect() {
		if(!(self::$DBLINK = @mysql_connect(self::$DBHOST,self::$DBUSER,self::$DBPASS))) {self::$DBLINK = null; throw new Exception("db");}
		if(!(@mysql_select_db(self::$DBNAME))) {self::$DBLINK = null; throw new Exception("db");}
	}
	private static function dbDisc() {
		if(self::$DBLINK !== null) {
			@mysql_close(self::$DBLINK);
			self::$DBLINK = null;
		}
	}
	private static function getGPC(&$conf) {
		$mq = get_magic_quotes_gpc();
		
		if(!isset($_GET['id'])) throw new Exception('noid');
		$conf['title'] = $mq ? stripslashes($_GET['id']) : $_GET['id'];
		
		if(isset($_GET['l']) && in_array($_GET['l'],array('G','V','W'))) $conf['layout'] = $_GET['l'];
		else $conf['layout'] = 'G';
		
		$conf['adm'] = isset($_GET['adm']) ? true : false;
		
		if(isset($_GET['pw'])) $conf['gpc_upw']       = ($mq ? stripslashes($_GET['pw']) : $_GET['pw']);
			else $conf['gpc_upw'] = null;
		
		$conf['gpc_upwh'] = $conf['gpc_upw'] !== null ? md5($conf['gpc_upw']) : 'unmatchable';
			
		if(isset($_COOKIE['apw'])) $conf['gpc_apw'] = ($mq ? stripslashes($_COOKIE['apw']) : $_COOKIE['apw']);
			else $conf['gpc_apw'] = 'unmatchable';
			
		if(isset($_POST['apw'])) $conf['gpc_apw'] = md5( ($mq ? stripslashes($_POST['apw']) : $_POST['apw']) );
		
		if(isset($_GET['logout']) && isset($_COOKIE['apw'])) {
			setcookie('apw','dickseverywhere',-999,'/',self::$cookiepath);
			$conf['gpc_apw'] = 'unmatchable';
			$conf['adm_auth'] = false;
		}
	}
	private static function getGuildMembers(&$g,&$c,$i) {
		$g = stripslashes(trim($g));
		if($g != '' && preg_match('~^[A-Za-z\'\s\-]+$~',$g) == 1) {
			$ch = curl_init();
			$url = self::$A_GUILD . urlencode($g);
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_AUTOREFERER, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 7);
			$html = curl_exec($ch);
			curl_close($ch);
			if($html !== null) {
				if(preg_match('~<BR>\nThe guild was founded on ([A-Za-z]*) on [A-Za-z0-9&#;]*\.<BR>\nIt is currently active~',$html,$temp) == 1) {
					$html = str_replace(array("&nbsp;","&#160;","&#39;"),array(" "," ","'"),$html);
					if(!$i) {
						$pLeft = strpos($html,'Guild Members</B>');
						$pRight = strpos($html,'<B>Invited Characters');
						$html = substr($html,$pLeft,$pRight-$pLeft);
					}
					preg_match_all('~subtopic=characters\&name=[^\"]*\">([^<]*)</A>~',$html,$temp);
					if(is_array($temp[1]))
					for($i=0;$i<sizeof($temp[1]);$i++) 
						$c[] = "'".mysql_real_escape_string($temp[1][$i])."'";
				}  else self::$err = array('',"Guild $g does not exist (or Tibia.com is being gay).");
			} else self::$err = array('',"Failed to retrieve $g guild page. Try again later.");
		} else self::$err = array('',"Invalid guild name.");
	}
	private static function postActions(&$conf,&$chars) {
		if(isset($_POST['apw']) && $conf['adm_auth']) {
				setcookie('apw',md5($_POST['apw']),0,'/',self::$cookiepath);
		}
		if(isset($_POST['act']) && $conf['adm_auth'])  {
			switch($_POST['act']) {
			
				case 'addchar':
					$max = $conf['capacity'] - sizeof($chars);
					if($max > 30) $max = 30;
					$issec = isset($_POST['add_as_second']) && $_POST['add_as_second'] == '1' ? 1 : 0;
					$c = isset($_POST['chars_to_add']) ? $_POST['chars_to_add'] : '';
					$c = str_replace("\r","",$c);
					$c = explode("\n",$c);
					$count = 0;
					foreach($c as $k => $v) {
						$c[$k] = stripslashes(trim($v));
						if($c[$k] == '' || $count >= $max || preg_match('~^[A-Za-z\s\-\'\.]+$~',$c[$k])!=1 || $c[$k] === null) unset($c[$k]);
						else {
							$c[$k] = "'".mysql_real_escape_string($c[$k])."'";
							$count++;
						}
					}
					if(sizeof($c) > 0) {
						if((sizeof($chars) + sizeof($c)) <= $conf['capacity']) {
							$d = time();
							$ci = "(".implode("),(",$c).")";
							$cs = "(".implode(",",$c).")";
							
							$q = "INSERT INTO chars (name) VALUES $ci ON DUPLICATE KEY UPDATE updated='$d'";
							mysql_query($q);
							if(!mysql_error()) {
								$q = "SELECT uid,name from chars WHERE name IN $cs";
								$r = mysql_query($q);
								if(!mysql_error()) {
									$ids = array();
									while($l = mysql_fetch_assoc($r))
										$ids[$l['name']] = $l['uid'];
									$glue = "," . $conf['uid'] . "," . $issec . "),(";
									$idsx = "(" . implode($glue,$ids) . substr($glue,0,-2);
									$q = "INSERT INTO cp (cid,pid,issec) VALUES $idsx ON DUPLICATE KEY UPDATE issec=VALUES(issec)";
									mysql_query($q);
									if(!mysql_error()) {
										self::$err = array('1',sizeof($c)." chars added/updated.");
										$issec = $issec == 1 ? true : false;
										foreach($ids as $nm => $id) {
											$tmpkey = self::inArrayCI($nm,$chars);
											if($tmpkey !== false) {
												$chars[$tmpkey]['issec'] = $issec;
											} else {
												$chars[] = array(	'name'		=> $nm,
																	'level' 	=> 0,
																	'voc' 		=> '??',
																	'issec' 	=> $issec,
																	'comment' 	=> '',
																	'uid'		=> $id
																	);
											}
										}
									} else self::$err = array('','Temporary database error. Try again in a second.');
								} else self::$err = array('','Temporary database error. Try again in a second.');
							} else self::$err = array('','Temporary database error. Try again in a second.');
						} else self::$err = array('','Your DB is too full. Delete other chars first or ask me for a bigger DB.');
					} else self::$err = array('','No valid names given.');
				break;
				
				case 'addguild':
					$issec = isset($_POST['add_as_second']) && $_POST['add_as_second'] == '1' ? 1 : 0;
					$g = isset($_POST['guild']) ? $_POST['guild'] : '';
					$m = isset($_POST['name_as_comment']) && $_POST['name_as_comment'] == '1' ? true : false;
					$o = isset($_POST['overwrite']) && $_POST['overwrite'] == '1' ? true : false;
					$i = isset($_POST['invited_chars']) && $_POST['invited_chars'] == '1' ? true : false;
					$c = array();
					self::getGuildMembers($g,$c,$i);
					if(sizeof($c)>0) {
						if((sizeof($chars) + sizeof($c)) <= $conf['capacity']) {
							$d = time();
							$ci = "(".implode("),(",$c).")";
							$cs = "(".implode(",",$c).")";
							
							$q = "INSERT INTO chars (name) VALUES $ci ON DUPLICATE KEY UPDATE updated='$d'";
							mysql_query($q);
							if(!mysql_error()) {
								$q = "SELECT uid,name from chars WHERE name IN $cs";
								$r = mysql_query($q);
								if(!mysql_error()) {
									$ids = array();
									while($l = mysql_fetch_assoc($r))
										$ids[$l['name']] = $l['uid'];
									$gglue = "'".mysql_real_escape_string($g)."'";
									$glue = "," . $conf['uid'] . "," . $issec . ($m?",".$gglue:'') . "),(";
									$idsx = "(" . implode($glue,$ids) . substr($glue,0,-2);
									$q = "INSERT INTO cp (cid,pid,issec".($m?',comment':'').") VALUES $idsx ON DUPLICATE KEY UPDATE issec=VALUES(issec)".($m&&$o?',comment=VALUES(comment)':'');
									mysql_query($q);
									if(!mysql_error()) {
										self::$err = array('1',sizeof($c)." chars from $g added/updated.");
										$issec = $issec == 1 ? true : false;
										foreach($ids as $nm => $id) {
											$tmpkey = self::inArrayCI($nm,$chars);
											if($tmpkey !== false) {
												$chars[$tmpkey]['issec'] = $issec;
												if($m && $o) $chars[$tmpkey]['comment'] = $g;
											} else {
												$chars[] = array(	'name'		=> $nm,
																	'level' 	=> 0,
																	'voc' 		=> '??',
																	'issec' 	=> $issec,
																	'comment' 	=> $m ? $g : '',
																	'uid'		=> $id
																	);
											}
										}
									} else self::$err = array('','Temporary database error [#1]. Try again later.');
								} else self::$err = array('','Temporary database error [#2]. Try again later.');
							} else self::$err = array('','Temporary database error [#3]. Try again later.');
						} else self::$err = array('','Your DB is too full. Delete other chars first or ask me for a bigger DB.');
					}
				break;
				
				case 'change_motd':
					$motd = isset($_POST['new_motd']) ? stripslashes($_POST['new_motd']) : '';
					if(strlen($motd) > 250) $motd = substr($motd,0,250);
					$motd2 = mysql_real_escape_string($motd);
					$q = "UPDATE projects SET motd='$motd2' WHERE uid=".$conf['uid'];
					mysql_query($q);
					if(!mysql_error()) {
						self::$err = array('1','MotD changed.');
						$conf['motd'] = $motd;
					}
					else self::$err = array('','MotD could not be changed.');
				break;
				
				case 'comment':
					$comment = isset($_POST['new_comment']) ? mysql_real_escape_string(stripslashes($_POST['new_comment'])): '';
					if(strlen($comment) > 250) $comment = substr($comment,0,250);
					$char = isset($_POST['char']) ? (int)$_POST['char'] : false;
					if($char !== false) {
						$q = "UPDATE cp SET comment='$comment' WHERE pid=".$conf['uid']." AND cid=$char";
						mysql_query($q);
						if(!mysql_error()) 
							self::$err = array('1','Comment changed.');
						else self::$err = array('','Comment could not be changed.');
					} else self::$err = array('','No char selected.');
				break;
				
				case 'change_title':
					$forbidden = array('adm','admin','config','test','setup','login','signup','help','index','main','tnuc','flo','get_started');
					$t = isset($_POST['new_title']) ? trim($_POST['new_title']) : '';
					if(strlen($t)>0 && preg_match('~^[A-Za-z0-9\_\-]*$~',$t) == 1) {
						if(strlen($t)<=60) {
							if(!in_array(strtolower($t),$forbidden)) {
								$q = "SELECT * FROM projects WHERE title='".mysql_real_escape_string($t)."'";
								$r = mysql_query($q);
								if(!mysql_error()) {
									if(mysql_num_rows($r) == 0) {
										$q = "UPDATE projects SET title='".mysql_real_escape_string($t)."' WHERE uid=".$conf['uid'];
										mysql_query($q);
										if(!mysql_error()) {
											$conf['title'] = $t;
											$loc = "./?id=$t".($conf['upw']!==null?"&pw=".urlencode($conf['gpc_upw']):"").($conf['layout']!='G'?"&l=".$conf['layout']:"")."&adm";
											header("Location: $loc");
											exit(1);
										} else self::$err = array('','Temporary database error. Try again in a second.');
									} else self::$err = array('','Title &quot;'.$t.'&quot; is already taken.');
								} else self::$err = array('','Temporary database error. Try again in a second.');
							} else self::$err = array('','New title is a banned word.');
						} else self::$err = array('','Title cannot be longer than 60 characters.');
					} else self::$err = array('','Title can only consist of letters (A-Z) numbers _ and -');
				break;
				
				case 'issec0';
				case 'issec1';
					$issec = $_POST['act'] == 'issec0' ? 0 : 1;
					$char = isset($_POST['char']) ? (int)$_POST['char'] : false;
					if($char !== false) {
						$q = "UPDATE cp SET issec=$issec WHERE cid=$char AND pid=".$conf['uid'];
						mysql_query($q);
						if(!mysql_error()) {
							self::$err = array('1','Char successfully changed to '.htmlspecialchars(($issec==0?$conf['str_m']:$conf['str_s'])).".");
							foreach($chars as $k => $v) {
								if($chars[$k]['uid'] == $char) {
									$chars[$k]['issec'] = $issec == 1 ? true : false;
									break;
								}
							}
						}
						else self::$err = array('','Temporary database failure. Try again inabit.');
					} else self::$err = array('','No char selected.');
				break;
				
				case 'rmchar':
					$char = isset($_POST['char']) ? (int)$_POST['char'] : false;
					if($char !== false) {
						$q = "DELETE FROM cp WHERE cid=$char AND pid=".$conf['uid']." LIMIT 1";
						mysql_query($q);
						if(!mysql_error()) {
							self::$err = array('1','Character deleted.');
							foreach($chars as $k => $v) {
								if($chars[$k]['uid'] == $char) {
									unset($chars[$k]);
									break;
								}
							}
						} else self::$err = array('','Temporary database failure. Try again inabit.');
					} else self::$err = array('','No char selected.');
				
				break;
				
				case 'change_upw':
					$pw = isset($_POST['new_upw']) && trim($_POST['new_upw']) != '' ? trim($_POST['new_upw']) : null;
					if($pw === null || preg_match('~^[A-Za-z0-9\_\-]*$~',$pw)) {
						$q = $pw === null ?
							"UPDATE projects SET upw = null WHERE uid=".$conf['uid'] :
							"UPDATE projects SET upw = '".md5($pw)."' WHERE uid=".$conf['uid'];
						mysql_query($q);
						if(!mysql_error()) {
							$redir = "./?id=".urlencode($conf['title']).($pw===null?'':'&pw='.urlencode($pw)).($conf['layout']!='G'?'&l='.$conf['layout']:'').'&adm';
							header("Location: ".$redir);
							exit(1);
						} else self::$err = array('','Failed to change password. Try again inabit.');
					} else self::$err = array('','Password can only consist of letters (A-Z) numbers _ and -');
				break;
				
				case 'change_apw':
					$pw1 = isset($_POST['new_apw1']) && trim($_POST['new_apw1']) != '' ? stripslashes(trim($_POST['new_apw1'])) : null;
					$pw2 = isset($_POST['new_apw2']) && trim($_POST['new_apw2']) != '' ? stripslashes(trim($_POST['new_apw2'])) : null;
					if($pw1 !== null && $pw2 !== null) {
						if($pw1 === $pw2) {
							$hash = md5($pw1);
							$q = "UPDATE projects SET apw = '" . $hash . "' WHERE uid=".$conf['uid'];
							mysql_query($q);
							if(!mysql_error()) {
								setcookie('apw',$hash,0,'/',self::$cookiepath);
								self::$err = array('1','Admin password changed.');
							} else self::$err = array('','Temporary database failure. Try again later.');
						} else self::$err = array('','New passwords do not match.');
					} else self::$err = array('','Repeat the new password twice.');
				break;
				
				case 'labels':
					$lm = isset($_POST['lbl_m']) && strlen(trim($_POST['lbl_m'])) > 1 ? stripslashes(trim($_POST['lbl_m'])) : null;
					$ls = isset($_POST['lbl_s']) && strlen(trim($_POST['lbl_s'])) > 1 ? stripslashes(trim($_POST['lbl_s'])) : null;
					if($lm !== null && $ls !== null) {
						if($lm !== $ls) {
							if(strlen($lm)<26 && strlen($ls)<26) {
								$q = "UPDATE projects SET str_m='".mysql_real_escape_string($lm)."',str_s='".mysql_real_escape_string($ls)."' WHERE uid=".$conf['uid'];
								mysql_query($q);
								if(!mysql_error()) {
									 $conf['str_m'] = $lm;
									 $conf['str_s'] = $ls;
									 self::$err = array('1','Labels Changed.');
								} else self::$err = array('','Temporary database failure. Try again later.');
							} else self::$err = array('','Labels cannot be longer than 25 characters.');
						}  else self::$err = array('','Why would you make both labels the same?');
					} else self::$err = array('','Labels cannot be left blank.');
				break;
				
				case 'hideoffline':
					$val = isset($_POST['val']) && $_POST['val'] == '1' ? 1 : 0;
					$q = "UPDATE projects SET hideoffline={$val} WHERE uid={$conf['uid']}";
					mysql_query($q);
					if (!mysql_error()) {
						$conf['hideoffline'] = !!$val;
						self::$err = array('1', 'Offline characters are now ' . ($val ? 'hidden' : 'visible'));
					} else self::$err = array('','Temporary database failure. Try again later.');
				break;
			}
		}
		
	}
	private static function getConf(&$conf) {
		$q = 'SELECT uid,title,server,updated,upw,apw,motd,str_m,str_s,capacity,hideoffline FROM projects WHERE title = \''.
			mysql_real_escape_string($conf['title']). '\' LIMIT 1';
		$r = @mysql_query($q);
		if(mysql_error() || !($l = mysql_fetch_assoc($r))) throw new Exception("idInvalid");
		$conf['uid'] = $l['uid'];
		$conf['upw'] = $l['upw'];
		$conf['apw'] = $l['apw'];
		$conf['title'] = $l['title'];
		$conf['motd'] = $l['motd'];
		$conf['server'] = $l['server'];
		$conf['updated'] = $l['updated'];
		$conf['str_m'] = $l['str_m'];
		$conf['str_s'] = $l['str_s'];
		$conf['capacity'] = (int)$l['capacity'];
		$conf['adm_auth'] = $conf['apw'] == $conf['gpc_apw'] ? true : false;
		$conf['hideoffline'] = !!$l['hideoffline'];
		if($conf['upw'] !== null && $conf['upw'] != $conf['gpc_upwh'] && !$conf['adm_auth']) throw new Exception("upw");
		
	}
	private static function getDbChars(&$conf,&$chars) {
		$q = 'SELECT c.uid,c.name,c.level,c.voc,p.issec,p.comment,c.updated FROM chars c JOIN cp p ON c.uid = p.cid WHERE p.pid =' . $conf['uid'];
		$r = @mysql_query($q);
		if(mysql_error(self::$DBLINK)) {
			// if ($_SERVER['REMOTE_ADDR'] === '93.232.67.163') {
				// var_dump(mysql_error(self::$DBLINK));
			// }
			throw new Exception("db");
		}
		while($l = mysql_fetch_assoc($r)) {
			$chars[] = array(		'name'		=> $l['name'],
									'level' 	=> (int)$l['level'],
									'voc' 		=> $l['voc'] != '' ? $l['voc'] : '??',
									'issec' 	=> ($l['issec'] == 1 ? true : false),
									'comment' 	=> $l['comment'],
			//						'updated'	=> ($l['updated'] == 0 ? 'never' : date("M jS Y",$l['updated']))
									'uid'		=> $l['uid']
									);
		}
	}
	private static function getOnlinePage(&$html,$serv) {
		$ch = curl_init();
		$url = self::$A_OLIST . $serv;
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 7);
		$html = curl_exec($ch);
		curl_close($ch);
		if($html === null) throw new Exception("olist");
	}
	private static function inArrayCI($val,&$ar) {
		if(empty($ar)) return false;
		$val = strtolower($val);
		foreach($ar as $k => $v) {
			if($val == strtolower($v['name'])) return $k;
		}
		return false;
	}
	private static function vocToAbbrev($v) {
		switch($v) {
			case 'Elder Druid': 	return 'ED'; break;
			case 'Druid': 			return 'D';  break;
			case 'Master Sorcerer': return 'MS'; break;
			case 'Sorcerer': 		return 'S';  break;
			case 'Elite Knight': 	return 'EK'; break;
			case 'Knight': 			return 'K';  break;
			case 'Royal Paladin': 	return 'RP'; break;
			case 'Paladin': 		return 'P';  break;
			default: 				return '--'; break;
		}
	}
	private static function parseOnlinePage(&$html,&$conf,&$chars) {
		if(strpos($html,'<td class="LabelV200" >Players Online:</td><td>') === false) {
			throw new Exception("olist");
		}
		$html = str_replace(array("&nbsp;","&#160;","&#39;"),array(" "," ","'"),$html);
		/*preg_match_all('~name=[A-Za-z%27\-\+]*\">([^<]*)</A></TD><TD WIDTH=10%>([^<]*)</TD><TD WIDTH=20%>([^<]*)</TD></TR>~',$html,$matches);*/
		preg_match_all('~name=[^"]*?"[^>]*?>([^<]+?)</a></td><td[^>]*?>([^<]+?)</td><td[^>]*?>([^<]+?)</td>~',$html,$matches);
		foreach($matches[1] as $k => $name) {
			if(($t = self::inArrayCI($name,$chars)) !== false) {
				$chars[$t]['name'] = $name;
				$chars[$t]['level'] = (int)$matches[2][$k];
				$chars[$t]['voc'] = self::vocToAbbrev($matches[3][$k]);
				//$chars[$t]['updated'] = date("M jS Y");
				$chars[$t]['on'] = true;
			}
		}
		$html = null;
	}
	private static function quicksortByInner(&$ar,$start = 0,$end = null) {
		if($end === null) $end = sizeof($ar)-1;
		$l = $start;
		$r = $end;
		$pivot = $ar[floor(($l + $r)/2)]['level'];
		while($l <= $r) {
			while ($ar[$l]['level'] > $pivot) $l++;
	        while ($ar[$r]['level'] < $pivot) $r--;
			if($l <= $r) {
				$temp = $ar[$l];
				$ar[$l] = $ar[$r];
				$ar[$r] = $temp;
				$l++;
				$r--;
			}
		}
		if($start < $r) self::quicksortByInner($ar,$start,$r);
		if( $l < $end ) self::quicksortByInner($ar, $l, $end);
	}
	private static function updateChars($conf,$chars) {
		$t = time();
		if($t - $conf['updated'] > 300) {
			$c = 0;
			$query = array();
			foreach($chars as $i => $char) {
				if(isset($char['on'])) {
					$query[] = "('" . mysql_real_escape_string($char['name']) . "'," . $char['level'] . ",'" . $char['voc'] . "','".$t."')";
					$c++;
					if($c > 30) break;
				}
			}
			if($c > 0) {
				$query = implode(",",$query);
				$query = "INSERT INTO chars (name,`level`,voc,updated) VALUES $query ON DUPLICATE KEY UPDATE name=VALUES(name),level=VALUES(level),voc=VALUES(voc),updated=VALUES(updated)";
				@mysql_query($query);
				$query = "UPDATE projects SET updated='$t' WHERE uid=".$conf['uid'];
				@mysql_query($query);
			}
		}
	}
	private static function outputHeader($conf,$default = null) {
		$style = in_array($conf['layout'],array('V','W')) ? '&amp;l='.$conf['layout'] : '';
		$type = !$conf['adm'] && in_array($conf['layout'],array('V','W')) ? $conf['layout'] : 'G';
		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>'.($default===null ? htmlspecialchars($conf['title']) . " - Wartool Lite": $default).'</title>
<link rel="stylesheet" href="./style.css">
</head>
<body class="'.$type.'">
<center>
<h1>Wartool Lite</h1><h3 style="font-size:12px">Ventcheck is (almost) back! <a href="http://tnuc.org/news">Click here</a> for last minute feature requests</h3>';
			if($default===null) { echo '
<table width="720" cellpadding="4" cellspacing="0">
 <tr>
  
  <td width="33%" align="center">
  <form method="get" action="./">
  <input type="hidden" name="id" value="'.htmlspecialchars($conf['title']).'">
  '.($conf['upw']!==null?'<input type="hidden" name="pw" value="'.urlencode($conf['gpc_upw']).'">':'').'
  <select name="l" class="txt" size="1">
  <option value="G"'.($conf['layout']=='G'?' selected="selected"':'').'>style: default</option>
  <option value="V"'.($conf['layout']=='V'?' selected="selected"':'').'>style: vip</option>
  <option value="W"'.($conf['layout']=='W'?' selected="selected"':'').'>style: wtool 2.0</option>
  </select>
  <input class="btn" type="submit" value="go">
  </form>
  </td>
  <td width="34%" align="center"><a href="./">Home</a><br />
  <a href="./?id='.urlencode($conf['title']).($conf['upw']!==null?'&amp;pw='.urlencode($conf['gpc_upw']):'').$style.'">Refresh</a></td>
  <td width="33%" align="center">';
			if(!$conf['adm_auth']) echo '
   <form action="./?id='.urlencode($conf['title']).($conf['upw']!==null?'&amp;pw='.urlencode($conf['gpc_upw']):'').$style.'&amp;adm" method="post">
   <input name="apw" class="txt" type="text" value="'.(!$conf['adm']?'admin login':'wrong password').'" onclick="if(this.value==this.defaultValue)this.value=\'\';" onblur="if(this.value==\'\')this.value=this.defaultValue;"/>
   <input class="btn" type="submit" value="go" />';
			else echo '
  <a href="./?id='.urlencode($conf['title']).($conf['upw']!==null?'&amp;pw='.urlencode($conf['gpc_upw']):'').$style.'&amp;adm">admin CP</a> | 
  <a href="./?id='.urlencode($conf['title']).($conf['upw']!==null?'&amp;pw='.urlencode($conf['gpc_upw']):'').$style.'&amp;logout">logout</a>';
			echo '
   </form>
  </td>
 </tr>';
			if($conf['motd'] != '');
			echo '
 <tr>
  <td colspan="3" class="motd" align="center">'.nl2br(htmlspecialchars($conf['motd'])).'</td>';
  echo '
</table>
<hr />
';
		}
	}
	private static function outputFooter() {
		echo '
<div><br />by Flo - <a href="http://tnuc.org">tnuC.org</a></div>
<!-- Start of StatCounter Code -->
<script type="text/javascript">
var sc_project=5721951; 
var sc_invisible=1; 
var sc_partition=60; 
var sc_click_stat=1; 
var sc_security="d1812a26"; 
</script>

<script type="text/javascript"
src="http://www.statcounter.com/counter/counter_xhtml.js"></script><noscript><div
class="statcounter"><a title="hit counter joomla"
class="statcounter"
href="http://www.statcounter.com/joomla/"><img
class="statcounter"
src="http://c.statcounter.com/5721951/0/d1812a26/1/"
alt="hit counter joomla" /></a></div></noscript>
<!-- End of StatCounter Code -->
</center>
</body>
</html>';
	}
	private static function output(&$conf,&$chars) {
		if($conf['layout'] == 'W') self::W_output($conf,$chars);
		elseif($conf['layout'] == 'V') self::V_output($conf,$chars);
		else self::G_output($conf,$chars);
	}
	private static function V_outputRow(&$content,&$char,$green) {
		$v =& $char['voc'];
		$icon = null; if($v=='D' || $v == 'ED') $icon = 'ed.png';
		elseif($v == 'K' || $v == 'EK') $icon = 'ek.png';
		elseif($v == 'S' || $v == 'MS') $icon = 'ms.png';
		elseif($v == 'P' || $v == 'RP') $icon = 'rp.png';
		
		$content .= '
  <tr class="'.($green?'on':'off').'">
   <td>'.($icon === null ? '' : '<img src="./'.$icon.'" title="'.$char['voc'].'"/>').'</td>
   <td><a href="' . self::$A_CHAR_ESC . urlencode($char['name']) . '">' . $char['name'] . '</a></td>
   <td align="right">' . $char['level'] . '</td>
   <td>&nbsp;' . $char['voc'] . '</td>
   <td align="center">'.($char['comment']==''?'':'<span title="'.htmlspecialchars($char['comment']).'">[?]</span>').'</td>
  </tr>';
	}
	private static function V_output(&$conf,&$chars) {
		$vocs = array(0 => array('Druids' => array(),
							'Sorcerers' => array(),
							'Paladins' => array(),
							'Knights' => array(),
							'Rookies' => array(),
							'Offline' => array()),
					  1 => array('Druids' => array(),
							'Sorcerers' => array(),
							'Paladins' => array(),
							'Knights' => array(),
							'Rookies' => array(),
							'Offline' => array())
					);
		foreach($chars as $index => $char) {
			if(!isset($char['on'])) {
				$vocs[($char['issec']?1:0)]['Offline'][] =& $chars[$index];
			} else
			switch($char['voc']) {
				case 'D': case 'ED':
				$vocs[($char['issec']?1:0)]['Druids'][] =& $chars[$index]; break;
				case 'S': case 'MS':
				$vocs[($char['issec']?1:0)]['Sorcerers'][] =& $chars[$index]; break;
				case 'P': case 'RP':
				$vocs[($char['issec']?1:0)]['Paladins'][] =& $chars[$index]; break;
				case 'K': case 'EK':
				$vocs[($char['issec']?1:0)]['Knights'][] =& $chars[$index]; break;
				default:
				$vocs[($char['issec']?1:0)]['Rookies'][] =& $chars[$index]; break;
			}
		}
		foreach($vocs[0] as $vocname => $vocdata) {
			if(sizeof($vocdata) == 0) { unset($vocs[0][$vocname]); continue; }
			$temp = "";
			self::quicksortByInner($vocs[0][$vocname]);
			if($vocname !== 'Offline' || !$conf['hideoffline']) {
				for($i=0;$i<sizeof($vocdata);$i++)
					self::V_outputRow($temp,$vocdata[$i],($vocname==='Offline'));
			}
			if($vocname === 'Offline' && sizeof($vocs[0]) > 1) $temp = "\n<tr><td colspan=\"5\">&nbsp;</td></tr>" . $temp;
			$vocs[0][$vocname] = $temp;
		}
		foreach($vocs[1] as $vocname => $vocdata) {
			if(sizeof($vocdata) == 0) { unset($vocs[1][$vocname]); continue; }
			$temp = "";
			self::quicksortByInner($vocs[1][$vocname]);
			if($vocname !== 'Offline' || !$conf['hideoffline']) {
				for($i=0;$i<sizeof($vocdata);$i++)
					self::V_outputRow($temp,$vocdata[$i],($vocname==='Offline'));
			}
			if($vocname === 'Offline' && sizeof($vocs[1]) > 1) $temp = "\n<tr><td colspan=\"5\">&nbsp;</td></tr>" . $temp;
			$vocs[1][$vocname] = $temp;
		}
		$vocs[0] = implode("\n",$vocs[0]);
		$vocs[1] = implode("\n",$vocs[1]);
		
		$o = '
<table width="720">
 <td width="340" class="hdr">'.htmlspecialchars($conf['str_m']).'</th class="outer">
 <td width="40"></th>
 <td width="340" class="hdr">'.htmlspecialchars($conf['str_s']).'</th class="outer">
</tr>
<tr>
 <td class="left" valign="top">
  <table width="100%" cellpadding="0" cellspacing="0">
  
  <tr height="15"><td class="nw" width="16"></td><td class="n"></td><td class="ne" width="16"></td></tr>
  <tr><td class="w" width="16"></td><td>
  <table width="100%" class="main" cellpadding="0" cellspacing="1">
  '.$vocs[0].'
  </table>
  
  </td><td class="e" width="16"></td></tr>
  <tr height="15"><td class="sw" width="16"></td><td class="s"></td><td class="se" width="16"></td></tr>
  </table>
  
 </td>
 <td></td>
 <td class="right" valign="top">
  <table width="100%" cellpadding="0" cellspacing="0" cellpadding="0" cellspacing="0">
  
  <tr height="15"><td class="nw" width="16"></td><td class="n"></td><td class="ne" width="16"></td></tr>
  <tr><td class="w" width="16"></td><td>
  <table width="100%" class="main" cellpadding="0" cellspacing="1">
  '.$vocs[1].'
  </table>
  
  </td><td class="e" width="16"></td></tr>
  <tr height="15"><td class="sw" width="16"></td><td class="s"></td><td class="se" width="16"></td></tr>
  </table>
  
 </td>
</tr>
</table>';
		self::outputHeader($conf);
		echo $o;
		self::outputFooter();
	}
	private static function W_outputRow(&$content,&$char,$green) {
		$content .= '
  <tr onmouseover="this.style.backgroundColor=\'#C4F4CC\';" onmouseout="this.style.backgroundColor=\'\';">
   <td>'.($green ? self::$IMG_ON : self::$IMG_OFF).'</td>
   <td><a href="' . self::$A_CHAR_ESC . urlencode($char['name']) . '">' . $char['name'] . '</a></td>
   <td align="right">' . $char['level'] . '</td>
   <td>&nbsp;' . $char['voc'] . '</td>
   <td align="center">'.($char['comment']==''?'':'<img src="./cmt.png" title="'.htmlspecialchars($char['comment']).'"/>').'</td>
  </tr>';		
	}
	private static function W_output(&$conf,&$chars) {
		$vocs = array(0 => array('Druids' => array(),
							'Sorcerers' => array(),
							'Paladins' => array(),
							'Knights' => array(),
							'Rookies' => array(),
							'Offline' => array()),
					  1 => array('Druids' => array(),
							'Sorcerers' => array(),
							'Paladins' => array(),
							'Knights' => array(),
							'Rookies' => array(),
							'Offline' => array())
					);
		foreach($chars as $index => $char) {
			if(!isset($char['on'])) {
				$vocs[($char['issec']?1:0)]['Offline'][] =& $chars[$index];
			} else
			switch($char['voc']) {
				case 'D': case 'ED':
				$vocs[($char['issec']?1:0)]['Druids'][] =& $chars[$index]; break;
				case 'S': case 'MS':
				$vocs[($char['issec']?1:0)]['Sorcerers'][] =& $chars[$index]; break;
				case 'P': case 'RP':
				$vocs[($char['issec']?1:0)]['Paladins'][] =& $chars[$index]; break;
				case 'K': case 'EK':
				$vocs[($char['issec']?1:0)]['Knights'][] =& $chars[$index]; break;
				default:
				$vocs[($char['issec']?1:0)]['Rookies'][] =& $chars[$index]; break;
			}
		}
		foreach($vocs[0] as $vocname => $vocdata) {
			if($vocname === 'Offline' && $conf['hideoffline']) { unset($vocs[0][$vocname]); continue; }
			$green = $vocname === 'Offline' ? false : true;
			if(sizeof($vocdata) == 0) { unset($vocs[0][$vocname]); continue; }
			$temp = "";
			self::quicksortByInner($vocs[0][$vocname]);
			for($i=0;$i<sizeof($vocdata);$i++)
				self::W_outputRow($temp,$vocdata[$i],$green);
			$vocs[0][$vocname] = '
  <tr class="hdr">
   <td align="left" colspan="5">' . strtoupper($vocname) . ' ('.count($vocdata).')</td>
  </tr>
  <tr class="bld">
   <td></td>
   <td align="left">name</td>
   <td align="right">lvl</th>
   <td>voc</th>
   <td>info</th>
  </tr>' . $temp;
		}
		foreach($vocs[1] as $vocname => $vocdata) {
			if(sizeof($vocdata) == 0) { unset($vocs[1][$vocname]); continue; }
			if($vocname === 'Offline' && $conf['hideoffline']) { unset($vocs[1][$vocname]); continue; }
			$green = $vocname === 'Offline' ? false : true;
			$temp = "";
			self::quicksortByInner($vocs[1][$vocname]);
			for($i=0;$i<sizeof($vocdata);$i++)
				self::W_outputRow($temp,$vocdata[$i],$green);
			$vocs[1][$vocname] = '
  <tr class="hdr">
   <td align="left" colspan="5">' . strtoupper($vocname) . ' ('.count($vocdata).')</td>
  </tr>
  <tr class="bld">
   <td></td>
   <td align="left">name</td>
   <td align="right">lvl</th>
   <td>&nbsp;voc</th>
   <td>info</th>
  </tr>' . $temp;
		}
		$vocs[0] = implode('
  <tr class="spacer">
   <td colspan="3">&nbsp;</td>
  </tr>',$vocs[0]);
		$vocs[1] = implode('
  <tr class="spacer">
   <td colspan="3">&nbsp;</td>
  </tr>',$vocs[1]);
  

		$o = '
<table width="720">
 <td width="340" class="hdr">'.htmlspecialchars($conf['str_m']).'</th class="outer">
 <td width="40"></th>
 <td width="340" class="hdr">'.htmlspecialchars($conf['str_s']).'</th class="outer">
</tr>
<tr>
 <td class="left" valign="top">
  <table width="100%">
  <tr class="spacer">
   <td colspan="3">&nbsp;</td>
  </tr>'.$vocs[0].'
  </table>
 </td>
 <td></td>
 <td class="right" valign="top">
  <table width="100%">
  <tr class="spacer">
   <td colspan="3">&nbsp;</td>
  </tr>'.$vocs[1].'
  </table>
 </td>
</tr>
</table>';
		self::outputHeader($conf);
		echo $o;
		self::outputFooter();
	}
	private static function G_outputRow(&$content,&$char) {
		$content .= '
  <tr onmouseover="this.style.backgroundColor=\'#D4C0A1\';" onmouseout="this.style.backgroundColor=\'\';">
   <td><a href="' . self::$A_CHAR_ESC . urlencode($char['name']) . '">' . $char['name'] . '</a></td>
   <td align="right">' . $char['level'] . '</td>
   <td>' . $char['voc'] . '</td>
   <td align="center">'.($char['comment']==''?'':'<img src="./cmt.png" title="'.htmlspecialchars($char['comment']).'"/>').'</td>
  </tr>';		
	}
	private static function G_output(&$conf,&$chars) {
		$vocs = array(0 => array('Druids' => array(),
							'Sorcerers' => array(),
							'Paladins' => array(),
							'Knights' => array(),
							'Rookies' => array(),
							'Offline' => array()),
					  1 => array('Druids' => array(),
							'Sorcerers' => array(),
							'Paladins' => array(),
							'Knights' => array(),
							'Rookies' => array(),
							'Offline' => array())
					);
		foreach($chars as $index => $char) {
			if(!isset($char['on'])) {
				$vocs[($char['issec']?1:0)]['Offline'][] =& $chars[$index];
			} else
			switch($char['voc']) {
				case 'D': case 'ED':
				$vocs[($char['issec']?1:0)]['Druids'][] =& $chars[$index]; break;
				case 'S': case 'MS':
				$vocs[($char['issec']?1:0)]['Sorcerers'][] =& $chars[$index]; break;
				case 'P': case 'RP':
				$vocs[($char['issec']?1:0)]['Paladins'][] =& $chars[$index]; break;
				case 'K': case 'EK':
				$vocs[($char['issec']?1:0)]['Knights'][] =& $chars[$index]; break;
				default:
				$vocs[($char['issec']?1:0)]['Rookies'][] =& $chars[$index]; break;
			}
		}
		foreach($vocs[0] as $vocname => $vocdata) {
			if(sizeof($vocdata) == 0) { unset($vocs[0][$vocname]); continue; }
			$temp = "";
			self::quicksortByInner($vocs[0][$vocname]);
			for($i=0;$i<sizeof($vocdata);$i++)
				self::G_outputRow($temp,$vocdata[$i]);
			$vocs[0][$vocname] = '
  <tr class="hdr">
   <td align="left">' . ($vocname !== 'Offline' ? self::$IMG_ON : self::$IMG_OFF) . " " . $vocname . ' ('.count($vocdata).')</td>
   <td>Lvl</th>
   <td>Voc</th>
   <td>Info</th>
   
  </tr>' . ($conf['hideoffline'] && strtolower($vocname) === "offline" ? '' : $temp);
		}
		foreach($vocs[1] as $vocname => $vocdata) {
			if(sizeof($vocdata) == 0) { unset($vocs[1][$vocname]); continue; }
			$temp = "";
			self::quicksortByInner($vocs[1][$vocname]);
			for($i=0;$i<sizeof($vocdata);$i++)
				self::G_outputRow($temp,$vocdata[$i]);
			$vocs[1][$vocname] = '
  <tr class="hdr">
   <td align="left">' . ($vocname !== 'Offline' ? self::$IMG_ON : self::$IMG_OFF) . " " . $vocname . ' ('.count($vocdata).')</td>
   <td>Lvl</th>
   <td>Voc</th>
   <td>Info</th>
  </tr>' . ($conf['hideoffline'] && strtolower($vocname) === "offline" ? '' : $temp);
		}
		$vocs[0] = implode('
  <tr class="spacer">
   <td colspan="3">&nbsp;</td>
  </tr>',$vocs[0]);
		$vocs[1] = implode('
  <tr class="spacer">
   <td colspan="3">&nbsp;</td>
  </tr>',$vocs[1]);
  
		$o = '
<table cellpadding="4" cellspacing="1" width="720">
 <td width="340" class="hdr">'.htmlspecialchars($conf['str_m']).'</th class="outer">
 <td width="40"></th>
 <td width="340" class="hdr">'.htmlspecialchars($conf['str_s']).'</th class="outer">
</tr>
<tr>
 <td class="left" valign="top">
  <table width="100%" cellpadding="4" cellspacing="1">
  <tr class="spacer">
   <td colspan="3">&nbsp;</td>
  </tr>'.$vocs[0].'
  </table>
 </td>
 <td></td>
 <td class="right" valign="top">
  <table width="100%" cellpadding="4" cellspacing="1">
  <tr class="spacer">
   <td colspan="3">&nbsp;</td>
  </tr>'.$vocs[1].'
  </table>
 </td>
</tr>
</table>';
		self::outputHeader($conf);
		echo $o;
		self::outputFooter();
	}
	private static function adminPanel(&$conf,&$chars) {
		$nochars = sizeof($chars) == 0 ? true : false;
		$selectopts = array(array(), array(),array());
		foreach($chars as $k => $v) {
			$selectopts[($chars[$k]['issec']?1:0)][$chars[$k]['uid']] = $chars[$k]['name'];
			$selectopts[2][$chars[$k]['uid']] = $chars[$k]['name'];
		}
		if(empty($selectopts[0])) $selectopts[0] = "";
		else {
			asort($selectopts[0]);
			foreach($selectopts[0] as $k => $v) {
				$selectopts[0][$k] = '<option value="'.$k.'">'.$v."</option>";
			}
			$selectopts[0] = implode("\n",$selectopts[0]);
		}
		if(empty($selectopts[1])) $selectopts[1] = "";
		else {
			asort($selectopts[1]);
			foreach($selectopts[1] as $k => $v) {
				$selectopts[1][$k] = '<option value="'.$k.'">'.$v."</option>";
			}
			$selectopts[1] = implode("\n",$selectopts[1]);
		}
		if(empty($selectopts[2])) $selectopts[2] = "";
		else {
			asort($selectopts[2]);
			foreach($selectopts[2] as $k => $v) {
				$selectopts[2][$k] = '<option value="'.$k.'">'.$v."</option>";
			}
			$selectopts[2] = implode("\n",$selectopts[2]);
		}		
		$backlink = "./?id=".urlencode($conf['title']).
			($conf['upw']===null?'':'&amp;pw='.urlencode($conf['gpc_upw'])).
			($conf['layout']=='G'?'':"&amp;l=".$conf['layout']);
		$postlink = $backlink . '&amp;adm';
		
		self::outputHeader($conf,"Admin CP for ".$conf['title']." - Wartool Lite");
		echo '<table width="720">
 <tr align="center">
  <td colspan="2" style="padding-bottom:10px;"><a href="'.$backlink.'">back</a></td>
 </tr>';
		
		if(self::$err == '' && isset($_POST['apw'])) self::$err = array('','Note: Cookies must be enabled for the Admin CP to work<br />(they should be by default)');
			echo '
 <tr align="center">
  <td colspan="2" class="err'.self::$err[0].'">'.self::$err[1].'</td>
 </tr>';
		echo '
 <tr align="center">
  <td width="50%" align="left" valign="top">
   <fieldset><legend>Add Characters</legend>
   <form method="post" action="'.$postlink.'">
   <input type="hidden" name="act" value="addchar" />
   <p class="sma">- one name per line, max 30 at a time<br />
   - currently <strong>'.sizeof($chars).' / '.$conf['capacity'].'</strong> - need more? <a href="http://tnuc.org/contact/">mail me</a>.</p>
   <textarea class="wi he" name="chars_to_add"></textarea><br />
   <input type="radio" name="add_as_second" value="0" checked="checked"> as '.htmlspecialchars($conf['str_m']).'<br />
   <input type="radio" name="add_as_second" value="1" /> as '.htmlspecialchars($conf['str_s']).'<br />
   <input class="btn" type="submit" value=" apply " />
   </form>
   </fieldset>
   
   <fieldset><legend>Add Guild</legend>
   <p class="sma">- adds all characters from the guild to the character list<br />- does <b>not</b> monitor the guild for future joiners/leavers</p>
   <form method="post" action="'.$postlink.'">
   <input type="hidden" name="act" value="addguild" />
   <input type="text" class="txt wi" name="guild" /><br />
   <input type="radio" name="add_as_second" value="0" checked="checked" /> as '.htmlspecialchars($conf['str_m']).'<br />
   <input type="radio" name="add_as_second" value="1" /> as '.htmlspecialchars($conf['str_s']).'<br />
   <input type="checkbox" name="name_as_comment" value="1" /> set guildname as comment<br />
   <input type="checkbox" name="overwrite" value="1" checked="checked" /> overwrite existing comments<br />
   <input type="checkbox" name="invited_chars" value="1" checked="checked" /> include invited characters<br />
   <input class="btn" type="submit" value=" apply " />
   </form>
   </fieldset>
   
   <fieldset><legend>Remove Characters</legend>
   <form method="post" action="'.$postlink.'">
   <input type="hidden" name="act" value="rmchar" />
   <select size="1" class="wi" name="char">
'.$selectopts[2].'</select><br />
   <input class="btn" type="submit" value=" apply " />
   </form>
   </fieldset>
   
   <fieldset><legend>Edit Labels</legend>
   <form method="post" action="'.$postlink.'">
   <input type="hidden" name="act" value="labels" />
   <p class="sma">- sets names of your 2 char categories (table headers)</p>
   <input class="txt wi" name="lbl_m" type="text" value="'.htmlspecialchars($conf['str_m']).'" /><br />
   <input class="txt wi" name="lbl_s" type="text" value="'.htmlspecialchars($conf['str_s']).'" /><br />
   <input class="btn" type="submit" value=" apply " /><br />
   </form>
   </fieldset>
   
   <fieldset><legend>Set Password</legend>
   <form method="post" action="'.$postlink.'">
   <input type="hidden" name="act" value="change_upw" />
   <p class="sma">- leave blank to disable</p>
   <input class="txt wi" type="text" name="new_upw" /><br />
   <input class="btn" type="submit" value=" apply " />
   </form>
   </fieldset>
   
   <fieldset><legend>Hide offline chars</legend>
   <form method="post" action="'.$postlink.'">
   <input type="hidden" name="act" value="hideoffline" />
   <p class="sma">- leave blank to disable</p>
   <input type="checkbox" name="val" value="1"' . ($conf['hideoffline'] ? ' checked="checked"' : '') . ' /> check to hide<br />
   <input class="btn" type="submit" value=" apply " />
   </form>
   </fieldset>
   
  </td>
  <td width="50%" align="right" valign="top">
  
   <fieldset><legend>Toggle Char Type</legend>
   <form method="post" action="'.$postlink.'">
   <input type="hidden" name="act" value="issec1" />
   <p class="sma">- decides what table (left/right) char is shown in</p>
   <select size="1" name="char" class="wi">
'.$selectopts[0].'</select><br />
   <input class="btn" type="submit" value=" change to '.$conf['str_s'].' " />
   </form>
   <br /><br />
   <form method="post" action="'.$postlink.'">
   <input type="hidden" name="act" value="issec0" />
   <select size="1" name="char" class="wi">
'.$selectopts[1].'</select><br />
   <input class="btn" type="submit" value=" change to '.$conf['str_m'].' " />
   </form>
   </fieldset>
   
   <fieldset><legend>Set Char Comment</legend>
   <form method="post" action="'.$postlink.'">
   <input type="hidden" name="act" value="comment" />
   <p class="sma">- comments are shown when you hover over the "info" icon next to a character name</p>
   <select size="1" name="char" class="wi">
'.$selectopts[2].'</select><br />
   <input class="txt wi" type="text" name="new_comment" /><br />
   <input class="btn" type="submit" value=" apply " />
   </form>
   </fieldset>
   
   <fieldset><legend>Message of the Day</legend>
   <form method="post" action="'.$postlink.'">
   <input type="hidden" name="act" value="change_motd" />
   <p class="sma">- leave blank to disable</p>
   <textarea name="new_motd" class="wi he">'.htmlspecialchars($conf['motd']).'</textarea><br />
   <input class="btn" type="submit" value=" apply " />
   </form>
   </fieldset>
   
   <fieldset><legend>Edit Title / URL</legend>
   <form method="post" action="'.$postlink.'">
   <p class="sma">- changes the title and thereby the url of your wartool</p>
   <input type="hidden" name="act" value="change_title" />
   <input type="text" name="new_title" value="'.$conf['title'].'" /><br />
   <input class="btn" type="submit" value=" apply " />
   </form>
   </fieldset>
   
   <fieldset><legend>Change Admin Password</legend>
   <form method="post" action="'.$postlink.'">
   <input type="hidden" name="act" value="change_apw" />
   <p class="sma">- enter new password twice</p>
   <input class="txt wi" name="new_apw1" type="text" /><br />
   <input class="txt wi" name="new_apw2" type="text" /><br />
   <input class="btn" type="submit" value=" apply " />
   </form>
   </fieldset>
   
  </td>
 </tr>
</table>';
		self::outputFooter();
	}
	private static function signUp(&$conf) {
		self::$err = '';
		if(isset($_POST['act'])) {
			$t = isset($_POST['s_id']) && trim($_POST['s_id']) != '' ? $t = stripslashes(trim($_POST['s_id'])) : '';
			$u = isset($_POST['s_upw']) && trim($_POST['s_upw']) != '' ? $u = stripslashes(trim($_POST['s_upw'])) : null;
			$a = isset($_POST['s_apw']) && trim($_POST['s_apw']) != '' ? $a = stripslashes(trim($_POST['s_apw'])) : '';
			if(isset($_POST['s_server']) && in_array($_POST['s_server'],self::$servers)) $s = $_POST['s_server'];
			if(self::$DBLINK !== null) {
				if(isset($s)) {
					if(strlen($t) > 1 && strlen($t) <= 60) {
						if(preg_match('~^[A-Za-z0-9\_\-]*$~',$t)) {
							if($u === null || preg_match('~^[A-Za-z0-9\_\-]+$~',$u) == 1) {
								if($a !== '') {
									$q = "SELECT uid FROM projects WHERE title='".mysql_real_escape_string($t)."'";
									$r = mysql_query($q);
									if(!mysql_error()) {
										if(mysql_num_rows($r) == 0) {
											$q = "INSERT INTO projects (title,server,updated".($u !== null?',upw':'').
												",apw) VALUES ('".mysql_real_escape_string($t)."','".mysql_real_escape_string($s).
												"','".time()."',".($u !== null ? "'".md5($u)."',":'')."'".md5($a)."')";
												mysql_query($q);
												if(!mysql_error()) {
													setcookie('apw',md5($a),0,'/',self::$cookiepath);
													$location = './?id='.urlencode($t).($u!==null?'&pw='.urlencode($u):'');
													header('Location: '.$location);
												}
										} else self::$err = 'A project by the name &quot;'.htmlspecialchars($t).'&quot; already exists.';
									} else self::$err = 'Temporary database failure. Try again later.';
								} else self::$err = 'You must pick an admin password.';
							} else self::$err = 'User PW can only contain letters, numbers, _ and -';
						} else self::$err = 'Title can only contain letters, numbers, _ and -';
					} else self::$err = 'Title must 1-60 characters long.';
				} else self::$err = 'Invalid server.';
			} else self::$err = 'Temporary database failure. Try again later.';
		}
		self::outputHeader($conf,'Wartool Lite');
		$servs = array();
		if(!isset($t)) $t = '';
		if(!isset($u) || $u === null) $u = '';
		if(!isset($a)) $a = '';
		if(!isset($s)) $s = self::$servers[0];
		foreach(self::$servers as $k => $v)
			$servs[] = '<option value="' . self::$servers[$k] . '"' . ($s == self::$servers[$k] ? ' selected="selected"':'') . '>' . self::$servers[$k] . '</option>';
		$servs = implode("\n",$servs);
		echo '
<form action="./?get_started" method="post">
<table class="login" style="margin-top:100px;">
<tr>
<td class="ac" colspan="2"><a href="./">back</a></td>
</tr>
<tr>
<td>
<fieldset>
<legend> Get Started </legend>
<table>';
		if(self::$err != '') echo '
 <tr>
  <td colspan="2" class="ac err">'.self::$err.'</td>
 </tr>';
		echo '
 <tr>
  <td width="150" class="ar bld">Project Title</td>
  <td width="200"><input name="s_id" type="text" class="txt wi" value="'.htmlspecialchars($t).'" /></td>
 </tr>
 <tr>
  <td class="ar bld" valign="middle">User Password<span class="sma hurrr"><br />(leave blank to disable)</p></td>
  <td><input name="s_upw" type="text" class="txt wi" value="'.htmlspecialchars($u).'" /></td>
 </tr>
 <tr>
  <td class="ar bld">Admin Password</td>
  <td><input name="s_apw" type="text" class="txt wi" value="'.htmlspecialchars($a).'" /></td></td>
 </tr>
 <tr>
  <td class="ar bld">Server</td>
  <td><select name="s_server" size="1" class="wi">
'.$servs.'  
      </select></td>
 </tr>
 <tr>
  <td colspan="2" class="ac"><input type="hidden" name="act" value="reg" /><input type="submit" class="btn" value=" go " /><br /><br /><span class="err">Note: Cookies must be enabled for Admin CP<br />(they should be by default)</span></td>
 </tr>
</table>
</fieldset>
</td>
</tr>
</table>
</form>
<br /><br />
';
	}
	public static function run() {
		$CONF = array();
		$CHARS = array();
		try {
			self::dbConnect();
			self::getGPC($CONF);
			
			self::getConf($CONF);
			self::getDbChars($CONF,$CHARS);
			
			if($CONF['adm'] && $CONF['adm_auth']) {
				self::postActions($CONF,$CHARS);
				self::adminPanel($CONF,$CHARS);
			} else {
				$HTML = null;
				self::getOnlinePage($HTML,$CONF['server']);
				self::parseOnlinePage($HTML,$CONF,$CHARS);
				self::output($CONF,$CHARS);
				self::updateChars($CONF,$CHARS);
			}
		} catch(Exception $e) {
			if(!isset($CONF['adm'])) $CONF['adm'] = false;
			if(!isset($CONF['layout'])) $CONF['layout'] = 'G';
			self::$err = '';
			$ex = $e->getMessage();  // 'noid,upw,olist,noid,idInvalid';
			switch($ex) {
				case 'db':  		self::$err = 'Temporary database failure. Try again later.';
			break;
				case 'upw': 		$title = stripslashes(trim(urldecode($_GET['id'])));
									self::$err = 'Not so fast... the password?';
			break;
				case 'idInvalid': 	if(trim($CONF['title']) != '')
									self::$err = '&quot;'.$CONF['title'].'&quot; does not exist.';
			break;
				case 'olist':		self::$err = 'Failed to retrieve online list. Try again later.';
									if($CONF['adm_auth']) self::$err .= '<br />Or go straight to the <a href="./?id=' . urlencode($CONF['title']) .
									($CONF['upw'] !== null ? '&pw=' . urlencode($CONF['upw']) : '') . ($CONF['layout'] != 'G' ? '&l=' . $CONF['layout'] : '') .
									'&adm">Admin CP</a>.';
			break;
			}		
			if($ex != 'noid' || !isset($_GET['get_started'])) {
				self::outputHeader($CONF,'Wartool Lite');
				echo '
<table class="login" style="margin-top:100px;"><tr><td class="err ac">';
				if(self::$err != '') echo '
' . self::$err .'</td></tr><tr><td>';
				echo '
<fieldset><legend> Login </legend>
<form action="./" method="get" onsubmit="if(document.getElementById(\'pwfield\').value==\'\') document.getElementById(\'pwfield\').disabled=\'disabled\';">
<table><tr><td width="100" class="ar bld">
ID&nbsp;<br />
Password&nbsp;
</td><td width="200">
<input type="text" name="id" class="txt wi" onclick="document.getElementById(\'pwfield\').disabled=\'\';" value="'.(isset($CONF['title'])?htmlspecialchars($CONF['title']):($ex=='upw'?$title:'')).'" /><br />
<input id="pwfield" type="text" name="pw" class="txt wi" onclick="if(this.disabled!=\'\') this.disabled=\'\';" />
</td></tr><tr><td colspan="2" align="center">
<input type="submit" class="btn" value=" go " />
</td></tr></table>
</form>
</fieldset>
</td></tr></table>
<a href="./?get_started">&#187; create your own &#171;</a>
<p class="sma hurrr2">(it takes less than a minute)</p>
<p class="sma">or check out these example projects first:<br />
<a href="./?id=example1">&#187; example 1 &#171;</a><br />
<a href="./?id=example2&pw=some_password&l=V">&#187; example 2 &#171;</a></p>
<br />
';
			}
			else {
				self::signUp($CONF);
			}
			self::outputFooter();
		}
		self::dbDisc();
	}
}
WTlite::run();
@mysql_close();