<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * KeyHelp API
 * Version: 2.4
 * @see https://app.swaggerhub.com/apis-docs/keyhelp/api/2.4
 */
class Server_Manager_KeyHelp extends Server_Manager {
    public function init()
    {
        if (!extension_loaded('curl')) {
            throw new Server_Exception('cURL extension is not enabled');
        }

        if(empty($this->_config['ip'])) {
            throw new Server_Exception('Server manager "KeyHelp" is not configured properly. IP address is not set!');
        }

        if(empty($this->_config['host'])) {
            throw new Server_Exception('Server manager "KeyHelp" is not configured properly. Hostname is not set!');
        }

        if(empty($this->_config['accesshash'])) {
            throw new Server_Exception('Server manager "KeyHelp" is not configured properly. API Key / Access Hash is not set!');
        } else {
            $this->_config['accesshash'] = preg_replace("'(\r|\n)'","",$this->_config['accesshash']);
        }        
    }

    public static function getForm()
    {
        return [
            'label' => 'KeyHelp',
            'form' => [
                'credentials' => [
                    'fields' => [
                             [
                            'name' => 'accesshash',
                            'type' => 'text',
                            'label' => 'API key',
                            'placeholder' => 'API key you generated from within KeyHelp.',
                            'required' => true,
                        ],
                    ],
                ],
            ]
        ];
    }

    /**
     * curl GET
     */
    public function getCURL($action, $actionEx)
    {
        $host = $this->_config['host'];
        $apiUrl = "https://".$host."/api/v2/";
        $action = $action . $actionEx;
        $actionapiUrl = $apiUrl . $action;
        $apiKey = $this->_config['accesshash'];

        $curl_session = curl_init();
        curl_setopt($curl_session ,CURLOPT_URL,$actionapiUrl);
        curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_session, CURLOPT_HTTPHEADER, array(
            'X-API-Key: '.$apiKey,
            'accept: application/json',
            'Content-Type: application/json',           
        ));
        $result = json_decode(curl_exec($curl_session));
        curl_close($curl_session );	

