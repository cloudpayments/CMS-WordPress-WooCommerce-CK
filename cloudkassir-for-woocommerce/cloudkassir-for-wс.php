<?php
/**
 * Plugin Name: CloudKassir for WooCommerce
 * Plugin URI: https://cloudkassir.ru/
 * Description: Extends WooCommerce with CloudKassir.
 * Version: 2.1
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Register New Order Statuses
function ckgwwc_register_post_statuses() 
{
    register_post_status( 'wc-pay_delivered', array(
        'label'                     => _x( 'Доставлен', 'WooCommerce Order status', 'text_domain' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Approved (%s)', 'Approved (%s)', 'text_domain' )
    ) );
}
add_filter( 'init', 'ckgwwc_register_post_statuses' );

// Add New Order Statuses to WooCommerce
function ckgwwc_add_order_statuses( $order_statuses )
{
    $order_statuses['wc-pay_delivered'] = _x( 'Доставлен', 'WooCommerce Order status', 'text_domain' );
    return $order_statuses;
}
add_filter( 'wc_order_statuses', 'ckgwwc_add_order_statuses' );

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

add_action('plugins_loaded', 'ckgwwc_CloudKassir', 0);

function ckgwwc_CloudKassir() 
{	
  if ( !class_exists( 'WC_Payment_Gateway' ) ) {
	    echo 'CloudKassir for WooCommerce plugin is disabled. Check to see if this plugin is active.';
	    return;
	}
	
  add_filter('woocommerce_payment_gateways','ckgwwc_add_ckgwwc');
  function ckgwwc_add_ckgwwc( $methods )
  {	
		$methods[] = 'WC_CloudKassir'; 
		return $methods;
  }


  class WC_CloudKassir extends WC_Payment_Gateway
  {
	public function __construct() 
    {
      $this->id                     = 'ckgwwc_kassir';
      $this->has_fields             = true;
      $this->method_title           = __( 'CloudKassir', 'woocommerce' );
      $this->method_description     = 'CloudKassir, онлайн-кассы для интернет-магазинов по 54-ФЗ';
      $this->supports               = array( 'products','pre-orders' );
      $this->enabled                = $this->get_option( 'enabled' );
      $this->inn                    = $this->get_option( 'inn' );
      $this->status_chancel         = $this->get_option( 'status_chancel' );
      $this->status_pay             = $this->get_option( 'status_pay' );
      $this->init_form_fields();
      $this->init_settings();
      
      $this->title              	= $this->get_option( 'title' );
      $this->description        	= $this->get_option( 'description' );
      $this->public_id    	        = $this->get_option( 'public_id' );
      $this->api_pass    	     	= $this->get_option( 'api_pass' );
      
      $this->kassa_taxtype          = $this->get_option( 'kassa_taxtype' );
      $this->delivery_taxtype       = $this->get_option( 'delivery_taxtype' );
      $this->kassa_taxsystem        = $this->get_option( 'kassa_taxsystem' );
      $this->calculationPlace       = $this->get_option( 'calculationPlace' );
      $this->kassa_skubarcode       = $this->get_option( 'kassa_skubarcode' );
      $this->kassa_includeshipping  = $this->get_option( 'kassa_includeshipping' );
      $this->payment_methods        = $this->get_option( 'payment_methods' );
      $this->kassa_method           = $this->get_option( 'kassa_method' );
      $this->kassa_object           = $this->get_option( 'kassa_object' );
      $this->status_delivered       = $this->get_option( 'status_delivered' );
            
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      add_action('woocommerce_order_status_changed', array( $this, 'ckgwwc_update_order_status'), 10, 3);
      add_action( 'woocommerce_api_'. strtolower( get_class( $this ) ), array( $this, 'ckgwwc_callback' ) );
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
					'default' 		=> __( 'Онлайн касса', 'woocommerce' ),
					'desc_tip'   	 => true,
				),
				'description' 	=> array(
					'title'       => __( 'Description', 'woocommerce' ),
					'type'        => 'textarea',
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
					'desc_tip' 		=> true,
                ),
                'calculationPlace' => array(
					'title' 		=> __( 'Место осуществления расчёта', 'woocommerce' ),
					'type' 			=> 'text',
					'description'	=> '',
					'default' 		=> '',
					'desc_tip' 		=> true,
				),
				'kassa_taxtype' => array(
					'title'       => __( 'Ставка НДС', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Выберите ставку НДС, которая применима к товарам в магазине.', 'woocommerce' ),
					'default'     => '10',
					'desc_tip'    => true,
					'options'     => array(
						'' => __( 'НДС не облагается', 'woocommerce' ),
						'20' => __( 'НДС 20%', 'woocommerce' ),
						'10' => __( 'НДС 10%', 'woocommerce' ),
						'0' => __( 'НДС 0%', 'woocommerce' ),
						'110' => __( 'расчетный НДС 10/110', 'woocommerce' ),
						'120' => __( 'расчетный НДС 20/120', 'woocommerce' ),
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
						'' => __( 'НДС не облагается', 'woocommerce' ),
						'20' => __( 'НДС 20%', 'woocommerce' ),
						'10' => __( 'НДС 10%', 'woocommerce' ),
						'0' => __( 'НДС 0%', 'woocommerce' ),
						'110' => __( 'расчетный НДС 10/110', 'woocommerce' ),
						'120' => __( 'расчетный НДС 20/120', 'woocommerce' ),
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
				'kassa_method' => array(
					'title'       => __( 'Способ расчета', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Выберите способ расчета', 'woocommerce' ),
					'default'     => '1',
					'desc_tip'    => true,
					'options'     => array(
						'0' => __( 'Способ расчета не передается', 'woocommerce' ),
						'1' => __( 'Предоплата 100%', 'woocommerce' ),
						'2' => __( 'Предоплата', 'woocommerce' ),
						'3' => __( 'Аванс', 'woocommerce' ),
						'4' => __( 'Полный расчёт', 'woocommerce' ),
						'5' => __( 'Частичный расчёт и кредит', 'woocommerce' ),
						'6' => __( 'Передача в кредит', 'woocommerce' ),
						'7' => __( 'Оплата кредита', 'woocommerce' ),
					),
				),
				'kassa_object' => array(
					'title'       => __( 'Предмет расчета', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Выберите предмет расчета', 'woocommerce' ),
					'default'     => '1',
					'desc_tip'    => true,
					'options'     => array(
						'0' => __( 'Предмет расчета не передается', 'woocommerce' ),
						'1' => __( 'Товар', 'woocommerce' ),
						'2' => __( 'Подакцизный товар', 'woocommerce' ),
						'3' => __( 'Работа', 'woocommerce' ),
						'4' => __( 'Услуга', 'woocommerce' ),
						'5' => __( 'Ставка азартной игры', 'woocommerce' ),
						'6' => __( 'Выигрыш азартной игры', 'woocommerce' ),
						'7' => __( 'Лотерейный билет', 'woocommerce' ),
						'8' => __( 'Выигрыш лотереи', 'woocommerce' ),
						'9' => __( 'Предоставление РИД', 'woocommerce' ),
						'10' => __( 'Платеж', 'woocommerce' ),
						'11' => __( 'Агентское вознаграждение', 'woocommerce' ),
						'12' => __( 'Составной предмет расчета', 'woocommerce' ),
						'13' => __( 'Иной предмет расчета', 'woocommerce' ),
					),
				),
				'status_delivered' => array(
					'title'       => __( 'Статус которым пробивать 2ой чек при отгрузке товара или выполнении услуги', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Согласно ФЗ-54 владельцы онлайн-касс должны формировать чеки для зачета и предоплаты. Отправка второго чека возможна только при следующих способах расчета: Предоплата, Предоплата 100%, Аванс', 'woocommerce' ),
					'default'     => 'wc-pay_delivered',
					'desc_tip'    => true,
					'options'     => $array_status,
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
                if ($paym == 'cpgwwc') $paym_name = 'CloudPayments';
                $this->form_fields['payment_methods']['options'][$paym]=__($paym_name, 'woocommerce');
            endforeach;
		}

    public function ckgwwc_Object_to_array($data)
    {
        if (is_array($data) || is_object($data))
        {
            $result = array();
            foreach ($data as $key => $value)
            {
                $result[$key] = self::ckgwwc_Object_to_array($value);
            }
            return $result;
        }
        return $data;
    }

    private function ckgwwc_detallheaders()
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
  
    public function ckgwwc_addError($text)
    {
          $debug=false;
          if ($debug)
          {
            $file=$_SERVER['DOCUMENT_ROOT'].'/wp-content/plugins/woocommerce-cloudkassir/log.txt';
            $current = file_get_contents($file);
            $current .= date("d-m-Y H:i:s").":".$text."\n";
            file_put_contents($file, $current);
          }
    }
        
  	public function ckgwwc_get_order($request)
    {
    	global $woocommerce;			
    	$order = new WC_Order($request['InvoiceId']);
      return $order;
    } 
    
  	public function ckgwwc_SendReceipt($order,$type,$old_status,$new_status)
    {
	    self::ckgwwc_addError('SendReceipt!!');
	    $cart=$order->get_items();
	    $total_amount = 0;

	    foreach ($cart as $item_id => $item_data):
		    $product = $item_data->get_product();
		    if ("wc-".$new_status == $this->status_delivered) {
			    $method = 4;
		    }
		    else {
			    $method = (int)$this->kassa_method;
		    };

		    //вычисление скидки
		    $fees = $order->get_items('fee');
		    foreach($fees as $fee){
			    $total_fees = floatval($fee->get_total());
		    }
		    //$this->ckgwwc_addError('fee:'.$total_fees);


		    $items[]=array(
			    'label'    => $product->get_name(),
			    'price'    => number_format($product->get_price(),2,".",''),
			    'quantity' => $item_data->get_quantity(),
			    'amount'   => number_format(floatval($item_data->get_total()),2,".",''),
			    'vat'      => $this->kassa_taxtype,
			    'method'   => $method,
			    'object'   => (int)$this->kassa_object,
		    );

		    $total_amount = $total_amount +  number_format(floatval($item_data->get_total()),2,".",'');

	    endforeach;

	    if ($order->get_total_shipping()):
		    $items[]=array(
			    'label'=>"Доставка",
			    'price'=>$order->get_total_shipping(),
			    'quantity'=>1,
			    'amount'=>$order->get_total_shipping(),
			    'vat'=>$this->delivery_taxtype,
			    'method'   => $method,
			    'object'   => 4,
		    );

		    $total_amount = $total_amount + number_format(floatval($order->get_total_shipping()),2,".",'');

	    endif;


	    $data['cloudPayments']['customerReceipt']['Items']=$items;
	    $data['cloudPayments']['customerReceipt']['taxationSystem']=$this->kassa_taxsystem;
	    $data['cloudPayments']['customerReceipt']['calculationPlace']=$this->calculationPlace;
	    $data['cloudPayments']['customerReceipt']['email']=$order->get_billing_email();
	    $data['cloudPayments']['customerReceipt']['phone']=$order->get_billing_phone();
	    //вычисление итогового amount
	    $data['cloudPayments']['customerReceipt']['amounts']['electronic']=$total_amount + $total_fees;
	    //вычитаем скидку

	    if(!empty($total_fees)){
		    $percent = $total_fees / floatval($total_amount);
	    }



	    foreach ($data['cloudPayments']['customerReceipt']['Items'] as &$item){
		    if(!empty($percent)){
			    $fee_item = floatval($item['amount']) * $percent;

			    $amount = $item['amount'] + $fee_item;
			    $item['amount'] = number_format(floatval($amount), 2);
		    }
	    }
	    /*for ($i=0; $i<(count($items)); $i++) {
		  if ($items[$i]['amount']>$total_fees) {
			  $data['cloudPayments']['customerReceipt']['Items'][$i]['amount']=$data['cloudPayments']['customerReceipt']['Items'][$i]['amount'] + $total_fees;
			  $this->ckgwwc_addError('data:'.json_encode($data['cloudPayments']['customerReceipt']['Items'][$i]['amount']));
			  break;
		  }
		}*/
	    //вычитаем скидку из второго чека
	    if ("wc-".$new_status == $this->status_delivered) {
		    $data['cloudPayments']['customerReceipt']['amounts']['electronic']=0;
		    $data['cloudPayments']['customerReceipt']['amounts']['advancePayment']=$total_amount + $total_fees;
	    }

	    $aData = array(
		    'Inn' => $this->inn,
		    'InvoiceId' => $order->get_id(), //номер заказа, необязательный
		    'AccountId' => $order->get_user_id(),
		    'Type' => $type,
		    'CustomerReceipt' => $data['cloudPayments']['customerReceipt']
	    );



	    self::ckgwwc_addError(json_encode($aData));
	    $API_URL='https://api.cloudpayments.ru/kkt/receipt';
	    self::ckgwwc_send_request($API_URL,$aData);
	    self::ckgwwc_addError("kkt/receipt");
    }
    
    public function ckgwwc_send_request($API_URL,$request)  ///OK
    {
	    $request2=self::ckgwwc_cur_json_encode($request);
	    $str=date("d-m-Y H:i:s").$request['Type'].$request['InvoiceId'].$request['AccountId'].$request['CustomerReceipt']['email'];
	    $reque=md5($str);
	    $auth = base64_encode($this->public_id. ":" . $this->api_pass);
	    $response = wp_remote_post( $API_URL, array(
		    'timeout'     => 30,
		    'redirection' => 5,
		    'httpversion' => '1.0',
		    'blocking'    => true,
		    'headers'     => array('Authorization' => 'Basic '.$auth, 'Content-Type' => 'application/json', 'X-Request-ID' => $reque),
		    'body'        => $request2,
		    'cookies'     => array()
	    ) );

	    $body = json_decode($response['body']);
	    if(!empty($body->Message)){
		    self::ckgwwc_addError('Check create resp message: '.mb_convert_encoding($body->Message, "windows-1251", "utf-8"));
	    }

	    self::ckgwwc_addError('Check create resp: '.json_encode($response));
    }
        
    public function ckgwwc_update_order_status($order_id,$old_status,$new_status) //OK
    {                                          
      $this->ckgwwc_addError('update_order_statuskassa');
      $this->ckgwwc_addError('payment_methods');
      $this->ckgwwc_addError($this->status_pay."==wc-".$new_status);
      $request['InvoiceId']=$order_id;
      $order=self::ckgwwc_get_order($request);
      if (in_array($order->get_payment_method(),$this->payment_methods)):
        if ($this->status_pay=="wc-".$new_status || ("wc-".$new_status == $this->status_delivered && ((int)$this->kassa_method == 1 || (int)$this->kassa_method == 2 || (int)$this->kassa_method == 3))):
          self::ckgwwc_addError("Send kkt Income!");
          $this->ckgwwc_addError('request');
          $this->ckgwwc_addError(print_r($request,1));
          self::ckgwwc_SendReceipt($order, 'Income',$old_status,$new_status);
        elseif ($this->status_chancel=="wc-".$new_status):
          self::ckgwwc_addError("Send kkt IncomeReturn!");
          self::ckgwwc_SendReceipt($order, 'IncomeReturn',$old_status,$new_status);
        endif;
      else:
      endif; 
    }
        
		// Callback
    public function ckgwwc_handle_callback() 
    {
      $this->ckgwwc_addError('handle_callbackkassa');
    }
    
    
    function ckgwwc_cur_json_encode($a=false)      /////ok
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
                $result[] = self::ckgwwc_cur_json_encode($v);
            }
        
            return '[ ' . join(', ', $result) . ' ]';
        } else {
            foreach ($a as $k => $v) {
                $result[] = self::ckgwwc_cur_json_encode($k) . ': ' . self::ckgwwc_cur_json_encode($v);
            }
    
            return '{ ' . join(', ', $result) . ' }';
        }
    }
    
    public function ckgwwc_callback() 
    {
        if ($_GET['action'] == 'receipt') {
            
            $request['InvoiceId']=$_POST['InvoiceId'];
            	
            if ($_POST['Type'] == 'IncomeReturn') {
                $Type = 'возврата прихода';
            }
            elseif ($_POST['Type'] == 'Income') {
                $Type = 'прихода';
            }
            $url = $_POST['Url'];
            $note= 'Ссылка на чек '.$Type.': '.$url;
            $order=self::ckgwwc_get_order($request);
            $var = $order->add_order_note( $note, 1 );
            $order->save();
            $data['CODE'] = 0;                         					
            echo json_encode($data);  
            exit;
        }
    }
	}
}