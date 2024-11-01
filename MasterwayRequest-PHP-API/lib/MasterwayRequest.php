<?php

class MasterwayRequest {
    
        private $_APIKey;
        private $_APISecret;
        private $_return;
        
        //&testmode=true
        //protected $_api_url = 'http://localhost/www/MasterStrategy/MasterWay/dev/sources/?action=apisoap&wsdl&testmode=true';
	protected $_api_url = 'https://app.masterway.net/?action=apisoap&wsdl';
	
	public function __construct($APIKey, $APISecret)
	{
		$this->_APIKey = $APIKey;
                $this->_APISecret = $APISecret;
	}
	

	/*
	 * Send the request
	 */
	public function request($method, $xml)
	{		
		require_once ('nusoap/nusoap.php');		
	
                $client = new nusoap_client($this->_api_url, NULL, false, false, false, false, 0, 300);
                
                
                $response=array();
                $response=$client->call($method, array('parameter' =>$xml));   
                
//                if($method=='ComercialDocs'){var_dump($client);die;}
                
                if($response['Errors'] || $client->faultcode || !$response)
                {
                    if($client->faultcode){$response['Errors'][0]['Msg']=$client->faultstring;}
                    $this->set_return(false); //errors in the request API  
                }else{
                    $this->set_return(true); //no errors in the request API   
                }
                        
                return $response;
	}
        
        /*
	 * Method to get the tax percentage
	 */
        public function get_tax_percentage($CompanyCode, $DocumentDate, $TaxCode, $TaxRegion){		
      
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><APIData></APIData>');
            $field_S01 = $xml->addChild('Header');

            $field_S01_1 = $field_S01->addChild('APIKey', $this->_APIKey);         
            $field_S01_2 = $field_S01->addChild('APISecret', $this->_APISecret);

            $field_S02 = $xml->addChild('Parameters');
            $field_S02_1 = $field_S02->addChild('CompanyCode', $CompanyCode);
            $field_S02_1 = $field_S02->addChild('Date', $DocumentDate);
            $field_S02_1 = $field_S02->addChild('TaxCode', $TaxCode);
            $field_S02_1 = $field_S02->addChild('TaxRegion', $TaxRegion);
           
//            header('Content-type: text/xml');
//            //header('Content-Disposition: attachment; filename="teste.xml"');
//            print($xml->asXML());die();
            
            $response=$this->request('get_tax_percentage', $xml->asXML());
            $return=$this->get_return();
            
            if($return){
                return trim($response['Data']['GetTaxPercentage'][0]['TaxPercentage']);
            }else{
                return false;
            }
	}
        
        function get_return() {
            return $this->_return;
        }

        function set_return($_return) {
            $this->_return = $_return;
        }
}

