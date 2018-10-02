<?php
/**
 * Plugin Name: WooCommerce CloudKassir Gateway
 * Plugin URI: http://woothemes.com/woocommerce
 * Description: Extends WooCommerce with CloudKassir Gateway.
 * Version: 1.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

 
//add_action('woocommerce_order_status_changed', 'woo_order_status_change_custom', 10, 3);



if (!function_exists('getallheaders'))  
{
    function getallheaders()
    {
        if (!is_array($_SERVER)) {
            return array();
        }

        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

add_action('plugins_loaded', 'CloudKassir', 0);
function CloudKassir() 
{	
  if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	
  add_filter('woocommerce_payment_gateways','woocommerce_add_cp_kassir');
  function woocommerce_add_cp_kassir( $methods )
  {	
		$methods[] = 'WC_CloudKassir'; 
		return $methods;
  }


  class WC_CloudKassir extends WC_Payment_Gateway
  {
	public function __construct() 
    {
      $this->id                 =  'cp_kassir';
      $this->has_fields         =  true;
      $this->method_title       =  __( 'CloudKassir', 'woocommerce' );
      $this->method_description =  'CloudKassir';
      $this->supports           =  array( 'products','pre-orders' );
      $this->enabled            =  $this->get_option( 'enabled' );
      $this->inn            =  $this->get_option( 'inn' );
      $this->status_chancel     =  $this->get_option( 'status_chancel' );
      $this->status_pay         =  $this->get_option( 'status_pay' );
      $this->init_form_fields();
      $this->init_settings();
      
      $this->title          	= $this->get_option( 'title' );
      $this->description    	= $this->get_option( 'description' );
      $this->public_id    	  = $this->get_option( 'public_id' );
      $this->api_pass    	  	= $this->get_option( 'api_pass' );
      
      $this->kassa_taxtype    = $this->get_option( 'kassa_taxtype' );
      $this->delivery_taxtype    = $this->get_option( 'delivery_taxtype' );
      $this->kassa_taxsystem  = $this->get_option( 'kassa_taxsystem' );
      $this->kassa_skubarcode  = $this->get_option( 'kassa_skubarcode' );
      $this->kassa_includeshipping  = $this->get_option( 'kassa_includeshipping' );
      $this->payment_methods  = $this->get_option( 'payment_methods' );
            
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      add_action('woocommerce_order_status_changed', array( $this, 'update_order_status'), 10, 3);
	}

		// Admin options
		public function admin_options() 
    {		
			?>
				<h3>CloudKassir</h3>
			<?php
		
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
    }
		

        
		// Initialize fields
		public function init_form_fields()
    {
			$array_status = wc_get_order_statuses();



		    $ordering  = (array) get_option( 'woocommerce_gateway_order' );     

			$this->form_fields = array(
				'status_pay' => array(
					'title'       => __( 'Статус для печати чека прихода', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( '', 'woocommerce' ),
					'default'     => 'wc-completed',
					'desc_tip'    => true,
					'options'     => $array_status,
				),
				'status_chancel' => array(
					'title'       => __( 'Статус для печати чека возврата', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( '', 'woocommerce' ),
					'default'     => 'wc-cancelled',
					'desc_tip'    => true,
					'options'     => $array_status,
				),
				'title' => array(
					'title' 		=> __( 'Title', 'woocommerce' ),
					'type' 			=> 'text',
					'description' 	=> __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default' 		=> __( 'Онлайн касса', 'woocommerce' ),
					'desc_tip'   	 => true,
				),
				'description' 	=> array(
					'title'       => __( 'Description', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
					'default'     => __( 'CloudKassir – онлайн-касса для соответсвия ФЗ-54', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'public_id' => array(
					'title' 		=> __( 'Public ID', 'woocommerce' ),
					'type' 			=> 'text',
					'description'	=> 'Возьмите из личного кабинета CloudPayments',
					'default' 		=> '',
					'desc_tip' 		=> false,
				),
				'api_pass' => array(
					'title' 		=> __( 'Пароль для API', 'woocommerce' ),
					'type' 			=> 'text',
					'description'	=> 'Возьмите из личного кабинета CloudPayments',
					'default' 		=> '',
					'desc_tip' 		=> false,
				),
				'inn' => array(
					'title' 		=> __( 'ИНН', 'woocommerce' ),
					'type' 			=> 'text',
					'description'	=> '',
					'default' 		=> '',
					'desc_tip' 		=> false,
				),
				'kassa_taxtype' => array(
					'title'       => __( 'Ставка НДС', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Выберите ставку НДС, которая применима к товарам в магазине.', 'woocommerce' ),
					'default'     => '10',
					'desc_tip'    => true,
					'options'     => array(
						'null' => __( 'НДС не облагается', 'woocommerce' ),
						'18' => __( 'НДС 18%', 'woocommerce' ),
						'10' => __( 'НДС 10%', 'woocommerce' ),
						'0' => __( 'НДС 0%', 'woocommerce' ),
						'110' => __( 'расчетный НДС 10/110', 'woocommerce' ),
						'118' => __( 'расчетный НДС 18/118', 'woocommerce' ),
					),
				),  
				'delivery_taxtype' => array(
					'title'       => __( 'Ставка НДС для доставки', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Выберите ставку НДС, которая применима к доставке в магазине.', 'woocommerce' ),
					'default'     => '10',
					'desc_tip'    => true,
					'options'     => array(
						'null' => __( 'НДС не облагается', 'woocommerce' ),
						'18' => __( 'НДС 18%', 'woocommerce' ),
						'10' => __( 'НДС 10%', 'woocommerce' ),
						'0' => __( 'НДС 0%', 'woocommerce' ),
						'110' => __( 'расчетный НДС 10/110', 'woocommerce' ),
						'118' => __( 'расчетный НДС 18/118', 'woocommerce' ),
					),
				), 
				'kassa_taxsystem' => array(
					'title'       => __( 'Cистема налогообложения организации', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Указанная система налогообложения должна совпадать с одним из вариантов, зарегистрированных в ККТ.', 'woocommerce' ),
					'default'     => '1',
					'desc_tip'    => true,
					'options'     => array(
						'0' => __( 'Общая система налогообложения', 'woocommerce' ),
						'1' => __( 'Упрощенная система налогообложения (Доход)', 'woocommerce' ),
						'2' => __( 'Упрощенная система налогообложения (Доход минус Расход)', 'woocommerce' ),
						'3' => __( 'Единый налог на вмененный доход', 'woocommerce' ),
						'4' => __( 'Единый сельскохозяйственный налог', 'woocommerce' ),
						'5' => __( 'Патентная система налогообложения', 'woocommerce' ),
					),
				),
				'payment_methods' => array(
					'title'       => __( 'Для каких платежных систем включать', 'woocommerce' ),
					'type'        => 'multiselect',
					'class'       => 'wc-enhanced-select',
					'description' => __( '', 'woocommerce' ),
					'default'     => '',   
                    'multiple' => true,
					'desc_tip'    => true,
					'options'     => array(
					),
				),                
			);
            
            foreach ($ordering as $paym=>$k):
                if ($paym == 'bacs') $paym_name = 'Прямой банковский перевод';
                if ($paym == 'cheque') $paym_name = 'Чековые платежи';
                if ($paym == 'cod') $paym_name = 'Оплата при доставке';
                if ($paym == 'paypal') $paym_name = 'PayPal';
                if ($paym == 'cp') $paym_name = 'CloudPayments';
                $this->form_fields['payment_methods']['options'][$paym]=__($paym_name, 'woocommerce');
            endforeach;
           // die();

           // $ordering
		}


   public function Object_to_array($data)      ///OK
   {
        if (is_array($data) || is_object($data))
        {
            $result = array();
            foreach ($data as $key => $value)
            {
                $result[$key] = self::Object_to_array($value);
            }
            return $result;
        }
        return $data;
   }

 
    private function detallheaders()  ///OK
    {
        if (!is_array($_SERVER)) {
            return array();
        }
        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
  
    public function addError($text)              ///addError7
    {
          $debug=true;
          if ($debug)
          {
            $file=$_SERVER['DOCUMENT_ROOT'].'/wp-content/plugins/woocommerce-cloudkassir/log.txt';
            $current = file_get_contents($file);
            $current .= date("d-m-Y H:i:s").":".$text."\n";
            file_put_contents($file, $current);
          }
    }
        
  	public function get_order($request)   ///OK
    {
    	global $woocommerce;			
    	$order = new WC_Order($request['InvoiceId']);
      return $order;
    } 
    
  	public function SendReceipt($order,$type)   ///OK
    {
      self::addError('SendReceipt!!');
      //self::addError(print_r($order,1));
      $cart=$order->get_items();
      //self::addError(print_r($cart,1));

    	foreach ($cart as $item_id => $item_data):
        $product = $item_data->get_product();
        $items[]=array(
                'label'=>$product->get_name(),
                'price'=>number_format($product->get_price(),2,".",''),
                'quantity'=>$item_data->get_quantity(),
                'amount'=>number_format(floatval($item_data->get_total()),2,".",''),
                'vat'=>$this->kassa_taxtype, ///     $Ar_params['cloudpayments_vat']
        ); 
  
        if ($order->get_total_shipping()):
            $items[]=array(
                    'label'=>"Доставка",
                    'price'=>$order->get_total_shipping(),
                    'quantity'=>1,
                    'amount'=>$order->get_total_shipping(),
                    'vat'=>$this->delivery_taxtype, 
            ); 
        endif; 
      endforeach; 
      
      $data['cloudPayments']['customerReceipt']['Items']=$items;
      $data['cloudPayments']['customerReceipt']['taxationSystem']=$this->kassa_taxsystem; 
      $data['cloudPayments']['customerReceipt']['email']=$order->get_billing_email(); 
      $data['cloudPayments']['customerReceipt']['phone']=$order->get_billing_phone();  
      
  		$aData = array(
  			'Inn' => $this->inn,
  			'InvoiceId' => $order->get_id(), //номер заказа, необязательный
  			'AccountId' => $order->get_user_id(),
  			'Type' => $type,
  			'CustomerReceipt' => $data['cloudPayments']['customerReceipt']
  		);
      $API_URL='https://api.cloudpayments.ru/kkt/receipt';
      self::send_request($API_URL,$aData);
      self::addError("kkt/receipt");
    }
    
    public function send_request($API_URL,$request)  ///OK
    {
      if($curl = curl_init()):
            self::addError("send_request111");
            self::addError(print_r($request,1));   
            $request2=self::cur_json_encode($request);
            
            $str=date("d-m-Y H:i:s").$request['Type'].$request['InvoiceId'].$request['AccountId'].$request['CustomerReceipt']['email'];
            $reque=md5($str);
            $ch = curl_init($API_URL);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch,CURLOPT_USERPWD,$this->public_id. ":" . $this->api_pass);
            curl_setopt($ch, CURLOPT_URL, $API_URL);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json","X-Request-ID:".$reque));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);              
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request2);
          	$content = curl_exec($ch);
            self::addError(print_r($content,1));
      	    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        		$curlError = curl_error($ch);
        		curl_close($ch);
      endif;
    }
        
    public function update_order_status($order_id,$old_status,$new_status) //OK
    {                                          
      $this->addError('update_order_statuskassa');
      $this->addError('payment_methods');
     // if ($this->payment_methods)
      $this->addError($this->status_pay."==wc-".$new_status);
      $request['InvoiceId']=$order_id;
      $order=self::get_order($request);
      if (in_array($order->get_payment_method(),$this->payment_methods)):
        if ($this->status_pay=="wc-".$new_status):
          self::addError("Send kkt Income!");
          $this->addError('request');
          $this->addError(print_r($request,1));
          self::SendReceipt($order, 'Income');
        elseif ($this->status_chancel=="wc-".$new_status):
          self::addError("Send kkt IncomeReturn!");
          self::SendReceipt($order, 'IncomeReturn');
        endif;
      else:
      endif; 
    }
        
		// Callback
    public function handle_callback() 
    {
      $this->addError('handle_callbackkassa');
    }
    
    
    function cur_json_encode($a=false)      /////ok
    {
        if (is_null($a) || is_resource($a)) {
            return 'null';
        }
        if ($a === false) {
            return 'false';
        }
        if ($a === true) {
            return 'true';
        }
        
        if (is_scalar($a)) {
            if (is_float($a)) {
                $a = str_replace(',', '.', strval($a));
            }
    
            static $jsonReplaces = array(
                array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'),
                array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"')
            );
    
            return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
        }
    
        $isList = true;
    
        for ($i = 0, reset($a); $i < count($a); $i++, next($a)) {
            if (key($a) !== $i) {
                $isList = false;
                break;
            }
        }
    
        $result = array();
        
        if ($isList) {
            foreach ($a as $v) {
                $result[] = self::cur_json_encode($v);
            }
        
            return '[ ' . join(', ', $result) . ' ]';
        } else {
            foreach ($a as $k => $v) {
                $result[] = self::cur_json_encode($k) . ': ' . self::cur_json_encode($v);
            }
    
            return '{ ' . join(', ', $result) . ' }';
        }
    }
    
	}
}



