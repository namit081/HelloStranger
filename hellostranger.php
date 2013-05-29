<?php
require_once("response.php");
session_start();

$r = new Response();


/* Asking caller to enter his gender 1# for males 2# for females*/
if($_REQUEST['event']=="NewCall") 
{
	$cd = new CollectDtmf();
	$cd->setTermChar("#");
	$cd->setTimeOut("4000");
	$cd->addPlayText("Press 1 followed by hash if you are Male press 2 followed by hash if you are Female");
	$r->addCollectDtmf($cd);
	$_SESSION['state'] = "firstNumber";
	$r->send();

}

/* Ask caller whom he wants to talk to, 1# if he/she wants to talk to males, 2# if he/she wants to talk to females*/
else if ($_REQUEST['event']=="GotDTMF" && $_SESSION['state'] == "firstNumber") 
{
if($_REQUEST['data'] == 1 or $_REQUEST['data'] == 2)
	{
	$_SESSION['gender'] = $_REQUEST['data'];
	$cd = new CollectDtmf();
	$cd->setTermChar("#");
	$cd->setTimeOut("4000");
	$cd->addPlayText("Press 1 followed by hash if you want to chat with Male press 2 followed by hash if you want to chat with Female");
	$r->addCollectDtmf($cd);
	$_SESSION['state'] = "secondNumber";
	$r->send();
	}
else //If caller presses digits other than 1 or 2 ask him to enter again
	{
	$cd = new CollectDtmf();
	$cd->setTermChar("#");
	$cd->setTimeOut("4000");
	$cd->addPlayText("Listen carefully Press 1 followed by hash if you are Male press 2 followed by hash if you are Female");
	$r->addCollectDtmf($cd);
	$r->send();
	}


}

/*adding the caller to the list of online callers or connecting the caller to already online callers*/
else if ($_REQUEST['event']=="GotDTMF" && $_SESSION['state'] == "secondNumber")
{if($_REQUEST['data'] == 1 or $_REQUEST['data'] == 2 )
{	$first = $_REQUEST['data'];
	$_SESSION['confno'] = -1; 
	if($_SESSION['gender']!=$first)
		{
		if($_SESSION['gender'] == 1)
			{$talk = 2;
			 $me = 1;
			$_SESSION['state']="binconf";}
		else if($_SESSION['gender'] == 2)
			{$talk = 1;
			 $me = 2;
			$_SESSION['state']="ginconf";}
		}
	else if($_SESSION['gender']==$first)
		{
		if($_SESSION['gender'] == 1)
			{$talk = 3;
			$me = 3;
			$_SESSION['state']="gainconf";}
		else if($_SESSION['gender'] == 2)
			{$talk = 4;
			$me = 4;
			$_SESSION['state']="linconf";}
		}
	
	
	$got = 0;
		$fp = fopen("confnumb.txt","r");
		
		$buffer = fgets($fp);
		$exploded_data = explode("   ",$buffer); 
			
		for($i=0;$i<sizeof($exploded_data);$i++)	// checking the list of online users according to the callers choice
			{
			if(($test = $exploded_data[$i]%10) == $talk) 
				{
				$conf = floor($exploded_data[$i]/10);
				$r->addPlayText("We have found someone for you");
				$r->addConference($conf);			// adding the user to conference if he/she found his/her match
				
				$got = 1;
				fclose($fp);
				delete($exploded_data[$i],"confnumb.txt");
				$_SESSION['confno']=$conf;
				
				$fp = fopen("inconf.txt","a+");
				fwrite($fp,$conf."   ");
				fclose($fp);
				break;
				}
			}
		if($got != 1)					// adding user to waiting list if no match is found
			{
			$fp = fopen("confnumb.txt","a+");
			$rand = rand(1, 10000000);
			$conf = $rand;
			$rand = ($rand * 10) + $me;
			$line = "	";
			fwrite($fp,$rand."   ");
			fclose($fp);
			
		
			$r->addPlayText("Please wait someone will join you soon");
			
			$r->addConference($conf); 
			
			$_SESSION['confno']=$conf; 
			}
	
	$r->send();
}
else //If caller presses digits other than 1 or 2 ask him to enter again
{
$cd = new CollectDtmf();
$cd->setTermChar("#");
$cd->setTimeOut("4000");
$cd->addPlayText(" Listen Carefully Press 1 followed by hash if you want to chat with Male press 2 followed by hash if you want to chat with Female");
$r->addCollectDtmf($cd);
$r->send();
}
}


/*IF the caller hangups before he/she is connected to anyone he/she will be removed from the list otherwise the other person 
he/she is connected to will be added to waiting callers list*/ 
else if ($_REQUEST['event']=="Hangup")
{
$done = 0;
$del = $_SESSION['confno'];

$fp = fopen("inconf.txt","r");
$buffer = fgets($fp);
$check = explode("   ",$buffer);
fclose($fp);

for($i=0;$i<sizeof($check);$i++)	// If one of the person in conference hangs up then the other user is added to waiting list
			{
			if($del == $check[$i]) 
				{
				delete($del,"inconf.txt");
				if($_SESSION['state']=="binconf")
					{
					$fp = fopen("confnumb.txt","a+");
					fwrite($fp,$del."2"."   ");
					fclose($fp);
					}
				else if($_SESSION['state']=="ginconf")
					{
					$fp = fopen("confnumb.txt","a+");
					fwrite($fp,$del."1"."   ");
					fclose($fp);
					}
				else if($_SESSION['state']=="gainconf")
					{
					$fp = fopen("confnumb.txt","a+");
					fwrite($fp,$del."3"."   ");
					fclose($fp);
					}
				else if($_SESSION['state']=="linconf")
					{
					$fp = fopen("confnumb.txt","a+");
					fwrite($fp,$del."4"."   ");
					fclose($fp);
					}
				$done = 1;
				break;
				}
			}
	if($done == 0) 					// If user who is waiting alone hangs up then he is removed from waiting list
	{
	$fp = fopen("confnumb.txt","r");
	$buffer = fgets($fp);

	if($_SESSION['state']=="binconf")
	$new = str_replace($del."1"."   ","",$buffer);

	else if($_SESSION['state']=="ginconf")
	$new = str_replace($del."2"."   ","",$buffer);
	
	else if($_SESSION['state']=="gainconf")
	$new = str_replace($del."3"."   ","",$buffer);
	
	else if($_SESSION['state']=="linconf")
	$new = str_replace($del."4"."   ","",$buffer);
	
	fclose($fp);

	$fp = fopen("confnumb.txt","w");
	fwrite($fp,$new);
	fclose($fp);
	}
}

/* Delete function for removing the user from the waiting list*/
function delete($delvar,$str)
{
  $fp = fopen($str,"r");
  $buffer = fgets($fp);
  $new = str_replace($delvar."   ","",$buffer);
  fclose($fp);
  $fp = fopen($str,"w");
  fwrite($fp,$new);
  fclose($fp);
				
}
?>