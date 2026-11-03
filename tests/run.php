<?php
/**
 * Author: Salman | Version: 1.2.0
 * Description: Offline payment verification and return-handler regression tests.
 * Copyright (C) 2026 Salman. SPDX-License-Identifier: GPL-2.0-or-later
 */
// Offline contract tests. No WordPress installation, API credentials or network required.
define('ABSPATH', __DIR__);
class WP_Error { private $code; private $message; private $data; function __construct($c,$m,$d=null){$this->code=$c;$this->message=$m;$this->data=$d;} function get_error_data(){return $this->data;} function get_error_message(){return $this->message;} }
function is_wp_error($v){return $v instanceof WP_Error;}
function wp_unslash($s){return stripslashes($s);}
function nocache_headers(){}
class RedirectCaptured extends Exception {}
class ReturnRejected extends Exception {}
function wp_safe_redirect($url){throw new RedirectCaptured($url);}
function wp_die($message,$title,$args){throw new ReturnRejected($message);}
function get_option($k,$d=array()){return $GLOBALS['settings'];}
function home_url(){return 'https://store.example';}
function wp_parse_url($u,$c){return parse_url($u,$c);}
function wp_json_encode($x){return json_encode($x);}
function wp_remote_request($url,$args){$GLOBALS['requests'][]=array($url,$args);return array_shift($GLOBALS['responses']);}
function wp_remote_retrieve_response_code($r){return $r['code'];}
function wp_remote_retrieve_body($r){return json_encode($r['body']);}
function wc_format_decimal($x,$d){return number_format((float)$x,$d,'.','');}
function sanitize_text_field($s){return strip_tags((string)$s);}
function wc_get_order($id){return $GLOBALS['order'];}
function wp_next_scheduled($h,$a){return false;}
function wp_schedule_single_event($t,$h,$a){return true;}
function wp_generate_uuid4(){return '12345678-1234-4234-8234-123456789abc';}
function wc_get_price_decimals(){return 2;}
function get_woocommerce_currency(){return 'GBP';}
function esc_url_raw($s){return $s;}
function add_query_arg($args,$u){return $u.'?'.http_build_query($args);}
function WC(){return new class { public $cart=null; function api_request_url($name){return 'https://store.example/wc-api/'.$name.'/';} };}
function wc_add_notice($m,$t){$GLOBALS['notices'][]=$m;}
class DB { public $prefix='wp_';function prepare($sql,$v){return $sql;}function get_var($sql){return 1;} }
$GLOBALS['wpdb']=new DB;
class WC_Payment_Gateway { public $id='tribepay'; function get_option($k,$d=''){return $GLOBALS['settings'][$k]??$d;}function get_return_url($o){return 'https://store.example/thanks';} }
class FakeOrder {
 public $meta=array();public $status='pending';public $tx='';public $notes=array();public $complete=0;
 function get_id(){return 123;}function get_payment_method(){return 'tribepay';}function get_meta($k){return $this->meta[$k]??'';}
 function update_meta_data($k,$v){$this->meta[$k]=$v;}function save(){}function get_currency(){return 'GBP';}function get_total(){return '19.95';}
 function is_paid(){return in_array($this->status,array('processing','completed'));}function has_status($s){return in_array($this->status,(array)$s);}
 function get_transaction_id(){return $this->tx;}function add_order_note($n){$this->notes[]=$n;}
 function payment_complete($tx){$this->complete++;$this->tx=$tx;$this->status='processing';}
 function update_status($s,$n){$this->status=$s;$this->notes[]=$n;}
 function get_checkout_order_received_url(){return 'https://store.example/checkout/order-received/123/?key=wc_test';}
 function get_checkout_payment_url(){return 'https://store.example/checkout/order-pay/123/?key=wc_test';}
 function get_order_key(){return 'wc_test';}function get_formatted_billing_full_name(){return 'Test Customer';}
 function get_billing_email(){return 'test@example.com';}function get_billing_phone(){return '';}function get_order_number(){return '123';}
}
$root=dirname(__DIR__).'/tribepay-woocommerce/includes/';
require $root.'class-ztp-api.php';require $root.'class-ztp-service.php';require $root.'class-ztp-gateway.php';
$passed=0;
function check($ok,$name){global $passed;if(!$ok){fwrite(STDERR,'FAIL '.$name."\n");exit(1);} $passed++;}
function response($body,$code=200){return array('code'=>$code,'body'=>$body);}
$GLOBALS['settings']=array('mode'=>'live','api_key'=>'pk_live_dummy','secret_key'=>'sk_live_dummy','webhook_secret'=>'dummy-signing-secret','currencies'=>'GBP');
check(ZTP_API::ready(),'matching environment configured');
$GLOBALS['settings']['mode']='test';check(ZTP_API::ready() && ZTP_API::value('mode')==='live','legacy sandbox setting cannot change live-only build');$GLOBALS['settings']['mode']='live';
$GLOBALS['settings']['api_key']='pk_test_dummy';check(!ZTP_API::ready(),'test API key rejected');$GLOBALS['settings']['api_key']='pk_live_dummy';
$GLOBALS['settings']['secret_key']='sk_test_dummy';check(!ZTP_API::ready(),'test secret rejected');$GLOBALS['settings']['secret_key']='sk_live_dummy';
$GLOBALS['settings']['webhook_secret']='';check(ZTP_API::ready(),'webhook secret optional for API verification');$GLOBALS['settings']['webhook_secret']='dummy-signing-secret';
foreach(array('http://api.tribe-pay.com/checkout/x','https://evil.example/checkout/x','https://api.tribe-pay.com.evil.example/checkout/x','https://user@api.tribe-pay.com/checkout/x','https://api.tribe-pay.com:444/checkout/x','https://api.tribe-pay.com/other') as $url){check(!ZTP_API::checkout_url($url),'unsafe redirect rejected');}
check(ZTP_API::checkout_url('https://api.tribe-pay.com/checkout/token'),'official checkout accepted');
$ref='ztp-123-12345678123442348234';
$attempt=array('amount'=>'19.95','currency'=>'GBP','mode'=>'live','decimals'=>2);
$data=array('success'=>true,'order_id'=>$ref,'amount'=>19.95,'currency'=>'GBP','mode'=>'live','reference'=>'TRP-TEST','status'=>'completed');
check(ZTP_Service::matches($data,$attempt,$ref),'exact payment matches');
foreach(array('amount'=>19.951,'currency'=>'USD','mode'=>'sandbox','order_id'=>'other') as $key=>$val){$bad=$data;$bad[$key]=$val;check(!ZTP_Service::matches($bad,$attempt,$ref),'mismatch '.$key);}
$order=new FakeOrder;$order->meta=array('_ztp_attempts'=>array($ref=>$attempt),'_ztp_current'=>$ref);
$GLOBALS['order']=$order;
check(ZTP_Service::apply($order,$ref,$data)==='completed' && $order->complete===1,'verified payment completes');
ZTP_Service::apply($order,$ref,$data);check($order->complete===1,'duplicate event does not complete twice');
$failure=$data;$failure['status']='failed';ZTP_Service::apply($order,$ref,$failure);check($order->is_paid(),'late failure cannot regress paid order');
$bad=$data;$bad['amount']=1;check(is_wp_error(ZTP_Service::apply($order,$ref,$bad)),'tampered amount cannot complete');
$order=new FakeOrder;$order->status='cancelled';$order->meta=array('_ztp_attempts'=>array($ref=>$attempt),'_ztp_current'=>$ref);ZTP_Service::apply($order,$ref,$data);check($order->complete===0 && count($order->notes)===1,'late capture on cancelled order needs reconciliation');
$gateway=(new ReflectionClass('ZTP_Gateway'))->newInstanceWithoutConstructor();
$GLOBALS['settings']['currencies']='';check($gateway->allowed_currencies()===array('GBP'),'blank currency uses store currency');
$GLOBALS['settings']['currencies']='GBP, EUR';check(in_array('EUR',$gateway->allowed_currencies(),true),'explicit currency list supported');
$GLOBALS['settings']['currencies']='GBP';
$GLOBALS['order']=new FakeOrder;$GLOBALS['requests']=array();$GLOBALS['responses']=array(response(array_merge($data,array('checkout_url'=>'https://api.tribe-pay.com/checkout/token')),201));
$result=$gateway->process_payment(123);
check($result['result']==='success','checkout creation succeeds');
$sent=json_decode($GLOBALS['requests'][0][1]['body'],true);check($sent['amount']===19.95 && $sent['currency']==='GBP','decimal amount and order currency sent');
check($sent['order_id']===$ref,'unique reference persisted before request');
check(!isset($sent['secret_key']) && isset($GLOBALS['requests'][0][1]['headers']['X-Secret-Key']),'secret only in server header');
$GLOBALS['responses']=array(response(array_merge($data,array('status'=>'pending'))));$before=count($GLOBALS['requests']);
$result=$gateway->process_payment(123);check($result['result']==='success' && count($GLOBALS['requests'])===$before+1,'pending checkout reused without another initiate');
$GLOBALS['responses']=array(new WP_Error('timeout','timeout'));$result=$gateway->process_payment(123);check($result['result']==='failure','uncertain previous payment blocks new charge');
$GLOBALS['responses']=array(response($data));$result=$gateway->process_payment(123);check($result['result']==='success' && $GLOBALS['order']->complete===1,'retry verifies already completed payment');
$raw='{"event":"payment.completed"}';$signature=hash_hmac('sha256',$raw,'secret');
check(ZTP_Service::valid_signature($raw,$signature,'secret'),'valid webhook signature');
check(!ZTP_Service::valid_signature($raw.' ',$signature,'secret'),'tampered webhook rejected');
check(!ZTP_Service::valid_signature($raw,'','secret'),'missing signature rejected');
check(!ZTP_Service::valid_signature($raw,$signature,''),'missing signing secret rejected');
check(!ZTP_Service::valid_signature($raw,$signature,'wrong'),'wrong signing secret rejected');
$nested=ZTP_API::normalize(array('success'=>true,'data'=>array('payment_url'=>'https://api.tribe-pay.com/checkout/x','order_id'=>$ref)));
check($nested['checkout_url']==='https://api.tribe-pay.com/checkout/x' && $nested['order_id']===$ref,'nested payment_url response normalized');
check(ZTP_API::normalize(array('checkout_url'=>'https://api.tribe-pay.com/checkout/a','payment_url'=>'https://api.tribe-pay.com/checkout/b'))['checkout_url']==='https://api.tribe-pay.com/checkout/a','documented checkout field has precedence');
$GLOBALS['order']=new FakeOrder;$GLOBALS['responses']=array(response(array_merge($data,array('payment_url'=>'https://api.tribe-pay.com/checkout/alias')),201));$result=$gateway->process_payment(123);
check($result['result']==='success' && $result['redirect']==='https://api.tribe-pay.com/checkout/alias','payment_url checkout accepted');
$GLOBALS['settings']['webhook_secret']='';$GLOBALS['order']=new FakeOrder;$GLOBALS['responses']=array(response(array_merge($data,array('checkout_url'=>'https://api.tribe-pay.com/checkout/nohook')),201));$result=$gateway->process_payment(123);
check($result['result']==='success','checkout works without webhook secret');
$last=end($GLOBALS['requests']);check(!isset(json_decode($last[1]['body'],true)['webhook_url']),'unconfigured webhook URL not sent');
$GLOBALS['order']=new FakeOrder;$GLOBALS['responses']=array(response(array('success'=>false),401));$result=$gateway->process_payment(123);
check($result['result']==='failure' && strpos(end($GLOBALS['notices']),'key pair was rejected')!==false,'authentication error clearly reported');
check(strpos(end($GLOBALS['order']->notes),'key pair was rejected')!==false,'safe checkout failure recorded on order');

