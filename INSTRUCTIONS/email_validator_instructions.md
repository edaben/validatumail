


## PHP CURL CODE:

<?php

$curl = curl_init();

curl_setopt_array($curl, [
	CURLOPT_URL => "https://validect-email-verification-v1.p.rapidapi.com/v1/verify?email=mms75de139%40gmail.com",
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_ENCODING => "",
	CURLOPT_MAXREDIRS => 10,
	CURLOPT_TIMEOUT => 30,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST => "GET",
	CURLOPT_HTTPHEADER => [
		"x-rapidapi-host: validect-email-verification-v1.p.rapidapi.com",
		"x-rapidapi-key: XXXXXXXXXXXXXXX" // located in .api_key
	],
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
	echo "cURL Error #:" . $err;
} else {
	echo $response;
}


---


## RESPONSE EXPECTED:

{
  "disposable": false,
  "domain": "gmail.com",
  "email": "example@gmail.com",
  "public": true,
  "reason": "rejected_email",
  "role": false,
  "status": "invalid", // Esto es lo que más me importa: que el estatus sea válido. Si es inválido, no lo aceptamos. 
  "user": "example"
}