        return $result;
    }

    /**
     * Later: Custom login url for User ;) Waiting for FOSSBilling
     */
    public function getLoginUrl(?Server_Account $account = null)
    {
        $host = $this->_config['host'];
        return 'https://'.$host.'';
    }
    public function getResellerLoginUrl(?Server_Account $account = null)
    {
        $host = $this->_config['host'];
        return 'https://'.$host.'';
    }

    /**
     * Here we check if the API is working ;)
     */
    public function testConnection()
    {
        $action = "ping";
        $actionEx = "";
        $result = Server_Manager_KeyHelp::getCURL($action, $actionEx);

        if(isset($result)) {
            return true;
            var_dump($a);
        } else {
            throw new Server_Exception('Failed to connect to server');
        }
    }
    
    /**
     * Not working, because of FOSSBilling
     */
    public function synchronizeAccount(Server_Account $a)
    {
        $action = "clients/name/";
        $actionEx = $a->getUsername();
        $result = Server_Manager_KeyHelp::getCURL($action, $actionEx);

        $new = clone $a;
        if(empty($result->is_suspended)) {
            $new->setSuspended(false);
        } else {
            $new->setSuspended(true);
        }
        
        return $new;
    }
    
    /**
     * Package name must match on both KeyHelp and FOSSBilling!
     */
    public function createAccount(Server_Account $a)
    {
        $apiUrl = "https://".$this->_config['host']."/api/v2/";
        $apiKey = $this->_config['accesshash'];

        $this->getLog()->info('Creating account '.$a->getUsername());
        $client = $a->getClient();
        $package = $a->getPackage()->getName();

        $action = "hosting-plans/name/";
        $actionEx = preg_replace("/\s+/", "%20", $package);
        $result = Server_Manager_KeyHelp::getCURL($action, $actionEx);
        $packageID = $result->id;

        $data = array(
            "username"=> $a->getUsername(),
            "language"=> "de",
            "email"=> $client->getEmail(),
            "password"=> $a->getPassword(),
            "id_hosting_plan"=> $packageID,
            "is_suspended"=> false,
            "suspend_on"=> null,
            "delete_on"=> null,
            "send_login_credentials"=> true,
            "create_system_domain"=> false,
        );
        $action = "clients/";
        $actionapiUrl = $apiUrl . $action;
        $curl_session_b = curl_init();
        curl_setopt($curl_session_b ,CURLOPT_URL,$actionapiUrl);
        curl_setopt($curl_session_b, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_session_b, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl_session_b, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl_session_b, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl_session_b, CURLOPT_HTTPHEADER, array(
            'X-API-Key: '.$apiKey,
            'accept: application/json',
            'Content-Type: application/json',           
        ));
        curl_setopt($curl_session_b, CURLOPT_POST, 1);
        curl_setopt($curl_session_b, CURLOPT_POSTFIELDS, json_encode($data));
        $result_a = json_decode(curl_exec($curl_session_b));
        curl_close($curl_session_b );
        $keyhelp_user_id = $result_a->id;

        $data = array(
            "id_user"=> $keyhelp_user_id,
            "domain"=> $a->getDomain()
        );
        $action = "domains";
        $actionapiUrl = $apiUrl . $action;
        $curl_session = curl_init();
        curl_setopt($curl_session ,CURLOPT_URL,$actionapiUrl);
        curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_session, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl_session, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl_session, CURLOPT_HTTPHEADER, array(
            'X-API-Key: '.$apiKey,
            'accept: application/json',
            'Content-Type: application/json',           
        ));
        curl_setopt($curl_session, CURLOPT_POST, 1);
        curl_setopt($curl_session, CURLOPT_POSTFIELDS, json_encode($data));
        $result_c = json_decode(curl_exec($curl_session));
        curl_close($curl_session );	

        if(isset($result_a)&&isset($result_c)) {
            return true;
        } else {
            throw new Server_Exception('Failed to create the hosting');
        }
    }

    public function suspendAccount(Server_Account $a)
    {
        $apiUrl = "https://".$this->_config['host']."/api/v2/";
        $apiKey = $this->_config['accesshash'];

        $action = "clients/name/";
        $actionEx = $a->getUsername();
        $result = Server_Manager_KeyHelp::getCURL($action, $actionEx);
        $userID = $result->id;

        $data = array(
            "is_suspended"=> true,
        );
        $action = "clients/".$userID;
        $actionapiUrl = $apiUrl . $action;
        $curl_session = curl_init();
        curl_setopt($curl_session ,CURLOPT_URL,$actionapiUrl);
        curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_session, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($curl_session, CURLOPT_HTTPHEADER, array(
            'X-API-Key: '.$apiKey,
            'accept: application/json',
            'Content-Type: application/json',           
        ));
        curl_setopt($curl_session, CURLOPT_POSTFIELDS, json_encode($data));
        $result_c = json_decode(curl_exec($curl_session));
        curl_close($curl_session );	

        if(isset($result_c)) {
            return true;
        } else {
            throw new Server_Exception('Failed to suspend account!');
        }
    }

    public function unsuspendAccount(Server_Account $a)
    {
        $apiUrl = "https://".$this->_config['host']."/api/v2/";
        $apiKey = $this->_config['accesshash'];

        $action = "clients/name/";
        $actionEx = $a->getUsername();
        $result = Server_Manager_KeyHelp::getCURL($action, $actionEx);
        $userID = $result->id;

        $data = array(
            "is_suspended"=> false,
        );
        $action = "clients/".$userID;
        $actionapiUrl = $apiUrl . $action;
        $curl_session = curl_init();
        curl_setopt($curl_session ,CURLOPT_URL,$actionapiUrl);
        curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_session, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($curl_session, CURLOPT_HTTPHEADER, array(
            'X-API-Key: '.$apiKey,
            'accept: application/json',
            'Content-Type: application/json',           
        ));
        curl_setopt($curl_session, CURLOPT_POSTFIELDS, json_encode($data));
        $result_c = json_decode(curl_exec($curl_session));
        curl_close($curl_session );	

        if(isset($result_c)) {
            return true;
        } else {
            throw new Server_Exception('Failed to suspend account!');
        }
    }

    public function cancelAccount(Server_Account $a)
    {
        $apiUrl = "https://".$this->_config['host']."/api/v2/";
        $apiKey = $this->_config['accesshash'];


        $action = "clients/name/";
        $actionEx = $a->getUsername();
        $result = Server_Manager_KeyHelp::getCURL($action, $actionEx);
        $userID = $result->id;

        $action = "clients/".$userID;
        $actionapiUrl = $apiUrl . $action;
        $curl_session = curl_init();
        curl_setopt($curl_session ,CURLOPT_URL,$actionapiUrl);
        curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_session, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($curl_session, CURLOPT_HTTPHEADER, array(
            'X-API-Key: '.$apiKey,
            'accept: application/json',
            'Content-Type: application/json',           
        ));
        $result_c = json_decode(curl_exec($curl_session));
        curl_close($curl_session );	

        if(empty($result_c)) {
            return true;
        } else {
            throw new Server_Exception('Failed to cancel / delete account!');
        }

    }
    public function changeAccountPackage(Server_Account $a, Server_Package $p)
    {
        $this->getLog()->info('Changing password on account '.$a->getUsername());
        $apiUrl = "https://".$this->_config['host']."/api/v2/";
        $apiKey = $this->_config['accesshash'];

        $action = "clients/name/";
        $actionEx = $a->getUsername();
        $result = Server_Manager_KeyHelp::getCURL($action, $actionEx);
        $userID = $result->id;

        $package = preg_replace("/\s+/", "%20", $p->getName());
        $data = array(
            "id_hosting_plan"=> $package,
        );
        $action = "clients/".$userID;
        $actionapiUrl = $apiUrl . $action;
        $curl_session = curl_init();
        curl_setopt($curl_session ,CURLOPT_URL,$actionapiUrl);
        curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_session, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($curl_session, CURLOPT_HTTPHEADER, array(
            'X-API-Key: '.$apiKey,
            'accept: application/json',
            'Content-Type: application/json',           
        ));
        curl_setopt($curl_session, CURLOPT_POSTFIELDS, json_encode($data));
        $result_c = json_decode(curl_exec($curl_session));
        curl_close($curl_session );	

        if(isset($result_c)) {
            return true;
        } else {
            throw new Server_Exception('Failed to change the account password!');
        }
    }

    public function changeAccountPassword(Server_Account $a, $new)
    {
        $this->getLog()->info('Changing password on account '.$a->getUsername());
        $apiUrl = "https://".$this->_config['host']."/api/v2/";
        $apiKey = $this->_config['accesshash'];

        $action = "clients/name/";
        $actionEx = $a->getUsername();
        $result = Server_Manager_KeyHelp::getCURL($action, $actionEx);
        $userID = $result->id;

        $data = array(
            "password"=> $a->getPassword(),
        );
        $action = "clients/".$userID;
        $actionapiUrl = $apiUrl . $action;
        $curl_session = curl_init();
        curl_setopt($curl_session ,CURLOPT_URL,$actionapiUrl);
        curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_session, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($curl_session, CURLOPT_HTTPHEADER, array(
            'X-API-Key: '.$apiKey,
            'accept: application/json',
            'Content-Type: application/json',           
        ));
        curl_setopt($curl_session, CURLOPT_POSTFIELDS, json_encode($data));
        $result_c = json_decode(curl_exec($curl_session));
        curl_close($curl_session );	

        if(isset($result_c)) {
            return true;
        } else {
            throw new Server_Exception('Failed to change the account password!');
        }
    }

    /*
        Maybe upcoming - maybe not....
    */
    public function changeAccountUsername(Server_Account $a, $new)
    {
        throw new Server_Exception('KeyHelp does not support username changes');
    }
    public function changeAccountDomain(Server_Account $a, $new)
    {
        throw new Server_Exception('KeyHelp does not support changing the primary domain name');
    }
    public function changeAccountIp(Server_Account $a, $new)
    {
        throw new Server_Exception('KeyHelp does not support changing the IP');
    }
}
