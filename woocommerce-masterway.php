<?php
/*
Plugin Name: WooCommerce + Masterway
Plugin URI: http://woothemes.com/woocommerce
Description:  Allows you to invoice your clients using Masterway
Version: 1.2.2
Author: Masterway
Author URI: http://masterway.net
License: GPLv2
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class woocommerce_masterway
 */

/**
 * Required functions
 **/
if ( ! function_exists( 'wc_ie_is_woocommerce_active' ) ) require_once( 'woo-includes/woo-functions.php' );

if (wc_ie_is_woocommerce_active()) {
	
	add_action('plugins_loaded', 'woocommerce_masterway_init', 0);
	function woocommerce_masterway_init() {
            $woocommerce_masterway = new woocommerce_masterway;
	}

	add_action('init', 'localization_initmw', 0);
	function localization_initmw() {
            $path = dirname(plugin_basename( __FILE__ )) . '/languages/';
            $loaded = load_plugin_textdomain( 'wc_masterway', false, $path);
            if ( isset( $_GET['page'] ) && $_GET['page'] == basename(__FILE__) && !$loaded) {
                    return;
            } 
	}


	class woocommerce_masterway {
                        
                public $APIKey;
                public $APISecret;
                public $Serie;
                public $CompanyCode;
                
		function __construct() {
                    require_once('MasterwayRequest-PHP-API/lib/MasterwayRequest.php');


                    $this->APIKey = get_option('wc_mw_api_key');
                    $this->APISecret = get_option('wc_mw_api_secret');
                    $this->Serie = get_option('wc_mw_serie');
                    $this->CompanyCode 	= get_option('wc_mw_company');

                    add_action('admin_init',array(&$this,'settings_init'));
                    add_action('admin_init',array(&$this,'stocks_init'));
                    add_action('admin_init',array(&$this,'payments_init'));
                    add_action('admin_menu',array(&$this,'menu'));

                    add_action('woocommerce_order_actions', array(&$this,'my_woocommerce_order_actions'), 10, 1);
                    add_action('woocommerce_order_action_my_action', array(&$this,'do_my_action'), 10, 1);

                    //dev comunicacao auto
                    add_action( 'woocommerce_order_status_completed', array(&$this,'mysite_woocommerce_order_status_completed') );
                    
                    
                    /* add NIF field enabled */
                    if ( get_option('wc_ie_add_nif_field') == 1 ) {
                            add_filter('woocommerce_checkout_fields' , array(&$this,'wc_ie_nif_checkout'));
                            add_filter('woocommerce_address_to_edit', array(&$this,'wc_ie_nif_my_account'));
                            add_action('woocommerce_customer_save_address', array(&$this,'wc_ie_my_account_save'), 10, 2);
                            add_action('woocommerce_admin_order_data_after_billing_address', array(&$this,'wc_ie_nif_admin'), 10, 1);
                            add_action('woocommerce_checkout_process', array(&$this, 'wc_ie_nif_validation'));
                    }
		}
                
                
                function mysite_woocommerce_order_status_completed( $order_id ) {
                    if(get_option('wc_mw_auto_doc')==1) {
                        $this->process($order_id);
                    }
                }    

		function my_woocommerce_order_actions($actions) {
			$actions['my_action'] = "Create Invoice (Masterway)";
			return $actions;
		}
		

		function do_my_action($order) { 
                    
                    
			// Do something here with the WooCommerce $order object
			$this->process($order->id);
		
		}

		function menu() {
			add_submenu_page('woocommerce', __('Masterway', 'wc_masterway'),  __('Masterway', 'wc_masterway') , 'manage_woocommerce', 'woocommerce_masterway', array(&$this,'options_page'));
		}

                function stocks_init() {
			global $woocommerce;

			wp_enqueue_style('woocommerce_admin_styles', $woocommerce->plugin_url().'/assets/css/admin.css');

			$stocks_settings = array(
				array(
					'name'		=> 'wc_mw_stocks',
					'title' 	=> __('Stocks Settings','wc_masterway'),
					'page'		=> 'woocommerce_masterway_stocks',
					'settings'	=> array(
							     
							array(
                                                                'name'		=> 'wc_mw_warehouse',
                                                                'title'		=> __('Warehouse Code','wc_masterway'),
							),
                                                        array(
                                                                'name'		=> 'wc_mw_location',
                                                                'title'		=> __('Location Code','wc_masterway'),
							)
						),
					),
				);

			foreach($stocks_settings as $sections=>$section) {
				add_settings_section($section['name'],$section['title'],array(&$this,$section['name']),$section['page']);
				foreach($section['settings'] as $setting=>$option) {
					add_settings_field($option['name'],$option['title'],array(&$this,$option['name']),$section['page'],$section['name']);
					register_setting($section['page'],$option['name']);
                                        $option_get_content = get_option($option['name']);
                                        if($option_get_content)
                                        {
                                            $this->$option['name'] = $option_get_content;
                                        }
				}
			}

		}
                
                function payments_init() {
			global $woocommerce;

			wp_enqueue_style('woocommerce_admin_styles', $woocommerce->plugin_url().'/assets/css/admin.css');

			$payments_settings = array(
				array(
					'name'		=> 'wc_mw_payments',
					'title' 	=> __('Payments Settings','wc_masterway'),
					'page'		=> 'woocommerce_masterway_payments',
					'settings'	=> array(
							     
							array(
                                                                'name'		=> 'wc_mw_payment_multibanco',
                                                                'title'		=> __('Multibanco','wc_masterway'),
							),
                                                        array(
                                                                'name'		=> 'wc_mw_payment_paypal',
                                                                'title'		=> __('Paypal','wc_masterway'),
							),
                                                        array(
                                                                'name'		=> 'wc_mw_payment_stripe',
                                                                'title'		=> __('Stripe','wc_masterway'),
							),
                                                        array(
                                                                'name'		=> 'wc_mw_payment_mbway',
                                                                'title'		=> __('MBWAY','wc_masterway'),
							),
						),
					),
				);

			foreach($payments_settings as $sections=>$section) {
				add_settings_section($section['name'],$section['title'],array(&$this,$section['name']),$section['page']);
				foreach($section['settings'] as $setting=>$option) {
					add_settings_field($option['name'],$option['title'],array(&$this,$option['name']),$section['page'],$section['name']);
					register_setting($section['page'],$option['name']);
					$option_get_content = get_option($option['name']);
                                        if($option_get_content)
                                        {
                                            $this->$option['name'] = $option_get_content;
                                        }
				}
			}

		}
                
                function wc_mw_payment_multibanco() {
                    echo '<input type="text" name="wc_mw_payment_multibanco" id="wc_mw_payment_multibanco" value="'.get_option('wc_mw_payment_multibanco').'" />';
                    echo ' <label for="wc_mw_payment_multibanco">'.__( 'Leave it blank if you want to use the default bank.', 'wc_masterway' ).'</label>';
		}
                
                function wc_mw_payment_paypal() {
                    echo '<input type="text" name="wc_mw_payment_paypal" id="wc_mw_payment_paypal" value="'.get_option('wc_mw_payment_paypal').'" />';
                    echo ' <label for="wc_mw_payment_paypal">'.__( 'Leave it blank if you want to use the default bank.', 'wc_masterway' ).'</label>';
		}
                
                function wc_mw_payment_stripe() {
                    echo '<input type="text" name="wc_mw_payment_stripe" id="wc_mw_payment_stripe" value="'.get_option('wc_mw_payment_stripe').'" />';
                    echo ' <label for="wc_mw_payment_stripe">'.__( 'Leave it blank if you want to use the default bank.', 'wc_masterway' ).'</label>';
		}
                
                function wc_mw_payment_mbway() {
                    echo '<input type="text" name="wc_mw_payment_mbway" id="wc_mw_payment_mbway" value="'.get_option('wc_mw_payment_mbway').'" />';
                    echo ' <label for="wc_mw_payment_mbway">'.__( 'Leave it blank if you want to use the default bank.', 'wc_masterway' ).'</label>';
		}
                
                
                function wc_mw_warehouse() {
                    echo '<input type="text" name="wc_mw_warehouse" id="wc_mw_warehouse" value="'.get_option('wc_mw_warehouse').'" />';
                    echo ' <label for="wc_mw_warehouse">'.__( '', 'wc_masterway' ).'</label>';
		}
                
                function wc_mw_location() {
                    echo '<input type="text" name="wc_mw_location" id="wc_mw_location" value="'.get_option('wc_mw_location').'" />';
                    echo ' <label for="wc_mw_location">'.__( '', 'wc_masterway' ).'</label>';
		}
                
		function settings_init() {
			global $woocommerce;

			wp_enqueue_style('woocommerce_admin_styles', $woocommerce->plugin_url().'/assets/css/admin.css');

			$general_settings = array(
				array(
					'name'		=> 'wc_ie_settings',
					'title' 	=> __('General Settings','wc_masterway'),
					'page'		=> 'woocommerce_masterway_general',
					'settings'	=> array(
							     
							array(
									'name'		=> 'wc_mw_api_key',
									'title'		=> __('API KEY','wc_masterway'),
							),
                                                        array(
									'name'		=> 'wc_mw_api_secret',
									'title'		=> __('API SECRET','wc_masterway'),
							),
                                                        array(
									'name'		=> 'wc_mw_company',
									'title'		=> __('Company Code','wc_masterway'),
							),
                                                        array(
									'name'		=> 'wc_mw_serie',
									'title'		=> __('Serie Code','wc_masterway'),
							),
                                                        array(
									'name'		=> 'wc_mw_tax_region',
									'title'		=> __('Tax Region','wc_masterway'),
							),
							array(
									'name'		=> 'wc_ie_invoice_draft',
									'title'		=> __('Invoice as Draft','wc_masterway'),
							),  
                                                        array(
									'name'		=> 'wc_ie_create_simplified_invoice',
									'title'		=> __('Create Simplified Invoice','wc_masterway'),
							),
                                                        array(
									'name'		=> 'wc_ie_add_nif_field',
									'title'		=> __('Add NIF field','wc_masterway'),
							)
//                                                        ,
//                                                        array(
//									'name'		=> 'wc_mw_auto_doc',
//									'title'		=> __('Automatic comunication','wc_masterway'),
//							),
//							array(
//									'name'		=> 'wc_ie_send_invoice',
//									'title'		=> __('Email Notification','wc_masterway'),
//							)
						),
					),
				);

			foreach($general_settings as $sections=>$section) {
				add_settings_section($section['name'],$section['title'],array(&$this,$section['name']),$section['page']);
				foreach($section['settings'] as $setting=>$option) {
					add_settings_field($option['name'],$option['title'],array(&$this,$option['name']),$section['page'],$section['name']);
					register_setting($section['page'],$option['name']);
                                        $option_get_content = get_option($option['name']);
                                        if($option_get_content)
                                        {
                                            $this->$option['name'] = $option_get_content;
                                        }
				}
			}
		}

		function wc_ie_tabs( $current = 'general' ){

			$tabs = array(  
				'general'   => __( 'General', 'wc_masterway' ),
                                //'stocks'   => __( 'Stocks', 'wc_masterway' ),
                                'payments'   => __( 'Payments', 'wc_masterway' ),
                                'upgrade'   => __( 'Upgrade', 'wc_ie_upgrade_tab' )
			);

			echo '<div id="icon-themes" class="icon32"><br></div>';
			echo '<h2 class="nav-tab-wrapper">';

                            foreach ( $tabs as $tab => $name ) {
                                $class = ( $tab == $current ) ? ' nav-tab-active' : '';
                                echo '<a class="nav-tab'.$class.'" href="?page=woocommerce_masterway&tab='.$tab.'">'.$name.'</a>';
                            }

			echo '</h2>';
		}


		function options_page() { 
			global $pagenow;

			if( $pagenow == 'admin.php' && $_GET["page"] == 'woocommerce_masterway' ){
			?>
				<div class="wrap woocommerce">
                                    <div class="icon32 icon32-woocommerce-settings" id="icon-woocommerce"><br /></div>
                                    <h2><a target="_blank" href="http://masterway.net/"><img src="http://www.masterway.net/_style/images/mw_logo_ecommerce.png" alt="Smiley face"  width="112"></a><?php _e('Plugin WooCommerce Masterway','wc_masterway'); ?></h2>
				<form method="post" id="mainform" action="options.php">
			<?php
				if ( isset ( $_GET['tab'] ) ) $this->wc_ie_tabs($_GET['tab']); else $this->wc_ie_tabs('general');

				$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';

				switch ( $tab ) {
					case 'general':
					?>	
						<?php settings_fields('woocommerce_masterway_general'); ?>
						<?php do_settings_sections('woocommerce_masterway_general'); ?>
						<p class="submit"><input type="submit" class="button-primary" value="<?php _e( 'Save Changes', 'wc_masterway' ) ?>" /></p>
						</form>
						</div>
					<?php
						break;
					case 'stocks':
					?>
						<?php settings_fields('woocommerce_masterway_stocks'); ?>
						<?php do_settings_sections('woocommerce_masterway_stocks'); ?>
						<p class="submit"><input type="submit" class="button-primary" value="<?php _e( 'Save Changes', 'wc_masterway' ) ?>" /></p>
						</form>
						</div>
					<?php
						break;
                                        case 'payments':
					?>
						<?php settings_fields('woocommerce_masterway_payments'); ?>
						<?php do_settings_sections('woocommerce_masterway_payments'); ?>
						<p class="submit"><input type="submit" class="button-primary" value="<?php _e( 'Save Changes', 'wc_masterway' ) ?>" /></p>
						</form>
						</div>
					<?php
						break;
				}

				if ( $tab == 'upgrade' ){ $this->wc_ie_upgrade_tab(); }

			}

		}
                
                function wc_mw_payments() {
                    echo '<p>'.__('if you want to configure the masterway bank for each payment method.','wc_masterway').'</p>';
		}                
                
                function wc_mw_stocks() {
                    echo '<p>'.__('If you want to affect stock in Masterway, please fill in the necessary settings below.','wc_masterway').'</p>';
		}
                
		//wc_masterway
		function wc_ie_settings() {
			echo '<p>'.__('Please fill in the necessary settings below. Then create an invoice and go into an order and choose "Create Invoice (Masterway)".','wc_masterway').'</p>';
		}
		function wc_mw_serie() {
			echo '<input type="text" name="wc_mw_serie" id="wc_mw_serie" value="'.get_option('wc_mw_serie').'" />';
			echo ' <label for="wc_mw_serie">'.__( 'Enter the Serie code you want to create the invoices.', 'wc_masterway' ).'</label>';
		}
                function wc_mw_company() {
			echo '<input type="text" name="wc_mw_company" id="wc_mw_company" value="'.get_option('wc_mw_company').'" />';
			echo ' <label for="wc_mw_company">'.__( 'Enter the Company code you want to create the invoices.', 'wc_masterway' ).'</label>';
		}
                function wc_mw_api_secret() {
			echo '<input type="password" name="wc_mw_api_secret" id="wc_mw_api_secret" value="'.get_option('wc_mw_api_secret').'" />';
			echo ' <label for="wc_mw_api_secret">'.__( 'Enter the API Secret used to generate the API Key.', 'wc_masterway' ).'</label>';
		}
		function wc_mw_api_key() {
			echo '<input type="password" name="wc_mw_api_key" id="wc_mw_api_key" value="'.get_option('wc_mw_api_key').'" />';
			echo ' <label for="wc_mw_api_key">'.__( 'To generate one API KEY, log with your account in Masterway, access your user profile and go to the API tab.', 'wc_masterway' ).'</label>';
		}
		function wc_ie_send_invoice() {
			$checked = (get_option('wc_ie_send_invoice')==1) ? 'checked="checked"' : '';
			echo '<input type="hidden" name="wc_ie_send_invoice" value="0" />';
			echo '<input type="checkbox" name="wc_ie_send_invoice" id="wc_ie_send_invoice" value="1" '.$checked.' />';
			echo ' <label for="wc_ie_send_invoice">'.__( 'Send the client an e-mail with the order invoice attached (<i>recommended</i>).', 'wc_masterway' ).'</label>';
		}
		function wc_ie_add_nif_field() {
			$checked = (get_option('wc_ie_add_nif_field')==1) ? 'checked="checked"' : '';
			echo '<input type="hidden" name="wc_ie_add_nif_field" value="0" />';
			echo '<input type="checkbox" name="wc_ie_add_nif_field" id="wc_ie_add_nif_field" value="1" '.$checked.' />';
			echo ' <label for="wc_ie_add_nif_field">'.__( 'Add a client NIF field to the checkout form.', 'wc_masterway' ).'</label>';
		}
                
                function wc_mw_auto_doc() {
			$checked = (get_option('wc_mw_auto_doc')==1) ? 'checked="checked"' : '';
			echo '<input type="hidden" name="wc_mw_auto_doc" value="0" />';
			echo '<input type="checkbox" name="wc_mw_auto_doc" id="wc_mw_auto_doc" value="1" '.$checked.' />';
			echo ' <label for="wc_mw_auto_doc">'.__( 'Automatically generate documents on order status completed.', 'wc_masterway' ).'</label>';
		}
                
		function wc_ie_create_simplified_invoice() {
			$checked = (get_option('wc_ie_create_simplified_invoice')==1) ? 'checked="checked"' : '';
			echo '<input type="hidden" name="wc_ie_create_simplified_invoice" value="0" />';
			echo '<input type="checkbox" name="wc_ie_create_simplified_invoice" id="wc_ie_create_simplified_invoice" value="1" '.$checked.' />';
			echo ' <label for="wc_ie_create_simplified_invoice">'.__( 'Create simplified invoices. Only available for Portuguese accounts.', 'wc_masterway' ).'</label>';
		}

		function wc_ie_invoice_draft(){
			$checked = (get_option('wc_ie_invoice_draft')==1) ? 'checked="checked"' : '';
			echo '<input type="hidden" name="wc_ie_invoice_draft" value="0" />';
			echo '<input type="checkbox" name="wc_ie_invoice_draft" id="wc_ie_invoice_draft" value="1" '.$checked.' />';
			echo ' <label for="wc_ie_invoice_draft">'.__( 'Create invoice as draft. (Document type in Masterway: FPF_V)', 'wc_masterway' ).'</label>';
		}
                
                function wc_mw_tax_region(){
			$wc_mw_tax_region = get_option('wc_mw_tax_region');
			echo '<select name="wc_mw_tax_region" id="wc_mw_tax_region"  />';
                            echo '<option '; if($wc_mw_tax_region=='PT')echo 'selected="selected"'; echo' value="PT">Portugal - Continental</option>';
                            echo '<option '; if($wc_mw_tax_region=='PT-MA')echo 'selected="selected"'; echo' value="PT-MA">Portugal - Região Autónoma da Madeira</option>';
                            echo '<option '; if($wc_mw_tax_region=='PT-AC')echo 'selected="selected"'; echo' value="PT-AC">Portugal - Região Autónoma dos Açores</option>';
                        echo '</select>';
			echo ' <label for="wc_mw_tax_region">'.__( 'Select the tax region you want to generate your documents in.', 'wc_masterway' ).'</label>';
		}

		/**
		* Return the shipping tax status for an order (props @aaires)
		* 
		* @param  WC_Order
		* @return string|bool - status if exists, false otherwise
		*/
		function wc_ie_get_order_shipping_tax_status( $order ) 
		{
                    WC()->shipping->load_shipping_methods();

                    $shipping_tax_status = false;
                    $active_methods      = array();
                    $shipping_methods    = WC()->shipping->get_shipping_methods();

                    foreach ( $shipping_methods as $id => $shipping_method ) {

                            if ( isset( $shipping_method->enabled ) && $shipping_method->enabled == 'yes' ) {
                                    $active_methods[ $shipping_method->title ] = $shipping_method->tax_status ;
                            }
                    }

                    $shipping_method     = $order->get_shipping_method();
                    $shipping_tax_status = $active_methods[ $shipping_method ];

                    return $shipping_tax_status;
		}

		// upgrade tab
		function wc_ie_upgrade_tab(){
		?>
			<div class="wrap woocommerce">
				<div class="icon32 icon32-woocommerce-settings" id="icon-woocommerce"><br /></div>
				<h2><?php _e("Plugin WooCommerce Masterway Premium","wc_invoicexpress") ?></h2>
				<h3><?php _e('Apenas algumas razões para fazer o upgrade:','wc_invoicexpress') ?></h3>
				<ul>
					<li><?php _e('- Faturação automática.','wc_invoicexpress') ?></li>
					<li><?php _e('- Envie a faturação por email.') ?></li>
					<li><?php _e('- Configure o Armazém e a localização para movimentar stock automáticamente no Masterway.','wc_invoicexpress') ?></li>
				</ul>
				<h2><?php _e("Quer saber mais?", "wc_invoicexpress") ?></h2>
				Contacte-nos através do nosso email <a href="mailto:comercial@masterway.net" >comercial@masterway.net</a>
			</div>
		<?php
		}


		function process($order_id) {

                        $MasterwayRequest = new MasterwayRequest($this->APIKey, $this->APISecret);
                                     
			$order = new WC_Order($order_id);
                        $tax = new WC_TAX($order_id);
			if ( ! $order->get_total() ) {
                            $order->add_order_note(__('Warning: Order total is zero, invoice not created!','wc_masterway'));
                            return;
			}

                        $CompanyCode=$this->CompanyCode;
                        $EntityName = $order->billing_first_name." ".$order->billing_last_name;
                        $EntityTypeCode='CN';
                        
                        $TaxID = trim($order->billing_eu_vat_number);
                        if($TaxID)
                        {
                            $order->billing_country = substr($TaxID, 0, 2);
                            $TaxID = substr($TaxID, 2);
                        }
                        else
                        {
                            $TaxID = trim($order->get_meta('vat_number'));
                            if($TaxID)
                            {
                                $order->billing_country = $order->get_meta('_vat_country');
                                $TaxID = substr($TaxID, 2);
                            }
                        }
                        if(!$TaxID)
                        {
                            $TaxID = trim($order->billing_nif);
                        }   
                        
                        if(!$TaxID){
                            $EntityCode='CF';
                            $EntityName='Consumidor Final';
                            $EntityTypeCode='CN';
                            $TaxID='999999990';
                        }
                      
                        
                        $NotificationEmail = $order->billing_email;
                        $Country =  $order->billing_country ;
                        
                        $Phone=$order->billing_phone;
			$Classification='CF';
                        
                        if($Country=='PT'){
                            $VATType=$Country;
                        }else{
                            $VATType='I';
                        }

                        if($order->billing_address_1){
                            $AddressDetail_FAC = trim($order->billing_address_1.' '.$order->billing_address_2);
                        }else{
                            $AddressDetail_FAC = trim($order->billing_address_2);
                        }
                        $City_FAC=$order->billing_city;
                        $PostalCode_FAC=$order->billing_postcode;
                        $Country_FAC=$Country;
                        
                        /////////////////////////////////////////////////////
                        /////////////////////////////////////////////////////
                        // MORADAS DE ENVIO
                        if($order->shipping_address_1){
                            $AddressDetail_ENV = trim($order->shipping_address_1.' '.$order->shipping_address_2);
                        }else{
                            $AddressDetail_ENV = trim($order->shipping_address_2);
                        }
                        $City_ENV=$order->shipping_city;
                        $PostalCode_ENV=$order->shipping_postcode;
                        $Country_ENV = $order->shipping_country;
                        
                        if(!$AddressDetail_ENV)
                        {
                            if($order->billing_address_1){
                                $AddressDetail_ENV = trim($order->billing_address_1.' '.$order->billing_address_2);
                            }else{
                                $AddressDetail_ENV = trim($order->billing_address_2);
                            }
                        }
                        if(!$City_ENV)
                        {
                            $City_ENV=$order->billing_city;
                        }
                        if(!$PostalCode_ENV)
                        {
                            $PostalCode_ENV=$order->billing_postcode;
                        }
                        if(!$Country_ENV)
                        {
                            $Country_ENV=$Country;
                        }                        
                        /////////////////////////////////////////////////////
                        
                        
                        $Currency = $order->currency;
                        if(!$Currency)
                        {
                            $Currency = 'EUR';
                        }
                        $Observations='';
                        $MaturityCode='';
                        
                        if($order->completed_date){
                            $DocumentDate= date('Y-m-d', strtotime($order->completed_date));
                        }else{
                            $DocumentDate=date('Y-m-d');
                        }
                        
                        $EntityCode='';
                        
                        if(get_option('wc_ie_send_invoice')==1) {
                            $SendEmail='1';
                        }
                        
                        $Warehouse=get_option('wc_mw_warehouse');
                        $Location=get_option('wc_mw_location');
                        
                        if(get_option('wc_ie_invoice_draft')==1) {
                            $CodTipoDocumento='FPF_V';
                        }
                        else if(get_option('wc_ie_create_simplified_invoice')==1){
                            $CodTipoDocumento='FS_V';
                        }else{
                            $CodTipoDocumento='FT_V';
                        }
                        
                        $PaymentMethod = '';
                        $CodBanco = '';
                        if($CodTipoDocumento == 'FR_V' || $CodTipoDocumento == 'FS_V')
                        {
                            switch (trim($order->payment_method)) 
                            {
                                case 'eupago_multibanco':
                                    $PaymentMethod = 'MB';
                                    $CodBanco = get_option('wc_mw_payment_multibanco');
                                    break;
                                case 'paypal':
                                    $PaymentMethod = 'CC';
                                    $CodBanco = get_option('wc_mw_payment_paypal');
                                    break;
                                case 'stripe_p24':
                                case 'stripe':	
                                    $PaymentMethod = 'CC';
                                    $CodBanco = get_option('wc_mw_payment_stripe');
                                    break;
                                case 'eupago_mbway':
                                    $PaymentMethod = 'CD';
                                    $CodBanco = get_option('wc_mw_payment_mbway');
                                    break;
                                default: 
                                    $PaymentMethod = 'CD';
                                    break;   
                            }
                        }
                                
                        //***********************************
                        //INICIO XML
                        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><APIData></APIData>');
                        $field_S01 = $xml->addChild('Header');

                        $field_S01_1 = $field_S01->addChild('APIKey', $this->APIKey);         
                        $field_S01_2 = $field_S01->addChild('APISecret', $this->APISecret);
                        
                        $field_S02 = $xml->addChild('Comercial');    
                        $field_S02_1 = $field_S02->addChild($CodTipoDocumento);

                        $field_S02_1_1 = $field_S02_1->addChild('Document');
                            $field_S02_1_1_1 = $field_S02_1_1->addChild('CompanyCode', $CompanyCode);
                            if($this->Serie)
                            {
                                $field_S02_1_1_2 = $field_S02_1_1->addChild('Serie', $this->Serie);
                            }
                            else
                            {
                                $field_S02_1_1_2 = $field_S02_1_1->addChild('Serie');
                            }
                            $field_S02_1_1_3 = $field_S02_1_1->addChild('DocumentDate',$DocumentDate);
                            $field_S02_1_1_4 = $field_S02_1_1->addChild('Entity');
                                $field_S02_1_1_4_1 = $field_S02_1_1_4->addChild('EntityCode', $EntityCode);
                                $field_S02_1_1_4_2 = $field_S02_1_1_4->addChild('EntityName', $EntityName);
                                $field_S02_1_1_4_3 = $field_S02_1_1_4->addChild('EntityTypeCode', $EntityTypeCode);
                                $field_S02_1_1_4_4 = $field_S02_1_1_4->addChild('TaxID', $TaxID);
                                $field_S02_1_1_4_5 = $field_S02_1_1_4->addChild('Country', $Country);
                                $field_S02_1_1_4_6 = $field_S02_1_1_4->addChild('Classification', $Classification);
                                $field_S02_1_1_4_7 = $field_S02_1_1_4->addChild('VATType', $VATType);
                                $field_S02_1_1_4_8 = $field_S02_1_1_4->addChild('NotificationEmail', $NotificationEmail);
                            $field_S02_1_1_5 = $field_S02_1_1->addChild('Address');
                                $field_S02_1_1_5_1 = $field_S02_1_1_5->addChild('AddressDetail', $AddressDetail_FAC);
                                $field_S02_1_1_5_2 = $field_S02_1_1_5->addChild('City', $City_FAC);
                                $field_S02_1_1_5_3 = $field_S02_1_1_5->addChild('PostalCode', $PostalCode_FAC);
                                $field_S02_1_1_5_4 = $field_S02_1_1_5->addChild('Country', $Country_FAC);
                                $field_S02_1_1_5_5 = $field_S02_1_1_5->addChild('ContactName');
                                $field_S02_1_1_5_6 = $field_S02_1_1_5->addChild('Phone', $Phone);
                                $field_S02_1_1_5_7 = $field_S02_1_1_5->addChild('Fax');
                                $field_S02_1_1_5_8 = $field_S02_1_1_5->addChild('Email');
                            $field_S02_1_1_6 = $field_S02_1_1->addChild('Currency', $Currency);
                            $field_S02_1_1_7 = $field_S02_1_1->addChild('MaturityCode', $MaturityCode);
                            $field_S02_1_1_8 = $field_S02_1_1->addChild('Discount');
                            $field_S02_1_1_9 = $field_S02_1_1->addChild('Transport');
                                $field_S02_1_1_9_1 = $field_S02_1_1_9->addChild('StartDate');
                                $field_S02_1_1_9_2 = $field_S02_1_1_9->addChild('EndDate');
                                $field_S02_1_1_9_3 = $field_S02_1_1_9->addChild('Address');
                                    $field_S02_1_1_9_3_1 = $field_S02_1_1_9_3->addChild('AddressDetail', $AddressDetail_ENV);
                                    $field_S02_1_1_9_3_2 = $field_S02_1_1_9_3->addChild('City', $City_ENV);
                                    $field_S02_1_1_9_3_3 = $field_S02_1_1_9_3->addChild('PostalCode', $PostalCode_ENV);
                                    $field_S02_1_1_9_3_4 = $field_S02_1_1_9_3->addChild('Country', $Country_ENV);
                                    $field_S02_1_1_9_3_5 = $field_S02_1_1_9_3->addChild('ContactName');
                                    $field_S02_1_1_9_3_6 = $field_S02_1_1_9_3->addChild('Phone');
                                    $field_S02_1_1_9_3_7 = $field_S02_1_1_9_3->addChild('Fax');
                                    $field_S02_1_1_9_3_8 = $field_S02_1_1_9_3->addChild('Email');
                            $field_S02_1_1_10 = $field_S02_1_1->addChild('Observations', $Observations);
                            
                            if($PaymentMethod)
                            {
                                $field_S02_1_1_99 = $field_S02_1_1->addChild('PaymentMethod', $PaymentMethod);
                                if($CodBanco)
                                {
                                    $field_S02_1_1_100 = $field_S02_1_1->addChild('Bank', $CodBanco);         
                                }
                            }
                            
                            
                            $field_S02_1_1_11 = $field_S02_1_1->addChild('Lines');

                        $TaxRegion=get_option('wc_mw_tax_region');
                        $LineNumber=0;
                        foreach($order->get_items() as $item) {

                            $Discount=0;
                            $LineNumber++;
                            $ProductCode=$item['product_id'];

                            $Product_info = wc_get_product($ProductCode);
                            $TaxStatus=$Product_info->get_tax_status();
                            $TaxClass=$Product_info->get_tax_class(); //TaxClass == '' -> Standart 

                            $Rates_info = $tax->get_rates($TaxClass);
                            
                            
                            $ProductDescription=$item['name'];
                            $Quantity=(float)$item['qty'];
                            $UnitOfMeasure='UN';
                            $ProductType='P';
                            $ProductInventoryType='M';
                            $ExemptionReasonCode='';
                            
                            $line_subtotal=(float)$item['line_subtotal'];
                            $line_total=(float)$item['line_total'];
                            
                            // TRATAMENTO DE DESCONTOS EM CAMPANHAS OU ALTERAÇÃO DE PREÇOS
                            $RegularPrice = get_post_meta( $ProductCode, '_regular_price', true);
                            $TotalAmountProductPrice = (float)$RegularPrice * $Quantity;
                            if($TotalAmountProductPrice != $line_subtotal)
                            {
                                if($TotalAmountProductPrice > $line_subtotal)
                                {
                                    $line_subtotal = $TotalAmountProductPrice;
                                }
                            }  
                            
                            // TRATAMENTO DO DO DESCONTO
                            if($line_subtotal != $line_total)
                            {
                                if($line_total > $line_subtotal)
                                {
                                    $difvalue = $line_total - $line_subtotal;
                                    $line_subtotal = $line_total + $difvalue;
                                }
                                
                                $Discount = ($line_total * 100) / $line_subtotal;
                                $Discount = 100 - $Discount;
                                $Discount = round($Discount, 5);
                            }
                            
                            
                            $UnitPrice=round($line_subtotal,5)/$Quantity; 
                            $UnitPrice = round($UnitPrice, 5);
                            
                            //TaxCode
                            //CALC PRECO SEM IVA PARA ENVIAR
                            if($TaxStatus=='taxable' && $TaxClass!='zero-rate' && $VATType != 'I'){
                                if($TaxClass==''){
                                    $TaxCode='NOR';
                                    $Taxa=$MasterwayRequest->get_tax_percentage($CompanyCode, $DocumentDate, $TaxCode, $TaxRegion);
                                }
                                if($TaxClass=='reduced-rate'){
                                    $TaxCode='RED';
                                    $Taxa=$MasterwayRequest->get_tax_percentage($CompanyCode, $DocumentDate, $TaxCode, $TaxRegion);
                                }
                                if($TaxClass=='intermediate-rate'){
                                    $TaxCode='INT';
                                    $Taxa=$MasterwayRequest->get_tax_percentage($CompanyCode, $DocumentDate, $TaxCode, $TaxRegion);
                                }
                                $Taxa=($Taxa/100)+1;
                                //$UnitPrice=(float)($UnitPrice/$Taxa);
                            }else{
                                $TaxCode='ISE';
                                $ExemptionReasonCode='17';
                            }
                            
                            $ProductCodeXML = trim($Product_info->sku);
                            if(!$ProductCodeXML)
                            {
                                $ProductCodeXML = $ProductCode;
                            }

                            $field_S02_1_1_11_1 = $field_S02_1_1_11->addChild('ProductLine');
                                $field_S02_1_1_11_1_1 = $field_S02_1_1_11_1->addChild('LineNumber', $LineNumber);
                                $field_S02_1_1_11_1_2 = $field_S02_1_1_11_1->addChild('ProductCode', $ProductCodeXML);
                                $field_S02_1_1_11_1_3 = $field_S02_1_1_11_1->addChild('ProductType', $ProductType);
                                $field_S02_1_1_11_1_4 = $field_S02_1_1_11_1->addChild('ProductInventoryType', $ProductInventoryType);
                                $field_S02_1_1_11_1_5 = $field_S02_1_1_11_1->addChild('ProductDescription', $ProductDescription);
                                $field_S02_1_1_11_1_6 = $field_S02_1_1_11_1->addChild('Quantity', $Quantity);
                                $field_S02_1_1_11_1_7 = $field_S02_1_1_11_1->addChild('UnitOfMeasure', $UnitOfMeasure);
                                $field_S02_1_1_11_1_8 = $field_S02_1_1_11_1->addChild('UnitPrice', $UnitPrice);
                                $field_S02_1_1_11_1_9 = $field_S02_1_1_11_1->addChild('Discount', $Discount);
                                $field_S02_1_1_11_1_10 = $field_S02_1_1_11_1->addChild('Tax');
                                    $field_S02_1_1_11_1_10_1 = $field_S02_1_1_11_1_10->addChild('TaxCode', $TaxCode);
                                    $field_S02_1_1_11_1_10_2 = $field_S02_1_1_11_1_10->addChild('TaxRegion', $TaxRegion);
                                    $field_S02_1_1_11_1_10_3 = $field_S02_1_1_11_1_10->addChild('ExemptionReasonCode', $ExemptionReasonCode);      
                                $field_S02_1_1_11_1_11 = $field_S02_1_1_11_1->addChild('Warehouse', $Warehouse);
                                $field_S02_1_1_11_1_12 = $field_S02_1_1_11_1->addChild('Location', $Location);
                                $field_S02_1_1_11_1_13 = $field_S02_1_1_11_1->addChild('Lot');
                        }   
                      
                        
                        /*
                         FEES
                         */
			foreach($order->get_fees() as $item) {

                            $LineNumber++;
                            $ProductCode='WC_FEE';
                            $ProductDescription=$item['name'];
                            $Quantity=1;
                            $UnitOfMeasure='UN';
                            $line_subtotal=$item['amount'];
                            $UnitPrice=$line_subtotal/$Quantity;
                            $ProductType='P';
                            $Discount=0;
                            $ProductInventoryType='M';
                            $ExemptionReasonCode='';
                            
                            
                            if($TaxStatus=='taxable' && $TaxClass!='zero-rate' && $VATType != 'I'){
                                if($TaxClass==''){
                                    $TaxCode='NOR';
                                    $Taxa=$MasterwayRequest->get_tax_percentage($CompanyCode, $DocumentDate, $TaxCode, $TaxRegion);
                                }
                                if($TaxClass=='reduced-rate'){
                                    $TaxCode='RED';
                                    $Taxa=$MasterwayRequest->get_tax_percentage($CompanyCode, $DocumentDate, $TaxCode, $TaxRegion);
                                }
                                if($TaxClass=='intermediate-rate'){
                                    $TaxCode='INT';
                                    $Taxa=$MasterwayRequest->get_tax_percentage($CompanyCode, $DocumentDate, $TaxCode, $TaxRegion);
                                }
                                $Taxa=($Taxa/100)+1;
                                //$UnitPrice=(float)($UnitPrice/$Taxa);
                            }else{
                                $TaxCode='ISE';
                                $ExemptionReasonCode='17';
                            }
               
                            $field_S02_1_1_11_1 = $field_S02_1_1_11->addChild('ProductLine');
                            $field_S02_1_1_11_1_1 = $field_S02_1_1_11_1->addChild('LineNumber', $LineNumber);
                            $field_S02_1_1_11_1_2 = $field_S02_1_1_11_1->addChild('ProductCode', $ProductCode);
                            $field_S02_1_1_11_1_3 = $field_S02_1_1_11_1->addChild('ProductType', $ProductType);
                            $field_S02_1_1_11_1_4 = $field_S02_1_1_11_1->addChild('ProductInventoryType', $ProductInventoryType);
                            $field_S02_1_1_11_1_5 = $field_S02_1_1_11_1->addChild('ProductDescription', $ProductDescription);
                            $field_S02_1_1_11_1_6 = $field_S02_1_1_11_1->addChild('Quantity', $Quantity);
                            $field_S02_1_1_11_1_7 = $field_S02_1_1_11_1->addChild('UnitOfMeasure', $UnitOfMeasure);
                            $field_S02_1_1_11_1_8 = $field_S02_1_1_11_1->addChild('UnitPrice', $UnitPrice);
                            $field_S02_1_1_11_1_9 = $field_S02_1_1_11_1->addChild('Discount', $Discount);
                            $field_S02_1_1_11_1_10 = $field_S02_1_1_11_1->addChild('Tax');
                                $field_S02_1_1_11_1_10_1 = $field_S02_1_1_11_1_10->addChild('TaxCode', $TaxCode);
                                $field_S02_1_1_11_1_10_2 = $field_S02_1_1_11_1_10->addChild('TaxRegion', $TaxRegion);
                                $field_S02_1_1_11_1_10_3 = $field_S02_1_1_11_1_10->addChild('ExemptionReasonCode', $ExemptionReasonCode);        
			}

			/*
			 SHIPPING
			 */
			$ShippingCost =  $order->get_total_shipping();
			if ( $ShippingCost > 0 ) {
                            
                            $LineNumber++;
                            $ProductCode='WC_CE';
                            $ProductDescription='Custos de Envio';
                            $Quantity=1;
                            $UnitOfMeasure='UN';
                            $line_subtotal=$item['line_subtotal'];
                            $UnitPrice=$ShippingCost/$Quantity;
                            $ProductType='P';
                            $Discount=0;
                            $ProductInventoryType='M';
                            $ExemptionReasonCode='';
                            
                            if($TaxStatus=='taxable' && $TaxClass!='zero-rate' && $VATType != 'I'){
                                if($TaxClass==''){
                                    $TaxCode='NOR';
                                    $Taxa=$MasterwayRequest->get_tax_percentage($CompanyCode, $DocumentDate, $TaxCode, $TaxRegion);
                                }
                                if($TaxClass=='reduced-rate'){
                                    $TaxCode='RED';
                                    $Taxa=$MasterwayRequest->get_tax_percentage($CompanyCode, $DocumentDate, $TaxCode, $TaxRegion);
                                }
                                if($TaxClass=='intermediate-rate'){
                                    $TaxCode='INT';
                                    $Taxa=$MasterwayRequest->get_tax_percentage($CompanyCode, $DocumentDate, $TaxCode, $TaxRegion);
                                }
                                $Taxa=($Taxa/100)+1;
                                //$UnitPrice=(float)($UnitPrice/$Taxa);
                            }else{
                                $TaxCode='ISE';
                                $ExemptionReasonCode='17';
                            }
                            
                            
                            $field_S02_1_1_11_1 = $field_S02_1_1_11->addChild('ProductLine');
                            $field_S02_1_1_11_1_1 = $field_S02_1_1_11_1->addChild('LineNumber', $LineNumber);
                            $field_S02_1_1_11_1_2 = $field_S02_1_1_11_1->addChild('ProductCode', $ProductCode);
                            $field_S02_1_1_11_1_3 = $field_S02_1_1_11_1->addChild('ProductType', $ProductType);
                            $field_S02_1_1_11_1_4 = $field_S02_1_1_11_1->addChild('ProductInventoryType', $ProductInventoryType);
                            $field_S02_1_1_11_1_5 = $field_S02_1_1_11_1->addChild('ProductDescription', $ProductDescription);
                            $field_S02_1_1_11_1_6 = $field_S02_1_1_11_1->addChild('Quantity', $Quantity);
                            $field_S02_1_1_11_1_7 = $field_S02_1_1_11_1->addChild('UnitOfMeasure', $UnitOfMeasure);
                            $field_S02_1_1_11_1_8 = $field_S02_1_1_11_1->addChild('UnitPrice', $UnitPrice);
                            $field_S02_1_1_11_1_9 = $field_S02_1_1_11_1->addChild('Discount', $Discount);
                            $field_S02_1_1_11_1_10 = $field_S02_1_1_11_1->addChild('Tax');
                                $field_S02_1_1_11_1_10_1 = $field_S02_1_1_11_1_10->addChild('TaxCode', $TaxCode);
                                $field_S02_1_1_11_1_10_2 = $field_S02_1_1_11_1_10->addChild('TaxRegion', $TaxRegion);
                                $field_S02_1_1_11_1_10_3 = $field_S02_1_1_11_1_10->addChild('ExemptionReasonCode', $ExemptionReasonCode);      
			}
                        $field_S02_1_1_12 = $field_S02_1_1->addChild('SendEmail', $SendEmail);
	
//                        header('Content-type: text/xml');
//                        //    header('Content-Disposition: attachment; filename="teste.xml"');
//                        print($xml->asXML());die(); 
                        
                        

                        
                        $response=$MasterwayRequest->request('ComercialDocs', $xml->asXML());
                        $return=$MasterwayRequest->get_return();

			if($return) {
				$invoice_id = $response['Imports'][0]['Reference'];
                                
                                $CodTipoDocumento=$response['Imports'][0]['DocumentType'];
                                $CodSerie=$response['Imports'][0]['Serie'];
                                $NumeroDocumento=$response['Imports'][0]['DocumentNumber'];
                                
				$order->add_order_note(__('Client invoice in Masterway ['.$CodTipoDocumento.' '.$CodSerie.' / '.$NumeroDocumento.']','wc_masterway').' #'.$invoice_id);
				add_post_meta($order_id, 'wc_mw_inv_keys', $CodTipoDocumento.' '.$CodSerie.' / '.$NumeroDocumento, true);
                                add_post_meta($order_id, 'wc_mw_inv_ref', $invoice_id, true);

			} else {
				$error = utf8_encode(trim($response['Errors'][0]['Msg']));
				if (is_array($error)) {
                                    $order->add_order_note(__('Masterway Invoice API Error:', 'wc_masterway').': '.print_r($error, true));
				} else {
                                    $order->add_order_note(__('Masterway Invoice API Error:', 'wc_masterway').': '.$error);
				}
			}
			
		}

		function wc_ie_is_tax_exempt() {

			$tax_exemption = get_option( 'wc_ie_tax_exemption_reason_options');
			
			if ( $tax_exemption && 'M00' != $tax_exemption ){
				return $tax_exemption;
			}

			return false;
		}

		//Add field to checkout
		function wc_ie_nif_checkout( $fields ) {

			$current_user=wp_get_current_user();
			$fields['billing']['billing_nif'] = array(
				'type'			=>	'text',
				'label'			=> __('VAT', 'wc_masterway'),
				'placeholder'	=> _x('VAT identification number', 'placeholder', 'wc_masterway'),
				'class'			=> array('form-row-last'),
				'required'		=> false,
				'default'		=> ($current_user->billing_nif ? trim($current_user->billing_nif) : ''),
			);

			return $fields;
		}

		//Add NIF to My Account / Billing Address form
		function wc_ie_nif_my_account( $fields ) {
			global $wp_query;
			if (isset($wp_query->query_vars['edit-address']) && $wp_query->query_vars['edit-address']!='billing') {
				return $fields;
			} else {
				$current_user=wp_get_current_user();
				if ($current_user->billing_country=='PT') {
					$fields['billing_nif']=array(
						'type'			=>	'text',
						'label'			=> __('NIF / NIPC', 'wc_masterway'),
						'placeholder'	=> _x('Portuguese VAT identification number', 'placeholder', 'wc_masterway'),
						'class'			=> array('form-row-last'),
						'required'		=> false,
						//'clear'			=> true,
						'default'		=> ($current_user->billing_nif ? trim($current_user->billing_nif) : ''),
					);
				}
				return $fields;
			}
		}

		//Save NIF to customer Billing Address
		function wc_ie_my_account_save($user_id, $load_address) {
			if ($load_address=='billing') {
				if (isset($_POST['billing_nif'])) {
					update_user_meta( $user_id, 'billing_nif', trim($_POST['billing_nif']) );
				}
			}
		}

		// Add field to order admin panel
                function wc_ie_nif_admin($order){
                    if (isset($order->order_custom_fields['_billing_country'])) {
                        if (is_array($order->order_custom_fields['_billing_country'])) {
                            // Old WooCommerce versions
                            if (in_array('PT', $order->order_custom_fields['_billing_country'])) {
                                echo "<p><strong>".__('NIF / NIPC', 'wc_masterway').":</strong> " . $order->order_custom_fields['_billing_nif'][0] . "</p>";
                            }
                        } else {
                            // New WooCommerce versions
                            if ($order->order_custom_fields['_billing_country'] == 'PT') {
                                $order_custom_fields = get_post_custom($order->ID);
                                echo "<p><strong>".__('NIF / NIPC', 'wc_masterway').":</strong> " . $order_custom_fields['_billing_nif'][0] . "</p>";
                            }
                        }
                    } else {
                        // Handle the case where '_billing_country' is not set in the custom fields
                    }
                }


		function wc_ie_nif_validation() {
                    // Check if set, if its not set add an error.
                    if(isset($_POST['billing_nif']) && !empty($_POST['billing_nif']) && isset($_POST['billing_country']) && $_POST['billing_country'] == 'PT'){
                        if(! $this->wc_ie_validate_portuguese_vat($_POST['billing_nif'])){
                                wc_add_notice( __( 'Invalid NIF / NIPC', 'wc_masterway' ), 'error' );
                        }
                    }
		}

		function wc_ie_validate_portuguese_vat($vat) {

			$valid_first_digits = array(1, 2, 3, 5, 6, 8 );
			$valid_first_two_digits = array(45, 70, 71, 72, 77, 79, 90, 91, 98, 99);

			// if first digit is valid
			$first_digit = (int) substr($vat, 0, 1);
			$first_two_digits = (int) substr($vat, 0, 2);

			if ( ! in_array($first_digit, $valid_first_digits) &&
				 ! in_array($first_two_digits, $valid_first_two_digits) )
			{
				return false;
			}

			$check1 = substr($vat, 0,1)*9;
			$check2 = substr($vat, 1,1)*8;
			$check3 = substr($vat, 2,1)*7;
			$check4 = substr($vat, 3,1)*6;
			$check5 = substr($vat, 4,1)*5;
			$check6 = substr($vat, 5,1)*4;
			$check7 = substr($vat, 6,1)*3;
			$check8 = substr($vat, 7,1)*2;

			$total= $check1 + $check2 + $check3 + $check4 + $check5 + $check6 + $check7 + $check8;

			$totalDiv11 = $total / 11;
			$modulusOf11 = $total - intval($totalDiv11) * 11;
			if ( $modulusOf11 == 1 || $modulusOf11 == 0)
			{
				$check = 0;
			}
			else
			{
				$check = 11 - $modulusOf11;
			}


			$lastDigit = substr($vat, 8,1)*1;
			if ( $lastDigit != $check ) {
				return false;
			}

			return true;
		}

	}
}