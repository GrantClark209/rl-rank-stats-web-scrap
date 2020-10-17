<?php
/*
 * This file is subject to the terms and conditions defined in
 * file 'LICENSE.txt', which is part of this source code package.
 */


	$user = ''; // default
	$plat = 'steam'; // default
	$type = 'ranked'; // default (alt: 'extra', 'stats')
	$mmr = 'show'; // default (alt: 'hide')
		
	$rankData = array(
		'name' => '',
		'ranked' => array(
			'1v1' => array(0, 100), // rankNumber, MMR
			'2v2' => array(0, 100), // rankNumber, MMR
			'3v3' => array(0, 100), // rankNumber, MMR
			'Tournament' => array(0, 100), // rankNumber, MMR
		),
		'extra' => array(
			'Hoops' => array(0, 100), // rankNumber, MMR
			'Rumble' => array(0, 100), // rankNumber, MMR
			'Dropshot' => array(0, 100), // rankNumber, MMR
			'Snowday' => array(0, 100), // rankNumber, MMR
		),
		'stats' => array(
			'SeasonReward' => array(0, 0), // Level, Wins
			'Wins' => 0,
			'Goals' => 0,
			'Saves' => 0,
			'Assists' => 0,
			'Shots' => 0,
			'MVPs' => 0,
			'GoalShotRatio' => 0.0
		)
  );
	
	function hasPlaylist($name, $data){
		for ($i = 0 ; $i < count($data) ; $i++){
			if (isset($data[$i]['metadata'])){
				if ($data[$i]['metadata']['name'] == $name){
					return $i;
				}
			}
		}
		return -1;
	}

	if (!empty($_GET['user'])) { // is user parameter given 
		$user = str_replace(array(' ', '%20'), array('-', '-') , strtolower($_GET['user'])); // set user the value of given parameter in lower case and replace spaces with hyphen 
	}
	if (!empty($_GET['plat'])) { // is plat parameter given
		$plat = strtolower($_GET['plat']); // set plat the value of given parameter in lower case
	}
	if (!empty($_GET['type'])) { // is type parameter given
		$type = strtolower($_GET['type']); // set type the value of given parameter in lower case
	}
	if (!empty($_GET['mmr'])) { // is type parameter given
		$mmr = strtolower($_GET['mmr']); // set type the value of given parameter in lower case
	}


	// valid arguments
	$platforms = array('psn'=>1, 'steam'=>1, 'xbox'=>1); 
	$types = array('ranked'=>1, 'extra'=>1, 'stats'=>1);
	$mmrVisibility = array('show'=>1, 'hide'=>1);


	// no username or too long username
	if (($user=='') || (strlen($user)>32)) {
		$error = array(
			'message' => 'No/Invalid username given',
			'code' => '0'
    );
		die(json_encode($error));
	}

	// validate platform
	if (!isset($platforms[$plat])) {
		$error = array(
			'message' => $plat.' is an invalid platform on rocketleague.tracker.network',
			'code' => '0'
    );
		die(json_encode($error));
	}

	if (!isset($types[$type])) {
		$error = array(
			'message' => '$type must be blank, "ranked", "extra", or "stats"',
			'code' => '0'
    );
		die(json_encode($error));
	}

	if (!isset($mmrVisibility[$mmr])) {
		$error = array(
			'message' => '$mmr must be blank, "show", or "hide"',
			'code' => '0'
    );
		die(json_encode($error));
	}

  $RL_tracker = @file_get_contents('https://rocketleague.tracker.network/rocket-league/profile/'.$plat.'/'.$user.'/overview'); // get html code

	preg_match_all("/\"segments\":(.+?),\"availableSegments\"/is", $RL_tracker, $first); // first = json of stats and rank data
	preg_match_all("/\"platformUserHandle\":\"(.+?)\",\"platformUserIdentifier\"/is", $RL_tracker, $name); // fetch name

	$rankData['name'] = isset($name[1][0]) ? html_entity_decode($name[1][0], ENT_QUOTES | ENT_XML1, 'UTF-8') : $user; // set name

	if (count($first[0])==0) { // checking for existing data (if not, no ranks given)
		$error = array(
			'message' => $user.' ('.$plat.') has no data on rocketleague.tracker.network yet.',
			'code' => 0
    );
		die(json_encode($error));
	}

	$data = json_decode($first[1][0], true); // decode to php array 
	// or use the array $data instead of $rankData, ofc it has lots more info.
	// var_dump($data); 

	$rankData['stats']['SeasonReward'] = array($data[0]['stats']['seasonRewardLevel']['value'], $data[0]['stats']['seasonRewardWins']['value']);
	$rankData['stats']['Wins'] = $data[0]['stats']['wins']['value'];
	$rankData['stats']['Goals'] = $data[0]['stats']['goals']['value'];
	$rankData['stats']['MVPs'] = $data[0]['stats']['mVPs']['value'];
	$rankData['stats']['Saves'] = $data[0]['stats']['saves']['value'];
	$rankData['stats']['Assists'] = $data[0]['stats']['assists']['value'];
	$rankData['stats']['Shots'] = $data[0]['stats']['shots']['value'];
	$rankData['stats']['GoalShotRatio'] = $data[0]['stats']['goalShotRatio']['value'];

	// shortened playlist names / associations
	$rankedPlaylists = array('Ranked Duel 1v1'=>'1v1', 'Ranked Doubles 2v2'=>'2v2', 'Ranked Standard 3v3'=>'3v3', 'Tournament Matches' => 'Tournament');
	$extraPlaylists = array('Hoops', 'Rumble', 'Dropshot', 'Snowday');

	if(count($data) > 1 ) { // not sure if consistent yet, maybe needs some revamp
		foreach ($rankedPlaylists as $key => $value) {
			$id = hasPlaylist($key, $data);

			if($id != -1){
				$rankData['ranked'][$value] = array($data[$id]['stats']['tier']['value'], $data[$id]['stats']['rating']['value']);
			}
		}
		foreach ($extraPlaylists as $value) {
			$id = hasPlaylist($key, $data);

			if($id != -1){
				$rankData['extra'][$value] = array($data[$id]['stats']['tier']['value'], $data[$id]['stats']['rating']['value']);
			}
		}
	}
	
	
	$rewardLevels = array('Unranked', 'Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Champion', 'Grand Champion', 'Supersonic Legend'); // array of all possible reward levels (bottom up)

	$rankNames = array('Unranked', 'Bronze I', 'Bronze II', 'Bronze III', 'Silver I', 'Silver II', 'Silver III', 'Gold I', 'Gold II', 'Gold III', 'Platinum I', 'Platinum II', 'Platinum III', 'Diamond I', 'Diamond II', 'Diamond III', 'Champion I', 'Champion II', 'Champion III', 'Grand Champion I', 'Grand Champion II', 'Grand Champion III', 'Supersonic Legend'); // array of all possible rank names (bottom up)

	echo json_encode($rankData);

?>