// Exercise the actual production return handler; intercept only the final WP redirect/403.
$GLOBALS['order']=new FakeOrder;
$base='https://store.example/wc-api/tribepay_return/?ztp_order=123&key=wc_test';
foreach(array('&status=completed&order_id='.$ref, '?status=completed&order_id='.$ref) as $suffix){
 parse_str(parse_url($base.$suffix,PHP_URL_QUERY),$query);
 check(ZTP_Service::return_order($query) instanceof FakeOrder,'standard and legacy malformed query authenticates');
}
foreach(array(array('ztp_order'=>'123','key'=>'wrong?status=completed'),array('ztp_order'=>'123','key'=>'wc_test_extra?status=completed'),array('ztp_order'=>'123','key'=>array('wc_test')),array('ztp_order'=>array(123),'key'=>'wc_test'),array('ztp_order'=>'123junk','key'=>'wc_test'),array('ztp_order'=>'123','key'=>'wc_test?unknown=value'),array()) as $bad){
 check(ZTP_Service::return_order($bad)===false,'invalid return credentials rejected');
}
$newReturn=ZTP_Service::return_url($GLOBALS['order']);
foreach(array('&status=completed&order_id='.$ref,'?status=completed&order_id='.$ref) as $suffix){
 parse_str(parse_url($newReturn.$suffix,PHP_URL_QUERY),$q);
 check($q['key']==='wc_test' && ZTP_Service::return_order($q) instanceof FakeOrder,'new URL survives both provider query separators');
}
foreach(array('processing','completed') as $paidStatus){
 foreach(array('&status=completed','?status=completed') as $suffix){
  $GLOBALS['order']=new FakeOrder;$GLOBALS['order']->status=$paidStatus;
  parse_str(parse_url($base.$suffix,PHP_URL_QUERY),$_GET);
  try {ZTP_Service::handle_return();check(false,'handler must redirect');}
  catch(RedirectCaptured $e){check(strpos($e->getMessage(),'/order-received/')!==false,'paid processing/completed order redirects to thank you');}
 }
}
$GLOBALS['order']=new FakeOrder;$GLOBALS['order']->meta=array('_ztp_attempts'=>array($ref=>$attempt),'_ztp_current'=>$ref);
$GLOBALS['responses']=array(response($data));parse_str(parse_url($base.'?status=completed&order_id='.$ref,PHP_URL_QUERY),$_GET);
try{ZTP_Service::handle_return();check(false,'verified payment must redirect');}
catch(RedirectCaptured $e){check(strpos($e->getMessage(),'/order-received/')!==false && $GLOBALS['order']->status==='processing','actual handler verifies payment then redirects Processing order');}
ZTP_Service::return_order($_GET);check($GLOBALS['order']->complete===1,'return flow does not duplicate fulfillment');
$GLOBALS['order']=new FakeOrder;$GLOBALS['order']->meta=array('_ztp_attempts'=>array($ref=>$attempt),'_ztp_current'=>$ref);
$GLOBALS['responses']=array(response(array_merge($data,array('status'=>'pending'))));
try{ZTP_Service::handle_return();check(false,'pending return must redirect');}
catch(RedirectCaptured $e){check(strpos($e->getMessage(),'/order-pay/')!==false && !$GLOBALS['order']->is_paid(),'forged completed browser status cannot mark pending API payment paid');}
$_GET=array('ztp_order'=>'123','key'=>'incorrect?status=completed');$before=count($GLOBALS['requests']);
try{ZTP_Service::handle_return();check(false,'wrong key must fail');}
catch(ReturnRejected $e){check(count($GLOBALS['requests'])===$before,'invalid key rejected before provider request');}
echo 'PASS '.$passed." offline checks\n";
