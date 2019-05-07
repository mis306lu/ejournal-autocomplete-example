<?php

    //**THIS CODE IS ONLY CALLED (VIA AJAX) WHEN THE TOKEN EXPIRES
    //AUTHENTICATE W/EBSCO TO USE THE AUTOCOMPLETE ON THE EJOURNAL TAB

    
    require_once("vendor/autoload.php"); 
    use GuzzleHttp\Client;
    use GuzzleHttp\Exception\RequestException;
	if (isset($_GET['token'])) {
	  $token = authenticate();
	  echo trim($token);
	  die;
	}
	
    function authenticate() {

		$client = new Client();
		$authString = '{"UserId":"redacted","Password":"redacted","Options":["autocomplete"],"InterfaceId":"redacted"}';


		try {
			$response = $client->post("https://eds-api.ebscohost.com/authservice/rest/uidauth", [
			  'connect_timeout' => 10,
			  'body' => $authString,
			   'headers' => ['Content-type' => 'application/json','Accept' => 'application/json']
		   ]);

		   if ($response->getBody()) {
			  $obj = json_decode($response->getBody());
			  $url = $obj->Autocomplete->Url;
			  $token = $obj->Autocomplete->Token;
			  echo $token;
		   }

	   } catch (RequestException $e) {

		  // Catch all 4XX errors
		  if ($e->getResponse()->getStatusCode() == '400') {
				//echo "got 400";
				echo "";
		  }

	   } catch (\Exception $e) {
			//echo "got an error";
			echo "";
	   }

} 
   
 ?>