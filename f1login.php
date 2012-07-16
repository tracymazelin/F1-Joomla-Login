<?php
/**
 * @version		f1login.php 2011-11
 * @copyright	Copyright (C) 2011 - 2012 Calvary Evangelical Free Church.
 * @license		GNU General Public License
 */

// No direct access
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

/**
 * Example Authentication Plugin
 *
 * @package		Joomla.Plugin
 * @subpackage	Authentication.f1login
 * @since 1.5
 */
class plgAuthenticationF1Login extends JPlugin
{
	/**
	 * This method should handle any authentication and report back to the subject
	 *
	 * @access	public
	 * @param	array	$credentials	Array holding the user credentials
	 * @param	array	$options		Array of extra options
	 * @param	object	$response		Authentication response object
	 * @return	boolean
	 * @since	1.5
	 */
	function onUserAuthenticate($credentials, $options, &$response)
	{
		/*
		 * Here you would do whatever you need for an authentication routine with the credentials
		 *
		 * In this example the mixed variable $return would be set to false
		 * if the authentication routine fails or an integer userid of the authenticated
		 * user if the routine passes
		 */
		 $message = '';
		$success = 0;

			if(strlen($credentials['username']) && strlen($credentials['password']))
			{
				$userId = $credentials['username'];			//UserId from Login form
				$userPassword = $credentials['password'];		//Password from Login form

				require_once 'OAuth/AppConfig.php';
				require_once 'OAuth/OAuthClient.php';

			/*********************2nd party authentication**************************/
			$oauth_token = "";
			$token_secret = "";

			$consumer_key = AppConfig::$consumer_key;
			$consumer_secret = AppConfig::$consumer_secret;

			//echo '<p>consumer_key = '.$consumer_key.'<br>consumer_secret = '.$consumer_secret.'</p>';
			// set the content type
			$getContentType = array("Accept: application/xml", "Content-type: application/xml");

			//create new OAuthClient
			$apiConsumer = new OAuthClient(AppConfig::$base_url, $consumer_key, $consumer_secret);

			// request string to get tokens using second party method
			$tokenRequestURL = sprintf( "%s%s", $apiConsumer->getBaseUrl(),"/v1/WeblinkUser/AccessToken");

			//echo '$tokenRequestURL = '.$tokenRequestURL;
			// set the username and password in $userCred
			$userCred = Util::urlencode_rfc3986(base64_encode( sprintf( "%s %s", $userId, $userPassword)));

			//echo '$userCred = '.$userCred;
			// set the content type
			$getContentType = array("Accept: application/xml", "Content-type: application/xml");

			// get the tokens
			$requestBody = $apiConsumer->postRequest($tokenRequestURL, $userCred , $getContentType, 200);

			//echo '<p>requestBody = '.$requestBody.'</p>';
			// get token values from $requestBody
			preg_match( "~oauth_token\=([^\&]+)\&oauth_token_secret\=([^\&]+)~i", $requestBody, $tokens );

			if( !isset( $tokens[1] ) || !isset( $tokens[2] ) ) {
				$message = 'F1 Tokens are not set';
			//	print 'Tokens are not set'; // Token are not set
			}
			else {
			$access_token = $tokens[1] ;
			$token_secret = $tokens[2] ;
			
			//print 'Access Tokens: '.$access_token.', token secret: '.$token_secret;
			//initialize the tokens before the doRequest using the token from the previous request
			$apiConsumer->initAccessToken($access_token, $token_secret);
			$responseHeaders = $apiConsumer->getResponseHeader();
			foreach ($responseHeaders as $val) {
				$start = 'Content-Location:';
				$contentLocation =  substr( $val, 0, 17 );
				if ($contentLocation == $start) {
					$personLocation = str_replace($start, "", $val);
				}
			}
			$F1Response = $apiConsumer->doRequest($personLocation);
			$xml = simplexml_load_string($F1Response);

			$peopleid = $xml->attributes()->id;
            $householdid = $xml->attributes()->householdID;
			$icode = $xml->attributes()->iCode;
			$lastname = $xml->lastName;
			$firstname = $xml->firstName;
			$session =& JFactory::getSession();
			$session->set('peopleid', "$peopleid");	
			$session->set('householdid', "$householdid");	
			$session->set('icode', "$icode");
			$session->set('lastname', "$lastname");	
			$session->set('firstname', "$firstname");	
			$session->set('access_token', "$access_token");	
			$session->set('token_secret', "$token_secret");	
			$message = JText::_('JGLOBAL_AUTH_ACCESS_GRANTED');
			$success = 1;		
			}
			}
			else  {
				$message = 'Username or password blank';
			}		
		$response->type = 'f1login';
		if ($success)
		{
			$response->status			= JAUTHENTICATE_STATUS_SUCCESS;
			$response->error_message	= '';
			// You may also define other variables:
			
		/*	$yourUser					= YourClass::getUser( $credentials );
			$response->email			= $yourUser->email;
			$response->fullname			= $yourUser->name;
		*/
			$response->email 	= $credentials['username'];
			$response->fullname = $firstname . " " . $lastname;
			return true;
		}
		else
		{
			$response->status			= JAUTHENTICATE_STATUS_FAILURE;
			$response->error_message	= 'Could not authenticate';
			return false;
		}
	}
}