<?php //DON'T CACHE THIS PAGE.  PHP HAS TO GENERATE HTML
      //EACH TIME IT'S REQUESTED BECAUSE IT RETRIEVES AUTHENTICATION 
	    //TOKENS (FOR AUTO-SUGGEST) ?>
<?php $GLOBALS['conf']['cache'] = FALSE; ?>

<?php //NEEDED SO PAGE CAN SEEMLESSLY GET ANOTHER AUTH TOKEN 
      //WITHOUT INTERRUPTION OF AUTOCOMPLETE ?>
<?php include('ebscoReAuthenticate.php'); ?>


<!--Library Website Code Removed-->


<script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
<script src="https://asa.lib.lehigh.edu/jquery.autocomplete.js"></script>


<?php
    //#1) SETUP AUTOCOMPLETE WITH EBSCO
    //AUTHENTICATE W/EBSCO TO USE THE AUTOCOMPLETE ON THE EJOURNAL TAB
    //JAVASCRIPT WILL USE THE TOKEN AND URL THIS API CALL RETREIVES
    require_once("vendor/autoload.php"); 
    use GuzzleHttp\Client;
    use GuzzleHttp\Exception\RequestException;

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
       }

    } catch (RequestException $e) {

       // Catch all 4XX errors
       if ($e->getResponse()->getStatusCode() == '400') {
            echo "<script>console.log('Got response 400 authenticating with ebsco');</script>";
			      echo "<script>console.log(" . $e->getResponse()->getBody() . ");</script>";
        }

    } catch (\Exception $e) {
        echo "<script>console.log(error authenticating with ebsco" . $e->getResponse()->getBody() . ");</script>";
    }


   //#2)SETUP AUTO COMPLETE FOR SPRINGSHARE
   try {
     //AUTH WITH SPRINGSHARE
     $springClient = new Client(); 

     $springShareResponse = $springClient->post("https://lgapi-us.libapps.com/1.2/oauth/token", [
        'connect_timeout' => 10,
		    'body' => array(
		    'client_id' => 'redacted',
		    'client_secret' => 'redacted',
		    'grant_type' => 'client_credentials'),
    ]);
	if ($springShareResponse->getBody()) {
		$obj = json_decode($springShareResponse->getBody());
		$springshareToken = $obj->access_token;
	}
	else {
	  echo "<script>console.log(unable to authenticate with springshare);</script>";
	}

  }
  catch (RequestException $e) {

      if ($e->getResponse()->getStatusCode() == '400') {
            echo "<script>console.log('Got response 400 authenticating with springshare');</script>";
			      echo "<script>console.log(" . $e->getResponse()->getBody() . ");</script>";
      }
  } catch (\Exception $e) {
       echo "<script>console.log(unable to authenticate w/springshare" . $e->getResponse()->getBody() . ");</script>";
  }




   try {
      //GET THE LIST OF DATABASES FROM SPRINGSPRING
      //THIS LIST WILL GO INTO A JS VARIABLE
      //THAT JAVASCRIPT WILL USE TO POPULATE THE
      //AUTO SUGGEST
      $springQueryClient = new Client(); 

      $springQueryResponse = $springQueryClient->get("https://lgapi-us.libapps.com/1.2/az", [
          'connect_timeout' => 10,
  		    'headers' => ['Authorization' => 'Bearer ' . $springshareToken,'Accept' => 'application/json']
      ]);
  	  if ($springQueryResponse->getBody()) {
  		   $listOfDbs = json_decode($springQueryResponse->getBody());
	     }
	     else {
	        echo "<script>console.log('unable to get db list from springshare');</script>";
	     }

  }
  catch (RequestException $e) {
      if ($e->getResponse()->getStatusCode() == '400') {
            echo "<script>console.log('Got response 400 authenticating with springshare');</script>";
			      echo "<script>console.log(" . $e->getResponse()->getBody() . ");</script>";
      }
  } catch (\Exception $e) {
       echo "<script>console.log(unable to authenticate w/springshare" . $e->getResponse()->getBody() . ");</script>";
  }
  
?>



<form id="tokenform">
  <input type="hidden" id="refreshToken" value="<?php echo $token; ?>">
</form>

<script>

  <?php

    //LOOP THROUGH THE SPRINGSHARE ENTIRES AND CREATE A VARIABLE 
    //THAT AUTOCOMPLETE WILL USE TO POPULATE THE DB LIST - THIS IS THE 
    //ENTIRE LIST - LOADED IN ALL AT THE SAME TIME SINCE THE DATASET IS
    //SMALL AND THEY DO NOT HAVE AN 'AUTO-COMPLETE' API CALL
    echo "var listOfDatabases = [";
    foreach($listOfDbs as $db){
      echo "{ value: '" . str_replace("'","",$db->name ). "', data: '" . str_replace("'","",$db->name) . "' },";

    }
    echo "];";
    
  ?>
 
 
<?php //START JAVASCRIPT  ?>


<?php $fullurl = "http://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']; ?>

$(document).ready(function(){
  //AUTO-SUGGEST FOR DATABASE NAMES
  $('#s-lg-guide-search').autocomplete({
    lookup: listOfDatabases,
    onSelect: function (suggestion) {
        //alert('You selected: ' + suggestion.value + ', ' + suggestion.data);
    }
  });


  //AUTO-SUGGEST FOR EJOURNALS
  $('#ejournalsearch').autocomplete({
    serviceUrl: "<?php echo "$url?idx=holdings&filters=%5B%7B%22name%22%3A%22custid%22%2C%22values%22%3A%5B%22redacted%22%5D%7D%5D"; ?>" + $('#ejournalsearch').val(),
	paramName: "term",
	params: {'token': function() { return $("#refreshToken").val()}},
	preventBadQueries: false,
	zIndex: 9999,
	dataType:"json",
	transformResult: function(response) {
	//IF TOKEN EXPIRED, REQUEST A NEW ONE
	if (response.error && response.error=="Unauthorized") {
		  console.log("in transform - hit error");
		  $.ajax({
			dataType: 'string',
			cache: false,
			method: "GET",
			url: "https://library.lehigh.edu?redacted",
			//RETURNS AS AN 'ERROR' BECAUSE IT'S SIMPLY RETURNING A STRING
			error: function(response) {
			   console.log("old:" + $('#refreshToken').val() );
			   //PUT FRESH TOKEN IN INPUT FIELD SO NEXT REQUEST
			   //WILL GRAB IT
			   $("#refreshToken").val(response.responseText);
			   console.log("new: " + $('#refreshToken').val() );
			}
			});
		}
    return {
            suggestions: $.map(response.terms, function(dataItem) {
                return { value: dataItem.term, data: dataItem.term };
            })
        }
    },
    onSelect: function (suggestion) {
        //alert('You selected: ' + suggestion.value + ', ' + suggestion.data);
    }
  });
}); 
</script>
