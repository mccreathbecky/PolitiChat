<?php

	$Open_Australia_Key = 'DfUS8qDPJCwxF7pLiiAw92pe';
	$They_Vote_Key = 'CUbVsIKQIuura7PxZOVX';
	

	//depending on what function is calling from chatFuel
	switch($_GET['function'])
	{
		case 'getBio':
			if ($_GET['bio_type'] == 0)
			{
				echo getPoliticianBio($_GET['postcode'],0);
				return;
			}
			else if($_GET['bio_type'] == 1)
			{
				echo getPoliticianBio($_GET['name'],1);
				return;
			}
			else
				getPoliticianBio($_GET['name'],2);
			break;
			
		case 'getMyMP':
			if(isset($_GET['name']) && ! empty($_GET['name']))
			{
				getMyMPPolicies($_GET['name'],1);
			}
			else if( isset($_GET['postcode']) && ! empty($_GET['postcode']) )
			{
				getMyMPPolicies($_GET['postcode'],0);
			}
			else
				returnMyMPError();
			break;
			
		case 'getPolicyByName':
			getPolicyByName($_GET['name']);
			break;
			
		default:
			testing();
			break;
	}
	
	
	


	
	
	
	/******************************************************************************/
	/*****************************POLLY BIO****************************************/
	/******************************************************************************/
	
	
	


	//This function is hopefully going to call the openaustralia api to get the politician data
	//type if 0- default bio from postcode, 1 - default bio from name, 2 - brief bio from name 
	function getPoliticianBio($search_param, $type)
	{
		//build the api request using the key [global variable] and json format, and postcode 
		global $Open_Australia_Key;
		
		$politician_list = [];
		$bioNb = 0;
		$dataError = false;
		$mp_senator = "MP";

		
		if(is_null($search_param))
		{
			if($type == 0)
				returnError();
			else
				returnFromPolicyError();

			return;
		}
		
		
		//check for both MP and senator if fails for MP
		for($count = 0; $bioNb < 1 && $count < 2; $count++)
		{			
			if($count == 1)
				$mp_senator = "Senator";

			if($count == 0)
				$url = "http://www.openaustralia.org/api/getRepresentatives";
			else
				$url = "http://www.openaustralia.org/api/getSenators";
			
			//if searching by name or postcode
			if($type == 0)
				$data = array('postcode'=>$search_param,'output'=>'js','key'=> $Open_Australia_Key);
			else
				$data = array('search'=>$search_param,'output'=>'js','key'=> $Open_Australia_Key);
			
			$GETurl = sprintf("%s?%s", $url, http_build_query($data));
			//translate fullBio into a usable form
			$fullBio = json_decode(file_get_contents($GETurl));
			
			

			
			
			//check whether there was any actual data 
			if( (isset($fullBio->error)) || ($fullBio == new StdClass()) )
			{
				$dataError = true;
			}
			else
			{
				//fills politician_list with an array of politician data
				$bioNb = getDataForPerson($fullBio, $politician_list);
				
				$dataError = false;
			}
		}
		
		//DETERMINE IF ERROR AFTER CHECKING BOTH MP AND SENATOR
		if($dataError == true || $bioNb > 2)
		{
			if($type == 0)
				returnErrorPostcode();
			else if($type == 1)
				returnErrorName();
			else
				returnFromPolicyError();
			
			return;
		}
		
		
		//IF NO ERROR, THEN RETURN DATA
		//only option of multiple candidates when searching by postcode, not by name
		if($type == 0 || $type == 1)
		{	
			$cards = [];
			
			if($bioNb > 1)
				addText("There are ".$bioNb." possible MPs for your postcode:", $cards);
			
			foreach($politician_list as $mp)
			{
				if ($mp[4] != null)
					addImage($mp[4], $cards);
				
				//0full name, 1party, 2entered_house, 3constituency, 5titles, 4image_url
				addText(formatForBot($mp[0], $mp[1], $mp[2], $mp[3],$mp[5], $mp_senator), $cards);	
			}
			
			if($bioNb > 1)
			{
				selectMP("Poly_Name", $politician_list[0][0], $politician_list[1][0], $cards);
				
			}
			else
			{
				addButton("I hope they're doing a good job...", [["Policy Overview", "Yeah, me too!"],["PollieBio-NotKnow", "Typo! Try again"]], $cards);
			}
			compileChatFuel($cards);
		}
		else
		{
			fromPolicyPrintBio($politician_list[0][0], $politician_list[0][1], $politician_list[0][2], $politician_list[0][3], $politician_list[0][5], $mp_senator);
		}
	}
	
	
	function getDataForPerson($fullBio, &$politician_list)
	{
		$count = 0;
		foreach($fullBio as $pollie)
		{	
			$count++;
			$person = [];
			
			//get the values from the object
			$person[0] = $pollie->full_name;
			$person[1] = $pollie->party;
			$person[2] = $pollie->entered_house;
			$person[3] = $pollie->constituency;

			
			if(isset($pollie->image))
				$person[4] = "http://www.openaustralia.org" . $pollie->image;
			else
			{
				$person[4] = null;
			}
			
			if(isset($pollie->office))
			{
				$positions = [];
				$titles = "";
				foreach($pollie->office as $job)
				{
					if($job->to_date == "9999-12-31")
					{
						array_push($positions, $job->position);				
					}
				}
				
				$job_nb = 0;
				$amt_pos = sizeof($positions);
				if($amt_pos > 0)
				{
					if($amt_pos == 1)
					{
						$titles = "Their current position is ".$positions[0];
					}
					else
					{
						$titles = "Their current positions are: ";
						foreach($positions as $polly_job)
						{
							$titles .= $polly_job;	
							if ($job_nb < $amt_pos - 1 )
								$titles .= " and ";
							$job_nb++;
						}
					}
					$titles .= ".";
				}
			}
			else
				$titles = null;
			
			$person[5] = $titles;
			array_push($politician_list, $person);
		}

		return $count;
	}
	
	
	//Formats the Politician Bio into a string with "" on either side to be inserted into returnToBot()
	function formatForBot($full_name, $party, $entered_house, $constituency, $position = null, $mp_senator)
	{
		$formatted = $full_name . " is a member of the " . $party . ". Joining Parliament for the first time in " . $entered_house . " they are now seated as the ". $mp_senator. " for ". $constituency. ".";
		
		if($position != null)
			$formatted .= " ".$position;
		
		return $formatted;
	}
	
	
	function returnErrorPostcode()
	{
		$cards = [];
		addButton("Uh-oh. Unfortunately, I can't find any data for them.", [["PollieBio-NotKnow", "Typo! Try again"],["NoData", "Let's move on"]], $cards);
		compileChatFuel($cards);
	}
	
	function returnErrorName()
	{
		$cards = [];
		addButton("Uh-oh. Unfortunately, I can't find any data for them.", [["PollieBio-NotKnow", "Try my postcode"],["NoData", "Let's move on"]], $cards);
		compileChatFuel($cards);
	}
	
	function fromPolicyPrintBio($full_name, $party, $entered_house, $constituency, $titles, $mp_senator)
	{
		$cards = [];
		addText(formatForBot($full_name, $party, $entered_house, $constituency, $titles, $mp_senator), $cards);
		addButton("I hope they're doing a good job...", [["Policy_By_Name", "Look up another one!"],["Policy Topics", "Let's keep moving"]], $cards);
		compileChatFuel($cards);
	}
	
	function returnFromPolicyError()
	{
		$cards = [];
		addButton("Uh-oh. Unfortunately, I can't find any bio data for that person.", [["Policy_By_Name", "Look up more!"],["Policy Topics", "Let's keep moving"]], $cards);
		compileChatFuel($cards);
	}
	
	function selectMP($att_title, $att_value, $att_value2, &$cardList)
	{
		$select_MP_string = '
			{
			  "attachment": {
				"type": "template",
				"payload": {
				  "template_type": "button",
				  "text": "Which MP would you like to set as your representative?",
				  "buttons": 
				  [
					{
						"set_attributes": 
							{
								"'.$att_title.'": "'.$att_value.'"
							},
							"block_name": "Set MP",
							"type": "show_block",
							"title": "'.$att_value.'"
					},
					{
						"set_attributes": 
							{
								"'.$att_title.'": "'.$att_value2.'"
							},
						"block_name": "Set MP",
						"type": "show_block",
						"title": "'.$att_value2.'"
					}
				  ]
				}
			  }
			}
		';
		
		$attribute_card[0] = "set_attributes";
		$attribute_card[1] = $select_MP_string;

		addAttribute($attribute_card, $cardList);
	}
	
	
	/******************************************************************************/
	/*****************************GET YOUR MP POLICIES*****************************/
	/******************************************************************************/
	
	
	//TO FIX: Integrate this with the normal policies section	
	function getMyMPPolicies($search_param, $param_type)
	{
		//build the api request using the key [global variable] and json format, and postcode 
		global $Open_Australia_Key;
		$official_name = "";
		
		$url = "http://www.openaustralia.org/api/getRepresentatives";
		
		if($param_type == 0)
			$data = array('postcode'=>$search_param,'output'=>'js','key'=> $Open_Australia_Key);
		else
			$data = array('search'=>$search_param,'output'=>'js','key'=> $Open_Australia_Key);
		
		$GETurl = sprintf("%s?%s", $url, http_build_query($data));
	
		//translate fullBio into a usable form
		$fullBio = json_decode(file_get_contents($GETurl));
		
		//check whether the postcode yielded any actual data
		if(isset($fullBio->error) || $fullBio == new StdClass())
		{
			returnMyMPError();
			return;
		}
			
		//pull the first record [it's now an object!!!]
		$pollie = $fullBio[0];
		
		$person_id = $pollie->person_id;
		$official_name = $pollie->full_name;
		$deets = getPersonDetails($person_id, $official_name);
		$sorted_policies = sortPolicies($deets, $official_name);
		
		
		returnMyMP($sorted_policies);
	}
	
	
	function returnMyMP($sorted_policies)
	{
		$cards = [];
		addText($sorted_policies, $cards);
		compileChatFuel($cards);
	}
	
	function returnMyMPError()
	{
		$cards = [];
		addText("Uh-oh. Unfortunately, we can't find any data for your MP.", $cards);
		compileChatFuel($cards);
	}
	

	

	
	
	
	
	
	
	/******************************************************************************/
	/*****************************POLICIES*****************************************/
	/******************************************************************************/

	
	function getPolicyByName($name)
	{
		$official_name = "";
		
		//make name have first letters uppercase
		$formatted_name = ucwords($name, " ");
		
		
		//STEP 1: GET THE PERSON'S ID:
		$person_id = getPerson_ID($formatted_name, $official_name);
		

		if($person_id == null)
		{
			returnPersonIDError();
			return;
		}
		
		
		//STEP 2: GET THE PERSON'S DATA FROM THEYVOTEFORYOU
		$person_details = getPersonDetails($person_id);
		if($person_details == null)
		{
			returnPersonIDError();
			return;
		}
		
		
		//STEP 3: SORT OUT THE THREE TOP AND BOTTOM VOTED POLICIES
		$formatted_policies = sortPolicies($person_details, $official_name);
		
		
		//STEP 4: RETURN IN A FORMAT CHATFUEL CAN READ
		returnPersonPolicies($formatted_policies, $official_name);	

	}
	


			
	//gets the ID from OpenAustralia to be used in getPolicy functions
	function getPerson_ID($name, &$official_name)
	{
		//build the api request using the key [global variable] and json format, and postcode 
		global $Open_Australia_Key;
		$person_id = null;

		
		for($count = 0; $person_id == null && $count < 2; $count++)
		{
			if($count == 0)
				$url = "http://www.openaustralia.org/api/getRepresentatives";
			else
				$url = "http://www.openaustralia.org/api/getSenators";
				
			$data = array('search'=>$name,'output'=>'js','key'=> $Open_Australia_Key);
			$GETurl = sprintf("%s?%s", $url, http_build_query($data));
		
			//translate fullBio into a usable form
			$fullBio = json_decode(file_get_contents($GETurl));
			
			//check if the name yielded any actual data;
			if(! ($fullBio == new StdClass()))
			{
				//pull the first record [it's now an object!!!]
				$pollie = $fullBio[0];

				
				//get the values from the object
				$person_id = $pollie->person_id;
				$official_name = $pollie->full_name;
			}
		}
		
		return $person_id;
	}
	
	
	//get policy details from theyVoteForYou
	function getPersonDetails($id)
	{
		//build the api request using the key [global variable] and json format, and postcode 
		global $They_Vote_Key;

		$url = 	"https://theyvoteforyou.org.au/api/v1/people/".$id.".json";

		$data = array('key'=> $They_Vote_Key);
		$GETurl = sprintf("%s?%s", $url, http_build_query($data));
	
		//translate fullBio into a usable form
		$fullDetails = json_decode(file_get_contents($GETurl));
		
		if(isset($fullDetails->error))
		{
			return null;
			
		}
		
		return $fullDetails;	
	}
	
	
	
	
	
	
	function sortPolicies($person_details, $official_name)
	{
		
		$policies = $person_details->policy_comparisons;
		$policy_amt = sizeof($policies);	
		$policy_cnt = 1;
		
		$formatted_policies = "This is a small snap shot into ". $official_name. ".\\n";
		$formatted_policies .= "The 3 policies they most STRONGLY AGREE with are: \\n";		
		
		for($count=0; $count < 3; $count++, $policy_cnt++)
		{
			//this checks for " " and replaces it with ' ' so that the json string formats correctly
			$policy = ucwords($policies[$count]->policy->name, " ");
			$policy = str_replace("\"","'",$policy);
			
			$formatted_policies .= "\\n"."- ".$policy;	
			
		}
		
		$formatted_policies .= "\\n\\nThe 3 policies they most STRONGLY DISAGREE with are: \\n";
		
		for($count = 3; $count > 0; $count--, $policy_cnt++)
		{
			//this checks for " " and replaces it with ' ' so that the json string formats correctly
			$policy = ucwords($policies[$count]->policy->name, " ");
			$policy = str_replace("\"","'",$policy);
			
			$formatted_policies.= "\\n"."- ".ucwords($policies[$policy_amt - $count]->policy->name, " ");		
		}
		
		
		return $formatted_policies;
	}
	
	
	function returnPersonPolicies($policyString, $official_name)
	{
		$cards = [];
		addText($policyString, $cards);
		addButton("Would you like to look up more politicians?",[["Policy_By_Name","Yes please!"],["Policy Topics","That's all for now!"],["LookupFromPolicy", "Can I see their bio?"]],$cards);
		compileChatFuel($cards);
	}
	
	
	
	function returnPersonIDError()
	{
		$cards = [];
		addButton("Uh-oh. Unfortunately, we can't find any data for that person.",[["Policy_By_Name","Try again!"],["Policy Topics","Let's move on"]],$cards);
		compileChatFuel($cards);
	}

	
	
	
	
	
	/******************************GENERIC CHATFUEL****************************/
	

	function testing()
	{
		$cards = [];
		addText("this is some interesting text", $cards);
		addImage("www.openaustralia.org.au/images/mpsL/10001.jpg", $cards);
		addButton("look at this cool stuff", [["Final Block", "the end"],["Policy Topics", "policies"]], $cards);
		compileChatFuel($cards);
	}
	
	function addText($text, &$cardList)
	{
		$newCard = ["text", $text];
		array_push($cardList, $newCard);
	}
	
	function addImage($image_url, &$cardList)
	{
		$newCard = ["image", $image_url];
		array_push($cardList, $newCard);
	}
	
	function addButton($button_text, $button_array, &$cardList)
	{
		$newCard = ["button", $button_text, $button_array];
		array_push($cardList, $newCard);
	}
	
	function addAttribute($string, &$cardList)
	{
		array_push($cardList, $string);
	}

	
	//$cards is an array of arrays {{'text', 'cats are great'}, {'image', 'http://google.com'}}
	function compileChatFuel($cards)
	{		
		//beginning and end
		$start = '{"messages":[';
		$end = ']}';
		
		//STEP 1: Start the message
		$chatFuel = $start;
		
		//STEP 2: For each element in array $card_types, add the appropriate type and data to $chatFuel
		foreach($cards as $card)
		{
			//first element in sub-array contains the card type
			//second element contains the data
			switch($card[0])
			{
				case "text":
					$chatFuel .= '{"text": "'.$card[1].'"},';
					break;
					
				case "image":
					$chatFuel .= '{"attachment":{"type": "image","payload":{"url": "'.$card[1].'"}}},';
					break;
				
				
				//assume that the format of data passed for a button is {"button", "text_for_start", {{"btn1_block", "btn1_title"}, {"btn2_block", "btn2_title"}...}}
				case "button":
					$chatFuel .= '{"attachment": {"type": "template","payload": {"template_type": "button","text": "'.$card[1].'","buttons": [';
					
					//for each button in the array, add it to $chatFuel
					foreach($card[2] as $button_data)
					{
						$chatFuel .= '{"type": "show_block","block_name": "'.$button_data[0].'","title": "'.$button_data[1].'"},';
					}
					
					//remove the trailing comma on the end of the last button
					$chatFuel = rtrim($chatFuel, ",");
					$chatFuel .= ']}}},';
					break;
					
				case "set_attributes":
					$chatFuel .= $card[1];
				
				default:
					break;
			}
		}
		
		//remove the trailing comma on the end of the last element
		$chatFuel = rtrim($chatFuel, ",");
		$chatFuel .= $end;
		
		echo $chatFuel;
	}
?>
